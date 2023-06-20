<?php
namespace BLEST\BLEST;

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