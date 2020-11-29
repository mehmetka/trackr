<?php

namespace App\middleware;

use App\CustomException;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;

class Guest extends Middleware
{
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {

        if (isset($_SESSION['userInfos']) || $_SESSION['userInfos']['user_id']) {
            return $response->withRedirect($this->container->router->pathFor('home'));
        }

        $response = $next($request, $response);

        return $response;

    }
}