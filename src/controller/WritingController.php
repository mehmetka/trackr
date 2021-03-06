<?php

namespace App\controller;

use App\model\WritingModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class WritingController extends Controller
{
    public $writingModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->writingModel = new WritingModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $sources = $this->writingModel->getWritings();

        $data = [
            'writings' => $sources,
            'activeWritings' => 'active'
        ];

        return $this->view->render($response, 'writings.mustache', $data);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        $resource = [
            "message" => "Can't be null!",
            "status" => StatusCode::HTTP_BAD_REQUEST
        ];

        if ($params['writing'] != null) {
            $this->writingModel->create($params['writing']);

            $resource = [
                "message" => "Success!",
                "status" => StatusCode::HTTP_OK
            ];
        }

        return $this->response($resource['status'], $resource);
    }

}