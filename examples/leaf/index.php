<?php

use BLEST\BLEST\RequestHandler;

require __DIR__ . '/vendor/autoload.php';

$hello = function($params, $context) {
    return [
        'hello' => 'world',
        'bonjour' => 'le monde',
        'hola' => 'mundo',
        'hallo' => 'welt'
    ];
};

$auth = function($params, $context) {
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

$fail = function($params, $context) {
    throw new Exception('Intentional failure');
};

$request_handler = new RequestHandler([
    'hello' => $hello,
    'greet' => [$auth, $greet],
    'fail' => $fail
]);

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