<?php

namespace App\Console\Commands;

use App\Models\SipsaImport;
use App\Models\SipsaPrice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncSipsaPrices extends Command
{
    protected $signature = 'sipsa:sync
                            {--days=400 : Conserva solo los últimos N días de precios (mínimo 30)}
                            {--full : Reprocesa toda la ventana de días, no solo lo nuevo}';

    protected $description = 'Lee en streaming los precios mayoristas por ciudad del DANE (SIPSA), sin guardar archivos, y actualiza la base';

    private const ENDPOINT = 'https://appweb.dane.gov.co/sipsaWS/SrvSipsaUpraBeanService';
    private const SOAP_NS = 'http://servicios.sipsa.co.gov.dane/';
    private const METHOD = 'promediosSipsaCiudad';
    private const BATCH = 500;

    private ?string $failure = null;
    private bool $checked = false;
    private int $bytes = 0;
    private int $nextReport = 20971520;
    private int $read = 0;
    private int $invalid = 0;
    private int $old = 0;
    private int $inserted = 0;
    private string $since = '';
    private string $now = '';
    private ?string $newest = null;
    private array $batch = [];
    private bool $inRecord = false;
    private array $record = [];
    private ?string $field = null;
    private string $buffer = '';

    public function handle(): int
    {
        $days = max(30, (int) $this->option('days'));
        $cutoff = now()->subDays($days)->toDateString();
        $this->now = now()->toDateTimeString();

        $last = SipsaPrice::max('captured_on');

        $this->since = (!$this->option('full') && $last)
            ? max($cutoff, Carbon::parse($last)->subDays(10)->toDateString())
            : $cutoff;

        $this->info('Sincronizando precios SIPSA (DANE)...');
        $this->line("Se guardan precios desde {$this->since}. Ventana de conservación: {$days} días (desde {$cutoff}).");
        $this->line('La respuesta se lee mientras llega: no se guarda ningún archivo.');

        $this->download();

        if ($this->failure !== null) {
            $this->newLine();
            $this->error($this->failure);

            if ($this->inserted > 0) {
                $this->warn("Antes del error se guardaron {$this->inserted} registros nuevos.");
            }

            return self::FAILURE;
        }

        if ($this->read === 0) {
            $this->error('DANE no devolvió registros.');
            return self::FAILURE;
        }

        $pruned = SipsaPrice::where('captured_on', '<', $cutoff)->delete();

        $range = SipsaPrice::selectRaw('MIN(captured_on) as first_date, MAX(captured_on) as last_date')->first();

        SipsaImport::create([
            'source'       => 'sync ' . self::METHOD,
            'records_read' => $this->read,
            'records_new'  => $this->inserted,
            'first_date'   => $range->first_date,
            'last_date'    => $range->last_date,
        ]);

        SipsaImport::where('created_at', '<', now()->subDays(90))->delete();

        $this->newLine();
        $this->table(['Resumen', 'Valor'], [
            ['Datos recibidos', number_format($this->bytes / 1048576, 1) . ' MB (no guardados)'],
            ['Registros leídos', number_format($this->read)],
            ['Registros nuevos en la base', number_format($this->inserted)],
            ['Anteriores a la fecha de corte (omitidos)', number_format($this->old)],
            ['Registros inválidos', number_format($this->invalid)],
            ['Eliminados por antigüedad', number_format($pruned)],
            ['Total en la base', number_format(SipsaPrice::count())],
            ['Fechas en la base', ($range->first_date ?? '—') . ' → ' . ($range->last_date ?? '—')],
            ['Dato más reciente publicado por DANE', $this->newest ?? '—'],
        ]);

        if ($this->newest && Carbon::parse($this->newest)->lt(now()->subDays(5))) {
            $this->warn('DANE no ha publicado precios en los últimos 5 días: los datos pueden estar desactualizados.');
        }

        return self::SUCCESS;
    }

    private function download(): void
    {
        $parser = xml_parser_create_ns('UTF-8', '|');
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_set_element_handler(
            $parser,
            fn ($p, $name) => $this->onStart($name),
            fn ($p, $name) => $this->onEnd($name)
        );
        xml_set_character_data_handler($parser, fn ($p, $data) => $this->onText($data));

        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:ws="' . self::SOAP_NS . '">'
            . '<soap:Body><ws:' . self::METHOD . '/></soap:Body></soap:Envelope>';

        $ch = curl_init(self::ENDPOINT);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $envelope,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/soap+xml; charset=utf-8'],
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 3600,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_ENCODING       => '',
            CURLOPT_WRITEFUNCTION  => function ($ch, string $chunk) use ($parser): int {
                if ($this->failure !== null) {
                    return 0;
                }

                if (!$this->checked) {
                    $this->checked = true;
                    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

                    if ($code !== 200) {
                        $this->failure = "DANE respondió HTTP {$code} (se esperaba 200).";
                        return 0;
                    }
                }

                $this->bytes += strlen($chunk);

                if (!xml_parse($parser, $chunk, false)) {
                    $this->failure = 'La respuesta de DANE no es un XML válido: '
                        . xml_error_string(xml_get_error_code($parser))
                        . ' (línea ' . xml_get_current_line_number($parser) . ')';
                    return 0;
                }

                if ($this->bytes >= $this->nextReport) {
                    $this->nextReport += 20971520;
                    $this->line(sprintf(
                        '  Recibidos %.0f MB · %s registros leídos · %s nuevos',
                        $this->bytes / 1048576,
                        number_format($this->read),
                        number_format($this->inserted)
                    ));
                }

                return strlen($chunk);
            },
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);

        if ($this->failure === null && $result === false) {
            $this->failure = 'Falló la conexión con DANE: ' . $error;
        }

        if ($this->failure === null && !xml_parse($parser, '', true)) {
            $this->failure = 'La respuesta de DANE terminó incompleta: ' . xml_error_string(xml_get_error_code($parser));
        }

        $this->flush();
    }

    private function local(string $name): string
    {
        $pos = strrpos($name, '|');

        return $pos === false ? $name : substr($name, $pos + 1);
    }

    private function onStart(string $name): void
    {
        $local = $this->local($name);

        if (!$this->inRecord) {
            if ($local === 'return') {
                $this->inRecord = true;
                $this->record = [];
                $this->field = null;
            }

            return;
        }

        $this->field = $local;
        $this->buffer = '';
    }

    private function onText(string $data): void
    {
        if ($this->inRecord && $this->field !== null) {
            $this->buffer .= $data;
        }
    }

    private function onEnd(string $name): void
    {
        if (!$this->inRecord) {
            return;
        }

        $local = $this->local($name);

        if ($local === 'return') {
            $this->inRecord = false;
            $this->handleRecord();
            return;
        }

        if ($this->field === $local) {
            $this->record[$local] = trim($this->buffer);
            $this->field = null;
            $this->buffer = '';
        }
    }

    private function handleRecord(): void
    {
        $this->read++;

        $row = $this->mapRow($this->record);

        if ($row === null) {
            $this->invalid++;
            return;
        }

        if ($this->newest === null || $row['captured_on'] > $this->newest) {
            $this->newest = $row['captured_on'];
        }

        if ($row['captured_on'] < $this->since) {
            $this->old++;
            return;
        }

        $this->batch[] = $row;

        if (count($this->batch) >= self::BATCH) {
            $this->flush();
        }
    }

    private function mapRow(array $v): ?array
    {
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

        return [
            'reg_id'       => (int) $regId,
            'city'         => mb_substr($city, 0, 80),
            'product_code' => (int) $code,
            'product'      => mb_substr($product, 0, 120),
            'price'        => (float) $price,
            'captured_on'  => $date,
            'created_at'   => $this->now,
            'updated_at'   => $this->now,
        ];
    }

    private function flush(): void
    {
        if (empty($this->batch)) {
            return;
        }

        // insertOrIgnore: los registros que ya existen (misma reg_id) se omiten sin reescribirlos
        $this->inserted += DB::table('sipsa_prices')->insertOrIgnore($this->batch);
        $this->batch = [];
    }
}