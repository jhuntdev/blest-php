<?php

require __DIR__ . '/vendor/autoload.php';

use BLEST\BLEST\RequestHandler;

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

$app->add(function ($request, $response) {
    $response->addHeader('Access-Control-Allow-Origin', '*');
    $response->addHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
    $response->addHeader('Access-Control-Allow-Headers', 'Origin, Content-Type, Authorization, Accept');
    return $response;
});

app()->post('/', function () use ($request_handler) {
    $body = request()->body();
    [$result, $error] = $request_handler->handle($body);
    if ($error) {
        response()->json($error, 500);
    } else {
        response()->json($result, 200);
    }
});

app()->run();