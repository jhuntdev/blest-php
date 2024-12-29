<?php

require __DIR__ . '/vendor/autoload.php';

use BLEST\BLEST\Router;

$app = new Leaf\App;

$helloController = function() {
    $greetingArray = [
        ['hello' => 'world' ],
        ['bonjour' => 'le monde' ],
        ['hola' => 'mundo' ],
        ['hallo' => 'welt' ]
    ];
    $randomIndex = array_rand($greetingArray);
    return $greetingArray[$randomIndex];
};

$greetController = function($body) {
    return [
        'greeting' => 'Hi, ' . $body['name'] . '!'
    ];
};

$failController = function() {
    throw new Exception('Intentional failure');
};

$router = new Router();
$router->route('hello', $helloController);
$router->route('fail', $failController);
$router->use($authMiddleware);
$router->route('greet', $greetController);

$app->add(function ($request, $response) {
    $response->addHeader('Access-Control-Allow-Origin', '*');
    $response->addHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
    $response->addHeader('Access-Control-Allow-Headers', 'Origin, Content-Type, Authorization, Accept');
    return $response;
});

$app->post('/', function () use ($router) {
    $body = request()->body();
    $context = [
        'httpHeaders' => request()->headers()
    ];
    [$result, $error] = $router->handle($body, $context);
    if ($error) {
        response()->json($error, 500);
    } else {
        response()->json($result, 200);
    }
});

$app->cors();

$app->run();