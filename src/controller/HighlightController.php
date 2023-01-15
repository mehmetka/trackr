<?php

namespace App\controller;

use App\model\TagModel;
use App\util\EncryptionUtil;
use Slim\Http\StatusCode;
use App\model\HighlightModel;
use App\exception\CustomException;
use Psr\Container\ContainerInterface;
use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\ServerRequestInterface;

class HighlightController extends Controller
{
    public const SOURCE_TYPE = 1;
    private $highlightModel;
    private $tagModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->highlightModel = new HighlightModel($container);
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
            'activeHighlights' => 'active',
            'base_url' => $_ENV['TRACKR_BASE_URL']
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

        $this->tagModel->deleteTagsBySourceId($highlightID, self::SOURCE_TYPE);
        $this->tagModel->updateSourceTags($params['tags'], $highlightID, self::SOURCE_TYPE);
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

        $highlightExist = $this->highlightModel->searchHighlightFulltext(trim($params['highlight']));

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
        $this->tagModel->updateIsDeletedStatusBySourceId(HighlightController::SOURCE_TYPE, $highlightID,
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
