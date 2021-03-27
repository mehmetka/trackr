<?php

namespace App\controller;

use App\exception\CustomException;
use App\model\BookmarkModel;
use App\model\BookModel;
use App\model\TagModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class BookmarkController extends Controller
{
    private $bookmarkModel;
    private $bookModel;
    private $tagModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->bookmarkModel = new BookmarkModel($container);
        $this->bookModel = new BookModel($container);
        $this->tagModel = new TagModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $highlights = $this->bookmarkModel->getBookmarks();
        $subject = $this->bookModel->getCategories();

        $data = [
            'categories' => $subject,
            'bookmarks' => $highlights,
            'activeBookmarks' => 'active'
        ];

        return $this->view->render($response, 'bookmarks.mustache', $data);
    }

    public function highlights(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkId = $args['id'];
        $highlights = $this->bookmarkModel->getHighlights($bookmarkId);
        $_SESSION['bookmarks']['highlights']['bookmarkID'] = $bookmarkId;

        $data = [
            'highlights' => $highlights,
            'activeBookmarks' => 'active',
            'bookmarkID' => $bookmarkId
        ];

        return $this->view->render($response, 'bookmark-highlights.mustache', $data);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        $bookmarkExist = $this->bookmarkModel->getBookmarkByBookmark($params['bookmark']);

        if ($bookmarkExist) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'Bookmark exist!');
        }

        $this->bookmarkModel->create($params['bookmark'], $params['note'], $params['category']);

        $resource = [
            "message" => "Successfully added"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function addHighlight(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkID = $args['id'];
        $params = $request->getParsedBody();

        if (!isset($_SESSION['bookmarks']['highlights']['bookmarkID']) || $bookmarkID != $_SESSION['bookmarks']['highlights']['bookmarkID']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Inconsistency! You're trying to add highlight for different bookmark!");
        }

        $bookmarkDetail = $this->bookmarkModel->getBookmarkById($_SESSION['bookmarks']['highlights']['bookmarkID']);
        $bookmarkDetail['highlight'] = $params['highlight'];
        $bookmarkDetail['author'] = null;
        $highlightId = $this->bookmarkModel->addHighlight($bookmarkDetail);

        if (strpos($params['tags'], ',') !== false) {
            $tags = explode(',', $params['tags']);

            foreach ($tags as $tag) {
                $this->tagModel->insertTagByChecking($highlightId, $tag);
            }

        } else {
            $this->tagModel->insertTagByChecking($highlightId, $params['tags']);
        }

        $resource = [
            "message" => "Successfully added"
        ];

        unset($_SESSION['bookmarks']['highlights']['bookmarkID']);

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function changeStatus(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $bookmarkId = $args['id'];

        if ($params['status'] == 1) {
            $this->bookmarkModel->updateStartedDate($bookmarkId);
        } elseif ($params['status'] == 2) {
            $this->bookmarkModel->updateDoneDate($bookmarkId);
        }

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $bookmarkId = $args['id'];

        $this->bookmarkModel->deleteBookmark($bookmarkId);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }
}