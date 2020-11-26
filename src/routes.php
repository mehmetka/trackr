<?php

$app->group('', function () {
    $this->get('/login', \App\controller\AuthController::class . ':index')->setName('login');
    $this->post('/login', \App\controller\AuthController::class . ':login');
});