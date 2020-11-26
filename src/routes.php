<?php

use App\middleware;

$app->group('', function () {
    $this->get('/login', \App\controller\AuthController::class . ':index')->setName('login');
    $this->post('/login', \App\controller\AuthController::class . ':login');
});

$app->group('', function () {

    $this->get('/', \App\controller\HomeController::class . ':index')->setName('home');

})->add(new Middleware\Authentication($container));