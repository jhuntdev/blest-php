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

require __DIR__ . '/EventEmitter.php';
require __DIR__ . '/HttpClient.php';
require __DIR__ . '/HttpServer.php';
require __DIR__ . '/RequestHandler.php';