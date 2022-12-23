<?php

namespace App\controller;

use App\exception\CustomException;
use App\model\LogModel;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\StatusCode;

class LogController extends Controller
{
    private $logModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->logModel = new LogModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $data['title'] = "Logs | trackr";
        $today = date('Y-m-d', time());

        $todayLog = $this->logModel->getLog($today);

        if (!$todayLog) {
            $this->logModel->insert($today, null);
        }

        $data['logs'] = $this->logModel->getLogs();
        $data['base_url'] = $_ENV['TRACKR_BASE_URL'];
        $data['todaysLog'] = $todayLog['log'];
        $data['today'] = $today;

        return $this->view->render($response, 'logs.mustache', $data);
    }

    public function save(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();
        $today = date('Y-m-d', time());

        if (!$params['log']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Log cannot be null!");
        }

        $todaysLog = $this->logModel->getLog($today);

        if ($todaysLog) {
            $this->logModel->update($today, $params['log']);

            if ($todaysLog['log']) {
                $this->logModel->saveOldVersion($todaysLog['id'], $todaysLog['log']);
            }

        } else {
            // while saving the log, date might change
            $this->logModel->insert($today, null);
            $this->logModel->update($today, $params['log']);
        }

        $resource['responseCode'] = StatusCode::HTTP_OK;
        $resource['message'] = "Saved successfully";

        return $this->response($resource['responseCode'], $resource);
    }

}