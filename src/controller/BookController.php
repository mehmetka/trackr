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
        $pathId = $this->bookModel->getPathIdByUid($args['pathUID']);
        $books = $this->bookModel->getBooksPathInside($pathId);

        $data = [
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
        $authors = $this->bookModel->getAuthors();
        $categories = $this->bookModel->getCategories();
        $books = $this->bookModel->getAllBooks();

        $data = [
            'categories' => $categories,
            'authors' => $authors,
            'books' => $books,
            'activeAllBooks' => 'active'
        ];

        return $this->view->render($response, 'all-books.mustache', $data);
    }

    public function myBooks(ServerRequestInterface $request, ResponseInterface $response)
    {
        $books = $this->bookModel->getMyBooks();

        $data = [
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

        $pathId = $this->bookModel->getPathIdByUid($params['pathUID']);
        $bookId = $this->bookModel->getBookIdByUid($args['bookUID']);

        $bookDetail = $this->bookModel->getBookDetailByBookIdAndPathId($bookId, $pathId);

        if ($bookDetail['status'] == 2) {
            $response['message'] = "Can't be add progress to done books!";
            $response['responseCode'] = 400;
        } else {
            $this->bookModel->insertProgressRecord($bookId, $pathId, $params['amount']);
            $response['responseCode'] = 200;
            $response['message'] = "Success!";
        }

        return $this->response($response['responseCode'], $response);
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
        $bookId = $this->bookModel->getBookIdByUid($args['bookUID']);
        $this->bookModel->addToLibrary($bookId);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

    public function addBookToPath(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();

        $pathId = $this->bookModel->getPathIdByUid($params['pathUID']);
        $bookId = $this->bookModel->getBookIdByUid($args['bookUID']);

        $this->bookModel->addBookToPath($pathId, $bookId);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

    public function extendPathFinish(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $pathDetail = $this->bookModel->getPathByUid($args['pathUID']);
        $extendedFinishDate = strtotime($pathDetail['finish']) + 864000;
        $this->bookModel->extendFinishDate($args['pathUID'], $extendedFinishDate);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

    public function saveBook(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();
        $bookId = $this->bookModel->saveBook($params);
        $authors = $params['authors'];

        foreach ($authors as $author) {
            $this->bookModel->insertBookAuthor($bookId, $author);
        }

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

    public function createPath(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();
        $this->bookModel->createPath($params['pathName'], $params['pathFinish']);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

    public function removeBookFromPath(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $pathId = $this->bookModel->getPathIdByUid($args['pathUID']);
        $bookId = $this->bookModel->getBookIdByUid($params['bookUID']);

        $bookDetail = $this->bookModel->getBookDetailByBookIdAndPathId($bookId, $pathId);

        if ($bookDetail['status'] == 2) {
            $response['message'] = "Can't be remove done books!";
            $response['responseCode'] = 400;
        } else {
            $this->bookModel->deleteBookTrackingsByPath($bookId, $pathId);
            $this->bookModel->deleteBookFromPath($bookId, $pathId);

            $response['message'] = "Can't be remove done books!";
            $response['responseCode'] = 400;
        }

        return $this->response($response['responseCode'], $response);
    }

    public function categories(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $categories = $this->bookModel->getCategories();
        $defaultCategory = $this->bookModel->getDefaultCategory();

        if (!$defaultCategory) {
            $defaultCategoryId = $this->bookModel->createCategory('default');
            $this->bookModel->resetCategoriesDefaultStatus();
            $this->bookModel->setDefaultCategory($defaultCategoryId, 1);
        }

        $data = [
            'categories' => $categories,
            'activeCategories' => 'active'
        ];

        return $this->view->render($response, 'categories.mustache', $data);
    }

    public function createCategory(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $this->bookModel->createCategory($params['category']);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

    public function deleteCategory(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $defaultCategory = $this->bookModel->getDefaultCategory();
        $this->bookModel->deleteCategory($args['categoryId']);

        if ($defaultCategory['id'] == $args['categoryId']) {
            $defaultCategoryId = $this->bookModel->createCategory('default');
            $this->bookModel->resetCategoriesDefaultStatus();
            $this->bookModel->setDefaultCategory($defaultCategoryId, 1);
            $this->bookModel->changeBooksCategoryByGivenCategory($args['categoryId'], $defaultCategoryId);
        } else {
            $this->bookModel->changeBooksCategoryByGivenCategory($args['categoryId'], $defaultCategory['id']);
        }

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

    public function setDefaultCategory(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $categoryId = $args['categoryId'];
        $active = 1;

        $this->bookModel->resetCategoriesDefaultStatus();
        $this->bookModel->setDefaultCategory($categoryId, $active);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }
}