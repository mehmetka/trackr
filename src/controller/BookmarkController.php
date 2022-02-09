<?php

namespace App\controller;

use App\exception\CustomException;
use App\model\BookmarkModel;
use App\model\CategoryModel;
use App\model\TagModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class BookmarkController extends Controller
{
    private $bookmarkModel;
    private $categoryModel;
    private $tagModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->bookmarkModel = new BookmarkModel($container);
        $this->categoryModel = new CategoryModel($container);
        $this->tagModel = new TagModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $highlights = $this->bookmarkModel->getBookmarks();
        $categories = $this->categoryModel->getCategories();

        $data = [
            'title' => 'Bookmarks | trackr',
            'categories' => $categories,
            'bookmarks' => $highlights,
            'activeBookmarks' => 'active'
        ];

        return $this->view->render($response, 'bookmarks.mustache', $data);
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

        return $this->view->render($response, 'bookmark-highlights.mustache', $data);
    }

    public function details(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkUid = $args['uid'];
        $bookmarkId = $this->bookmarkModel->getBookmarkIdByUid($bookmarkUid);
        $details = $this->bookmarkModel->getBookmarkById($bookmarkId);
        $categories = $this->categoryModel->getCategories($details['categoryId']);

        $data = [
            'title' => 'Bookmark\'s Details | trackr',
            'details' => $details,
            'categories' => $categories,
            'activeBookmarks' => 'active',
        ];

        return $this->view->render($response, 'bookmark-details.mustache', $data);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        $bookmarkID = $this->bookmarkModel->createOperations($params['bookmark'], $params['note'], $params['category']);
        
        $_SESSION['badgeCounts']['bookmarkCount'] += 1;

        $resource = [
            "message" => "Successfully added bookmark",
            "id" => $bookmarkID
        ];

        return $this->response(StatusCode::HTTP_CREATED, $resource);
    }

    public function addHighlight(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkUid = $args['uid'];
        $bookmarkId = $this->bookmarkModel->getBookmarkIdByUid($bookmarkUid);
        $params = $request->getParsedBody();

        if(!$params['highlight']){
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

        $this->tagModel->updateHighlightTags($params['tags'], $highlightId);
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

    public function update(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkUid = $args['uid'];
        $bookmarkId = $this->bookmarkModel->getBookmarkIdByUid($bookmarkUid);
        $params = $request->getParsedBody();

        $this->bookmarkModel->updateBookmark($bookmarkId, $params);

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

        $_SESSION['badgeCounts']['bookmarkCount'] -= 1;

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function updateBookmarkTitleAsync(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkId = $args['id'];
        
        $bookmarkDetails = $this->bookmarkModel->getBookmarkById($bookmarkId);
        $title = $this->bookmarkModel->getTitle($bookmarkDetails['bookmark']);

        if($title){
            $this->bookmarkModel->updateTitleByID($bookmarkId, $title);
        }

        return $this->response(StatusCode::HTTP_NO_CONTENT, []);
    }
}