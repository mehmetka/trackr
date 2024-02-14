<?php

namespace App\controller;

use App\model\AuthModel;
use App\exception\CustomException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\StatusCode;

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
        $data['pageTitle'] = "Login | trackr";

        return $this->view->render($response, 'login.mustache', $data);
    }

    public function registerPage(ServerRequestInterface $request, ResponseInterface $response)
    {
        $data['pageTitle'] = "trackr";
        return $this->view->render($response, 'register.mustache', $data);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        $this->authModel->login($params['username'], $params['password']);

        $resource['responseCode'] = StatusCode::HTTP_OK;
        $resource['message'] = "Logged in successfully";

        return $this->response($resource['responseCode'], $resource);
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();
        $userExist = $this->authModel->userCreatedBefore($params['username']);

        if (!$params['username'] || !$params['password']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'Credentials cannot be null',
                'Credentials cannot be null');
        }

        if ($userExist) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'User created before!',
                'User created before!');
        }

        if (trim($params['password']) != trim($params['passwordAgain'])) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'Passwords must be matched!',
                'Passwords must be matched!');
        }

        $this->authModel->register($params['username'], $params['password']);

        $resource['responseCode'] = StatusCode::HTTP_CREATED;
        $resource['message'] = "Registered successfully";

        return $this->response($resource['responseCode'], $resource);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response)
    {
        $_SESSION = array();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"],
                $params["httponly"]);
            session_destroy();
        }

        return $response->withRedirect($this->container->router->pathFor('home'), StatusCode::HTTP_FOUND);
    }

}