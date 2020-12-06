<?php

namespace App\controller;

use App\model\TrackingModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;

class TrackingController extends Controller
{
    private $trackingsModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->trackingsModel = new TrackingModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $highlights = $this->trackingsModel->getWorkTrackings();
        $date = date('Y-m-d', time());
        $todaysTracking = $this->trackingsModel->getWorkTrackingByDate($date);

        $data = [
            'average' => round($this->trackingsModel->average(), 3),
            'today' => $date,
            'todaysDescription' => $todaysTracking['description'],
            'data' => $highlights,
            'activeTrackings' => 'active'
        ];

        return $this->view->render($response, 'trackings.mustache', $data);
    }

    public function add(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();
        $date = date('Y-m-d', time());
        $todaysTracking = $this->trackingsModel->getWorkTrackingByDate($date);

        if (!$todaysTracking) {
            $this->trackingsModel->create($params['workingsAmount'], $params['workingsDescription']);
        } else {
            $params['workingsAmount'] = $params['workingsAmount'] ? $params['workingsAmount'] : 0;
            $amount = $params['workingsAmount'] + $todaysTracking['amount'];
            $this->trackingsModel->update($amount, $params['workingsDescription'], $date);
        }

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

}