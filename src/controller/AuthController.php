<?php

namespace App\controller;

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;

class AuthController extends Controller
{
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $data['title'] = "trackr";
        return $this->view->render($response, 'login.mustache', $data);
    }

}