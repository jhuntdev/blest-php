# BLEST PHP

The PHP reference implementation of BLEST (Batch-able, Lightweight, Encrypted State Transfer), an improved communication protocol for web APIs which leverages JSON, supports request batching and selective returns, and provides a modern alternative to REST. It includes examples for Laravel, Leaf, Lumen, and Slim.

To learn more about BLEST, please refer to the white paper: https://jhunt.dev/BLEST%20White%20Paper.pdf

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

Use the `RequestHandler` class to create a request handler suitable for use in an existing PHP application. Use the `HttpServer` class to create a standalone HTTP server for your request handler. Use the `HttpClient` class to create a BLEST HTTP client.

### RequestHandler

This example uses Slim, but you can find examples with other frameworks [here](examples).

```php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Middleware\BodyParsingMiddleware;
use Slim\Factory\AppFactory;
use BLEST\BLEST\RequestHandler;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();

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

// Create a route controller
$greetController = function($params, $context) {
  return [
    'greeting' => 'Hi, ' . $context['user']['name]' . '!'
  ];
};

// Create a request handler
$requestHandler = new RequestHandler([
    'greet' => [$authMiddleware, $greetController]
]);

// Parse the JSON body
$app->addBodyParsingMiddleware();

$app->post('/', function (Request $request, Response $response) use ($requestHandler) {
  $body = $request->getParsedBody();
  [$result, $error] = $requestHandler->handle($body);
  if ($error) {
    $response->getBody()->write(json_encode($error));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
  } else {
    $response->getBody()->write(json_encode($result));
    return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
  }
});

$app->run();
```

### HttpServer

```php
use BLEST\BLEST\HttpServer;
use BLEST\BLEST\RequestHandler;

require __DIR__ . '/vendor/autoload.php';

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

// Create a route controller
$greetController = function($params, $context) {
  return [
    'greeting' => 'Hi, ' . $context['user']['name]' . '!'
  ];
};

// Create a request handler
$requestHandler = new RequestHandler([
    'greet' => [$authMiddleware, $greetController]
]);

$server = new HttpServer($requestHandler);

$server->run();
```

### HttpClient

```php
use BLEST\BLEST\HttpClient;

require __DIR__ . '/vendor/autoload.php';

// Create an HTTP client
$client = new HttpClient('http://localhost:8080', [
  'headers' => [
    'Authorization' => 'Bearer token'
  ]
]);

// Use the client to make a request
$client->request('greet', ['name' => 'Steve'], ['greeting'])
```


## Contributing

We actively welcome pull requests. Learn how to [contribute](CONTRIBUTING.md) for more information.

## License

This project is licensed under the [MIT License](LICENSE).