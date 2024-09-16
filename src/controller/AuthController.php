<?php

namespace App\controller;

use App\util\lang;
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
        $resource['message'] = lang\En::AUTH_LOGGED_IN_SUCCESSFULLY;

        return $this->response($resource['responseCode'], $resource);
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();
        $userExist = $this->authModel->userCreatedBefore($params['username']);

        if (!$params['username']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::AUTH_USERNAME_CANNOT_BE_NULL);
        }

        if (!$params['password']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::AUTH_PASSWORD_CANNOT_BE_NULL);
        }

        if ($userExist) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::AUTH_USER_ALREADY_EXISTS);
        }

        if (trim($params['password']) != trim($params['passwordAgain'])) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, lang\En::AUTH_PASSWORDS_NOT_MATCHED);
        }

        $this->authModel->register($params['username'], $params['password']);

        $resource['responseCode'] = StatusCode::HTTP_CREATED;
        $resource['message'] = lang\En::AUTH_USER_CREATED_SUCCESSFULLY;

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