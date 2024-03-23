<?php

namespace App\controller;

use App\enum\BookStatus;
use App\enum\Sources;
use App\exception\CustomException;
use App\model\BookModel;
use App\model\TagModel;
use App\rabbitmq\AmqpJobPublisher;
use App\util\RequestUtil;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class BookController extends Controller
{
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
            $active = $queryParams['status'] === 'active';
        }

        $pathId = $this->bookModel->getPathIdByUid($args['pathUID']);
        $books = $this->bookModel->getBooksPathInside($pathId, $active);

        $data = [
            'pageTitle' => "Chosen Path's Books | trackr",
            'books' => $books,
            'activeBookPaths' => 'active'
        ];

        return $this->view->render($response, 'books/index.mustache', $data);
    }

    public function paths(ServerRequestInterface $request, ResponseInterface $response)
    {
        $paths = $this->bookModel->getBookPaths();

        $data = [
            'pageTitle' => 'Paths | trackr',
            'bookPaths' => $paths,
            'activeBookPaths' => 'active',
        ];

        return $this->view->render($response, 'books/paths.mustache', $data);
    }

    public function allBooks(ServerRequestInterface $request, ResponseInterface $response)
    {
        $authors = $this->bookModel->getAuthors();
        $publishers = $this->bookModel->getPublishers();
        $books = $this->bookModel->getAllBooks();
        $paths = $this->bookModel->getPathsList();

        $data = [
            'pageTitle' => 'All Books | trackr',
            'authors' => $authors,
            'books' => $books,
            'publishers' => $publishers,
            'paths' => $paths,
            'activeAllBooks' => 'active'
        ];

        return $this->view->render($response, 'books/all.mustache', $data);
    }

    public function myBooks(ServerRequestInterface $request, ResponseInterface $response)
    {
        $books = $this->bookModel->getMyBooks();
        $paths = $this->bookModel->getPathsList();

        $data = [
            'pageTitle' => 'My Books | trackr',
            'books' => $books,
            'paths' => $paths,
            'activeMyBooks' => 'active'
        ];

        return $this->view->render($response, 'books/my.mustache', $data);
    }

    public function finishedBooks(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $books = $this->bookModel->finishedBooks();

        $data = [
            'pageTitle' => 'Finished Books | trackr',
            'books' => $books,
            'activeFinished' => 'active'
        ];

        return $this->view->render($response, 'books/finished.mustache', $data);
    }

    public function getHighlights(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookUid = $args['bookUID'];
        $bookId = $this->bookModel->getBookIdByUid($bookUid);
        $highlights = $this->bookModel->getHighlights($bookId);

        $tags = $this->tagModel->getTagsBySourceId($bookId, Sources::BOOK->value);

        $_SESSION['books']['highlights']['bookID'] = $bookId;

        $data = [
            'pageTitle' => 'Book\'s Highlights | trackr',
            'highlights' => $highlights,
            'activeAllBooks' => 'active',
            'bookUID' => $bookUid,
            'tags' => $tags['imploded_comma']
        ];

        return $this->view->render($response, 'books/highlights.mustache', $data);
    }

    public function readingHistory(ServerRequestInterface $request, ResponseInterface $response)
    {
        $readingHistory = $this->bookModel->getReadingHistory();

        $data = [
            'pageTitle' => 'Reading History | trackr',
            'readingHistory' => $readingHistory,
            'activeReadingHistory' => 'active'
        ];

        return $this->view->render($response, 'books/reading-history.mustache', $data);
    }

    public function addProgress(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();

        if (!isset($params['amount']) || !$params['amount']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Amount cannot be null!");
        }

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
                    if ($params['amount'] > 0) {
                        $recordTime = $params['readYesterday'] ? strtotime("today 1 sec ago") : time();
                        $this->bookModel->insertProgressRecord($bookId, $pathId, $params['amount'], $recordTime);
                        $this->bookModel->setBookPathStatus($pathId, $bookId, BookStatus::STARTED->value);
                        $resource['responseCode'] = StatusCode::HTTP_OK;
                        $resource['message'] = "Success!";
                        $this->bookModel->addActivityLog($pathDetails['id'], $bookId,
                            "read {$params['amount']} page(s)");
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

        if (!isset($params['author']) || !$params['author']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Author cannot be null!");
        }

        $this->bookModel->createAuthorOperations($params['author']);

        $resource['responseCode'] = StatusCode::HTTP_CREATED;
        $resource['message'] = "Created author(s) successfully";

        return $this->response($resource['responseCode'], $resource);
    }

    public function changeStatus(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();

        if (!isset($args['bookUID']) || !isset($params['pathUID']) || !isset($params['status'])) {
            $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;
            $resource['message'] = "Missing required params";

            return $this->response($resource['responseCode'], $resource);
        }

        $pathId = $this->bookModel->getPathIdByUid($params['pathUID']);
        $bookId = $this->bookModel->getBookIdByUid($args['bookUID']);
        $details = $this->bookModel->getBookDetailByBookIdAndPathId($bookId, $pathId);

        if ($details['status'] === BookStatus::PRIORITIZED->value) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Already prioritized!");
        }

        if ((int)$params['status'] === BookStatus::PRIORITIZED->value && $details['status'] !== BookStatus::NEW->value) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Book status is not 'New'!");
        }

        $this->bookModel->changePathBookStatus($pathId, $bookId, $params['status']);
        $this->bookModel->addActivityLog($pathId, $bookId,
            "changed book status from {$details['status']} to {$params['status']}");

        $resource['responseCode'] = StatusCode::HTTP_OK;
        $resource['message'] = "Changed status successfully";

        return $this->response($resource['responseCode'], $resource);
    }

    public function addToLibrary(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookId = $this->bookModel->getBookIdByUid($args['bookUID']);
        $this->bookModel->addToLibrary($bookId);
        $_SESSION['badgeCounts']['myBookCount'] += 1;

        $this->bookModel->addActivityLog(null, $bookId, "added to library");

        $resource['responseCode'] = StatusCode::HTTP_OK;
        $resource['message'] = "Success";

        return $this->response($resource['responseCode'], $resource);
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

        $resource['responseCode'] = StatusCode::HTTP_OK;
        $resource['message'] = "Success";

        return $this->response($resource['responseCode'], $resource);
    }

    public function extendPathFinish(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $pathId = $this->bookModel->getPathIdByUid($args['pathUID']);

        $pathDetail = $this->bookModel->getPathById($pathId);
        $extendedFinishDate = strtotime($pathDetail['finish']) + 864000;
        $this->bookModel->extendFinishDate($pathId, $extendedFinishDate);

        $this->bookModel->addActivityLog($pathId, null, "extend path finish date");

        $resource['responseCode'] = StatusCode::HTTP_OK;
        $resource['message'] = "Success";

        return $this->response($resource['responseCode'], $resource);
    }

    public function saveBook(ServerRequestInterface $request, ResponseInterface $response)
    {
        //$rabbitmq = new AmqpJobPublisher();
        $params = $request->getParsedBody();

        $params['published_date'] = null;
        $params['description'] = null;
        $params['thumbnail'] = null;
        $params['thumbnail_small'] = null;
        $params['subtitle'] = null;

        if (isset($params['isbn']) && $params['isbn'] && isset($params['useAPI']) && $params['useAPI']) {

            $params['isbn'] = trim(str_replace("-", "", $params['isbn']));
            $params['is_complete_book'] = 1;
            $params['ebook_version'] = 0;
            $params['ebook_page_count'] = 0;

            $bookDetail = $this->bookModel->getBookByISBN($params['isbn']);

            if ($bookDetail) {
                throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST,
                    "Book already exist: " . htmlspecialchars($bookDetail['title']));
            }

            $url = 'https://www.googleapis.com/books/v1/volumes?q=isbn:' . $params['isbn'];
            $bookResponse = RequestUtil::makeHttpRequest($url, RequestUtil::HTTP_GET, [], []);

            if (!$bookResponse['totalItems']) {
//                $rabbitmq->publishJob(JobTypes::SCRAPE_BOOK_ON_IDEFIX, [
//                    'isbn' => $params['isbn'],
//                    'retry_count' => 0,
//                    'user_id' => $_SESSION['userInfos']['user_id']
//                ]);
                throw CustomException::clientError(StatusCode::HTTP_NOT_FOUND, "Book not found");
            }

            $params['bookTitle'] = $bookResponse['items'][0]['volumeInfo']['title'];
            $params['subtitle'] = $bookResponse['items'][0]['volumeInfo']['subtitle'] ?? null;

            $publisher = trim($bookResponse['items'][0]['volumeInfo']['publisher']);
            if ($publisher) {
                $publisherDetails = $this->bookModel->getPublisher($publisher);
                $params['publisher'] = !$publisherDetails ? $this->bookModel->insertPublisher($publisher) : $publisherDetails['id'];
            }

            $params['pdf'] = $bookResponse['items'][0]['accessInfo']['epub']['isAvailable'] ? 1 : 0;
            $params['epub'] = $bookResponse['items'][0]['accessInfo']['pdf']['isAvailable'] ? 1 : 0;
            $params['notes'] = null;
            $params['own'] = 0;
            $params['pageCount'] = $bookResponse['items'][0]['volumeInfo']['pageCount'];
            $params['published_date'] = $bookResponse['items'][0]['volumeInfo']['publishedDate'];
            $params['thumbnail'] = $bookResponse['items'][0]['volumeInfo']['imageLinks']['thumbnail'] ?: null;
            $params['thumbnail_small'] = $bookResponse['items'][0]['volumeInfo']['imageLinks']['smallThumbnail'] ?: null;
            $params['info_link'] = $bookResponse['items'][0]['volumeInfo']['infoLink'] ?: null;

            if ($bookResponse['items'][0]['volumeInfo']['description']) {
                $params['description'] = $bookResponse['items'][0]['volumeInfo']['description'];
            } elseif ($bookResponse['items'][0]['searchInfo']['textSnippet']) {
                $params['description'] = $bookResponse['items'][0]['searchInfo']['textSnippet'];
            } else {
                $params['description'] = null;
            }

            $params['tags'] = '';

            if (!$bookResponse['items'][0]['volumeInfo']['authors']) {
                $bookResponse['items'][0]['volumeInfo']['authors'] = ['###'];
                //throw CustomException::clientError(StatusCode::HTTP_INTERNAL_SERVER_ERROR, 'Author cannot be null!');
            }

            foreach ($bookResponse['items'][0]['volumeInfo']['authors'] as $author) {
                $params['authors'][] = $this->bookModel->insertAuthorByChecking($author);
            }

        }

        if (!$params['bookTitle']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'Title cannot be null');
        }

        $bookId = $this->bookModel->saveBook($params);
        $authors = $params['authors'];

        if ($params['tags']) {
            $this->tagModel->updateSourceTags($params['tags'], $bookId, Sources::BOOK->value);
        }

        foreach ($authors as $authorId) {
            $this->bookModel->insertBookAuthor($bookId, $authorId);
        }

        if ($params['own']) {
            $this->bookModel->addToLibrary($bookId, $params['notes']);
            $_SESSION['badgeCounts']['myBookCount'] += 1;
        }

        $_SESSION['badgeCounts']['allBookCount'] += 1;
        unset($_SESSION['books']['list']);

        $this->bookModel->addActivityLog(null, $bookId, 'created new book');

        $resource['responseCode'] = StatusCode::HTTP_OK;
        $resource['message'] = "Successfully created new book!";

        return $this->response($resource['responseCode'], $resource);
    }

    public function createPath(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        if (!isset($params['pathName']) || !$params['pathName'] || !isset($params['pathFinish'])) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST,
                'Path Name or Path Finish Date cannot be null');
        }

        $pathID = $this->bookModel->createPath($params['pathName'], $params['pathFinish']);

        $this->bookModel->addActivityLog($pathID, null, 'created new path');

        $resource['responseCode'] = StatusCode::HTTP_OK;
        $resource['message'] = "Success";

        return $this->response($resource['responseCode'], $resource);
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
        $finishedBookUid = $args['bookUID'];
        $finishedBookId = $this->bookModel->getBookIdByUid($finishedBookUid);

        if (!$finishedBookId) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'Book not found');
        }

        $finishedBookDetails = $this->bookModel->finishedBookByID($finishedBookId);

        $this->bookModel->rateBook($finishedBookId, $params['rate']);
        $this->bookModel->addActivityLog($finishedBookDetails['path_id'], $finishedBookDetails['book_id'],
            "rated {$params['rate']}");

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

    public function addHighlight(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $bookUid = $args['bookUID'];
        $bookId = $this->bookModel->getBookIdByUid($bookUid);
        $bookDetail = $this->bookModel->getBookById($bookId);

        if (!$params['highlight']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Highlight cannot be null!");
        }

        if (!isset($_SESSION['books']['highlights']['bookID']) || $bookId != $_SESSION['books']['highlights']['bookID']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST,
                "Inconsistency! You're trying to add highlight for different book!");
        }

        $highlightDetail['book_id'] = $bookId;
        $highlightDetail['highlight'] = $params['highlight'];
        $highlightDetail['page'] = $params['page'];
        $highlightDetail['location'] = $params['location'];
        $highlightDetail['blogPath'] = $params['blogPath'];
        $highlightDetail['author'] = $bookDetail['author'];
        $highlightDetail['source'] = $bookDetail['title'];
        $highlightDetail['type'] = 1;

        $highlightId = $this->bookModel->addHighlight($highlightDetail);

        $this->tagModel->updateSourceTags($params['tags'], $highlightId, Sources::HIGHLIGHT->value);

        unset($_SESSION['highlights']['minMaxID']);
        unset($_SESSION['books']['highlights']['bookID']);

        $resource['message'] = "Successfully added highlight";
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

    public function getBookTrackingsGraphicData(ServerRequestInterface $request, ResponseInterface $response)
    {
        $graphicDatas = $this->bookModel->getBookTrackingsGraphicData();

        $resource['data'] = $graphicDatas;
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

}