<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;

class SipsaProbe extends Command
{
    protected $signature = 'sipsa:probe
                            {--call= : Método a invocar (por ejemplo promediosSipsaCiudad)}
                            {--param=* : Parámetro del método en formato nombre=valor (se puede repetir)}
                            {--soapclient : Además, prueba con la extensión SOAP de PHP, si está instalada}';

    protected $description = 'Revisa el servicio web de SIPSA (DANE): muestra su contrato y prueba cómo invocarlo';

    private const WSDL = 'http://appweb.dane.gov.co/sipsaWS/SrvSipsaUpraBeanService?WSDL';
    private const FALLBACK_ADDRESS = 'http://appweb.dane.gov.co:80/sipsaWS/SrvSipsaUpraBeanService';

    public function handle(): int
    {
        $this->info('Descargando el WSDL de DANE...');

        try {
            $response = Http::timeout(30)->get(self::WSDL);
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar con DANE: ' . $e->getMessage());
            return self::FAILURE;
        }

        $wsdl = $response->ok() ? @simplexml_load_string($response->body()) : false;

        if ($wsdl === false) {
            $this->error('La respuesta no es un WSDL válido (HTTP ' . $response->status() . ').');
            $this->line(mb_substr($response->body(), 0, 300));
            return self::FAILURE;
        }

        Storage::disk('local')->put('sipsa/wsdl.xml', $response->body());

        $namespace = (string) $wsdl['targetNamespace'];

        $operations = collect($wsdl->xpath('//*[local-name()="portType"]/*[local-name()="operation"]') ?: [])
            ->map(fn ($op) => (string) $op['name'])
            ->unique()
            ->values();

        $address = (string) (($wsdl->xpath('//*[local-name()="address"]') ?: [null])[0]['location'] ?? '');
        $address = $address !== '' ? $address : self::FALLBACK_ADDRESS;

        $actions = [];
        foreach ($wsdl->xpath('//*[local-name()="binding"]/*[local-name()="operation"]') ?: [] as $op) {
            $inner = $op->xpath('*[local-name()="operation"]');
            $actions[(string) $op['name']] = $inner ? (string) $inner[0]['soapAction'] : '';
        }

        $style = '';
        $transport = '';
        foreach ($wsdl->xpath('//*[local-name()="binding"]/*[local-name()="binding"]') ?: [] as $b) {
            $style = (string) $b['style'];
            $transport = (string) $b['transport'];
        }

        $this->newLine();
        $this->line('Espacio de nombres: ' . $namespace);
        $this->line('Dirección declarada: ' . $address);
        $this->line('Estilo: ' . ($style ?: '—') . ' · Transporte: ' . ($transport ?: '—'));

        $schemas = $this->loadSchemas($wsdl);
        $this->line('Esquemas leídos: ' . count($schemas));

        $this->newLine();
        $this->info('Contrato de los métodos (' . $operations->count() . '):');

        foreach ($operations as $operation) {
            $d = $this->describe($schemas, $operation);

            $this->newLine();
            $this->line('<fg=cyan>' . $operation . '</>');
            $this->line('  SOAPAction: ' . (($actions[$operation] ?? '') !== '' ? $actions[$operation] : '(vacía)'));
            $this->line('  Parámetros: ' . ($d['params'] === null
                ? 'no se pudo determinar'
                : (empty($d['params']) ? 'ninguno' : $this->fmt($d['params']))));
            $this->line('  Cada registro trae: ' . ($d['fields'] === null
                ? 'no se pudo determinar' . ($d['type'] ? " (tipo {$d['type']})" : '')
                : $this->fmt($d['fields'])));
        }

        $method = $this->option('call');

        if (!$method) {
            $this->newLine();
            $this->comment('Para probar un método: php artisan sipsa:probe --call=promediosSipsaCiudad');
            return self::SUCCESS;
        }

        if (!$operations->contains($method)) {
            $this->error("El método {$method} no aparece en el WSDL.");
            return self::FAILURE;
        }

        $params = [];
        foreach ((array) $this->option('param') as $pair) {
            if (str_contains($pair, '=')) {
                [$k, $v] = explode('=', $pair, 2);
                $params[trim($k)] = trim($v);
            }
        }

        $this->newLine();
        $this->warn('Algunos métodos de DANE solo entregan los registros que aún no se han consultado.');
        $this->warn('Cada respuesta SOAP queda guardada en disco: no la borres.');
        $this->newLine();
        $this->info("Probando {$method}...");

        $ok = $this->tryVariants($method, $namespace, $actions[$method] ?? '', $params, $address);

        if ($this->option('soapclient')) {
            $this->trySoapClient($method, $params);
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function tryVariants(string $method, string $namespace, string $soapAction, array $params, string $address): bool
    {
        $paramXml = collect($params)
            ->map(fn ($v, $k) => '<' . $k . '>' . htmlspecialchars($v, ENT_XML1) . '</' . $k . '>')
            ->implode('');

        $env12 = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:ws="' . $namespace . '">'
            . '<soap:Body><ws:' . $method . '>' . $paramXml . '</ws:' . $method . '></soap:Body></soap:Envelope>';

        $env11 = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ws="' . $namespace . '">'
            . '<soapenv:Body><ws:' . $method . '>' . $paramXml . '</ws:' . $method . '></soapenv:Body></soapenv:Envelope>';

        $plain = str_replace(':80', '', $address);

        $urls = array_values(array_unique([
            $address,
            $plain,
            preg_replace('#^http:#', 'https:', $plain),
        ]));

        $formats = [
            ['SOAP 1.2 con action', 'application/soap+xml; charset=utf-8; action="' . $soapAction . '"', [], $env12],
            ['SOAP 1.2 simple', 'application/soap+xml; charset=utf-8', [], $env12],
            ['SOAP 1.1', 'text/xml; charset=utf-8', ['SOAPAction' => '"' . $soapAction . '"'], $env11],
        ];

        $n = 0;

        foreach ($urls as $url) {
            foreach ($formats as [$label, $type, $headers, $xml]) {
                $n++;

                try {
                    $response = Http::timeout(60)
                        ->withoutRedirecting()
                        ->withHeaders($headers)
                        ->withBody($xml, $type)
                        ->post($url);
                } catch (\Throwable $e) {
                    $this->line("  {$label} | {$url} -> error: " . mb_substr($e->getMessage(), 0, 150));
                    continue;
                }

                $body = $response->body();
                $isSoap = str_contains($body, 'Envelope');
                $redirect = $response->status() >= 300 && $response->status() < 400
                    ? ' · redirige a ' . $response->header('Location')
                    : '';

                $this->line(sprintf(
                    '  %s | %s -> HTTP %d, %s bytes, %s%s',
                    $label, $url, $response->status(), number_format(strlen($body)),
                    $isSoap ? 'SOAP' : 'no es SOAP', $redirect
                ));

                if (!$isSoap) {
                    continue;
                }

                $path = 'sipsa/' . $method . '_' . now()->format('Ymd_His') . '_' . $n . '.xml';
                Storage::disk('local')->put($path, $body);
                $this->line('  Guardada en: ' . Storage::disk('local')->path($path));

                if (str_contains($body, 'Fault>')) {
                    $this->warn('El servicio respondió con un error SOAP (el formato es correcto):');
                    $this->line(mb_substr($body, 0, 1200));
                    return false;
                }

                $this->info('¡Esta combinación funciona!');
                return $this->summarize($body);
            }
        }

        $this->newLine();
        $this->error('Ninguna de las combinaciones respondió como SOAP.');
        return false;
    }

    private function trySoapClient(string $method, array $params): void
    {
        $this->newLine();
        $this->info('Probando con la extensión SOAP de PHP...');

        if (!class_exists(\SoapClient::class)) {
            $this->warn('La extensión soap no está activa en este PHP (ext-soap). Se omite esta prueba.');
            return;
        }

        $client = null;

        try {
            $client = new \SoapClient(self::WSDL, [
                'soap_version'       => SOAP_1_2,
                'trace'              => true,
                'exceptions'         => true,
                'connection_timeout' => 30,
                'cache_wsdl'         => WSDL_CACHE_NONE,
            ]);

            $client->__soapCall($method, $params ? [$params] : []);

            $body = (string) $client->__getLastResponse();
            $path = 'sipsa/soapclient_' . $method . '_' . now()->format('Ymd_His') . '.xml';
            Storage::disk('local')->put($path, $body);

            $this->line('Solicitud que envió PHP:');
            $this->line(mb_substr((string) $client->__getLastRequest(), 0, 800));
            $this->line('Respuesta guardada en: ' . Storage::disk('local')->path($path));
            $this->summarize($body);
        } catch (\Throwable $e) {
            $this->error('Falló: ' . $e->getMessage());

            if ($client) {
                $this->line('Última solicitud:');
                $this->line(mb_substr((string) $client->__getLastRequest(), 0, 800));
                $this->line('Última respuesta (inicio):');
                $this->line(mb_substr((string) $client->__getLastResponse(), 0, 400));
            }
        }
    }

    private function summarize(string $body): bool
    {
        $xml = @simplexml_load_string($body);

        if ($xml === false) {
            $this->error('La respuesta no es XML válido.');
            $this->line(mb_substr($body, 0, 1200));
            return false;
        }

        $records = $xml->xpath('//*[local-name()="return"]') ?: [];

        $this->info('Registros recibidos: ' . count($records));

        if (empty($records)) {
            $this->warn('No hay registros con la forma esperada. Respuesta cruda:');
            $this->line(mb_substr($body, 0, 1500));
            return true;
        }

        foreach (array_slice($records, 0, 2) as $i => $record) {
            $this->line('Registro de ejemplo ' . ($i + 1) . ':');

            $rows = [];
            foreach ($record->children() as $child) {
                $rows[] = [$child->getName(), mb_substr(trim((string) $child), 0, 80)];
            }

            $this->table(['Campo', 'Valor'], $rows);
        }

        return true;
    }

    /** Lee los esquemas del WSDL y los archivos XSD que importa. */
    private function loadSchemas(SimpleXMLElement $wsdl): array
    {
        $schemas = $wsdl->xpath('//*[local-name()="schema"]') ?: [];
        $loaded = [];

        foreach ($schemas as $schema) {
            foreach ($schema->xpath('*[local-name()="import" or local-name()="include"]') ?: [] as $import) {
                $location = (string) $import['schemaLocation'];

                if ($location === '') {
                    continue;
                }

                $url = str_starts_with($location, 'http')
                    ? $location
                    : preg_replace('#[^/]*$#', '', strtok(self::WSDL, '?')) . $location;

                try {
                    $res = Http::timeout(30)->get($url);
                } catch (\Throwable $e) {
                    $this->warn('No se pudo leer el esquema ' . $url);
                    continue;
                }

                $doc = $res->ok() ? @simplexml_load_string($res->body()) : false;

                if ($doc !== false) {
                    $loaded[] = $doc;
                }
            }
        }

        return array_merge($schemas, $loaded);
    }

    private function findTopLevel(array $schemas, string $kind, string $name): ?SimpleXMLElement
    {
        foreach ($schemas as $schema) {
            $found = $schema->xpath('*[local-name()="' . $kind . '"][@name="' . $name . '"]');

            if (!empty($found)) {
                return $found[0];
            }
        }

        return null;
    }

    private function fieldsOf(SimpleXMLElement $node): array
    {
        $fields = [];

        foreach ($node->xpath('.//*[local-name()="element"]') ?: [] as $el) {
            $type = (string) $el['type'];
            $fields[] = [
                'name' => (string) $el['name'] ?: (string) $el['ref'],
                'type' => $type !== '' ? preg_replace('/^.*:/', '', $type) : '',
            ];
        }

        return $fields;
    }

    private function describe(array $schemas, string $operation): array
    {
        $input = $this->findTopLevel($schemas, 'element', $operation);
        $params = $input ? $this->fieldsOf($input) : null;

        $fields = null;
        $type = '';

        $output = $this->findTopLevel($schemas, 'element', $operation . 'Response');
        $return = $output ? ($output->xpath('.//*[local-name()="element"][@name="return"]') ?: []) : [];

        if ($return) {
            $type = (string) $return[0]['type'];
            $local = preg_replace('/^.*:/', '', $type);
            $complex = $local !== '' ? $this->findTopLevel($schemas, 'complexType', $local) : null;

            if ($complex) {
                $fields = $this->fieldsOf($complex);
            } elseif ($return[0]->xpath('.//*[local-name()="element"]')) {
                $fields = $this->fieldsOf($return[0]);
            }
        }

        return ['params' => $params, 'fields' => $fields, 'type' => $type];
    }

    private function fmt(array $fields): string
    {
        return collect($fields)
            ->map(fn ($f) => $f['name'] . ($f['type'] !== '' ? ':' . $f['type'] : ''))
            ->implode(', ');
    }
}