<?php

namespace App\controller;

use Psr\Container\ContainerInterface;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Controller
{
    protected $container;
    protected $view;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->view = $container->get('view');
    }

    public function __get($property)
    {
        if ($this->container->{$property}) {
            return $this->container->{$property};
        }
    }

    public function response($status = StatusCode::HTTP_OK, $data = [], $allow = [])
    {
        if (!isset($this->response)) {
            $this->response = new  Response($status);
        }

        /** @var \Psr\Http\Message\ResponseInterface $response */
        $response = $this->response->withStatus($status);

        if (!empty($allow)) {
            $response = $response->withHeader('Allow', strtoupper(implode(',', $allow)));
        }

        if (!empty($data)) {
            $response = $response->withJson($data);
        }

        return $response;
    }

}