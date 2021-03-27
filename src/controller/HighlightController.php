<?php

namespace App\controller;

use App\model\TagModel;
use Slim\Http\StatusCode;
use App\model\BookmarkModel;
use App\model\HighlightModel;
use Psr\Container\ContainerInterface;
use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\ServerRequestInterface;

class HighlightController extends Controller
{
    private $highlightModel;
    private $bookmarkModel;
    private $tagModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->highlightModel = new HighlightModel($container);
        $this->bookmarkModel = new BookmarkModel($container);
        $this->tagModel = new TagModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $queryString = $request->getQueryParams();

        if (isset($queryString['tag'])) {
            $highlights = $this->highlightModel->getHighlightsByTag($queryString['tag']);
        } else {
            $highlights = $this->highlightModel->getHighlights(300);
        }

        $tags = $this->tagModel->getHighlightTagsAsHTML($queryString['tag']);

        $data = [
            'tag' => htmlentities($queryString['tag']),
            'headerTags' => $tags,
            'highlights' => $highlights,
            'activeHighlights' => 'active'
        ];

        return $this->view->render($response, 'highlights.mustache', $data);
    }

    public function details(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];

        $detail = $this->highlightModel->getHighlightsByID($highlightID);
        $subHighlights = $this->highlightModel->getSubHighlightsByHighlightID($highlightID);

        $data = [
            'detail' => $detail,
            'subHighlights' => $subHighlights,
            'activeHighlights' => 'active'
        ];

        return $this->view->render($response, 'highlight-details.mustache', $data);
    }

    public function all(ServerRequestInterface $request, ResponseInterface $response)
    {
        $highlights = $this->highlightModel->getHighlights(100);

        $data = [
            'highlights' => $highlights
        ];

        return $this->view->render($response, 'highlights-all.mustache', $data);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];
        $params = $request->getParsedBody();

        if ($params['link']) {
            if ($params['link'] !== $_SESSION['update']['highlight']['link']) {
                $bookmarkExist = $this->bookmarkModel->getBookmarkByBookmark($params['link']);
                if ($bookmarkExist) {
                    $params['link'] = $bookmarkExist['id'];
                } else {
                    $bookmarkId = $this->bookmarkModel->create($params['link'], null, 6665);
                    $params['link'] = $bookmarkId;
                }
            } else {
                $params['link'] = $_SESSION['update']['highlight']['linkID'];
            }
        } else {
            $params['link'] = null;
        }

        $this->tagModel->deleteTagsByHighlightID($highlightID);

        if (strpos($params['tags'], ',') !== false) {
            $tags = explode(',', $params['tags']);

            foreach ($tags as $tag) {
                $this->tagModel->insertTagByChecking($highlightID, $tag);
            }

        } else {
            $this->tagModel->insertTagByChecking($highlightID, $params['tags']);
        }

        $this->highlightModel->update($highlightID, $params);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        // TODO highlight cannot be null!

        if ($params['link']) {
            $bookmarkExist = $this->bookmarkModel->getBookmarkByBookmark($params['link']);
            if ($bookmarkExist) {
                $params['link'] = $bookmarkExist['id'];
            } else {
                $bookmarkId = $this->bookmarkModel->create($params['link'], null, 6665);
                $params['link'] = $bookmarkId;
            }
        } else {
            $params['link'] = null;
        }

        $highlightId = $this->highlightModel->create($params);

        if (strpos($params['tags'], ',') !== false) {
            $tags = explode(',', $params['tags']);

            foreach ($tags as $tag) {
                $this->tagModel->insertTagByChecking($highlightId, trim($tag));
            }

        } else {
            $this->tagModel->insertTagByChecking($highlightId, trim($params['tags']));
        }

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function createSub(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $highlightID = $args['id'];

        if ($params['link']) {
            $bookmarkExist = $this->bookmarkModel->getBookmarkByBookmark($params['link']);
            if ($bookmarkExist) {
                $params['link'] = $bookmarkExist['id'];
            } else {
                $bookmarkId = $this->bookmarkModel->create($params['link'], null, 6665);
                $params['link'] = $bookmarkId;
            }
        }

        $subHighlightID = $this->highlightModel->create($params);

        if (strpos($params['tags'], ',') !== false) {
            $tags = explode(',', $params['tags']);

            foreach ($tags as $tag) {
                $this->tagModel->insertTagByChecking($subHighlightID, trim($tag));
            }

        } else {
            $this->tagModel->insertTagByChecking($subHighlightID, trim($params['tags']));
        }

        $this->highlightModel->createSubHighlight($highlightID, $subHighlightID);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }
}