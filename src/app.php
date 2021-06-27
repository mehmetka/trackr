<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\App;

date_default_timezone_set('Europe/Istanbul');

session_name('trackr');
ini_set( 'session.cookie_httponly', 1 );
session_start();

$settings['settings'] = parse_ini_file(__DIR__ . '/../conf/conf.ini', true);

$app = new App($settings);

$container = $app->getContainer();

require __DIR__ . "/container.php";
require __DIR__ . "/routes.php";