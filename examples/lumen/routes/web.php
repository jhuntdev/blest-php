<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$authMiddleware = function($params, $context) {
    if ($params['name']) {
        $context['user'] = array(
            'name' => $params['name']
        );
    } else {
        throw new Exception('Unauthorized');
    }
};

$request_handler = new RequestHandler([
    'hello' => [new ExampleController(), 'hello'],
    'greet' => [$authMiddleware, [new ExampleController(), 'greet']],
    'fail' => [new ExampleController(), 'fail']
]);

$router->post('/', function (Request $request) {
    try {
        $data = json_decode($request->getContent(), true);
        [$result, $error] = $request_handler->handle($data);
        if ($error) {
            response()->json(json_encode(['message' => $error['message']]), 500);
        } else {
            response()->json(json_encode(result), 200);
        }
    } catch (\Exception $e) {
        Log::error($e->getMessage());
        return response()->json(['message' => 'An error occurred'], 500);
    }
});