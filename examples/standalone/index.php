<?php

use BLEST\BLEST\HttpServer;
use BLEST\BLEST\RequestHandler;

require __DIR__ . '/vendor/autoload.php';

$hello = function() {
    return [
        'hello' => 'world',
        'bonjour' => 'le monde',
        'hola' => 'mundo',
        'hallo' => 'welt'
    ];
};

$auth = function($params, &$context) {
    if ($params['name']) {
        $context['user'] = array(
            'name' => $params['name']
        );
    } else {
        throw new Exception('Unauthorized');
    }
};

$greet = function($params, $context) {
    if (!$context['user']['name']) {
        throw new Exception('Unauthorized');
    }
    return [
        'geeting' => 'Hi, ' . $context['user']['name'] . '!'
    ];
};

$fail = function() {
    throw new Exception('Intentional failure');
};

$request_handler = new RequestHandler([
    'hello' => $hello,
    'greet' => [$auth, $greet],
    'fail' => $fail
]);

$server = new HttpServer($request_handler);

$server->run();