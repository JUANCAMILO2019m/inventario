<?php

namespace App\Console\Commands;

use DOMDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use XMLReader;

class SipsaFetch extends Command
{
    protected $signature = 'sipsa:fetch
                            {method : Método de SIPSA, por ejemplo promediosSipsaParcial}
                            {--max-mb=15 : Detiene la descarga al llegar a estos MB (0 = sin límite)}
                            {--analyze=20000 : Máximo de registros a analizar}
                            {--file= : Analiza un archivo ya descargado, sin descargar de nuevo}';

    protected $description = 'Descarga (o lee) una respuesta del servicio SIPSA del DANE y resume los campos que trae';

    private const ENDPOINT = 'https://appweb.dane.gov.co/sipsaWS/SrvSipsaUpraBeanService';
    private const SOAP_NS = 'http://servicios.sipsa.co.gov.dane/';

    private const METHODS = [
        'consultarInsumosSipsaMesMadr',
        'promedioAbasSipsaMesMadr',
        'promediosSipsaCiudad',
        'promediosSipsaMesMadr',
        'promediosSipsaParcial',
        'promediosSipsaSemanaMadr',
    ];

    public function handle(): int
    {
        $method = (string) $this->argument('method');
        $file = $this->option('file');

        if ($file) {
            if (!is_file($file)) {
                $this->error("No existe el archivo: {$file}");
                return self::FAILURE;
            }
        } else {
            if (!in_array($method, self::METHODS, true)) {
                $this->error("Método desconocido: {$method}");
                $this->line('Métodos válidos: ' . implode(', ', self::METHODS));
                return self::FAILURE;
            }

            $file = $this->download($method);

            if ($file === null) {
                return self::FAILURE;
            }
        }

        return $this->analyze($file, max(1, (int) $this->option('analyze')));
    }

    private function download(string $method): ?string
    {
        Storage::disk('local')->makeDirectory('sipsa');

        $path = Storage::disk('local')->path('sipsa/' . $method . '_' . now()->format('Ymd_His') . '.xml');
        $maxBytes = (int) round(((float) $this->option('max-mb')) * 1048576);

        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:ws="' . self::SOAP_NS . '">'
            . '<soap:Body><ws:' . $method . '/></soap:Body></soap:Envelope>';

        $this->info("Descargando {$method} desde DANE (HTTPS, SOAP 1.2)...");
        $this->line($maxBytes > 0
            ? 'Se detendrá al llegar a ' . number_format($maxBytes / 1048576, 0) . ' MB: alcanza para ver cómo vienen los datos.'
            : 'Sin límite de tamaño: puede tardar mucho y pesar cientos de MB.');

        $aborted = false;
        $response = null;
        $lastShown = 0;
        $startedAt = microtime(true);

        $progress = function ($total, $downloaded) use (&$aborted, &$lastShown, $maxBytes, $startedAt) {
            if ($downloaded - $lastShown >= 1048576) {
                $lastShown = $downloaded;
                $this->output->write(sprintf(
                    "\r  Recibidos %.1f MB en %d s   ",
                    $downloaded / 1048576,
                    (int) (microtime(true) - $startedAt)
                ));
            }

            if ($maxBytes > 0 && $downloaded >= $maxBytes) {
                $aborted = true;
                throw new \RuntimeException('límite alcanzado');
            }
        };

        try {
            $response = Http::withOptions([
                    'sink'            => $path,
                    'progress'        => $progress,
                    'connect_timeout' => 30,
                    'timeout'         => 1800,
                ])
                ->withBody($envelope, 'application/soap+xml; charset=utf-8')
                ->post(self::ENDPOINT);
        } catch (\Throwable $e) {
            if (!$aborted) {
                $this->newLine();
                $this->error('La descarga se interrumpió: ' . mb_substr($e->getMessage(), 0, 200));
            }
        }

        $this->newLine();
        clearstatcache(true, $path);
        $size = is_file($path) ? (int) filesize($path) : 0;

        if ($size === 0) {
            $this->error('No llegó ningún dato.');
            return null;
        }

        $this->line('Archivo: ' . $path);
        $this->line(sprintf(
            'Tamaño: %.1f MB · %s',
            $size / 1048576,
            $aborted
                ? 'cortado a propósito por el límite'
                : ($response ? 'descarga completa (HTTP ' . $response->status() . ')' : 'interrumpida')
        ));

        return $path;
    }

    private function analyze(string $file, int $limit): int
    {
        $this->newLine();
        $this->info('Analizando el archivo...');

        $previous = libxml_use_internal_errors(true);
        $reader = new XMLReader();

        if (!$reader->open($file, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            libxml_use_internal_errors($previous);
            $this->error('No se pudo abrir el archivo.');
            return self::FAILURE;
        }

        $count = 0;
        $fields = [];
        $samples = [];
        $ok = @$reader->read();

        while ($ok) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'return') {
                $dom = new DOMDocument();
                $node = @$reader->expand($dom);

                if (!$node) {
                    break;
                }

                $row = [];

                foreach ($node->childNodes as $child) {
                    if ($child->nodeType !== XML_ELEMENT_NODE) {
                        continue;
                    }

                    $name = $child->localName;
                    $value = trim($child->textContent);
                    $row[$name] = $value;
                    $this->track($fields, $name, $value);
                }

                if (!$row) {
                    $row['(valor)'] = trim($node->textContent);
                    $this->track($fields, '(valor)', $row['(valor)']);
                }

                $count++;

                if (count($samples) < 2) {
                    $samples[] = $row;
                }

                if ($count >= $limit) {
                    break;
                }

                $ok = @$reader->next();
            } else {
                $ok = @$reader->read();
            }
        }

        $reader->close();
        libxml_use_internal_errors($previous);

        $this->info('Registros leídos: ' . number_format($count) . ($count >= $limit ? ' (límite de análisis)' : ''));

        if ($count === 0) {
            $this->warn('No encontré registros con la forma esperada. Inicio del archivo:');
            $this->line((string) file_get_contents($file, false, null, 0, 1500));
            return self::FAILURE;
        }

        $rows = [];

        foreach ($fields as $name => $f) {
            $filled = $f['n'] - $f['empty'];
            $type = $filled > 0 && $f['numeric'] === $filled
                ? 'número'
                : ($filled > 0 && $f['date'] === $filled ? 'fecha' : 'texto');

            if ($type !== 'texto' && $f['min'] !== null) {
                $examples = $this->shorten($f['min']) . ' → ' . $this->shorten($f['max']);
            } else {
                $examples = collect(array_keys($f['distinct']))
                    ->take(6)
                    ->map(fn ($v) => mb_substr((string) $v, 0, 28))
                    ->implode(' | ');
            }

            $rows[] = [
                $name,
                $type,
                round($f['empty'] / max(1, $f['n']) * 100) . '%',
                $f['overflow'] ? '300+' : (string) count($f['distinct']),
                $examples,
            ];
        }

        $this->newLine();
        $this->table(['Campo', 'Tipo', 'Vacíos', 'Distintos', 'Ejemplos o rango'], $rows);

        foreach ($samples as $i => $row) {
            $this->newLine();
            $this->line('Registro de ejemplo ' . ($i + 1) . ':');
            $this->table(
                ['Campo', 'Valor'],
                collect($row)->map(fn ($v, $k) => [$k, mb_substr($v, 0, 80)])->values()->all()
            );
        }

        return self::SUCCESS;
    }

    private function track(array &$fields, string $name, string $value): void
    {
        if (!isset($fields[$name])) {
            $fields[$name] = [
                'n' => 0, 'empty' => 0, 'numeric' => 0, 'date' => 0,
                'distinct' => [], 'overflow' => false,
                'min' => null, 'max' => null,
            ];
        }

        $f = &$fields[$name];
        $f['n']++;

        if ($value === '') {
            $f['empty']++;
            return;
        }

        $isNumber = (bool) preg_match('/^-?\d+([.,]\d+)?$/', $value);
        $isDate = !$isNumber && preg_match('#^(\d{4}-\d{2}-\d{2}|\d{2}/\d{2}/\d{4})#', $value);

        $cmp = null;

        if ($isNumber) {
            $f['numeric']++;
            $cmp = (float) str_replace(',', '.', $value);
        } elseif ($isDate) {
            $f['date']++;
            $cmp = $value;
        }

        if ($cmp !== null) {
            if ($f['min'] === null || (gettype($cmp) === gettype($f['min']) && $cmp < $f['min'])) {
                $f['min'] = $cmp;
            }

            if ($f['max'] === null || (gettype($cmp) === gettype($f['max']) && $cmp > $f['max'])) {
                $f['max'] = $cmp;
            }
        }

        if (!$f['overflow']) {
            $f['distinct'][$value] = true;

            if (count($f['distinct']) > 300) {
                $f['overflow'] = true;
            }
        }
    }

    private function shorten(mixed $v): string
    {
        return is_float($v) ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : (string) $v;
    }
}