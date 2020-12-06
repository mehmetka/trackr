<?php

namespace App\controller;

use App\model\HighlightModel;
use App\model\TagModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;

class HighlightController extends Controller
{
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

        if (isset($queryString['tag'])) {
            $highlights = $this->highlightModel->getHighlightsByTag($queryString['tag']);
        } else {
            $highlights = $this->highlightModel->getHighlights();
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

    public function create(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        $highlightId = $this->highlightModel->create($params);

        if (strpos($params['tags'], ',') !== false) {
            $tags = explode(',', $params['tags']);

            foreach ($tags as $tag) {
                $this->tagModel->insertTagByChecking($highlightId, $tag);
            }

        } else {
            $this->tagModel->insertTagByChecking($highlightId, $params['tags']);
        }

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }
}