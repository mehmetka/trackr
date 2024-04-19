<?php

namespace App\controller;

use App\exception\CustomException;
use App\model\BookmarkModel;
use App\model\BookModel;
use App\model\HighlightModel;
use App\model\LogModel;
use App\util\VersionDiffUtil;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\StatusCode;
use Jfcherng\Diff\DiffHelper;

class LogController extends Controller
{
    private $logModel;
    private $bookModel;
    private $bookmarkModel;
    private $highlightModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->logModel = new LogModel($container);
        $this->bookModel = new BookModel($container);
        $this->bookmarkModel = new BookmarkModel($container);
        $this->highlightModel = new HighlightModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $limit = 30;
        $data['pageTitle'] = "Logs | trackr";
        $today = date('Y-m-d', time());

        $todayLog = $this->logModel->getLog($today);

        if ($todayLog) {
            $_SESSION['todays_log'] = $todayLog['log'];
        } else {
            $this->logModel->insert($today, null);
        }

        $logs = $this->logModel->getLogs($limit);
        foreach ($logs as $key => $log) {
            $from = strtotime($log['date']);
            $to = strtotime($log['date']) + 86400;
            $logs[$key]['reading'] = $this->bookModel->getDailyReadingAmount($log['date']);
            $logs[$key]['bookmarks'] = $this->bookmarkModel->getFinishedBookmarks($from, $to);
            $logs[$key]['bookmarksExist'] = count($logs[$key]['bookmarks']);
            $logs[$key]['highlights'] = $this->highlightModel->getHighlightsByDateRange($from, $to);
            $logs[$key]['highlightsExist'] = count($logs[$key]['highlights']);
        }
        $data['logs'] = $logs;
        $data['todaysLog'] = $todayLog['log'];
        $data['today'] = $today;
        $data['activeLogs'] = 'active';

        return $this->view->render($response, 'logs.mustache', $data);
    }

    public function logsVersions(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $date = htmlspecialchars($args['date']);
        $versionDiffs = [];

        $data['pageTitle'] = "Versions $date | trackr";
        $data['activeLogs'] = 'active';

        $versions = $this->logModel->getVersionsByDate($date);
        $latest = $this->logModel->getLog($date);
        $newString = $latest['log'];

        $latestDiff = DiffHelper::calculate(
            $newString,
            $newString,
            'Inline',
            VersionDiffUtil::logsDiffOptions(),
            VersionDiffUtil::logsRendererOptions(),
        );
        $versionDiffs[] = ['diff' => $latestDiff, 'created_at' => 'Latest'];

        foreach ($versions as $version) {
            $new = $newString;
            $old = $version['old'];
            $sideBySideResult = DiffHelper::calculate(
                $old,
                $new,
                'Inline',
                VersionDiffUtil::logsDiffOptions(),
                VersionDiffUtil::logsRendererOptions(),
            );

            $versionDiffs[] = ['diff' => $sideBySideResult, 'created_at' => $version['created_at']];
            $newString = $version['old'];
        }

        $data['versionDiffs'] = $versionDiffs;
        $data['date'] = $date;

        return $this->view->render($response, 'logs-versions.mustache', $data);
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