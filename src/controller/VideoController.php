<?php

namespace App\controller;

use App\model\CategoryModel;
use App\model\VideoModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class VideoController extends Controller
{
    private $videoModel;
    private $categoryModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->videoModel = new VideoModel($container);
        $this->categoryModel = new CategoryModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $sources = $this->videoModel->getVideos();
        $categories = $this->categoryModel->getCategories();

        $data = [
            'title' => 'Videos | trackr',
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
            $_SESSION['badgeCounts']['todosCount'] -= 1;
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

        $_SESSION['badgeCounts']['todosCount'] += 1;

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

}