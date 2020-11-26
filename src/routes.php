<?php

use App\middleware;
use App\controller\AuthController;
use App\controller\HomeController;

$app->group('', function () {
    $this->get('/login', AuthController::class . ':index')->setName('login');
    $this->post('/login',AuthController::class . ':login');
});

$app->group('', function () {

    $this->get('/',HomeController::class . ':index')->setName('home');

    $this->get('/logout', \App\controller\AuthController::class . ':logout');

})->add(new Middleware\Authentication($container));