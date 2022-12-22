<?php

use App\middleware;
use App\controller\AuthController;
use App\controller\HomeController;
use App\controller\BookController;
use App\controller\BookmarkController;
use App\controller\DateTrackingController;
use App\controller\HighlightController;
use App\controller\LogController;

$app->group('', function () {
    $this->get('/login', AuthController::class . ':loginPage')->setName('login');
    $this->post('/login', AuthController::class . ':login');
    $this->get('/register', AuthController::class . ':registerPage')->setName('register');
    $this->post('/register', AuthController::class . ':register');
})->add(new Middleware\Guest($container));

// TODO Add authorization
$app->group('/api', function () {
    $this->post('/bookmarks', BookmarkController::class . ':create');
    $this->post('/books', BookController::class . ':saveBook');
    $this->put('/bookmarks/{uid}/title', BookmarkController::class . ':updateTitle');
});

$app->group('', function () {

    $this->get('/', HomeController::class . ':index')->setName('home');

    $this->get('/logs', LogController::class . ':index');
    $this->post('/logs', LogController::class . ':save');

    $this->get('/menu-badge-counts', HomeController::class . ':getMenuBadgeCounts');

    $this->get('/books/paths', BookController::class . ':paths')->setName('paths');
    $this->get('/books/paths/{pathUID}', BookController::class . ':booksPathInside');
    $this->get('/books/trackings/graphic-datas', BookController::class . ':getBookTrackingsGraphicData');
    $this->get('/books', BookController::class . ':allBooks');
    $this->get('/books/my-library', BookController::class . ':myBooks');
    $this->put('/books/{bookUID}/add-to-library', BookController::class . ':addToLibrary');
    $this->get('/books/finished', BookController::class . ':finishedBooks');
    $this->post('/books/{bookUID}/progress', BookController::class . ':addProgress');
    $this->post('/authors', BookController::class . ':createAuthor');
    $this->post('/books/{bookUID}/paths', BookController::class . ':addBookToPath');
    $this->delete('/books/paths/{pathUID}', BookController::class . ':removeBookFromPath');
    $this->post('/books/paths', BookController::class . ':createPath');
    $this->post('/books/paths/{pathUID}/extend', BookController::class . ':extendPathFinish');
    $this->post('/books', BookController::class . ':saveBook');
    $this->put('/books/rate/{finishedBookId:[0-9]+}', BookController::class . ':rateBook');
    $this->get('/books/{bookUID}/reading-history', BookController::class . ':getReadingHistory');
    $this->get('/books/reading-history', BookController::class . ':readingHistory');

    $this->post('/datetrackings', DateTrackingController::class . ':create');

    $this->get('/bookmarks', BookmarkController::class . ':index');
    $this->get('/bookmarks/{uid}/highlights', BookmarkController::class . ':highlights');
    $this->post('/bookmarks/{uid}/highlights', BookmarkController::class . ':addHighlight');
    $this->post('/bookmarks', BookmarkController::class . ':create');
    $this->put('/bookmarks/{uid}/status', BookmarkController::class . ':changeStatus');
    $this->put('/bookmarks/{uid}/title', BookmarkController::class . ':updateTitle');
    $this->get('/bookmarks/{uid}', BookmarkController::class . ':details');
    $this->put('/bookmarks/{uid}', BookmarkController::class . ':update');
    $this->delete('/bookmarks/{uid}', BookmarkController::class . ':delete');

    $this->get('/highlights', HighlightController::class . ':index');
    $this->post('/highlights', HighlightController::class . ':create');
    $this->get('/highlights/{id:[0-9]+}', HighlightController::class . ':details');
    $this->put('/highlights/{id:[0-9]+}', HighlightController::class . ':update');
    $this->delete('/highlights/{id:[0-9]+}', HighlightController::class . ':delete');

    $this->post('/highlights/{id:[0-9]+}/sub', HighlightController::class . ':createSub');
    $this->post('/highlights/search', HighlightController::class . ':search');
    $this->get('/highlights-all', HighlightController::class . ':all');

    $this->get('/logout', AuthController::class . ':logout');

})->add(new Middleware\Authentication($container));