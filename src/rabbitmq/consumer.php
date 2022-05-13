<?php

require __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

try {
    $dbConnection = new \PDO("mysql:host={$_ENV['MYSQL_HOST']}:3306;dbname={$_ENV['MYSQL_DATABASE']};charset=utf8mb4", $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASSWORD']);
} catch (Exception $e) {
    echo 'Database access problem: ' . $e->getMessage();
    die;
}

$exchange = 'router';
$queue = 'msgs';
$consumerTag = 'consumer';

$connection = new AMQPStreamConnection($_ENV['RABBITMQ_HOST'], $_ENV['RABBITMQ_PORT'], $_ENV['RABBITMQ_USER'], $_ENV['RABBITMQ_PASSWORD'], $_ENV['RABBITMQ_VHOST']);
$channel = $connection->channel();

$channel->queue_declare($queue, false, true, false, false);

$channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);

$channel->queue_bind($queue, $exchange);

/**
 * @param \PhpAmqpLib\Message\AMQPMessage $message
 */
function process_message($message)
{
    $messageBody = unserialize($message->body);

    if ($messageBody['job_type'] === 'get_bookmark_title') {
        $bookmarkDetails = getBookmarkByID($messageBody['id']);
        $title = getTitle($bookmarkDetails['bookmark']);
        $title =  strip_tags(trim($title));

        if($title){
            updateBookmarkTitle($bookmarkDetails['id'], $title);
        }

        echo "Completed 'get_bookmark_title' job for: {$bookmarkDetails['id']}";
    }

    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);

    if ($message->body === 'quit') {
        $message->delivery_info['channel']->basic_cancel($message->delivery_info['consumer_tag']);
    }
}

$channel->basic_consume($queue, $consumerTag, false, false, false, false, 'process_message');

/**
 * @param \PhpAmqpLib\Channel\AMQPChannel $channel
 * @param \PhpAmqpLib\Connection\AbstractConnection $connection
 */
function shutdown($channel, $connection)
{
    $channel->close();
    $connection->close();
}

register_shutdown_function('shutdown', $channel, $connection);

while ($channel->is_consuming()) {
    $channel->wait();
}


function getBookmarkByID($bookmarkID)
{
    $dbConnection = $GLOBALS['dbConnection'];

    $sql = 'SELECT * FROM bookmarks WHERE id = :id';

    $stm = $dbConnection->prepare($sql);
    $stm->bindParam(':id', $bookmarkID, \PDO::PARAM_INT);

    if (!$stm->execute()) {
        return false;
    }

    $bookmark = [];

    while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
        $bookmark = $row;
    }

    return $bookmark;
}

function getTitle($url)
{
    try {
        $data = @file_get_contents($url);
        $code = getHttpCode($http_response_header);

        if ($code === 404) {
            return '404 Not Found';
        }
    } catch (\Exception $exception) {
        return null;
    }

    if (preg_match('/<title[^>]*>(.*?)<\/title>/ims', $data, $matches)) {
        return mb_check_encoding($matches[1], 'UTF-8') ? $matches[1] : utf8_encode($matches[1]);
    }

    return null;
}

function getHttpCode($http_response_header)
{
    if (is_array($http_response_header)) {
        $parts = explode(' ', $http_response_header[0]);
        if (count($parts) > 1) //HTTP/1.0 <code> <text>
            return intval($parts[1]); //Get code
    }
    return 0;
}

function updateBookmarkTitle($bookmarkID, $title)
{
    $dbConnection = $GLOBALS['dbConnection'];

    $sql = 'UPDATE bookmarks SET title = :title WHERE id = :id';

    $stm = $dbConnection->prepare($sql);
    $stm->bindParam(':id', $bookmarkID, \PDO::PARAM_INT);
    $stm->bindParam(':title', $title, \PDO::PARAM_STR);

    if (!$stm->execute()) {
        return false;
    }

    return true;
}