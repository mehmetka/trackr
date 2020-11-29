<?php

namespace App\middleware;

use App\exception\CustomException;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;

class Authentication extends Middleware
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {

        if (!isset($_SESSION['userInfos']) || !$_SESSION['userInfos']['user_id']) {

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                throw CustomException::clientError(401, 'Please log in again!');
            }

            return $response->withRedirect($this->container->router->pathFor('login'));
        }

        $response = $next($request, $response);

        return $response;

    }
}