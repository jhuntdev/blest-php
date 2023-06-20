<?php
namespace BLEST\BLEST;

if (!function_exists('is_list')) {
  function is_list($value) {
    if (!is_array($value)) {
      return false;
    }
    $count = count($value);
    for ($i = 0; $i < $count; $i++) {
      if (!array_key_exists($i, $value)) {
        return false;
      }
    }
    return true;
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
    $onceCallback = function (...$args) use ($event, $callback) {
      $this->removeListener($event, $onceCallback);
      call_user_func_array($callback, $args);
    };
    $this->on($event, $onceCallback);
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

class HttpClient {
  private $url;
  private $options;
  private $maxBatchSize = 100;
  private $queue = [];
  private $timeout = null;
  private $emitter;

  public function __construct($url, $options = null) {
    $this->url = $url;
    $this->options = $options;

    if ($this->options) {
      echo 'The "options" argument is not yet used, but may be used in the future.';
    }

    $this->emitter = new EventEmitter();
  }

  private function process() {
    $newQueue = array_splice($this->queue, 0, $this->maxBatchSize);
    if ($this->timeout !== null) {
      clearTimeout($this->timeout);
    }
    if (count($this->queue) === 0) {
      $this->timeout = null;
    } else {
      $this->timeout = setTimeout(function () {
        $this->process();
      }, 1);
    }

    $payload = json_encode($newQueue);
    $headers = [
      'Accept: application/json',
      'Content-Type: application/json'
    ];
    $options = [
      'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $payload
      ]
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($this->url, false, $context);
    if ($result === false) {
      foreach ($newQueue as $q) {
        $this->emitter->emit($q[0], null, error_get_last());
      }
    } else {
      $data = json_decode($result);
      foreach ($data as $r) {
        $this->emitter->emit($r[0], $r[2], $r[3]);
      }
    }
  }

  public function request($route, $params = null, $selector = null) {
    return new Promise(function ($resolve, $reject) use ($route, $params, $selector) {
      if (!$route) {
        return $reject(new Exception('Route is required'));
      } elseif ($params && !is_array($params)) {
        return $reject(new Exception('Params should be an array'));
      } elseif ($selector && !is_array($selector)) {
        return $reject(new Exception('Selector should be an array'));
      }

      $id = uniqid();
      $this->emitter->once($id, function ($result, $error) use ($resolve, $reject) {
        if ($error) {
          $reject($error);
        } else {
          $resolve($result);
        }
      });
      $this->queue[] = [$id, $route, $params ?: null, $selector ?: null];
      if ($this->timeout === null) {
        $this->timeout = setTimeout(function () {
          $this->process();
        }, 1);
      }
    });
  }
}