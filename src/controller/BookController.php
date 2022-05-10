<?php

namespace App\controller;

use App\exception\CustomException;
use App\model\BookModel;
use App\model\TagModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class BookController extends Controller
{
    public const SOURCE_TYPE = 3;
    private $bookModel;
    private $tagModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->bookModel = new BookModel($container);
        $this->tagModel = new TagModel($container);
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

        // TODO give a decent name
        return $this->view->render($response, 'books/books.mustache', $data);
    }

    public function paths(ServerRequestInterface $request, ResponseInterface $response)
    {
        $paths = $this->bookModel->getBookPaths();

        $data = [
            'title' => 'Paths | trackr',
            'bookPaths' => $paths,
            'activeBookPaths' => 'active'
        ];

        return $this->view->render($response, 'books/paths.mustache', $data);
    }

    public function allBooks(ServerRequestInterface $request, ResponseInterface $response)
    {
        $authors = $this->bookModel->getAuthors();
        $publishers = $this->bookModel->getPublishers();
        $books = $this->bookModel->getAllBooks();

        $data = [
            'title' => 'All Books | trackr',
            'authors' => $authors,
            'books' => $books,
            'publishers' => $publishers,
            'activeAllBooks' => 'active'
        ];

        return $this->view->render($response, 'books/all.mustache', $data);
    }

    public function myBooks(ServerRequestInterface $request, ResponseInterface $response)
    {
        $books = $this->bookModel->getMyBooks();

        $data = [
            'title' => 'My Books | trackr',
            'books' => $books,
            'activeMyBooks' => 'active'
        ];

        return $this->view->render($response, 'books/my.mustache', $data);
    }

    public function finishedBooks(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $books = $this->bookModel->finishedBooks();

        $data = [
            'title' => 'Finished Books | trackr',
            'books' => $books,
            'activeFinished' => 'active'
        ];

        return $this->view->render($response, 'books/finished.mustache', $data);
    }

    public function readingHistory(ServerRequestInterface $request, ResponseInterface $response)
    {
        $readingHistory = $this->bookModel->getReadingHistory();

        $data = [
            'title' => 'Reading History | trackr',
            'readingHistory' => $readingHistory,
            'activeReadingHistory' => 'active'
        ];

        return $this->view->render($response, 'books/reading-history.mustache', $data);
    }

    public function addProgress(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $pathId = $this->bookModel->getPathIdByUid($params['pathUID']);

        $pathDetails = $this->bookModel->getPathById($pathId);
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
                        $this->bookModel->addActivityLog($pathDetails['id'], $bookId, "read {$params['amount']} page(s)");
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
                $author = trim($params['author']);
                $this->bookModel->createAuthor($author);
                $this->bookModel->addActivityLog(null, null, "add new author: $author");
            }

        } else {
            $author = trim($params['author']);
            $this->bookModel->createAuthor($author);
            $this->bookModel->addActivityLog(null, null, "add new author: $author");
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

        $this->bookModel->addActivityLog(null, $bookId, "added to library");

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

        $pathDetails = $this->bookModel->getPathById($pathId);

        if ($pathDetails['status']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "You can't add book to expired paths!");
        }

        $this->bookModel->addBookToPath($pathId, $bookId);
        $this->bookModel->addActivityLog($pathId, $bookId, "added to path");

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function extendPathFinish(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $pathId = $this->bookModel->getPathIdByUid($args['pathUID']);

        $pathDetail = $this->bookModel->getPathById($pathId);
        $extendedFinishDate = strtotime($pathDetail['finish']) + 864000;
        $this->bookModel->extendFinishDate($pathId, $extendedFinishDate);

        $this->bookModel->addActivityLog($pathId, null, "extend path finish date");

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

        if ($params['tags']) {
            $this->tagModel->updateSourceTags($params['tags'], $bookId, self::SOURCE_TYPE);
        }

        foreach ($authors as $author) {
            $this->bookModel->insertBookAuthor($bookId, $author);
        }

        $_SESSION['badgeCounts']['allBookCount'] += 1;

        if($params['own']){
            $_SESSION['badgeCounts']['myBookCount'] += 1;
        }

        $this->bookModel->addActivityLog(null, $bookId, 'created new book');
    
        $resource = [
            "message" => "Successfully created new book!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function createPath(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();
        $pathID = $this->bookModel->createPath($params['pathName'], $params['pathFinish']);

        $this->bookModel->addActivityLog($pathID, null, 'created new path');

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

            $this->bookModel->addActivityLog($pathId, $bookId, 'removed from path');

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
        $params = $request->getParsedBody();
        $finishedBookId = $args['finishedBookId'];
        $finishedBookDetails = $this->bookModel->finishedBookByID($finishedBookId);

        $this->bookModel->rateBook($finishedBookId, $params['rate']);
        $this->bookModel->addActivityLog($finishedBookDetails['path_id'], $finishedBookDetails['book_id'], "rated {$params['rate']}");

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