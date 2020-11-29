<?php

namespace App\controller;

use App\model\BookModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;

class BookController extends Controller
{
    private $bookModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->bookModel = new BookModel($container);
    }

    public function booksPathInside(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $authors = $this->bookModel->getAuthorsKeyValue();
        $subject = $this->bookModel->getSubjectsKeyValue();
        $books = $this->bookModel->getBooksPathInside($args['pathId']);

        $data = [
            'subjects' => $subject,
            'authors' => $authors,
            'books' => $books,
            'activeBookPaths' => 'active'
        ];

        return $this->view->render($response, 'books.mustache', $data);
    }

    public function paths(ServerRequestInterface $request, ResponseInterface $response)
    {
        $averageData = $this->bookModel->readingAverage();
        $paths = $this->bookModel->getBookPaths();

        $data = [
            "bookPaths" => $paths,
            'readingAverage' => round($averageData['average'], 3),
            'readingTotal' => $averageData['total'],
            'dayDiff' => $averageData['diff'],
            'activeBookPaths' => 'active'
        ];

        return $this->view->render($response, 'paths.mustache', $data);
    }

    public function allBooks(ServerRequestInterface $request, ResponseInterface $response)
    {
        $authors = $this->bookModel->getAuthorsKeyValue();
        $subject = $this->bookModel->getSubjectsKeyValue();
        $books = $this->bookModel->getAllBooks();

        $data = [
            'subjects' => $subject,
            'authors' => $authors,
            'books' => $books,
            'activeAllBooks' => 'active'
        ];

        return $this->view->render($response, 'all-books.mustache', $data);
    }

    public function myBooks(ServerRequestInterface $request, ResponseInterface $response)
    {
        $authors = $this->bookModel->getAuthorsKeyValue();
        $subject = $this->bookModel->getSubjectsKeyValue();
        $books = $this->bookModel->getMyBooks();

        $data = [
            'subjects' => $subject,
            'authors' => $authors,
            'books' => $books,
            'activeMyBooks' => 'active'
        ];

        return $this->view->render($response, 'my-books.mustache', $data);
    }

    public function finishedBooks(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $books = $this->bookModel->finishedBooks();

        $data = [
            'books' => $books,
            'activeFinished' => 'active'
        ];

        return $this->view->render($response, 'finished.mustache', $data);
    }

    public function addProgress(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();

        $this->bookModel->insertProgressRecord($args['bookId'], $params['pathId'], $params['amount']);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

    public function createAuthor(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();

        if (strpos($params['author'], ',') !== false) {
            $authors = explode(',', $params['author']);

            foreach ($authors as $author) {
                $this->bookModel->createAuthor(trim($author));
            }

        } else {
            $this->bookModel->createAuthor(trim($params['author']));
        }

        $resource = [
            "message" => "Created successfully: " . htmlentities($params['author'])
        ];

        return $this->response(200, $resource);
    }

    public function addToLibrary(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookId = $args['bookId'];
        $this->bookModel->addToLibrary($bookId);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

    public function addBookToPath(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();

        $pathId = $params['pathId'];
        $bookId = $args['bookId'];

        $this->bookModel->addBookToPath($pathId,$bookId);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

    public function resetBook(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookId = $args['bookId'];
        $this->bookModel->deleteBookTrackings($bookId);
        $this->bookModel->deleteBookRecordsFromPaths($bookId);
        $this->bookModel->setBookStatus($bookId, 1);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

    public function extend(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $pathDetail = $this->bookModel->getPathById($args['pathId']);
        $extendedFinishDate = strtotime($pathDetail['finish']) + 864000;
        $this->bookModel->extendFinishDate($args['pathId'], $extendedFinishDate);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }
}