<?php

namespace App\controller;

use App\enum\BookmarkStatus;
use App\enum\JobTypes;
use App\enum\Sources;
use App\exception\CustomException;
use App\model\BookmarkModel;
use App\model\HighlightModel;
use App\model\TagModel;
use App\util\ArrayUtil;
use App\util\lang;
use App\rabbitmq\AmqpJobPublisher;
use App\util\TwitterUtil;
use App\util\Typesense;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class BookmarkController extends Controller
{
    private $bookmarkModel;
    private $tagModel;
    private $highlightModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->bookmarkModel = new BookmarkModel($container);
        $this->tagModel = new TagModel($container);
        $this->highlightModel = new HighlightModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $queryString = $request->getQueryParams();
        $data['activeBookmarks'] = 'active';
        $data['defaultTag'] = 'technical';

        if (isset($queryString['tag']) && $queryString['tag']) {
            $data['pageTitle'] = "Bookmarks #{$queryString['tag']} | trackr";
            $data['bookmarks'] = $this->bookmarkModel->getBookmarks($queryString['tag']);
        } else {
            $data['pageTitle'] = 'Bookmarks | trackr';
            $data['bookmarks'] = $this->bookmarkModel->getBookmarks();
        }

        $data['bookmarkCategories'] = $this->tagModel->getSourceTagsByType(Sources::BOOKMARK->value,
            $queryString['tag']);

        return $this->view->render($response, 'bookmarks/index.mustache', $data);
    }

    public function highlights(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkUid = $args['uid'];
        $bookmarkId = $this->bookmarkModel->getBookmarkIdByUid($bookmarkUid);
//        $details = $this->bookmarkModel->getChildBookmarkById($bookmarkId, $_SESSION['userInfos']['user_id']);
        $highlights = $this->highlightModel->getHighlightsByGivenField('link',$bookmarkId);
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
        $status = (int)$params['status'];

        $this->bookmarkModel->updateChildBookmark($bookmarkId, $params, $_SESSION['userInfos']['user_id']);

        $this->tagModel->deleteTagsBySourceId($bookmarkId, Sources::BOOKMARK->value);

        if ($params['title'] !== $bookmarkDetails['title']) {
            $this->bookmarkModel->updateIsTitleEditedStatus($bookmarkId, BookmarkModel::TITLE_EDITED);
            $this->highlightModel->updateHighlightAuthorByBookmarkId($bookmarkId, $params['title'], $_SESSION['userInfos']['user_id']);
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
        $params = ArrayUtil::trimArrayElements($request->getParsedBody());
        $now = time();
        $bookmarkDetail['highlight'] = $params['highlight'];
        $bookmarkDetail['bookmark_id'] = $bookmarkDetail['id'];
        $bookmarkDetail['blogPath'] = 'general/uncategorized';
        $bookmarkDetail['created'] = $now;
        $bookmarkDetail['updated'] = $now;

        if (!isset($params['highlight']) || !$params['highlight']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::HIGHLIGHT_CANNOT_BE_NULL);
        }

        if (str_word_count($params['highlight']) < 2) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::HIGHLIGHT_MUST_BE_LONGER);
        }

        $highlightExist = $this->highlightModel->searchHighlight($params['highlight']);

        if ($highlightExist) {
            foreach ($highlightExist as $highlight) {
                $this->highlightModel->updateUpdatedFieldByHighlightId($highlight['id']);
            }
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::HIGHLIGHT_ADDED_BEFORE);
        }

        if (!isset($_SESSION['bookmarks']['highlights']['bookmarkID']) || $bookmarkDetail['id'] != $_SESSION['bookmarks']['highlights']['bookmarkID']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST,
                lang\En::BOOKMARK_INCONSISTENCY_FOR_ADDING_HIGHLIGHT);
        }

        if (TwitterUtil::isTwitterUrl($bookmarkDetail['bookmark'])) {
            $username = TwitterUtil::getUsernameFromUrl($bookmarkDetail['bookmark']);
            $bookmarkDetail['author'] = $username;
            $bookmarkDetail['source'] = 'Twitter';
        } else {
            $bookmarkDetail['author'] = $bookmarkDetail['title'] ?: null;
            $bookmarkDetail['source'] = 'Bookmark Highlight';
        }

        unset($bookmarkDetail['title']);

        $highlightId = $this->highlightModel->create($bookmarkDetail);

        $typesenseClient = new Typesense('highlights');
        $document = [
            'id' => (string)$highlightId,
            'highlight' => $bookmarkDetail['highlight'],
            'is_deleted' => 0,
            'author' => $bookmarkDetail['author'] ?: $_SESSION['userInfos']['username'],
            'source' => $bookmarkDetail['source'] ?: '',
            'created' => (int)$now,
            'updated' => (int)$now,
            'is_encrypted' => 0,
            'is_secret' => 0,
            'blog_path' => '',
            'user_id' => (int)$_SESSION['userInfos']['user_id'],
        ];
        $typesenseClient->indexDocument($document);

        $this->tagModel->updateSourceTags($params['tags'], $highlightId, Sources::HIGHLIGHT->value);

        if ($bookmarkDetail['status'] != 2) {
            $this->bookmarkModel->updateStartedDate($bookmarkDetail['id'], time());
            $this->bookmarkModel->updateBookmarkStatus($bookmarkDetail['id'], BookmarkStatus::STARTED->value);
        }

        $_SESSION['badgeCounts']['highlightsCount'] += 1;

        $resource = [
            "message" => lang\En::HIGHLIGHT_SUCCESSFULLY_ADDED
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