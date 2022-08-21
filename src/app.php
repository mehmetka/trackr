<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\App;

date_default_timezone_set('Europe/Istanbul');

session_name('trackr');
ini_set('session.cookie_httponly', 1);
session_start();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

if(!strpos($_ENV['TRACKR_BASE_URL'], $_SERVER['HTTP_HOST'])){
    header('location: ' . $_ENV['TRACKR_BASE_URL']);
    exit();
}

$settings['settings'] = [
    'displayErrorDetails' => $_ENV['displayErrorDetails'],
    'debug' => $_ENV['debug']
];

$app = new App($settings);

$container = $app->getContainer();

require __DIR__ . "/container.php";
require __DIR__ . "/routes.php";