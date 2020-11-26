<?php

$app->group('', function () {
    $this->get('/login', \App\controller\AuthController::class . ':index')->setName('login');
});