<?php

namespace App\controller;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;

class HomeController extends Controller
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        return $response->withRedirect($this->container->router->pathFor('paths'), 302);
    }

}