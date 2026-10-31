<?php

namespace App\Console\Commands;

use App\Models\SipsaImport;
use App\Models\SipsaPrice;
use DOMDocument;
use DOMElement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use XMLReader;

class ImportSipsaPrices extends Command
{
    protected $signature = 'sipsa:import
                            {--download : Descarga la respuesta completa de DANE y luego la importa}
                            {--file= : Importa un archivo XML ya descargado}
                            {--days=400 : Importa solo los últimos N días (0 = todo)}';

    protected $description = 'Carga en la base de datos los precios mayoristas por ciudad del DANE (SIPSA)';

    private const ENDPOINT = 'https://appweb.dane.gov.co/sipsaWS/SrvSipsaUpraBeanService';
    private const SOAP_NS = 'http://servicios.sipsa.co.gov.dane/';
    private const METHOD = 'promediosSipsaCiudad';
    private const BATCH = 500;

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $temporaryDownload = false;

        if ($this->option('download')) {
            $file = $this->download();

            if ($file === null) {
                return self::FAILURE;
            }

            $temporaryDownload = true;

        } elseif ($this->option('file')) {
            $file = (string) $this->option('file');

        } else {
            $file = $this->latestFile();

            if ($file === null) {
                $this->error('No hay archivos XML disponibles.');
                return self::FAILURE;
            }

            $this->line('Usando archivo: ' . $file);
        }

        if (!is_file($file)) {
            $this->error("No existe el archivo: {$file}");
            return self::FAILURE;
        }

        try {
            $this->line(sprintf(
                'Tamaño del archivo: %.1f MB',
                filesize($file) / 1048576
            ));

            return $this->import($file, $days);

        } finally {
            if ($temporaryDownload && is_file($file)) {
                @unlink($file);
                $this->info('Archivo XML temporal eliminado.');
            }
        }
    }

    private function latestFile(): ?string
    {
        $pattern = Storage::disk('local')->path('sipsa') . DIRECTORY_SEPARATOR . self::METHOD . '_*.xml';

        return collect(glob($pattern) ?: [])
            ->sortByDesc(fn ($f) => filemtime($f))
            ->first();
    }

    private function download(): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'sipsa_');

        if ($path === false) {
            $this->error('No se pudo crear el archivo temporal.');
            return null;
        }

        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" '
            . 'xmlns:ws="' . self::SOAP_NS . '">'
            . '<soap:Body><ws:' . self::METHOD . '/></soap:Body>'
            . '</soap:Envelope>';

        $this->info('Descargando datos SIPSA del DANE...');
        $this->line('El archivo se eliminará al finalizar la importación.');

        $last = 0;
        $startedAt = microtime(true);

        $progress = function ($total, $downloaded) use (&$last, $startedAt) {
            if ($downloaded - $last >= 5 * 1048576) {
                $last = $downloaded;

                $this->output->write(sprintf(
                    "\rRecibidos %.1f MB en %d segundos",
                    $downloaded / 1048576,
                    (int) (microtime(true) - $startedAt)
                ));
            }
        };

        try {
            $response = Http::withOptions([
                'sink' => $path,
                'progress' => $progress,
                'connect_timeout' => 30,
                'timeout' => 3600,
            ])
            ->withBody($envelope, 'application/soap+xml; charset=utf-8')
            ->post(self::ENDPOINT);

            $this->newLine();

            if (!$response->successful()) {
                $this->error(
                    'Error HTTP al descargar SIPSA: ' . $response->status()
                );

                @unlink($path);
                return null;
            }

            clearstatcache(true, $path);

            $size = is_file($path) ? filesize($path) : 0;

            if ($size === 0) {
                $this->error('La descarga no produjo datos.');
                @unlink($path);
                return null;
            }

            $this->info(sprintf(
                'Descarga completada: %.1f MB',
                $size / 1048576
            ));

            return $path;

        } catch (\Throwable $e) {
            $this->newLine();

            $this->error(
                'Error durante la descarga: ' .
                mb_substr($e->getMessage(), 0, 200)
            );

            @unlink($path);

            return null;
        }
    }

    private function import(string $file, int $days): int
    {
        $cutoff = $days > 0 ? now()->subDays($days)->toDateString() : null;

        $this->newLine();
        $this->info('Importando' . ($cutoff ? " los datos desde {$cutoff}" : ' todos los datos') . '...');

        if (!$this->validateXml($file)) {
            $this->error(
                'El XML está incompleto, dañado o no contiene registros SIPSA válidos.'
            );

            return self::FAILURE;
        }

        $this->info('XML validado correctamente. Iniciando importación...');

        $previous = libxml_use_internal_errors(true);
        $reader = new XMLReader();

        if (!$reader->open($file, null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            libxml_use_internal_errors($previous);
            $this->error('No se pudo abrir el archivo.');
            return self::FAILURE;
        }

        $before = SipsaPrice::count();
        $read = 0;
        $invalid = 0;
        $skipped = 0;
        $batch = [];

        $ok = @$reader->read();

        while ($ok) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'return') {
                $dom = new DOMDocument();
                $node = @$reader->expand($dom);

                if (!$node) {
                    break;
                }

                $read++;
                $row = $this->mapRow($node);

                if ($row === null) {
                    $invalid++;
                } elseif ($cutoff !== null && $row['captured_on'] < $cutoff) {
                    $skipped++;
                } else {
                    $batch[] = $row;
                }

                if (count($batch) >= self::BATCH) {
                    $this->flush($batch);
                    $batch = [];
                }

                if ($read % 5000 === 0) {
                    $this->output->write("\r  Leídos " . number_format($read) . ' registros   ');
                }

                $ok = @$reader->next();
            } else {
                $ok = @$reader->read();
            }
        }

        $this->flush($batch);

        $incomplete = collect(libxml_get_errors())->contains(fn ($e) => $e->level >= LIBXML_ERR_ERROR);

        $reader->close();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->newLine();

        if ($read === 0) {
            $this->error('No encontré registros en el archivo.');
            return self::FAILURE;
        }

        $after = SipsaPrice::count();

        if ($incomplete) {
            $this->warn('El archivo parece incompleto (terminó con un error de lectura). Se importó lo anterior.');
        }

        $range = SipsaPrice::selectRaw('MIN(captured_on) as first_date, MAX(captured_on) as last_date')->first();
        $cities = SipsaPrice::query()->distinct()->orderBy('city')->pluck('city');
        $products = SipsaPrice::query()->distinct()->count('product_code');

        SipsaImport::create([
            'source'       => basename($file),
            'records_read' => $read,
            'records_new'  => max(0, $after - $before),
            'first_date'   => $range->first_date,
            'last_date'    => $range->last_date,
        ]);

        $this->table(['Resumen', 'Valor'], [
            ['Registros leídos del archivo', number_format($read)],
            ['Registros nuevos en la base', number_format(max(0, $after - $before))],
            ['Fuera del período solicitado', number_format($skipped)],
            ['Registros inválidos', number_format($invalid)],
            ['Total en la base', number_format($after)],
            ['Fechas en la base', ($range->first_date ?? '—') . ' → ' . ($range->last_date ?? '—')],
            ['Ciudades', $cities->count()],
            ['Productos', $products],
        ]);

        $this->line('Ciudades: ' . $cities->map(fn ($c) => SipsaPrice::cityLabel($c))->implode(', '));

        $hasVilla = $cities->contains(fn ($c) => str_contains(mb_strtoupper($c), 'VILLAVICENCIO'));
        $this->line($hasVilla
            ? 'Villavicencio SÍ está entre las ciudades.'
            : 'Villavicencio NO aparece entre las ciudades que publica DANE en este método.');

        return self::SUCCESS;
    }

    private function validateXml(string $file): bool
    {
        $previous = libxml_use_internal_errors(true);

        libxml_clear_errors();

        $reader = new XMLReader();

        if (!$reader->open(
            $file,
            null,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        )) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return false;
        }

        $records = 0;

        try {
            while ($reader->read()) {
                if (
                    $reader->nodeType === XMLReader::ELEMENT &&
                    $reader->localName === 'return'
                ) {
                    $records++;
                }
            }

            $errors = libxml_get_errors();

            $valid = $records > 0;

            foreach ($errors as $error) {
                if ($error->level >= LIBXML_ERR_ERROR) {
                    $valid = false;
                    break;
                }
            }

            return $valid;

        } finally {
            $reader->close();

            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }


    private function mapRow(DOMElement $node): ?array
    {
        $v = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $v[$child->localName] = trim($child->textContent);
            }
        }

        $regId = $v['regId'] ?? '';
        $code = $v['codProducto'] ?? '';
        $city = $v['ciudad'] ?? '';
        $product = $v['producto'] ?? '';
        $price = $v['precioPromedio'] ?? '';
        $date = substr($v['fechaCaptura'] ?? '', 0, 10);

        if (!ctype_digit($regId) || !ctype_digit($code) || $city === '' || $product === ''
            || !is_numeric($price) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $now = now()->toDateTimeString();

        return [
            'reg_id'       => (int) $regId,
            'city'         => mb_substr($city, 0, 80),
            'product_code' => (int) $code,
            'product'      => mb_substr($product, 0, 120),
            'price'        => (float) $price,
            'captured_on'  => $date,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
    }

    private function flush(array $batch): void
    {
        if (empty($batch)) {
            return;
        }

        DB::table('sipsa_prices')->upsert(
            $batch,
            ['reg_id'],
            ['city', 'product_code', 'product', 'price', 'captured_on', 'updated_at']
        );
    }
}