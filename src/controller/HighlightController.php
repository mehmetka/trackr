<?php

namespace App\controller;

use App\model\TagModel;
use Slim\Http\StatusCode;
use App\model\BookmarkModel;
use App\model\HighlightModel;
use App\exception\CustomException;
use Psr\Container\ContainerInterface;
use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\ServerRequestInterface;

class HighlightController extends Controller
{
    public const SOURCE_TYPE = 1;
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

        $highlights = $this->highlightModel->getHighlights($queryString['tag'], 100);

        $tags = $this->tagModel->getSourceTagsByType(self::SOURCE_TYPE, $queryString['tag']);

        $data = [
            'title' => 'Highlights | trackr',
            'tag' => htmlentities($queryString['tag']),
            'headerTags' => $tags,
            'highlights' => $highlights,
            'activeHighlights' => 'active'
        ];

        return $this->view->render($response, 'highlights/index.mustache', $data);
    }

    public function details(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];

        $detail = $this->highlightModel->getHighlightByID($highlightID);
        $subHighlights = $this->highlightModel->getSubHighlightsByHighlightID($highlightID);
        $nextID = $this->highlightModel->getNextHighlight($highlightID);
        $previousID = $this->highlightModel->getPreviousHighlight($highlightID);

        $data = [
            'title' => 'Highlight Details | trackr',
            'detail' => $detail,
            'subHighlights' => $subHighlights,
            'activeHighlights' => 'active',
            'nextID' => $nextID,
            'previousID' => $previousID,
        ];

        return $this->view->render($response, 'highlights/details.mustache', $data);
    }

    public function all(ServerRequestInterface $request, ResponseInterface $response)
    {
        $highlights = $this->highlightModel->getHighlights(null, 100);

        $data = [
            'highlights' => $highlights
        ];

        return $this->view->render($response, 'highlights/all.mustache', $data);
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
                    $bookmarkId = $this->bookmarkModel->createOperations($params['link'], null);
                    $params['link'] = $bookmarkId;
                }
            } else {
                $params['link'] = $_SESSION['update']['highlight']['linkID'];
            }
        } else {
            $params['link'] = null;
        }

        $this->tagModel->deleteTagsBySourceId($highlightID, self::SOURCE_TYPE);
        $this->tagModel->updateSourceTags($params['tags'], $highlightID, self::SOURCE_TYPE);
        $this->highlightModel->update($highlightID, $params);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        if (!$params['highlight']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Highlight cannot be null!");
        }

        $highlightExist = $this->highlightModel->searchHighlight(trim($params['highlight']));

        if ($highlightExist) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Highlight added before.!");
        }

        if ($params['link']) {
            $bookmarkExist = $this->bookmarkModel->getBookmarkByBookmark($params['link']);
            if ($bookmarkExist) {
                $params['link'] = $bookmarkExist['id'];
            } else {
                $bookmarkId = $this->bookmarkModel->createOperations($params['link'], null);
                $params['link'] = $bookmarkId;
            }
        } else {
            $params['link'] = null;
        }

        $highlightId = $this->highlightModel->create($params);

        if (!$params['tags']) {
            $params['tags'] = 'general';
        }
        $this->tagModel->updateSourceTags($params['tags'], $highlightId, self::SOURCE_TYPE);

        $_SESSION['badgeCounts']['highlightsCount'] += 1;

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function createSub(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $resource = [];
        $params = $request->getParsedBody();
        $highlightID = $args['id'];

        $parentHighlightDetails = $this->highlightModel->getHighlightByID($highlightID);

        if($parentHighlightDetails) {
            if ($params['link']) {
                $bookmarkExist = $this->bookmarkModel->getBookmarkByBookmark($params['link']);
                if ($bookmarkExist) {
                    $params['link'] = $bookmarkExist['id'];
                } else {
                    $bookmarkId = $this->bookmarkModel->createOperations($params['link'], null);
                    $params['link'] = $bookmarkId;
                }
            }

            $subHighlightID = $this->highlightModel->create($params);

            if ($params['tags']) {
                $this->tagModel->updateSourceTags($params['tags'], $subHighlightID, self::SOURCE_TYPE);
            }

            $this->highlightModel->createSubHighlight($highlightID, $subHighlightID);
            $_SESSION['badgeCounts']['highlightsCount'] += 1;

            $resource['message'] = 'sub-highlight successfully added!';

            return $this->response(StatusCode::HTTP_OK, $resource);
        }

        $resource['message'] = 'parent highlight not found!';

        return $this->response(StatusCode::HTTP_BAD_REQUEST, $resource);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];

        $this->highlightModel->deleteHighlight($highlightID);
        //$this->highlightModel->deleteHighlightTagsByHighlightID($highlightID);
        //$this->highlightModel->deleteSubHighlightByHighlightID($highlightID);

        $_SESSION['badgeCounts']['highlightsCount'] -= 1;

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function search(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        $results = $this->highlightModel->searchHighlight($params['searchParam']);

        $resource = [
            "highlights" => $results
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }
}
