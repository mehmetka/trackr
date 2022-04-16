<?php

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

try {
    $dbConnection = new \PDO("mysql:host={$_ENV['MYSQL_HOST']}:3306;dbname={$_ENV['MYSQL_DATABASE']};charset=utf8mb4", $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASSWORD']);
} catch (Exception $e) {
    echo 'Database access problem: ' . $e->getMessage();
    die;
}