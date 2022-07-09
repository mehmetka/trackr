<?php

namespace App\rabbitmq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use PhpAmqpLib\Message\AMQPMessage;

class AmqpJobPublisher
{
    private $connection;

    public function __construct()
    {
        $this->connection = new AMQPStreamConnection($_ENV['RABBITMQ_HOST'], $_ENV['RABBITMQ_PORT'], $_ENV['RABBITMQ_USER'], $_ENV['RABBITMQ_PASSWORD'], $_ENV['RABBITMQ_VHOST']);
    }

    public function __destruct()
    {
        $this->connection->close();
    }

    public function publishBookmarkTitleJob($id, $retryCount = 0)
    {
        $jobType = 'get_bookmark_title';
        $exchange = 'router';
        $queue = 'msgs';

        $channel = $this->connection->channel();
        $channel->queue_declare($queue, false, true, false, false);
        $channel->exchange_declare($exchange, AMQPExchangeType::DIRECT, false, true, false);
        $channel->queue_bind($queue, $exchange);

        $messageBody = [
            'job_type' => $jobType,
            'id' => $id,
            'retry_count' => $retryCount
        ];
        $message = new AMQPMessage(serialize($messageBody), array('content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
        $channel->basic_publish($message, $exchange);

        $channel->close();
    }

}