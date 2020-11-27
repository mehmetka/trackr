<?php

use App\middleware;
use App\controller\AuthController;
use App\controller\HomeController;
use App\controller\BookController;

$app->group('', function () {
    $this->get('/login', AuthController::class . ':index')->setName('login');
    $this->post('/login', AuthController::class . ':login');
});

$app->group('', function () {

    $this->get('/', HomeController::class . ':index')->setName('home');

    $this->get('/books/paths', BookController::class . ':paths')->setName('paths');
    $this->get('/books/paths/{pathId:[0-9]+}', BookController::class . ':booksPathInside');
    $this->get('/all-books', BookController::class . ':allBooks');
    $this->get('/my-books', BookController::class . ':myBooks');
    $this->get('/books/finished', BookController::class . ':finishedBooks');
    $this->post('/books/{bookId:[0-9]+}/progress', BookController::class . ':addProgress');
    $this->post('/authors', BookController::class . ':createAuthor');

    $this->get('/logout', AuthController::class . ':logout');

})->add(new Middleware\Authentication($container));