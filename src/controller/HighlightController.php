<?php

namespace App\controller;

use App\entity\Book;
use App\enum\Sources;
use App\model\BookModel;
use App\model\TagModel;
use App\util\EncryptionUtil;
use App\util\HighlightUtil;
use App\util\TagUtil;
use Slim\Http\StatusCode;
use App\model\HighlightModel;
use App\exception\CustomException;
use Psr\Container\ContainerInterface;
use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\ServerRequestInterface;

class HighlightController extends Controller
{
    private $highlightModel;
    private $tagModel;
    private $bookModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->highlightModel = new HighlightModel($container);
        $this->tagModel = new TagModel($container);
        $this->bookModel = new BookModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $queryString = $request->getQueryParams();

        if (isset($queryString['tag'])) {
            $highlights = $this->highlightModel->getHighlightsByTag($queryString['tag'], $_ENV['HIGHLIGHT_LIMIT']);
        } elseif (isset($queryString['author'])) {
            $highlights = $this->highlightModel->getHighlightsByGivenField(Book::COLUMN_AUTHOR, $queryString['author'],
                $_ENV['HIGHLIGHT_LIMIT']);
        } elseif (isset($queryString['source'])) {
            $highlights = $this->highlightModel->getHighlightsByGivenField(Book::COLUMN_SOURCE, $queryString['source'],
                $_ENV['HIGHLIGHT_LIMIT']);
        } elseif (isset($queryString['bookUID'])) {
            $bookId = $this->bookModel->getBookIdByUid($queryString['bookUID']);
            $highlights = $this->highlightModel->getHighlightsByGivenField(Book::COLUMN_BOOK_ID, $bookId);
        } else {
            $highlights = $this->highlightModel->getHighlights($_ENV['HIGHLIGHT_LIMIT']);
        }

        $tags = $this->tagModel->getSourceTagsByType(Sources::HIGHLIGHT->value, $queryString['tag']);

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
            'previousID' => $previousID
        ];

        return $this->view->render($response, 'highlights/details.mustache', $data);
    }

    public function all(ServerRequestInterface $request, ResponseInterface $response)
    {
        $highlights = $this->highlightModel->getHighlights(100);

        $data = [
            'highlights' => $highlights
        ];

        return $this->view->render($response, 'highlights/all.mustache', $data);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];
        $params = $request->getParsedBody();
        $highlightDetails = $this->highlightModel->getHighlightByID($highlightID);

        if (isset($_SESSION['userInfos']['not_editable_highlights'][$highlightID])) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "highlight not editable");
        }

        if (!$highlightDetails) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "highlight not found");
        }

        if (!$params['highlight'] || !trim($params['highlight'])) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "highlight cannot be null!");
        }

        if (isset($params['is_encrypted']) && $params['is_encrypted'] === 'Yes') {
            $params['is_encrypted'] = 1;
            $params['highlight'] = EncryptionUtil::encrypt(trim($params['highlight']));
        } else {
            $params['is_encrypted'] = 0;
            $params['highlight'] = trim($params['highlight']);
        }

        $this->tagModel->deleteTagsBySourceId($highlightID, Sources::HIGHLIGHT->value);
        $this->tagModel->updateSourceTags($params['tags'], $highlightID, Sources::HIGHLIGHT->value);
        $this->highlightModel->update($highlightID, $params);

        if ($highlightDetails['highlight'] !== $params['highlight']) {
            $this->highlightModel->addChangeLog($highlightID, $highlightDetails['highlight']);
        }

        $resource = [
            "message" => "successfully updated"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        if (!$params['highlight'] || !trim($params['highlight'])) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Highlight cannot be null!");
        }

        $highlightExist = $this->highlightModel->searchHighlight(trim($params['highlight']));

        if ($highlightExist) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Highlight added before!");
        }

        if (isset($params['is_encrypted']) && $params['is_encrypted'] === 'Yes') {
            $params['is_encrypted'] = 1;
            $params['highlight'] = EncryptionUtil::encrypt(trim($params['highlight']));
        } else {
            $params['is_encrypted'] = 0;
            $params['highlight'] = trim($params['highlight']);
        }

        if (!$params['tags']) {
            $params['tags'] = 'general';
            $params['blogPath'] = $params['blogPath'] ?? 'general';
        } else {
            $params['blogPath'] = $params['blogPath'] ?? HighlightUtil::prepareBlogPath(TagUtil::prepareTagsAsArray($params['tags']));
        }

        $highlightId = $this->highlightModel->create($params);

        $this->tagModel->updateSourceTags($params['tags'], $highlightId, Sources::HIGHLIGHT->value);

        $_SESSION['badgeCounts']['highlightsCount'] += 1;

        $resource = [
            "message" => "Success!"
        ];

        unset($_SESSION['userInfos']['highlightMinMaxID']);
        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function createSub(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $resource = [];
        $params = $request->getParsedBody();
        $highlightID = $args['id'];

        if (!$params['highlight'] || !trim($params['highlight'])) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Sub Highlight cannot be null!");
        }

        $parentHighlightDetails = $this->highlightModel->getHighlightByID($highlightID);

        if ($parentHighlightDetails) {
            $params['link'] = null;

            if (isset($params['is_encrypted']) && $params['is_encrypted'] === 'Yes') {
                $params['is_encrypted'] = 1;
                $params['highlight'] = EncryptionUtil::encrypt(trim($params['highlight']));
            } else {
                $params['is_encrypted'] = 0;
                $params['highlight'] = trim($params['highlight']);
            }

            $subHighlightID = $this->highlightModel->create($params);

            if ($params['tags']) {
                $this->tagModel->updateSourceTags($params['tags'], $subHighlightID, Sources::HIGHLIGHT->value);
            }

            $this->highlightModel->createSubHighlight($highlightID, $subHighlightID);
            $_SESSION['badgeCounts']['highlightsCount'] += 1;

            $resource['message'] = 'sub-highlight successfully added!';

            unset($_SESSION['userInfos']['highlightMinMaxID']);
            return $this->response(StatusCode::HTTP_OK, $resource);
        }

        $resource['message'] = 'parent highlight not found!';

        return $this->response(StatusCode::HTTP_BAD_REQUEST, $resource);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];

        $this->highlightModel->deleteHighlight($highlightID);
        $this->tagModel->updateIsDeletedStatusBySourceId(Sources::HIGHLIGHT->value, $highlightID,
            HighlightModel::NOT_DELETED);

        $_SESSION['badgeCounts']['highlightsCount'] -= 1;

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function search(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        $results = $this->highlightModel->searchHighlightFulltext($params['searchParam']);

        $resource = [
            "highlights" => $results
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }
}
