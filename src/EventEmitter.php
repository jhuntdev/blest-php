<?php
namespace BLEST\BLEST;

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