<?php
namespace BLEST\BLEST;

require_once __DIR__ . '/include/polyfill.php';

class HttpClient {
  private $url;
  private $max_batch_size = 1; // PHP is not async
  private $batch_delay = 0; // PHP is not async
  private $headers = [];
  private $timer = false;
  private $queue = [];
  private $emitter;

  public function __construct($url, $options = null) {
    $this->url = $url;
    $this->emitter = new EventEmitter();
    if ($options) {
      if (array_key_exists('max_batch_size', $options)) {
        $this->max_batch_size = $options['max_batch_size'];
      }
      if (array_key_exists('batch_delay', $options)) {
        $this->batch_delay = $options['batch_delay'];
      }
      if (array_key_exists('headers', $options)) {
        $this->headers = $options['headers'];
      }
    }
  }

  private function delay($func, $time) {
    usleep($time * 1000);
    $func();
  }

  private function process() {
    $new_queue = array_slice($this->queue, 0, $this->max_batch_size);
    $this->queue = array_slice($this->queue, $this->max_batch_size);
    
    if (empty($this->queue)) {
      $this->timer = false;
    } else {
      $this->timer = true;
      $this->delay([$this, 'process'], $this->batch_delay);
    }
    
    $post_data = json_encode($new_queue);
    $headers = $this->headers;
    $headers['Accept'] = 'application/json';
    $headers['Content-Type'] = 'application/json';
    
    $options = [
      'http' => [
        'header' => implode("\r\n", array_map(function ($key, $value) {
          return $key . ': ' . $value;
        }, array_keys($headers), $headers)),
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
      $response_json = json_decode($response, true);
      if ($response_json !== null) {
        foreach ($response_json as $r) {
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

  public function request(string $route, array $params = null, array $selector = null, callable $callback) {
    if (empty($route)) {
      throw new \Exception('Route is required');
    } elseif ($params && !is_array($params)) {
      throw new \Exception('Params should be an array');
    } elseif ($selector && !is_list($selector)) {
      throw new \Exception('Selector should be a list');
    }
    $id = $this->uuidv4();
    $this->emitter->once($id, $callback);
    $this->queue[] = [$id, $route, $params, $selector];
    if (!$this->timer) {
      $this->timer = true;
      $this->delay([$this, 'process'], $this->batch_delay);
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