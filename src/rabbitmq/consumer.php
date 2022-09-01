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
        echo 'Database access problem: ' . $e->getMessage() . PHP_EOL;
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
    $container = $GLOBALS['container'];
    $bookmarkModel = new BookmarkModel($container);

    if ($messageBody['job_type'] === 'get_parent_bookmark_title') {

        $bookmarkDetails = $bookmarkModel->getParentBookmarkById($messageBody['id']);

        if (!$bookmarkDetails) {
            echo "bookmark not found. given bookmark id: {$messageBody['id']}\n";
            return;
        }

        if (TwitterUtil::isTwitterUrl($bookmarkDetails['bookmark'])) {
            $username = TwitterUtil::getUsernameFromUrl($bookmarkDetails['bookmark']);
            $title = 'Twitter - ' . strip_tags(trim($username));
            $bookmarkModel->updateParentBookmarkTitleByID($bookmarkDetails['id'], $title);

            echo "completed 'get_parent_bookmark_title' job for: {$bookmarkDetails['id']}, title: $title (twitter-title)\n";
        } else {
            $metadata = RequestUtil::getUrlMetadata($bookmarkDetails['bookmark']);

            if ($metadata['title']) {

                $newBookmarkDetails['description'] = strip_tags(EncodingUtil::isLatin1(trim($metadata['description'])) ? Encoding::toLatin1(trim($metadata['description'])) : trim($metadata['description']));
                $newBookmarkDetails['thumbnail'] = strip_tags(EncodingUtil::isLatin1(trim($metadata['image'])) ? Encoding::toLatin1(trim($metadata['image'])) : trim($metadata['image']));
                $newBookmarkDetails['title'] = strip_tags(EncodingUtil::isLatin1(trim($metadata['title'])) ? Encoding::toLatin1(trim($metadata['title'])) : trim($metadata['title']));
                $newBookmarkDetails['site_name'] = strip_tags(EncodingUtil::isLatin1(trim($metadata['site_name'])) ? Encoding::toLatin1(trim($metadata['site_name'])) : trim($metadata['site_name']));
                $newBookmarkDetails['site_type'] = strip_tags(EncodingUtil::isLatin1(trim($metadata['type'])) ? Encoding::toLatin1(trim($metadata['type'])) : trim($metadata['type']));

                try {
                    $bookmarkModel->updateParentBookmark($bookmarkDetails['id'], $newBookmarkDetails);
                } catch (Exception $exception) {
                    echo 'Error occured: ' . $exception->getMessage() . PHP_EOL;
                }

                echo "completed 'get_parent_bookmark_title' job for: {$bookmarkDetails['id']}, title: {$newBookmarkDetails['title']}\n";

            } else {
                if ($messageBody['retry_count'] < 5) {
                    echo "Retry count: {$messageBody['retry_count']}\n";
                    $messageBody['retry_count']++;
                    $amqpPublisher = new AmqpJobPublisher();

                    $amqpPublisher->publishParentBookmarkTitleJob([
                        'id' => $bookmarkDetails['id'],
                        'retry_count' => $messageBody['retry_count']
                    ]);
                    echo "trigged again 'get_parent_bookmark_title' job for: {$bookmarkDetails['id']}, retry_count: {$messageBody['retry_count']}\n";
                }
            }
        }

    } elseif ($messageBody['job_type'] === 'get_child_bookmark_title') {

        $bookmarkDetails = $bookmarkModel->getChildBookmarkById($messageBody['id'], $messageBody['user_id']);

        if (!$bookmarkDetails) {
            echo "bookmark not found. given bookmark id: {$messageBody['id']}\n";
            return;
        }

        if (!$bookmarkDetails['is_title_edited']) {
            if (TwitterUtil::isTwitterUrl($bookmarkDetails['bookmark'])) {
                $username = TwitterUtil::getUsernameFromUrl($bookmarkDetails['bookmark']);
                $title = 'Twitter - ' . strip_tags(trim($username));
                $bookmarkModel->updateChildBookmarkTitleByID($bookmarkDetails['id'], $title, $messageBody['user_id']);

                echo "completed 'get_child_bookmark_title' job for: {$bookmarkDetails['id']}, title: $title (twitter-title)\n";
            } else {
                $metadata = RequestUtil::getUrlMetadata($bookmarkDetails['bookmark']);

                if ($metadata['title']) {

                    $newBookmarkDetails['description'] = strip_tags(EncodingUtil::isLatin1(trim($metadata['description'])) ? Encoding::toLatin1(trim($metadata['description'])) : trim($metadata['description']));
                    $newBookmarkDetails['thumbnail'] = strip_tags(EncodingUtil::isLatin1(trim($metadata['image'])) ? Encoding::toLatin1(trim($metadata['image'])) : trim($metadata['image']));
                    $newBookmarkDetails['title'] = strip_tags(EncodingUtil::isLatin1(trim($metadata['title'])) ? Encoding::toLatin1(trim($metadata['title'])) : trim($metadata['title']));
                    $newBookmarkDetails['site_name'] = strip_tags(EncodingUtil::isLatin1(trim($metadata['site_name'])) ? Encoding::toLatin1(trim($metadata['site_name'])) : trim($metadata['site_name']));
                    $newBookmarkDetails['site_type'] = strip_tags(EncodingUtil::isLatin1(trim($metadata['type'])) ? Encoding::toLatin1(trim($metadata['type'])) : trim($metadata['type']));
                    $newBookmarkDetails['note'] = $bookmarkDetails['note'];
                    $newBookmarkDetails['status'] = $bookmarkDetails['status'];

                    try {
                        $bookmarkModel->updateChildBookmark($bookmarkDetails['id'], $newBookmarkDetails, $messageBody['user_id']);
                    } catch (Exception $exception) {
                        echo 'Error occured: ' . $exception->getMessage() . PHP_EOL;
                        $web = new \spekulatius\phpscraper;
                        $web->go($bookmarkDetails['bookmark']);
                        $newBookmarkDetails['title'] = strip_tags(trim($web->title));
                        $newBookmarkDetails['description'] = strip_tags(trim($web->description));
                        $bookmarkModel->updateParentBookmark($bookmarkDetails['id'], $newBookmarkDetails);
                    }

                    if ($bookmarkDetails['title'] !== $newBookmarkDetails['title']) {
                        $bookmarkModel->updateHighlightAuthor($bookmarkDetails['id'], $newBookmarkDetails['title'],
                            $messageBody['user_id']);
                    }

                    echo "completed 'get_child_bookmark_title' job for: {$bookmarkDetails['id']}, title: {$newBookmarkDetails['title']}\n";

                } else {
                    if ($messageBody['retry_count'] < 5) {
                        echo "Retry count: {$messageBody['retry_count']}\n";
                        $messageBody['retry_count']++;
                        $amqpPublisher = new AmqpJobPublisher();

                        $amqpPublisher->publishChildBookmarkTitleJob([
                            'id' => $bookmarkDetails['id'],
                            'retry_count' => $messageBody['retry_count'],
                            'user_id' => $messageBody['user_id']
                        ]);

                        echo "trigged again 'get_child_bookmark_title' job for: {$bookmarkDetails['id']}, retry_count: {$messageBody['retry_count']}\n";
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