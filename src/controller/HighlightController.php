<?php

namespace App\controller;

use App\enum\Sources;
use App\model\BookModel;
use App\model\TagModel;
use Jfcherng\Diff\DiffHelper;
use Slim\Http\StatusCode;
use App\util\EncryptionUtil;
use App\util\VersionDiffUtil;
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
        $tagQueryString = htmlspecialchars($queryString['tag'], ENT_QUOTES | ENT_HTML401, "UTF-8");

        $data = [
            'pageTitle' => 'Highlights | trackr',
            'tag' => $tagQueryString,
            'activeHighlights' => 'active'
        ];

        if (isset($queryString['tag'])) {
            $highlights = $this->highlightModel->getHighlightsByTag($queryString['tag'], $_ENV['HIGHLIGHT_LIMIT']);
            $data['pageTitle'] = "Highlights #$tagQueryString | trackr";
        } elseif (isset($queryString['author'])) {
            $highlights = $this->highlightModel->getHighlightsByGivenField('author', $queryString['author'],
                $_ENV['HIGHLIGHT_LIMIT']);
            $data['pageTitle'] = "$tagQueryString's Highlights | trackr";
        } elseif (isset($queryString['source'])) {
            $highlights = $this->highlightModel->getHighlightsByGivenField('source', $queryString['source'],
                $_ENV['HIGHLIGHT_LIMIT']);
            $data['pageTitle'] = "$tagQueryString's Highlights | trackr";
        } elseif (isset($queryString['id'])) {
            $highlights = $this->highlightModel->getHighlightsByGivenField('id', $queryString['id'],
                $_ENV['HIGHLIGHT_LIMIT']);
        } elseif (isset($queryString['bookUID'])) {
            $book = $this->bookModel->getBookByGivenColumn('uid', $queryString['bookUID']);
            $bookId = $book['id'];
            $bookName = $book['author'] . ' - ' . $book['title'];
            $highlights = $this->highlightModel->getHighlightsByGivenField('book_id', $bookId);
            $data['pageTitle'] =  "$bookName's Highlights | trackr";
        } elseif (isset($queryString['is_secret'])) {
            $isSecret = $queryString['is_secret'] === 'true' ? 1 : 0;
            $highlights = $this->highlightModel->getHighlightsByGivenField('is_secret', $isSecret);
        } else {
            $highlights = $this->highlightModel->getHighlights($_ENV['HIGHLIGHT_LIMIT']);
        }

        $tags = $this->tagModel->getSourceTagsByType(Sources::HIGHLIGHT->value, $queryString['tag']);
        $books = $_SESSION['books']['list'] ?? $this->bookModel->getAuthorBookList();

        $data['headerTags'] = $tags;
        $data['highlights'] = $highlights;
        $data['books'] = $books;

        return $this->view->render($response, 'highlights/index.mustache', $data);
    }

    public function details(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];

        $detail = $this->highlightModel->getHighlightByID($highlightID);
        $subHighlights = $this->highlightModel->getSubHighlightsByHighlightID($highlightID);
        $nextID = $this->highlightModel->getNextHighlight($highlightID);
        $previousID = $this->highlightModel->getPreviousHighlight($highlightID);
        $this->highlightModel->updateUpdatedFieldByHighlightId($highlightID);

        $data = [
            'pageTitle' => 'Highlight Details | trackr',
            'detail' => $detail,
            'subHighlights' => $subHighlights,
            'activeHighlights' => 'active',
            'nextID' => $nextID,
            'previousID' => $previousID
        ];

        return $this->view->render($response, 'highlights/details.mustache', $data);
    }

    public function versions(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];
        $versionDiffs = [];

        $versions = $this->highlightModel->getVersionsById($highlightID);
        $currentHighlight = $this->highlightModel->getHighlightByID($highlightID);

        $newString = $currentHighlight['highlight'];

        $latestDiff = DiffHelper::calculate(
            $newString,
            $newString,
            'Inline',
            VersionDiffUtil::highlightsDiffOptions(),
            VersionDiffUtil::highlightsRendererOptions(),
        );

        $versionDiffs[] = ['diff' => $latestDiff, 'created_at' => 'Latest'];

        foreach ($versions as $version) {
            $new = $newString;
            $old = $version['old_highlight'];
            $sideBySideResult = DiffHelper::calculate(
                $old,
                $new,
                'Inline',
                VersionDiffUtil::highlightsDiffOptions(),
                VersionDiffUtil::highlightsRendererOptions(),
            );

            $versionDiffs[] = ['diff' => $sideBySideResult, 'created_at' => $version['created_at']];
            $newString = $version['old_highlight'];
        }

        $resource['data']['versionDiffs'] = $versionDiffs;
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
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

        if (isset($_SESSION['highlights']['not_editable'][$highlightID])) {
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
            foreach ($highlightExist as $highlight) {
                $this->highlightModel->updateUpdatedFieldByHighlightId($highlight['id']);
            }
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Highlight added before!");
        }

        if (isset($params['is_encrypted']) && $params['is_encrypted'] === 'Yes') {
            $params['is_encrypted'] = 1;
            $params['highlight'] = EncryptionUtil::encrypt(trim($params['highlight']));
        } else {
            $params['is_encrypted'] = 0;
            $params['highlight'] = trim($params['highlight']);
        }

        if (isset($params['is_secret']) && $params['is_secret'] === 'Public') {
            $params['is_secret'] = 0;
        } else {
            $params['is_secret'] = 1;
        }

        $params['book_id'] = $params['book'] ? $this->bookModel->getBookIdByUid($params['book']) : null;

        $highlightId = $this->highlightModel->create($params);

        $this->tagModel->updateSourceTags($params['tags'], $highlightId, Sources::HIGHLIGHT->value);

        $_SESSION['badgeCounts']['highlightsCount'] += 1;

        $resource = [
            "message" => "Success!"
        ];

        unset($_SESSION['highlights']['minMaxID']);
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

            unset($_SESSION['highlights']['minMaxID']);
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

    public function get(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $highlightID = $args['id'];
        $resource = $this->highlightModel->getHighlightByID($highlightID);

        if (!$resource) {
            $resource['highlight'] = 'Highlight not found!';
        }

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

}
