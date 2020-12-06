<?php

namespace App\controller;

use App\exception\CustomException;
use App\model\BookmarkModel;
use App\model\BookModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;

class BookmarkController extends Controller
{
    private $bookmarkModel;
    private $bookModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->bookmarkModel = new BookmarkModel($container);
        $this->bookModel = new BookModel($container);
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

    public function create(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        $bookmarkExist = $this->bookmarkModel->getBookmarkByBookmark($params['bookmark']);

        if ($bookmarkExist) {
            throw CustomException::clientError(400, 'Bookmark exist!');
        }

        $title = $this->bookmarkModel->create($params['bookmark'], $params['note'], $params['type']);

        $resource = [
            "message" => "Success! Title: " . htmlentities($title)
        ];

        return $this->response(200, $resource);
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

        return $this->response(200, $resource);
    }
}