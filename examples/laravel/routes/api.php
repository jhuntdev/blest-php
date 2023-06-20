<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExampleController;
use BLEST\BLEST\RequestHandler;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
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

Route::middleware('auth:api')->post('/', function (Request $request) {
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