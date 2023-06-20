<?php

namespace App\Http\Controllers;

class ExampleController extends Controller
{
    public function hello($params, $context) {
        return [
            'hello' => 'world',
            'bonjour' => 'le monde',
            'hola' => 'mundo',
            'hallo' => 'welt'
        ];
    }
    
    public function greet($params, $context) {
        if (!$context['user']['name']) {
            throw new Exception('Unauthorized');
        }
        return [
            'geeting' => 'Hi, ' . $context['user']['name'] . '!'
        ];
    }
    
    public function fail($params, $context) {
        throw new Exception('Intentional failure');
    }
}