<?php

namespace App\controller;

use App\exception\CustomException;
use App\model\DateTrackingModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

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

        if (!$params['name']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Name cannot be null!");
        }

        $this->dateTrackingModel->create($params['name'], $params['date']);

        $resource['message'] = "Success";
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

}