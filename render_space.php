<?php

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use RW\BearerToken;

require_once 'vendor/autoload.php';

$OUTPUT_DIR = __DIR__ . '/output';
$TEMPLATE_DIR = __DIR__ . '/templates';

$space = $argv[1];
$accessToken = $argv[2];

$stack = HandlerStack::create();
$stack->push(new BearerToken($accessToken));

$client = new Client([
    'base_uri' => 'https://cdn.contentful.com/spaces/' . $space . '/',
    'handler' => $stack
]);

$twig = new Twig_Environment(
    new Twig_Loader_Filesystem($TEMPLATE_DIR),
    array(
        'cache' => __DIR__ . '/cache',
    )
);
$assetUrlFunction = new Twig_SimpleFunction('asset_url', function ($link) use ($client) {
    $response = json_decode($client->get('assets/' . $link->sys->id)->getBody());
    return 'https:' . $response->fields->file->url ;
});
$twig->addFunction($assetUrlFunction);

$getEntryFunction = new Twig_SimpleFunction('get_entry', function ($link) use ($client) {
    $response = json_decode($client->get('entries/' . $link->sys->id)->getBody());

    $result = $response->fields;
    $result->sys = $response->sys;
    return $result;
});
$twig->addFunction($getEntryFunction);

function normalizeItem($item)
{
    $locale = 'en-US';
    $entry = new stdClass;
    foreach ($item->fields as $key => $field) {
        $entry->$key = $field->$locale;
    }

    return $entry;
}

$tokenFile = $space . '.token';

$params = null;
if (is_file($tokenFile)) {
    $url = file_get_contents($tokenFile);
} else {
    $url = 'sync';
    $params = ['query' => ['type' => 'Entry', 'initial' => 'true']];
}

try {
    $response = $client->get($url, $params);
    $decoded = json_decode($response->getBody());

    foreach ($decoded->items as $item) {
        $template = $item->sys->contentType->sys->id . '.twig';
        if (!is_file($TEMPLATE_DIR . '/' . $template)) {
            continue;
        }

        $fileName = $OUTPUT_DIR . '/' . $item->sys->id . '.html';
        $content = $twig->render($template, array('entry' => normalizeItem($item)));
        file_put_contents($fileName, $content);
    }

    file_put_contents($tokenFile, $decoded->nextSyncUrl);
}
catch (GuzzleHttp\Exception\RequestException $e) {
    var_dump((string)$e->getResponse()->getBody());
}
