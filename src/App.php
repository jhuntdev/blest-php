<?php
namespace BLEST\BLEST;

require_once __DIR__ . '/include/polyfill.php';
require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/HttpServer.php';

class App extends Router {

    private $options;

    public function __construct($options = array()) {
        parent::__construct($options);
        $this->options = $options ?: array();
    }

    public function run() {
        $routes = $this->routes;
        $options = $this->options;
        $handler = function(array $requests, array $context = []) {
            return $this->handle($requests, $context);
        };
        $server = new HttpServer($handler, $options);
        $args = func_get_args();
        call_user_func_array(array($server, 'run'), $args);
    }

}

$blestAppInstance = null;

function app() {
    global $blestAppInstance;
    if ($blestAppInstance === null) {
        $blestAppInstance = new App();
    }
    return $blestAppInstance;
}