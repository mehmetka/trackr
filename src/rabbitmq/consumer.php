<?php

require __DIR__ . '/../../vendor/autoload.php';

use Slim\App;
use App\model\BookmarkModel;
use App\util\RequestUtil;
use App\util\TwitterUtil;
use App\rabbitmq\AmqpJobPublisher;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

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

        if (TwitterUtil::isTwitterUrl($bookmarkDetails['bookmark'])) {
            $username = TwitterUtil::getUsernameFromUrl($bookmarkDetails['bookmark']);
            $title = 'Twitter - ' . strip_tags(trim($username));
            $bookmarkModel->updateTitleByID($bookmarkDetails['id'], $title);
            echo "Completed 'get_bookmark_title' job for: {$bookmarkDetails['id']}, title: $title (twitter-title)\n";
        } else {
            $metadata = RequestUtil::getUrlMetadata($bookmarkDetails['bookmark']);

            if ($metadata['title']) {
                $title = strip_tags(trim($metadata['title']));
                $bookmarkModel->updateTitleByID($bookmarkDetails['id'], $title);
                echo "Completed 'get_bookmark_title' job for: {$bookmarkDetails['id']}, title: $title\n";
            } else {
                if ($messageBody['retry_count'] < 5) {
                    echo "Retry count: {$messageBody['retry_count']}\n";
                    $messageBody['retry_count']++;
                    $amqpPublisher = new AmqpJobPublisher();
                    $amqpPublisher->publishBookmarkTitleJob($bookmarkDetails['id'], $messageBody['retry_count']);
                    echo "Trigged again 'get_bookmark_title' job for: {$bookmarkDetails['id']}, retry_count: {$messageBody['retry_count']}\n";
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