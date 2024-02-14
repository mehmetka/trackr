<?php

namespace App\controller;

use App\enum\Sources;
use App\model\BookmarkModel;
use App\model\BookModel;
use App\model\FavoriteModel;
use App\model\HighlightModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class FavoriteController extends Controller
{
    private $favoriteModel;
    private $bookModel;
    private $highlightModel;
    private $bookmarkModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->favoriteModel = new FavoriteModel($container);
        $this->bookModel = new BookModel($container);
        $this->highlightModel = new HighlightModel($container);
        $this->bookmarkModel = new BookmarkModel($container);
    }

    public function add(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        if (isset($params['type']) && $params['type'] && isset($params['id']) && $params['id']) {

            if ($params['type'] === 'highlight') {
                $type = Sources::HIGHLIGHT->value;
            } elseif ($params['type'] === 'bookmark') {
                $type = Sources::BOOKMARK->value;
            } elseif ($params['type'] === 'book') {
                $type = Sources::BOOK->value;
            } else {
                $resource['message'] = 'Unknown type';
                $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;
                return $this->response($resource['responseCode'], $resource);
            }

            $this->favoriteModel->add($type, $params['id']);
            $resource['message'] = "Success";
            $resource['responseCode'] = StatusCode::HTTP_OK;
        } else {
            $resource['message'] = "Required fields cannot be null";
            $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;
        }

        return $this->response($resource['responseCode'], $resource);
    }

    public function get(ServerRequestInterface $request, ResponseInterface $response)
    {
        $queryString = $request->getQueryParams();
        if (isset($queryString['type']) && $queryString['type'] === 'highlight') {
            $highlightFavorites = $this->favoriteModel->getHighlightFavorites();
            $resource['data']['highlightFavorites'] = $highlightFavorites;
        } else {
            $resource['message'] = "Unknown type";
            $resource['responseCode'] = StatusCode::HTTP_BAD_REQUEST;

            return $this->response($resource['responseCode'], $resource);
        }

        $resource['message'] = "Success";
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }
}
