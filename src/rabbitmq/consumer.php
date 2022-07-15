<?php

require __DIR__ . '/../../vendor/autoload.php';

use Slim\App;
use App\model\BookmarkModel;
use App\util\EncodingUtil;
use App\util\RequestUtil;
use App\util\TwitterUtil;
use App\rabbitmq\AmqpJobPublisher;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use ForceUTF8\Encoding;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$settings['settings'] = [
    'displayErrorDetails' => $_ENV['displayErrorDetails'],
    'debug' => $_ENV['debug']
];

$app = new App($settings);

$container = $app->getContainer();

$container['db'] = function ($container) {

    $dsn = "mysql:host=" . $_ENV['MYSQL_HOST'] . ";dbname=" . $_ENV['MYSQL_DATABASE'] . ";charset=utf8mb4";
    try {
        $db = new \PDO($dsn, $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASSWORD']);
    } catch (\Exception $e) {
        echo 'Database access problem: ' . $e->getMessage();
        die;
    }

    return $db;
};

$exchange = 'router';
$queue = 'msgs';
$consumerTag = 'consumer';

$connection = new AMQPStreamConnection($_ENV['RABBITMQ_HOST'], $_ENV['RABBITMQ_PORT'], $_ENV['RABBITMQ_USER'],
    $_ENV['RABBITMQ_PASSWORD'], $_ENV['RABBITMQ_VHOST']);
$channel = $connection->channel();

$channel->queue_declare($queue, false, true, false, false);
$channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
$channel->queue_bind($queue, $exchange);
$channel->basic_consume($queue, $consumerTag, false, false, false, false, 'process_message');
register_shutdown_function('shutdown', $channel, $connection);

while ($channel->is_consuming()) {
    $channel->wait();
}

/**
 * @param \PhpAmqpLib\Message\AMQPMessage $message
 */
function process_message($message)
{
    $messageBody = unserialize($message->body);

    if ($messageBody['job_type'] === 'get_bookmark_title') {
        $container = $GLOBALS['container'];

        $bookmarkModel = new BookmarkModel($container);
        $bookmarkDetails = $bookmarkModel->getBookmarkById($messageBody['id']);

        if (!$bookmarkDetails['is_title_edited']) {
            if (TwitterUtil::isTwitterUrl($bookmarkDetails['bookmark'])) {
                $username = TwitterUtil::getUsernameFromUrl($bookmarkDetails['bookmark']);
                $title = 'Twitter - ' . strip_tags(trim($username));
                $bookmarkModel->updateTitleByID($bookmarkDetails['id'], $title);
                echo "completed 'get_bookmark_title' job for: {$bookmarkDetails['id']}, title: $title (twitter-title)\n";
            } else {
                $metadata = RequestUtil::getUrlMetadata($bookmarkDetails['bookmark']);

                if ($metadata['title']) {

                    $title = trim($metadata['title']);
                    $description = trim($metadata['description']);
                    $siteName = trim($metadata['site_name']);
                    $siteType = trim($metadata['type']);
                    $thumbnail = trim($metadata['image']);

                    if (EncodingUtil::isLatin1($title)) {
                        $title = Encoding::toLatin1($title);
                        $description = Encoding::toLatin1($description);
                        $siteName = Encoding::toLatin1($siteName);
                        $siteType = Encoding::toLatin1($siteType);
                        $thumbnail = Encoding::toLatin1($thumbnail);
                    }

                    $newBookmarkDetails['description'] = strip_tags($description);
                    $newBookmarkDetails['thumbnail'] = strip_tags($thumbnail);
                    $newBookmarkDetails['title'] = strip_tags($title);
                    $newBookmarkDetails['note'] = $bookmarkDetails['note'];
                    $newBookmarkDetails['status'] = $bookmarkDetails['status'];
                    $newBookmarkDetails['site_name'] = strip_tags($siteName);
                    $newBookmarkDetails['site_type'] = strip_tags($siteType);

                    try {
                        $bookmarkModel->updateBookmark($bookmarkDetails['id'], $newBookmarkDetails);
                    } catch (Exception $exception) {
                        echo 'Error occured: ' . $exception->getMessage();
                    }

                    if ($bookmarkDetails['title'] !== $newBookmarkDetails['title']) {
                        $bookmarkModel->updateHighlightAuthor($bookmarkDetails['id'], $newBookmarkDetails['title'],
                            $messageBody['user_id']);
                    }

                    echo "completed 'get_bookmark_title' job for: {$bookmarkDetails['id']}, title: {$newBookmarkDetails['title']}\n";

                } else {
                    if ($messageBody['retry_count'] < 5) {
                        echo "Retry count: {$messageBody['retry_count']}\n";
                        $messageBody['retry_count']++;
                        $amqpPublisher = new AmqpJobPublisher();

                        $jobDetails = [
                            'id' => $bookmarkDetails['id'],
                            'retry_count' => $messageBody['retry_count'],
                            'user_id' => $messageBody['user_id']
                        ];

                        $amqpPublisher->publishBookmarkTitleJob($jobDetails);
                        echo "trigged again 'get_bookmark_title' job for: {$bookmarkDetails['id']}, retry_count: {$messageBody['retry_count']}\n";
                    }
                }
            }
        }

    }

    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);

    if ($message->body === 'quit') {
        $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
    }
}

/**
 * @param \PhpAmqpLib\Channel\AMQPChannel $channel
 * @param \PhpAmqpLib\Connection\AbstractConnection $connection
 */
function shutdown($channel, $connection)
{
    $channel->close();
    $connection->close();
}