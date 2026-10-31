
<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (!class_exists('SoapClient')) {
    exit("SOAP no está habilitado en PHP.\n");
}

$wsdl = 'http://appweb.dane.gov.co:9001/sipsaWS/SrvSipsaUpraBeanService?WSDL';

try {
    $cliente = new SoapClient($wsdl, [
        'soap_version' => SOAP_1_2,
        'exceptions' => true,
        'trace' => true,
        'cache_wsdl' => WSDL_CACHE_NONE,
        'connection_timeout' => 20,
    ]);

    echo "WSDL interpretado correctamente.\n\n";
    echo "Métodos disponibles:\n";

    foreach ($cliente->__getFunctions() as $metodo) {
        echo $metodo . "\n";
    }

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}