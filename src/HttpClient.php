<?php
namespace BLEST\BLEST;

require_once __DIR__ . '/include/polyfill.php';

class HttpClient {
  private $url;
  private $maxBatchSize = 1; // PHP is not async
  private $batchDelay = 0; // PHP is not async
  private $httpHeaders = [];
  private $timer = false;
  private $queue = [];
  private $emitter;

  public function __construct($url, $options = null) {
    $this->url = $url;
    $this->emitter = new EventEmitter();
    if ($options) {
      if (array_key_exists('maxBatchSize', $options)) {
        $this->maxBatchSize = $options['maxBatchSize'];
      }
      if (array_key_exists('batchDelay', $options)) {
        $this->batchDelay = $options['batchDelay'];
      }
      if (array_key_exists('httpHeaders', $options)) {
        $this->httpHeaders = $options['httpHeaders'];
      }
    }
  }

  private function delay($func, $time) {
    usleep($time * 1000);
    $func();
  }

  private function process() {
    $new_queue = array_slice($this->queue, 0, $this->maxBatchSize);
    $this->queue = array_slice($this->queue, $this->maxBatchSize);
    
    if (empty($this->queue)) {
      $this->timer = false;
    } else {
      $this->timer = true;
      $this->delay([$this, 'process'], $this->batchDelay);
    }
    
    $post_data = json_encode($new_queue);
    $httpHeaders = $this->httpHeaders;
    $httpHeaders['Accept'] = 'application/json';
    $httpHeaders['Content-Type'] = 'application/json';
    
    $options = [
      'http' => [
        'header' => implode("\r\n", array_map(function ($key, $value) {
          return $key . ': ' . $value;
        }, array_keys($httpHeaders), $httpHeaders)),
        'method' => 'POST',
        'content' => $post_data,
      ],
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents($this->url, false, $context);
    
    if ($response === false) {
      $error = error_get_last();
      $error_message = $error['message'] ?? 'Unknown error';
      foreach ($new_queue as $q) {
        $this->emitter->emit($q[0], null, $error_message);
      }
    } else {
      $responseJson = json_decode($response, true);
      if ($responseJson !== null) {
        foreach ($responseJson as $r) {
          $this->emitter->emit($r[0], $r[2], $r[3]);
        }
      }
    }
  }

  private function uuidv4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
  }

  public function request(string $route, array $body = null, array $headers = null, callable $callback) {
    if (empty($route)) {
      throw new \Exception('Route is required');
    } elseif ($body && !is_array($body)) {
      throw new \Exception('Body should be an array');
    } elseif ($headers && !is_array($headers)) {
      throw new \Exception('Headers should be an array');
    }
    $id = $this->uuidv4();
    $this->emitter->once($id, $callback);
    $this->queue[] = [$id, $route, $body, $headers];
    if (!$this->timer) {
      $this->timer = true;
      $this->delay([$this, 'process'], $this->batchDelay);
    }
  }
}

class EventEmitter {
  private $listeners = [];

  public function on($event, $callback) {
    if (!isset($this->listeners[$event])) {
      $this->listeners[$event] = [];
    }
    $this->listeners[$event][] = $callback;
  }

  public function emit($event, ...$args) {
    if (isset($this->listeners[$event])) {
      foreach ($this->listeners[$event] as $listener) {
        call_user_func_array($listener, $args);
      }
    }
  }

  public function once($event, $callback) {
    $once_callback = function (...$args) use ($event, $callback, &$once_callback) {
      $this->removeListener($event, $once_callback);
      call_user_func_array($callback, $args);
    };
    $this->on($event, $once_callback);
  }

  public function removeListener($event, $callback) {
    if (isset($this->listeners[$event])) {
      $index = array_search($callback, $this->listeners[$event], true);
      if ($index !== false) {
        unset($this->listeners[$event][$index]);
      }
    }
  }
}