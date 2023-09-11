# BLEST PHP

The PHP reference implementation of BLEST (Batch-able, Lightweight, Encrypted State Transfer), an improved communication protocol for web APIs which leverages JSON, supports request batching and selective returns, and provides a modern alternative to REST. It includes examples for Leaf and Slim.

To learn more about BLEST, please visit the website: https://blest.jhunt.dev

For a front-end implementation in Vue, please visit https://github.com/jhuntdev/blest-vue

## Features

- Built on JSON - Reduce parsing time and overhead
- Request Batching - Save bandwidth and reduce load times
- Compact Payloads - Save more bandwidth
- Selective Returns - Save even more bandwidth
- Single Endpoint - Reduce complexity and improve data privacy
- Fully Encrypted - Improve data privacy

## Installation

Install BLEST PHP with Composer.

```bash
composer require blest/blest
```

## Usage

The `App` class of this library has an interface similar to Leaf or Slim. It also provides a `Router` class with a `handle` method for use in an existing PHP application and an `HttpClient` class with a `request` method for making BLEST HTTP requests.

```php
require __DIR__ . '/vendor/autoload.php';

use BLEST\BLEST\App;

// Instantiate an app (or use functional mode: app()->...)
$app = new App();

// Create some middleware (optional)
$authMiddleware = function($params, $context) {
  if ($params['name']) {
    $context['user'] = [
      'name' => $params['name']
    ];
  } else {
    throw new Exception('Unauthorized');
  }
};
$app->use($authMiddleware);

// Create a route controller
$greetController = function($params, $context) {
  return [
    'greeting' => 'Hi, ' . $context['user']['name]' . '!'
  ];
};
$app->route('greet', $greetController);

// Run the app
$app->run();
```

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
$authMiddleware = function($params, $context) {
  if ($params['name']) {
    $context['user'] = [
      'name' => $params['name']
    ];
  } else {
    throw new Exception('Unauthorized');
  }
};
$router->use($authMiddleware);

// Create a route controller
$greetController = function($params, $context) {
  return [
    'greeting' => 'Hi, ' . $context['user']['name]' . '!'
  ];
};
$router->route('greet', $greetController);

// Create the Slim app
$app = AppFactory::create();

// Parse request body
$app->addBodyParsingMiddleware();

// Listen for POST requests on root URL ("/")
$app->post('/', function (Request $request, Response $response) use ($requestHandler) {
  $body = $request->getParsedBody();
  $context = [
    'headers' => $request->getHeaders()
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
  'headers' => [
    'Authorization' => 'Bearer token'
  ]
]);

// Use the client to make a request
$client->request('greet', ['name' => 'Steve'], ['greeting']);
```

## License

This project is licensed under the [MIT License](LICENSE).