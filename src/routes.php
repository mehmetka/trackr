<?php

$app->group('', function () {
    $this->get('/', \App\controller\AuthController::class . ':index')->setName('login');
});