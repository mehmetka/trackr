<?php

namespace App\controller;

use App\enum\BookmarkStatus;
use App\enum\JobTypes;
use App\enum\Sources;
use App\exception\CustomException;
use App\model\BookmarkModel;
use App\model\TagModel;
use App\rabbitmq\AmqpJobPublisher;
use App\util\HighlightUtil;
use App\util\TagUtil;
use App\util\TwitterUtil;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class BookmarkController extends Controller
{
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
        $defaultTag = 'technical';

        $bookmarks = $this->bookmarkModel->getBookmarks($queryString['tag']);

        $bookmarkCategories = $this->tagModel->getSourceTagsByType(Sources::BOOKMARK->value, $queryString['tag']);

        $data = [
            'pageTitle' => 'Bookmarks | trackr',
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
//        $details = $this->bookmarkModel->getChildBookmarkById($bookmarkId, $_SESSION['userInfos']['user_id']);
        $highlights = $this->bookmarkModel->getHighlights($bookmarkId);
        $tags = $this->tagModel->getTagsBySourceId($bookmarkId, Sources::BOOKMARK->value);

//        if ($details['keyword'] && !in_array($details['keyword'], $tags['tags'])) {
//            $tags['imploded_comma'] .= ', ' . $details['keyword'];
//        }

        $_SESSION['bookmarks']['highlights']['bookmarkID'] = $bookmarkId;

        $data = [
            'pageTitle' => 'Bookmark\'s Highlights | trackr',
            'highlights' => $highlights,
            'activeBookmarks' => 'active',
            'bookmarkUID' => $bookmarkUid,
            'tags' => $tags['imploded_comma']
        ];

        return $this->view->render($response, 'bookmarks/highlights.mustache', $data);
    }

    public function details(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkUid = $args['uid'];
        $bookmarkId = $this->bookmarkModel->getBookmarkIdByUid($bookmarkUid);

        $details = $this->bookmarkModel->getChildBookmarkById($bookmarkId, $_SESSION['userInfos']['user_id']);

        $data = [
            'pageTitle' => 'Bookmark\'s Details | trackr',
            'details' => $details,
            'activeBookmarks' => 'active',
        ];

        return $this->view->render($response, 'bookmarks/details.mustache', $data);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response)
    {
        $rabbitmq = new AmqpJobPublisher();
        $params = $request->getParsedBody();
        $bookmarkCreatedBefore = true;

        if (!$params['bookmark']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'Bookmark cannot be empty!');
        }

        $bookmarkExist = $this->bookmarkModel->getParentBookmarkByBookmark($params['bookmark']);

        if (!$bookmarkExist) {
            $bookmarkID = $this->bookmarkModel->create($params['bookmark']);
            $bookmarkCreatedBefore = false;
        } else {
            $bookmarkID = $bookmarkExist['id'];
        }

        $bookmarkAddedToReadingList = $this->bookmarkModel->getChildBookmarkById($bookmarkID,
            $_SESSION['userInfos']['user_id']);

        if ($bookmarkCreatedBefore && $bookmarkAddedToReadingList) {
            $this->bookmarkModel->updateUpdatedAt($bookmarkID);
            $this->bookmarkModel->updateIsDeletedStatus($bookmarkID, BookmarkModel::NOT_DELETED);
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'Bookmark exist!');
        }

        if ($params['tags']) {
            $this->tagModel->updateSourceTags($params['tags'], $bookmarkID, Sources::BOOKMARK->value);
        }

        $rabbitmq->publishJob(JobTypes::GET_PARENT_BOOKMARK_TITLE, [
            'id' => $bookmarkID,
            'retry_count' => 0,
            'user_id' => $_SESSION['userInfos']['user_id']
        ]);

        if (!$_ENV['DISABLE_ASK_CHATGPT']) {
            $rabbitmq->publishJob(JobTypes::GET_KEYWORD_ABOUT_BOOKMARK, [
                'id' => $bookmarkID
            ]);
        }

        $this->bookmarkModel->addOwnership($bookmarkID, $_SESSION['userInfos']['user_id'], $params['note']);
        $_SESSION['badgeCounts']['bookmarkCount'] += 1;

        $resource = [
            "message" => "Successfully added bookmark",
            "id" => $bookmarkID
        ];

        return $this->response(StatusCode::HTTP_CREATED, $resource);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkUid = $args['uid'];
        $bookmarkDetails = $this->bookmarkModel->getBookmarkByUid($bookmarkUid);
        $bookmarkId = $bookmarkDetails['id'];
        $params = $request->getParsedBody();
        $status = intval($params['status']);

        $this->bookmarkModel->updateChildBookmark($bookmarkId, $params, $_SESSION['userInfos']['user_id']);

        $this->tagModel->deleteTagsBySourceId($bookmarkId, Sources::BOOKMARK->value);

        if ($params['title'] !== $bookmarkDetails['title']) {
            $this->bookmarkModel->updateIsTitleEditedStatus($bookmarkId, BookmarkModel::TITLE_EDITED);
            $this->bookmarkModel->updateHighlightAuthor($bookmarkId, $params['title'],
                $_SESSION['userInfos']['user_id']);
        }

        if ($status !== $bookmarkDetails['status']) {
            if ($status === BookmarkStatus::NEW->value) {
                $this->bookmarkModel->updateStartedDate($bookmarkId, null);
                $this->bookmarkModel->updateDoneDate($bookmarkId, null);
                $this->bookmarkModel->updateIsDeletedStatus($bookmarkId, BookmarkModel::NOT_DELETED);
                $this->tagModel->updateIsDeletedStatusBySourceId(Sources::BOOKMARK->value, $bookmarkId,
                    BookmarkModel::NOT_DELETED);
            } elseif ($status === BookmarkStatus::STARTED->value) {
                $this->bookmarkModel->updateStartedDate($bookmarkId, time());
                $this->bookmarkModel->updateDoneDate($bookmarkId, null);
                $this->bookmarkModel->updateIsDeletedStatus($bookmarkId, BookmarkModel::NOT_DELETED);
                $this->tagModel->updateIsDeletedStatusBySourceId(Sources::BOOKMARK->value, $bookmarkId,
                    BookmarkModel::NOT_DELETED);
            } elseif ($status === BookmarkStatus::DONE->value) {
                $this->bookmarkModel->updateDoneDate($bookmarkId, time());
                $this->bookmarkModel->updateIsDeletedStatus($bookmarkId, BookmarkModel::NOT_DELETED);
                $this->tagModel->updateIsDeletedStatusBySourceId(Sources::BOOKMARK->value, $bookmarkId,
                    BookmarkModel::NOT_DELETED);
                $_SESSION['badgeCounts']['bookmarkCount'] -= 1;
            } elseif ($status === BookmarkStatus::PRIORITIZED->value) {
                $this->bookmarkModel->updateBookmarkStatus($bookmarkId, BookmarkStatus::PRIORITIZED->value);
                $this->bookmarkModel->updateIsDeletedStatus($bookmarkId, BookmarkModel::NOT_DELETED);
                $this->tagModel->updateIsDeletedStatusBySourceId(Sources::BOOKMARK->value, $bookmarkId,
                    BookmarkModel::NOT_DELETED);
            } else {
                return $this->response(StatusCode::HTTP_BAD_REQUEST, [
                    'message' => 'Status not found'
                ]);
            }
        }

        if ($params['tags']) {
            $this->tagModel->updateSourceTags($params['tags'], $bookmarkId, Sources::BOOKMARK->value);
        }

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function addHighlight(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkUid = $args['uid'];
        $bookmarkDetail = $this->bookmarkModel->getBookmarkByUid($bookmarkUid);
        $params = $request->getParsedBody();

        if (!$params['highlight']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Highlight cannot be null!");
        }

        if (!isset($_SESSION['bookmarks']['highlights']['bookmarkID']) || $bookmarkDetail['id'] != $_SESSION['bookmarks']['highlights']['bookmarkID']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST,
                "Inconsistency! You're trying to add highlight for different bookmark!");
        }

        $bookmarkDetail['highlight'] = $params['highlight'];
        $bookmarkDetail['blog_path'] = 'general/uncategorized';

        if (TwitterUtil::isTwitterUrl($bookmarkDetail['bookmark'])) {
            $username = TwitterUtil::getUsernameFromUrl($bookmarkDetail['bookmark']);
            $bookmarkDetail['author'] = $username;
            $bookmarkDetail['source'] = 'Twitter';
        } else {
            $bookmarkDetail['author'] = $bookmarkDetail['title'] ?? null;
            $bookmarkDetail['source'] = 'Bookmark Highlight';
        }

        $highlightId = $this->bookmarkModel->addHighlight($bookmarkDetail);

        $this->tagModel->updateSourceTags($params['tags'], $highlightId, Sources::HIGHLIGHT->value);

        if ($bookmarkDetail['status'] != 2) {
            $this->bookmarkModel->updateStartedDate($bookmarkDetail['id'], time());
            $this->bookmarkModel->updateBookmarkStatus($bookmarkDetail['id'], BookmarkStatus::STARTED->value);
        }

        $resource = [
            "message" => "Successfully added highlight"
        ];

        unset($_SESSION['highlights']['minMaxID']);
        unset($_SESSION['bookmarks']['highlights']['bookmarkID']);

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function changeStatus(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $bookmarkUid = $args['uid'];
        $bookmarkId = $this->bookmarkModel->getBookmarkIdByUid($bookmarkUid);
        $status = (int)$params['status'];

        if ($status === BookmarkStatus::STARTED->value) {
            $this->bookmarkModel->updateStartedDate($bookmarkId, time());
            $this->bookmarkModel->updateBookmarkStatus($bookmarkId, BookmarkStatus::STARTED->value);
        } elseif ($status === BookmarkStatus::DONE->value) {
            $this->bookmarkModel->updateDoneDate($bookmarkId, time());
            $this->bookmarkModel->updateBookmarkStatus($bookmarkId, BookmarkStatus::DONE->value);
            $_SESSION['badgeCounts']['bookmarkCount'] -= 1;
        } elseif ($status === BookmarkStatus::PRIORITIZED->value) {
            $this->bookmarkModel->updateBookmarkStatus($bookmarkId, BookmarkStatus::PRIORITIZED->value);
        } else {
            return $this->response(StatusCode::HTTP_BAD_REQUEST, [
                'message' => 'Status not found'
            ]);
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

        $this->bookmarkModel->updateIsDeletedStatus($bookmarkId, BookmarkModel::DELETED);
        $this->tagModel->updateIsDeletedStatusBySourceId(Sources::BOOKMARK->value, $bookmarkId,
            BookmarkModel::DELETED);

        $_SESSION['badgeCounts']['bookmarkCount'] -= 1;

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function updateTitle(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $rabbitmq = new AmqpJobPublisher();
        $bookmarkUid = $args['uid'];
        $bookmarkId = $this->bookmarkModel->getBookmarkIdByUid($bookmarkUid);

        $rabbitmq->publishJob(JobTypes::GET_CHILD_BOOKMARK_TITLE, [
            'id' => $bookmarkId,
            'retry_count' => 0,
            'user_id' => $_SESSION['userInfos']['user_id']
        ]);

        $resource = [
            "message" => "Title update request added to queue!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

}