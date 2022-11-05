<?php

require __DIR__ . '/../../vendor/autoload.php';

use Slim\App;
use App\entity\Book;
use App\model\BookmarkModel;
use App\model\BookModel;
use App\util\EncodingUtil;
use App\util\RequestUtil;
use App\util\TwitterUtil;
use App\rabbitmq\AmqpJobPublisher;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use ForceUTF8\Encoding;
use Goutte\Client;

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

            if (isset($metadata['title']) && $metadata['title']) {

                $metadata = array_map('trim', $metadata);

                $newBookmarkDetails['description'] = EncodingUtil::isLatin1($metadata['description']) ? Encoding::toLatin1($metadata['description']) : $metadata['description'];
                $newBookmarkDetails['thumbnail'] = EncodingUtil::isLatin1($metadata['image']) ? Encoding::toLatin1($metadata['image']) : $metadata['image'];
                $newBookmarkDetails['title'] = EncodingUtil::isLatin1($metadata['title']) ? Encoding::toLatin1($metadata['title']) : $metadata['title'];
                $newBookmarkDetails['site_name'] = EncodingUtil::isLatin1($metadata['site_name']) ? Encoding::toLatin1($metadata['site_name']) : $metadata['site_name'];
                $newBookmarkDetails['site_type'] = EncodingUtil::isLatin1($metadata['type']) ? Encoding::toLatin1($metadata['type']) : $metadata['type'];

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

                if (isset($metadata['title']) && $metadata['title']) {

                    $metadata = array_map('trim', $metadata);

                    $newBookmarkDetails['description'] = EncodingUtil::isLatin1($metadata['description']) ? Encoding::toLatin1($metadata['description']) : $metadata['description'];
                    $newBookmarkDetails['thumbnail'] = EncodingUtil::isLatin1($metadata['image']) ? Encoding::toLatin1($metadata['image']) : $metadata['image'];
                    $newBookmarkDetails['title'] = EncodingUtil::isLatin1($metadata['title']) ? Encoding::toLatin1($metadata['title']) : $metadata['title'];
                    $newBookmarkDetails['site_name'] = EncodingUtil::isLatin1($metadata['site_name']) ? Encoding::toLatin1($metadata['site_name']) : $metadata['site_name'];
                    $newBookmarkDetails['site_type'] = EncodingUtil::isLatin1($metadata['type']) ? Encoding::toLatin1($metadata['type']) : $metadata['type'];
                    $newBookmarkDetails['note'] = $bookmarkDetails['note'];
                    $newBookmarkDetails['status'] = $bookmarkDetails['status'];

                    try {
                        $bookmarkModel->updateChildBookmark($bookmarkDetails['id'], $newBookmarkDetails,
                            $messageBody['user_id']);
                    } catch (Exception $exception) {
                        echo 'Error occured: ' . $exception->getMessage() . PHP_EOL;
                        $web = new \spekulatius\phpscraper;
                        $web->go($bookmarkDetails['bookmark']);
                        $newBookmarkDetails['title'] = strip_tags(trim($web->title));
                        $newBookmarkDetails['description'] = strip_tags(trim($web->description));
                        $bookmarkModel->updateChildBookmark($bookmarkDetails['id'], $newBookmarkDetails,
                            $messageBody['user_id']);
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
    } elseif ($messageBody['job_type'] === 'scrape_book_on_idefix') {

        session_start();
        $_SESSION['userInfos']['user_id'] = $messageBody['user_id'];
        $isbn = $messageBody['isbn'];

        $bookModel = new BookModel($container);

        $exist = $bookModel->getBookByISBN($isbn);

        if (!$exist) {
            $elements = [
                'bookTitle' => ['fetch' => 'text', 'selector' => '.mt0'],
                'author' => [
                    'fetch' => 'text',
                    'selector' => '.product-info-list > ul:nth-child(1) > li:nth-child(2) > span:nth-child(2)'
                ],
                'description' => ['fetch' => 'text', 'selector' => '.product-description'],
                'thumbnail' => [
                    'fetch' => 'attribute',
                    'selector' => '#main-product-img',
                    'attributeName' => 'data-src'
                ],
//            'pageCount' => [
//                'fetch' => 'text',
//                'selector' => '.product-info-list > ul:nth-child(1) > li:nth-child(6) > a:nth-child(2)'
//            ],
                'publisher' => [
                    'fetch' => 'text',
                    'selector' => 'div.hidden-xs:nth-child(2) > div:nth-child(2) > a:nth-child(2)'
                ]
            ];


            $url = "https://www.idefix.com/search?q=$isbn&redirect=search";

            try {
                $client = new Client();
                $crawler = $client->request('GET', $url);

                $result = $crawler->filter(".box-title")->text();
                $link = $crawler->selectLink($result)->link();
                $crawler = $client->click($link);

                $bookData['info_link'] = $link->getUri();
            } catch (Exception $e) {
                echo 'Error occured while scraping book on Idefix: ' . $e->getMessage();
            }

            $bookData['isbn'] = $isbn;
            $bookData['pdf'] = 0;
            $bookData['epub'] = 0;
            $bookData['pageCount'] = 0;

            foreach ($elements as $key => $element) {
                if ($element['fetch'] === 'text') {
                    $bookData[$key] = getTextBySelector($crawler, $element['selector']);
                } elseif ($element['fetch'] === 'attribute') {
                    $bookData[$key] = getAttrBySelector($crawler, $element['selector'], $element['attributeName']);
                }
            }

            echo 'author: ' . $bookData['author'] . PHP_EOL;
            echo 'title: ' . $bookData['bookTitle'] . PHP_EOL;

            $exist = $bookModel->getBookByGivenColumn(Book::COLUMN_TITLE, $bookData['bookTitle']);

            if (!$exist && $bookData['bookTitle'] && $bookData['author']) {

                if ($bookData['publisher']) {
                    $publisherDetails = $bookModel->getPublisher($bookData['publisher']);
                    $bookData['publisher'] = !$publisherDetails ? $bookModel->insertPublisher($bookData['publisher']) : $publisherDetails['id'];
                }

                $bookId = $bookModel->saveBook($bookData);

                if ($bookId) {
                    $authors = $bookModel->createAuthorOperations($bookData['author']);

                    foreach ($authors as $authorId) {
                        $bookModel->insertBookAuthor($bookId, $authorId);
                    }
                }
            }
        }

        echo 'user id: ' . $_SESSION['userInfos']['user_id'];
        session_destroy();
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

function getTextBySelector($crawler, $selector)
{
    try {
        return trim($crawler->filter($selector)->text());
    } catch (Exception $exception) {
        echo "error occured while fetching '$selector', error: " . $exception->getMessage();
        return null;
    }
}

function getAttrBySelector($crawler, $selector, $attrName)
{
    try {
        return trim($crawler->filter($selector)->attr($attrName));
    } catch (Exception $exception) {
        echo "error occured while fetching '$selector', error: " . $exception->getMessage();
        return null;
    }
}