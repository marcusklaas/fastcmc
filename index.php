<?php
require_once 'vendor/autoload.php';

function performApiCall(string $keyLocation, string $outputFile, string $endpoint, array $data = []) : object {
    // read last modification date and abort if last update was too recent.
    // ~300 calls per days. 2 calls per update -> 150 updates per day
    // this means that we can update once every 10 minutes
    $modified = @filemtime($outputFile);
    if ($modified !== false && (time() - $modified) < 590) {
        return json_decode(file_get_contents($outputFile));
    }

    $key = file_get_contents($keyLocation);
    $curl = curl_init();
    $headers = [
        'X-CMC_PRO_API_KEY: ' . $key,
    ];
    $url = sprintf("%s?%s", $endpoint, http_build_query($data));

    curl_setopt($curl, CURLOPT_HEADER, 1);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $responseBody = substr($result, $header_size);

    curl_close($curl);
    file_put_contents($outputFile, $responseBody);

    return json_decode($responseBody);
}


$keyLocation = 'cmc-key.txt';

// get data
$globalData = performApiCall(
    $keyLocation,
    'global-data.json',
    'https://pro-api.coinmarketcap.com/v1/global-metrics/quotes/latest'
);
$coinData = performApiCall(
    $keyLocation,
    'coin-data.json',
    'https://pro-api.coinmarketcap.com/v1/cryptocurrency/listings/latest',
    [
        'start' => 1,
        'limit' => 100,
        'convert' => 'USD'
    ]
);

// render
$loader = new Twig_Loader_Filesystem('./');
$twig = new Twig_Environment($loader, ['cache' => './cache']);
$template = $twig->load('template.html');
echo $template->render(['coins' => json_decode($coinData), 'global' => json_decode($globalData)]);
