<?php

namespace App\controller;

use App\model\DateTrackingModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;

class DateTrackingController extends Controller
{
    private $dateTrackingModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->dateTrackingModel = new DateTrackingModel($container);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();

        $this->dateTrackingModel->create($params['name'], $params['start']);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

}