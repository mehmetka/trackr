<?php

namespace App\controller;

use App\model\BookModel;
use App\model\VideoModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class VideoController extends Controller
{
    private $videoModel;
    private $bookModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->videoModel = new VideoModel($container);
        $this->bookModel = new BookModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $sources = $this->videoModel->getVideos();
        $categories = $this->bookModel->getCategories();

        $data = [
            'sources' => $sources,
            'categories' => $categories,
            'activeVideos' => 'active'
        ];

        return $this->view->render($response, 'videos.mustache', $data);
    }

    public function changeStatus(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $bookmarkId = $args['id'];

        if ($params['status'] == 1) {
            $this->videoModel->updateStartedDate($bookmarkId);
        } elseif ($params['status'] == 2) {
            $this->videoModel->updateDoneDate($bookmarkId);
            unset($_SESSION['badgeCounts']);
        }

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();

        $this->videoModel->create($params);

        unset($_SESSION['badgeCounts']);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

}