# BLEST PHP

The PHP reference implementation of BLEST (Batch-able, Lightweight, Encrypted State Transfer), an improved communication protocol for web APIs which leverages JSON, supports request batching by default, and provides a modern alternative to REST. It includes examples for Leaf, Slim, and OpenSwoole.

To learn more about BLEST, please visit the website: https://blest.jhunt.dev

For a front-end implementation in Vue, please visit https://github.com/jhuntdev/blest-vue

## Features

- Built on JSON - Reduce parsing time and overhead
- Request Batching - Save bandwidth and reduce load times
- Compact Payloads - Save even more bandwidth
- Single Endpoint - Reduce complexity and facilitate introspection
- Fully Encrypted - Improve data privacy

## Installation

Install BLEST PHP with Composer.

```bash
composer require blest/blest
```

## Usage

### Router

This example uses Slim, but you can find examples with other frameworks [here](examples).

```php
require __DIR__ . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Middleware\BodyParsingMiddleware;
use Slim\Factory\AppFactory;
use BLEST\BLEST\Router;

// Instantiate the router
$router = new Router();

// Create some middleware (optional)
$userMiddleware = function($body, $context) {
  $context['user'] = [
    // user info for example
  ];
};
$app->use($userMiddleware);

// Create a route controller
$greetController = function($body, $context) {
  return [
    'greeting' => 'Hi, ' . $body['name]' . '!'
  ];
};
$app->route('greet', $greetController);

// Create the Slim app
$app = AppFactory::create();

// Parse request body
$app->addBodyParsingMiddleware();

// Listen for POST requests on root URL ("/")
$app->post('/', function (Request $request, Response $response) use ($requestHandler) {
  $body = $request->getParsedBody();
  $context = [
    'httpHeaders' => $request->getHeaders()
  ];
  [$result, $error] = $router->handle($body, $context);
  if ($error) {
    $response->getBody()->write(json_encode($error));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
  } else {
    $response->getBody()->write(json_encode($result));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  }
});

// Run the app
$app->run();
```

### HttpClient

```php
require __DIR__ . '/vendor/autoload.php';

use BLEST\BLEST\HttpClient;

// Create an HTTP client
$client = new HttpClient('http://localhost:8080', [
  'httpHeaders' => [
    'Authorization' => 'Bearer token'
  ]
]);

// Use the client to make a request
$client->request('greet', ['name' => 'Steve']);
```

## License

This project is licensed under the [MIT License](LICENSE).