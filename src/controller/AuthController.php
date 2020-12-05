<?php

namespace App\controller;

use App\model\AuthModel;
use App\exception\CustomException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthController extends Controller
{
    private $authModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->authModel = new AuthModel($container);
    }

    public function loginPage(ServerRequestInterface $request, ResponseInterface $response)
    {
        $data['title'] = "trackr";
        $data['userExist'] = $this->authModel->userCreatedBefore();

        return $this->view->render($response, 'login.mustache', $data);
    }

    public function registerPage(ServerRequestInterface $request, ResponseInterface $response)
    {
        $data['title'] = "trackr";
        return $this->view->render($response, 'register.mustache', $data);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        $this->authModel->login($params['username'], $params['password']);

        return $response->withRedirect($this->container->router->pathFor('home'), 302);
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response)
    {
        $userExist = $this->authModel->userCreatedBefore();

        if ($userExist) {
            throw CustomException::clientError(403, 'User created before!', 'User created before!');
        }

        $params = $request->getParsedBody();

        if (trim($params['password']) != trim($params['passwordAgain'])) {
            throw CustomException::clientError(401, 'Passwords must be matched!', 'Passwords must be matched!');
        }

        $this->authModel->register(trim($params['username']), trim($params['password']));

        return $response->withRedirect($this->container->router->pathFor('login'), 302);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response)
    {
        $_SESSION = array();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            session_destroy();
        }

        return $response->withRedirect($this->container->router->pathFor('home'), 302);
    }

}