<?php

namespace App\controller;

use App\exception\CustomException;
use App\model\BookmarkModel;
use App\model\TagModel;
use App\rabbitmq\AmqpJobPublisher;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class BookmarkController extends Controller
{
    public const SOURCE_TYPE = 2;
    private $bookmarkModel;
    private $tagModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->bookmarkModel = new BookmarkModel($container);
        $this->tagModel = new TagModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $queryString = $request->getQueryParams();
        $defaultTag = 'technical-uncategorized';

        $bookmarks = $this->bookmarkModel->getBookmarks($queryString['tag']);

        $bookmarkCategories = $this->tagModel->getSourceTagsByType(self::SOURCE_TYPE, $queryString['tag']);

        $data = [
            'title' => 'Bookmarks | trackr',
            'bookmarkCategories' => $bookmarkCategories,
            'bookmarks' => $bookmarks,
            'activeBookmarks' => 'active',
            'defaultTag' => $defaultTag
        ];

        return $this->view->render($response, 'bookmarks/index.mustache', $data);
    }

    public function highlights(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkUid = $args['uid'];
        $bookmarkId = $this->bookmarkModel->getBookmarkIdByUid($bookmarkUid);

        $highlights = $this->bookmarkModel->getHighlights($bookmarkId);
        $_SESSION['bookmarks']['highlights']['bookmarkID'] = $bookmarkId;

        $data = [
            'title' => 'Bookmark\' Highlights | trackr',
            'highlights' => $highlights,
            'activeBookmarks' => 'active',
            'bookmarkUID' => $bookmarkUid
        ];

        return $this->view->render($response, 'bookmarks/highlights.mustache', $data);
    }

    public function details(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkUid = $args['uid'];
        $bookmarkId = $this->bookmarkModel->getBookmarkIdByUid($bookmarkUid);
        $details = $this->bookmarkModel->getBookmarkById($bookmarkId);

        $data = [
            'title' => 'Bookmark\'s Details | trackr',
            'details' => $details,
            'activeBookmarks' => 'active',
        ];

        return $this->view->render($response, 'bookmarks/details.mustache', $data);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response)
    {
        $rabbitmq = new AmqpJobPublisher();
        $params = $request->getParsedBody();

        $bookmarkID = $this->bookmarkModel->createOperations($params['bookmark'], $params['note']);

        if ($params['tags']) {
            $this->tagModel->updateSourceTags($params['tags'], $bookmarkID, self::SOURCE_TYPE);
        }

        $rabbitmq->publishBookmarkTitleJob($bookmarkID);

        $_SESSION['badgeCounts']['bookmarkCount'] += 1;

        $resource = [
            "message" => "Successfully added bookmark",
            "id" => $bookmarkID
        ];

        return $this->response(StatusCode::HTTP_CREATED, $resource);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $rabbitmq = new AmqpJobPublisher();
        $bookmarkUid = $args['uid'];
        $bookmarkId = $this->bookmarkModel->getBookmarkIdByUid($bookmarkUid);
        $params = $request->getParsedBody();

        $this->bookmarkModel->updateBookmark($bookmarkId, $params);

        $this->tagModel->deleteTagsBySourceId($bookmarkId, self::SOURCE_TYPE);

        if ($params['tags']) {
            $this->tagModel->updateSourceTags($params['tags'], $bookmarkId, self::SOURCE_TYPE);
        }

        $rabbitmq->publishBookmarkTitleJob($bookmarkId);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function addHighlight(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkUid = $args['uid'];
        $bookmarkId = $this->bookmarkModel->getBookmarkIdByUid($bookmarkUid);
        $params = $request->getParsedBody();

        if (!$params['highlight']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Highlight cannot be null!");
        }

        if (!isset($_SESSION['bookmarks']['highlights']['bookmarkID']) || $bookmarkId != $_SESSION['bookmarks']['highlights']['bookmarkID']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Inconsistency! You're trying to add highlight for different bookmark!");
        }

        $bookmarkDetail = $this->bookmarkModel->getBookmarkById($_SESSION['bookmarks']['highlights']['bookmarkID']);
        $bookmarkDetail['highlight'] = $params['highlight'];
        $bookmarkDetail['author'] = $bookmarkDetail['title'] ? $bookmarkDetail['title'] : null;
        $bookmarkDetail['source'] = 'Bookmark Highlight';
        $highlightId = $this->bookmarkModel->addHighlight($bookmarkDetail);

        if ($params['tags']) {
            $this->tagModel->updateSourceTags($params['tags'], $highlightId, self::SOURCE_TYPE);
        }

        $this->bookmarkModel->updateStartedDate($bookmarkId);

        $resource = [
            "message" => "Successfully added highlight"
        ];

        unset($_SESSION['bookmarks']['highlights']['bookmarkID']);

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function changeStatus(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $bookmarkUid = $args['uid'];
        $bookmarkId = $this->bookmarkModel->getBookmarkIdByUid($bookmarkUid);

        if ($params['status'] == 1) {
            $this->bookmarkModel->updateStartedDate($bookmarkId);
        } elseif ($params['status'] == 2) {
            $this->bookmarkModel->updateDoneDate($bookmarkId);
            $_SESSION['badgeCounts']['bookmarkCount'] -= 1;
        }

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkUid = $args['uid'];
        $bookmarkId = $this->bookmarkModel->getBookmarkIdByUid($bookmarkUid);

        $this->bookmarkModel->deleteBookmark($bookmarkId);
        $this->tagModel->deleteTagsBySourceId($bookmarkId, self::SOURCE_TYPE);

        $_SESSION['badgeCounts']['bookmarkCount'] -= 1;

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

}