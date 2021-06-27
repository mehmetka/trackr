<?php

use App\middleware;
use App\controller\AuthController;
use App\controller\HomeController;
use App\controller\BookController;
use App\controller\BookmarkController;
use App\controller\DateTrackingController;
use App\controller\TrackingController;
use App\controller\VideoController;
use App\controller\HighlightController;
use App\controller\WritingController;
use App\controller\TodoController;

$app->group('', function () {
    $this->get('/login', AuthController::class . ':loginPage')->setName('login');
    $this->post('/login', AuthController::class . ':login');
    $this->get('/register', AuthController::class . ':registerPage')->setName('register');
    $this->post('/register', AuthController::class . ':register');
})->add(new Middleware\Guest($container));

$app->group('/api', function () {
    $this->post('/bookmarks', BookmarkController::class . ':create');
});

$app->group('', function () {

    $this->get('/', HomeController::class . ':index')->setName('home');

    $this->get('/menu-badge-counts', HomeController::class . ':getMenuBadgeCounts');

    $this->get('/books/paths', BookController::class . ':paths')->setName('paths');
    $this->get('/books/paths/{pathUID}', BookController::class . ':booksPathInside');
    $this->get('/books/trackings/graphic-datas', BookController::class . ':getBookTrackingsGraphicData');
    $this->get('/all-books', BookController::class . ':allBooks');
    $this->get('/my-books', BookController::class . ':myBooks');
    $this->put('/books/{bookUID}/add-to-library', BookController::class . ':addToLibrary');
    $this->get('/books/finished', BookController::class . ':finishedBooks');
    $this->post('/books/{bookUID}/progress', BookController::class . ':addProgress');
    $this->post('/authors', BookController::class . ':createAuthor');
    $this->post('/books/{bookUID}/paths', BookController::class . ':addBookToPath');
    $this->delete('/books/paths/{pathUID}', BookController::class . ':removeBookFromPath');
    $this->post('/books/paths', BookController::class . ':createPath');
    $this->post('/books/paths/{pathUID}/extend', BookController::class . ':extendPathFinish');
    $this->post('/books', BookController::class . ':saveBook');

    $this->get('/categories', BookController::class . ':categories');
    $this->post('/categories', BookController::class . ':createCategory');
    $this->delete('/categories/{categoryId:[0-9]+}', BookController::class . ':deleteCategory');
    $this->put('/categories/{categoryId:[0-9]+}', BookController::class . ':setDefaultCategory');

    $this->post('/datetrackings', DateTrackingController::class . ':create');

    $this->get('/bookmarks', BookmarkController::class . ':index');
    $this->get('/bookmarks/{id:[0-9]+}/highlights', BookmarkController::class . ':highlights');
    $this->post('/bookmarks/{id:[0-9]+}/highlights', BookmarkController::class . ':addHighlight');
    $this->post('/bookmarks', BookmarkController::class . ':create');
    $this->put('/bookmarks/{id:[0-9]+}/status', BookmarkController::class . ':changeStatus');
    $this->delete('/bookmarks/{id:[0-9]+}', BookmarkController::class . ':delete');

    $this->get('/trackings', TrackingController::class . ':index');
    $this->post('/trackings', TrackingController::class . ':add');

    $this->get('/videos', VideoController::class . ':index');
    $this->post('/videos', VideoController::class . ':create');
    $this->put('/videos/{id:[0-9]+}/status', VideoController::class . ':changeStatus');

    $this->get('/highlights', HighlightController::class . ':index');
    $this->get('/highlights/{id:[0-9]+}/details', HighlightController::class . ':details');
    $this->get('/highlights-all', HighlightController::class . ':all');
    $this->put('/highlights/{id:[0-9]+}', HighlightController::class . ':update');
    $this->post('/highlights', HighlightController::class . ':create');
    $this->post('/highlights/{id:[0-9]+}/sub', HighlightController::class . ':createSub');

    $this->get('/writings', WritingController::class . ':index');
    $this->post('/writings', WritingController::class . ':create');

    $this->get('/todos', TodoController::class . ':index');
    $this->get('/all-todos', TodoController::class . ':allTodos');
    $this->post('/todos', TodoController::class . ':add');
    $this->get('/todos/{id:[0-9]+}', TodoController::class . ':get');
    $this->put('/todos/{id:[0-9]+}', TodoController::class . ':update');
    $this->put('/todos/{id:[0-9]+}/status', TodoController::class . ':changeStatus');
    $this->put('/todos/{id:[0-9]+}/escalate', TodoController::class . ':escalateTodo');

    $this->get('/logout', AuthController::class . ':logout');

})->add(new Middleware\Authentication($container));