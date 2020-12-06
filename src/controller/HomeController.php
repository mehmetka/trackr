<?php

namespace App\controller;

use App\model\BookModel;
use App\model\DateTrackingModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;

class HomeController extends Controller
{
    private $bookModel;
    private $dateTrackingModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->bookModel = new BookModel($container);
        $this->dateTrackingModel = new DateTrackingModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $dateTrackings = $this->dateTrackingModel->getDateTrackings();
        $averageData = $this->bookModel->readingAverage();
        $today = date('m/d/Y');

        $data = [
            'dateTrackings' => $dateTrackings,
            'readingAverage' => round($averageData['average'], 3),
            'readingTotal' => $averageData['total'],
            'dayDiff' => $averageData['diff'],
            'today' => $today,
            'activeHome' => 'active'
        ];

        return $this->view->render($response, 'home.mustache', $data);
    }

}