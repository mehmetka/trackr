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

        if ($todayLog) {
            $_SESSION['todays_log'] = $todayLog['log'];
        } else {
            $this->logModel->insert($today, null);
        }

        $data['logs'] = $this->logModel->getLogs();
        $data['todaysLog'] = $todayLog['log'];
        $data['today'] = $today;
        $data['activeLogs'] = 'active';

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

            $previousLog = $_SESSION['todays_log'] ?? null;

            if ($params['log'] !== $previousLog) {
                $this->logModel->update($today, $params['log']);

                if ($todaysLog['log']) {
                    $this->logModel->saveOldVersion($todaysLog['id'], $todaysLog['log']);
                }
                $resource['message'] = "Saved successfully";
            } else {
                $resource['message'] = "Did not save. Logs are equal";
            }

        } else {
            // while saving the log, date might change
            $this->logModel->insert($today, $params['log']);
        }

        $_SESSION['todays_log'] = $todaysLog['log'];

        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

}