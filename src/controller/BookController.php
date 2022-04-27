<?php

namespace App\controller;

use App\exception\CustomException;
use App\model\BookModel;
use App\model\CategoryModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class BookController extends Controller
{
    private $bookModel;
    private $categoryModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->bookModel = new BookModel($container);
        $this->categoryModel = new CategoryModel($container);
    }

    public function booksPathInside(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $queryParams = $request->getQueryParams();
        $active = false;

        if (isset($queryParams['status'])) {
            $active = $queryParams['status'] === 'active' ? true : false;
        }

        $pathId = $this->bookModel->getPathIdByUid($args['pathUID']);
        $books = $this->bookModel->getBooksPathInside($pathId, $active);

        $data = [
            'title' => "Chosen Path's Books | trackr",
            'books' => $books,
            'activeBookPaths' => 'active'
        ];

        return $this->view->render($response, 'books.mustache', $data);
    }

    public function paths(ServerRequestInterface $request, ResponseInterface $response)
    {
        $paths = $this->bookModel->getBookPaths();

        $data = [
            'title' => 'Paths | trackr',
            'bookPaths' => $paths,
            'activeBookPaths' => 'active'
        ];

        return $this->view->render($response, 'paths.mustache', $data);
    }

    public function allBooks(ServerRequestInterface $request, ResponseInterface $response)
    {
        $authors = $this->bookModel->getAuthors();
        $categories = $this->categoryModel->getCategories();
        $publishers = $this->bookModel->getPublishers();
        $books = $this->bookModel->getAllBooks();

        $data = [
            'title' => 'All Books | trackr',
            'categories' => $categories,
            'authors' => $authors,
            'books' => $books,
            'publishers' => $publishers,
            'activeAllBooks' => 'active'
        ];

        return $this->view->render($response, 'all-books.mustache', $data);
    }

    public function myBooks(ServerRequestInterface $request, ResponseInterface $response)
    {
        $books = $this->bookModel->getMyBooks();

        $data = [
            'title' => 'My Books | trackr',
            'books' => $books,
            'activeMyBooks' => 'active'
        ];

        return $this->view->render($response, 'my-books.mustache', $data);
    }

    public function finishedBooks(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $books = $this->bookModel->finishedBooks();

        $data = [
            'title' => 'Finished Books | trackr',
            'books' => $books,
            'activeFinished' => 'active'
        ];

        return $this->view->render($response, 'finished.mustache', $data);
    }

    public function readingHistory(ServerRequestInterface $request, ResponseInterface $response)
    {
        $readingHistory = $this->bookModel->getReadingHistory();

        $data = [
            'title' => 'Reading History | trackr',
            'readingHistory' => $readingHistory,
            'activeReadingHistory' => 'active'
        ];

        return $this->view->render($response, 'reading-history.mustache', $data);
    }

    public function addProgress(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();

        $pathDetails = $this->bookModel->getPathByUid($params['pathUID']);
        $bookId = $this->bookModel->getBookIdByUid($args['bookUID']);

        $bookDetail = $this->bookModel->getBookDetailByBookIdAndPathId($bookId, $pathDetails['id']);
        $readAmount = $this->bookModel->getReadAmount($bookId, $pathDetails['id']);

        if ($pathDetails['status']) {
            $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;
            $resource['message'] = "You can't add progress to expired paths!";
        } else {
            if ($bookDetail['status'] == 2) {
                $resource['message'] = "Can't add progress to done books!";
                $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;
            } else {

                if (($bookDetail['page_count'] - $readAmount) - $params['amount'] < 0) {
                    $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;
                    $resource['message'] = "You can't add progress more than remaining amount!";
                } else {
                    if($params['amount'] > 0){
                        $this->bookModel->insertProgressRecord($bookId, $pathDetails['id'], $params['amount']);
                        $resource['responseCode'] = StatusCode::HTTP_OK;
                        $resource['message'] = "Success!";
                    } else {
                        $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;
                        $resource['message'] = "Amount must be positive";
                    }
                }
                
            }
        }

        return $this->response($resource['responseCode'], $resource);
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

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function addToLibrary(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookId = $this->bookModel->getBookIdByUid($args['bookUID']);
        $this->bookModel->addToLibrary($bookId);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function addBookToPath(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();

        $pathId = $this->bookModel->getPathIdByUid($params['pathUID']);
        $bookId = $this->bookModel->getBookIdByUid($args['bookUID']);

        $pathDetails = $this->bookModel->getPathByUid($params['pathUID']);
        
        if ($pathDetails['status']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "You can't add book to expired paths!");
        }

        $this->bookModel->addBookToPath($pathId, $bookId);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function extendPathFinish(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $pathDetail = $this->bookModel->getPathByUid($args['pathUID']);
        $extendedFinishDate = strtotime($pathDetail['finish']) + 864000;
        $this->bookModel->extendFinishDate($args['pathUID'], $extendedFinishDate);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function saveBook(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();
        $bookId = $this->bookModel->saveBook($params);
        $authors = $params['authors'];

        foreach ($authors as $author) {
            $this->bookModel->insertBookAuthor($bookId, $author);
        }

        $_SESSION['badgeCounts']['allBookCount'] += 1;

        if($params['own']){
            $_SESSION['badgeCounts']['myBookCount'] += 1;
        }
    
        $resource = [
            "message" => "Successfully created new book!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function createPath(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();
        $this->bookModel->createPath($params['pathName'], $params['pathFinish']);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function removeBookFromPath(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $pathId = $this->bookModel->getPathIdByUid($args['pathUID']);
        $bookId = $this->bookModel->getBookIdByUid($params['bookUID']);

        $bookDetail = $this->bookModel->getBookDetailByBookIdAndPathId($bookId, $pathId);

        if ($bookDetail['status'] == 0) {
            $this->bookModel->deleteBookTrackingsByPath($bookId, $pathId);
            $this->bookModel->deleteBookFromPath($bookId, $pathId);

            $resource['message'] = "Successfully removed.";
            $resource['responseCode'] = StatusCode::HTTP_OK;
        } else {
            $resource['message'] = "You can remove only 'Not Started' books from paths!";
            $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;
        }

        return $this->response($resource['responseCode'], $resource);
    }

    public function rateBook(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookUID = $args['bookUID'];
        $bookId = $this->bookModel->getBookIdByUid($bookUID);
        $params = $request->getParsedBody();
        
        $this->bookModel->rateBook($bookId, $params['rate']);

        $resource['message'] = "Successfully rated!";
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

    public function getReadingHistory(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookUID = $args['bookUID'];
        $bookId = $this->bookModel->getBookIdByUid($bookUID);

        $resource['data'] = $this->bookModel->getReadingHistory($bookId);
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

    public function getBookTrackingsGraphicData(ServerRequestInterface $request, ResponseInterface $response)
    {
        $graphicDatas = $this->bookModel->getBookTrackingsGraphicData();

        echo "<pre>";
        print_r($graphicDatas);
        die;

        $resource = [
            "data" => $graphicDatas,
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }
}