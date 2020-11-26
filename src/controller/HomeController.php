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
        $data = [
            'activeHome' => 'active'
        ];

        return $this->view->render($response, 'home.mustache', $data);
    }

}