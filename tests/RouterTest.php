<?php

namespace BLEST\BLEST;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../src/include/utilities.php';

class RouterTest extends TestCase
{
    protected static $router;
    protected static $benchmarks = [];

    public static function setUpBeforeClass(): void
    {
        self::$router = new Router(['timeout' => 1000]);

        self::$router->before(function ($body, &$context) {
            $context['test'] = ['value' => $body['testValue']];
            $context['time'] = microtime(true);
        });

        self::$router->after(function ($_, $context) {
            $completeTime = microtime(true);
            $difference = $completeTime - $context['time'];
            self::$benchmarks[] = $difference;
        });

        self::$router->route('basicRoute', function ($body, $context) {
            return ['route' => 'basicRoute', 'body' => $body, 'context' => $context];
        });

        $router2 = new Router(['timeout' => 100]);

        $router2->route('mergedRoute', function ($body, $context) {
            return ['route' => 'mergedRoute', 'body' => $body, 'context' => $context];
        });

        $router2->route('timeoutRoute', function ($body, $_) {
            usleep(200000); // Simulate async delay
            return ['testValue' => $body['testValue']];
        });

        self::$router->merge($router2);

        $router3 = new Router();

        $router3->route('errorRoute', function ($body, $_) {
            throw new \Exception($body['testValue']);
        });

        self::$router->namespace('subRoutes', $router3);
    }

    protected function runRoutes()
    {
        // Basic route
        $testId1 = generateUUIDv1();
        $testValue1 = rand() / getrandmax();
        list($result1, $error1) = self::$router->handle([[$testId1, 'basicRoute', ['testValue' => $testValue1]]], ['testValue' => $testValue1]);

        // Merged route
        $testId2 = generateUUIDv1();
        $testValue2 = rand() / getrandmax();
        list($result2, $error2) = self::$router->handle([[$testId2, 'mergedRoute', ['testValue' => $testValue2]]], ['testValue' => $testValue2]);

        // Error route
        $testId3 = generateUUIDv1();
        $testValue3 = rand() / getrandmax();
        list($result3, $error3) = self::$router->handle([[$testId3, 'subRoutes/errorRoute', ['testValue' => $testValue3]]], ['testValue' => $testValue3]);

        // Missing route
        $testId4 = generateUUIDv1();
        $testValue4 = rand() / getrandmax();
        list($result4, $error4) = self::$router->handle([[$testId4, 'missingRoute', ['testValue' => $testValue4]]], ['testValue' => $testValue4]);

        // Timeout route
        $testId5 = generateUUIDv1();
        $testValue5 = rand() / getrandmax();
        list($result5, $error5) = self::$router->handle([[$testId5, 'timeoutRoute', ['testValue' => $testValue5]]], ['testValue' => $testValue5]);

        // Malformed request
        list($result6, $error6) = self::$router->handle([[$testId4], [], [true, 1.25]]);
        
        return compact('result1', 'error1', 'result2', 'error2', 'result3', 'error3', 'result4', 'error4', 'result5', 'error5', 'result6', 'error6');
    }

    public function testClassProperties()
    {
        $this->assertInstanceOf(Router::class, self::$router);
        $this->assertCount(4, self::$router->routes);
        $this->assertTrue(method_exists(self::$router, 'handle'));
    }

    public function testValidRequests()
    {
        $results = $this->runRoutes();
        $this->assertNull($results['error1']);
        $this->assertNull($results['error2']);
        $this->assertNull($results['error3']);
        $this->assertNull($results['error4']);
        $this->assertNull($results['error5']);
    }
}