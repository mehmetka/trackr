<?php

namespace App\controller;

use App\model\BookmarkModel;
use App\model\BookModel;
use App\model\DateTrackingModel;
use App\model\HighlightModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class HomeController extends Controller
{
    private $bookModel;
    private $bookmarkModel;
    private $highlightModel;
    private $dateTrackingModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->bookModel = new BookModel($container);
        $this->bookmarkModel = new BookmarkModel($container);
        $this->highlightModel = new HighlightModel($container);
        $this->dateTrackingModel = new DateTrackingModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $dateTrackings = $this->dateTrackingModel->getDateTrackings();
        $averageData = $this->bookModel->readingAverage();
        $today = date('d/m/Y');
        $randomHighlight = $this->highlightModel->getRandomHighlight();

        $data = [
            'title' => 'Home | trackr',
            'dateTrackings' => $dateTrackings,
            'readingAverage' => round($averageData['average'], 3),
            'readingTotal' => $averageData['total'],
            'dayDiff' => $averageData['diff'],
            'today' => $today,
            'activeHome' => 'active',
            'randomHighlight' => $randomHighlight
        ];


        return $this->view->render($response, 'home.mustache', $data);
    }

    public function getMenuBadgeCounts(ServerRequestInterface $request, ResponseInterface $response)
    {
        if (!isset($_SESSION['badgeCounts']) || time() > $_SESSION['badgeCounts']['expires_at']) {
            $_SESSION['badgeCounts']['myBookCount'] = $this->bookModel->getMyBooksCount();
            $_SESSION['badgeCounts']['allBookCount'] = $this->bookModel->getAllBookCount();
            $_SESSION['badgeCounts']['finishedBookCount'] = $this->bookModel->getFinishedBookCount();
            $_SESSION['badgeCounts']['bookmarkCount'] = $this->bookmarkModel->getUncompleteBookmarks();
            $_SESSION['badgeCounts']['highlightsCount'] = $this->highlightModel->getHighlightsCount();
            $_SESSION['badgeCounts']['expires_at'] = time() + 3600;
        }

        $data = [
            'myBookCount' => $_SESSION['badgeCounts']['myBookCount'],
            'allBookCount' => $_SESSION['badgeCounts']['allBookCount'],
            'finishedBookCount' => $_SESSION['badgeCounts']['finishedBookCount'],
            'bookmarkCount' => $_SESSION['badgeCounts']['bookmarkCount'],
            'highlightsCount' => $_SESSION['badgeCounts']['highlightsCount'],
        ];

        return $this->response(StatusCode::HTTP_OK, $data);
    }
}
