<?php

namespace App\controller;

use App\model\BookmarkModel;
use App\model\BookModel;
use App\model\DateTrackingModel;
use App\model\HighlightModel;
use App\model\TodoModel;
use App\model\VideoModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class HomeController extends Controller
{
    private $bookModel;
    private $todoModel;
    private $bookmarkModel;
    private $videosModel;
    private $highlightsModel;
    private $dateTrackingModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->bookModel = new BookModel($container);
        $this->todoModel = new TodoModel($container);
        $this->bookmarkModel = new BookmarkModel($container);
        $this->videosModel = new VideoModel($container);
        $this->highlightsModel = new HighlightModel($container);
        $this->dateTrackingModel = new DateTrackingModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $dateTrackings = $this->dateTrackingModel->getDateTrackings();
        $averageData = $this->bookModel->readingAverage();
        $today = date('m/d/Y');

        $data = [
            'title' => 'Home | trackr',
            'dateTrackings' => $dateTrackings,
            'readingAverage' => round($averageData['average'], 3),
            'readingTotal' => $averageData['total'],
            'dayDiff' => $averageData['diff'],
            'today' => $today,
            'activeHome' => 'active'
        ];

        return $this->view->render($response, 'home.mustache', $data);
    }

    public function getMenuBadgeCounts(ServerRequestInterface $request, ResponseInterface $response)
    {
        if (!isset($_SESSION['badgeCounts'])) {
            $_SESSION['badgeCounts']['myBookCount'] = $this->bookModel->getMyBooksCount();
            $_SESSION['badgeCounts']['allBookCount'] = $this->bookModel->getAllBookCount();
            $_SESSION['badgeCounts']['finishedBookCount'] = $this->bookModel->getFinishedBookCount();
            $_SESSION['badgeCounts']['bookmarkCount'] = $this->bookmarkModel->getUncompleteBookmarks();
            $_SESSION['badgeCounts']['videosCount'] = $this->videosModel->getUncompleteVideos();
            $_SESSION['badgeCounts']['highlightsCount'] = $this->highlightsModel->getHighlightsCount();
            $_SESSION['badgeCounts']['todosCount'] = $this->todoModel->getUncompleteTodoCount();
        }

        $data = [
            'myBookCount' => $_SESSION['badgeCounts']['myBookCount'],
            'allBookCount' => $_SESSION['badgeCounts']['allBookCount'],
            'finishedBookCount' => $_SESSION['badgeCounts']['finishedBookCount'],
            'bookmarkCount' => $_SESSION['badgeCounts']['bookmarkCount'],
            'videosCount' => $_SESSION['badgeCounts']['videosCount'],
            'highlightsCount' => $_SESSION['badgeCounts']['highlightsCount'],
            'todosCount' => $_SESSION['badgeCounts']['todosCount'],
        ];

        return $this->response(StatusCode::HTTP_OK, $data);
    }
}
