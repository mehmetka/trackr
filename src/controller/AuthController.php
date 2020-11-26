<?php

namespace App\controller;

use App\model\AuthModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;

class AuthController extends Controller
{
    private $authModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->authModel = new AuthModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $data['title'] = "trackr";
        return $this->view->render($response, 'login.mustache', $data);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        $this->authModel->login($params['username'], $params['password']);

        $resource = [
            "message" => "Success!"
        ];

        return $response->withRedirect($this->container->router->pathFor('home'), 302);
    }

}