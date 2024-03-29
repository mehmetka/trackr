<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\App;

error_reporting(E_ERROR);
date_default_timezone_set('Europe/Istanbul');

session_name('trackr');
ini_set('session.gc_maxlifetime', 7200); // 7200 seconds - 120 minutes
ini_set('session.cookie_httponly', 1);
session_start();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

if (!strpos($_ENV['TRACKR_BASE_URL'], $_SERVER['HTTP_HOST'])) {
    header('location: ' . $_ENV['TRACKR_BASE_URL']);
    exit();
}

$settings['settings'] = [
    'displayErrorDetails' => $_ENV['DISPLAY_ERROR_DETAILS'],
    'debug' => $_ENV['DEBUG']
];

$app = new App($settings);

$container = $app->getContainer();

require __DIR__ . "/container.php";
require __DIR__ . "/routes.php";