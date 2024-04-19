<?php

namespace App\controller;

use App\exception\CustomException;
use App\model\BookmarkModel;
use App\model\BookModel;
use App\model\HighlightModel;
use App\model\LogModel;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\StatusCode;
use Jfcherng\Diff\Differ;
use Jfcherng\Diff\DiffHelper;
use Jfcherng\Diff\Renderer\RendererConstant;

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

        $diffOptions = [
            // show how many neighbor lines
            // Differ::CONTEXT_ALL can be used to show the whole file
            'context' => Differ::CONTEXT_ALL,
            // ignore case difference
            'ignoreCase' => false,
            // ignore line ending difference
            'ignoreLineEnding' => false,
            // ignore whitespace difference
            'ignoreWhitespace' => false,
            // if the input sequence is too long, it will just gives up (especially for char-level diff)
            'lengthLimit' => 2000,
            // if truthy, when inputs are identical, the whole inputs will be rendered in the output
            'fullContextIfIdentical' => true,
        ];

        $rendererOptions = [
            // how detailed the rendered HTML is? (none, line, word, char)
            'detailLevel' => 'char',
            // renderer language: eng, cht, chs, jpn, ...
            // or an array which has the same keys with a language file
            // check the "Custom Language" section in the readme for more advanced usage
            'language' => 'eng',
            // show line numbers in HTML renderers
            'lineNumbers' => true,
            // show a separator between different diff hunks in HTML renderers
            'separateBlock' => true,
            // show the (table) header
            'showHeader' => true,
            // convert spaces/tabs into HTML codes like `<span class="ch sp"> </span>`
            // and the frontend is responsible for rendering them with CSS.
            // when using this, "spacesToNbsp" should be false and "tabSize" is not respected.
            'spaceToHtmlTag' => false,
            // the frontend HTML could use CSS "white-space: pre;" to visualize consecutive whitespaces
            // but if you want to visualize them in the backend with "&nbsp;", you can set this to true
            'spacesToNbsp' => false,
            // HTML renderer tab width (negative = do not convert into spaces)
            'tabSize' => 4,
            // this option is currently only for the Combined renderer.
            // it determines whether a replace-type block should be merged or not
            // depending on the content changed ratio, which values between 0 and 1.
            'mergeThreshold' => 0.8,
            // this option is currently only for the Unified and the Context renderers.
            // RendererConstant::CLI_COLOR_AUTO = colorize the output if possible (default)
            // RendererConstant::CLI_COLOR_ENABLE = force to colorize the output
            // RendererConstant::CLI_COLOR_DISABLE = force not to colorize the output
            'cliColorization' => RendererConstant::CLI_COLOR_AUTO,
            // this option is currently only for the Json renderer.
            // internally, ops (tags) are all int type but this is not good for human reading.
            // set this to "true" to convert them into string form before outputting.
            'outputTagAsString' => false,
            // this option is currently only for the Json renderer.
            // it controls how the output JSON is formatted.
            // see available options on https://www.php.net/manual/en/function.json-encode.php
            'jsonEncodeFlags' => \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
            // this option is currently effective when the "detailLevel" is "word"
            // characters listed in this array can be used to make diff segments into a whole
            // for example, making "<del>good</del>-<del>looking</del>" into "<del>good-looking</del>"
            // this should bring better readability but set this to empty array if you do not want it
            'wordGlues' => [' ', '-'],
            // change this value to a string as the returned diff if the two input strings are identical
            'resultForIdenticals' => null,
            // extra HTML classes added to the DOM of the diff container
            'wrapperClasses' => ['diff-wrapper'],
        ];

        $versions = $this->logModel->getVersionsByDate($date);
        $latest = $this->logModel->getLog($date);
        $newString = $latest['log'];

        $latestDiff = DiffHelper::calculate(
            $newString,
            $newString,
            'Inline',
            $diffOptions,
            $rendererOptions,
        );
        $versionDiffs[] = ['diff' => $latestDiff, 'created_at' => 'Latest'];

        foreach ($versions as $version) {
            $new = $newString;
            $old = $version['old'];
            $sideBySideResult = DiffHelper::calculate(
                $old,
                $new,
                'Inline',
                $diffOptions,
                $rendererOptions,
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