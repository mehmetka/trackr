<?php

use Slim\Http\StatusCode;

$container['db'] = function ($container) {

    $dsn = "mysql:host=" . $_ENV['MYSQL_HOST'] . ";dbname=" . $_ENV['MYSQL_DATABASE'] . ";charset=utf8mb4";
    try {
        $db = new \PDO($dsn, $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASSWORD']);
    } catch (\Exception $e) {
        throw new Exception("Database access problem : " . $e->getMessage(), 500);
    }

    return $db;
};

$container['view'] = function ($container) {

    return $view = new \Slim\Views\Mustache([
        'template' => [
            'paths' => [
                realpath(__DIR__ . '/../views/modal'),
                realpath(__DIR__ . '/../views/include'),
                realpath(__DIR__ . '/../views')
            ],
            'extension' => 'mustache',
            'charset' => 'utf-8'
        ]
    ]);

};

$container['logger'] = function ($container) {
    $logger = new Monolog\Logger('trackr');
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler(__DIR__ . '/../logs/skeleton.log', \Monolog\Logger::DEBUG));
    return $logger;
};

$container['notFoundHandler'] = function ($container) {
    return function ($request, $response) use ($container) {
        $data = [
            'status' => 404,
            'message' => 'Not Found'
        ];
        return $container->get('response')->withStatus($data['status'])->withHeader('Content-Type', 'application/json')->write(json_encode($data));
    };
};

$container['errorHandler'] = function ($container) {

    return function ($request, $response, $exception) use ($container) {

        /** @var Monolog\Logger $logger */
        $logger = $container->get('logger');

        /** @var Exception $exception */
        if ($exception instanceof CustomException) {

            if ($exception->getErrorType() == 'client_error') {
                $data['status'] = 400;
                $logger->warning($exception->getMessage() . " detail:" . $exception->getErrorDetail() . ' trace:' . $exception->getBackTrace());
            }

            if ($exception->getErrorType() == 'server_error') {
                $data['status'] = 500;
                $logger->error($exception->getMessage() . " detail:" . $exception->getErrorDetail() . ' trace:' . $exception->getBackTrace());
            }

            if ($exception->getErrorType() == 'db_error') {
                $data['status'] = 503;
                $logger->critical($exception->getMessage() . " detail:" . $exception->getErrorDetail() . ' trace:' . $exception->getBackTrace());
            }

            $data = [
                'status' => $exception->getHttpStatusCode(),
                'message' => $exception->getMessage()
            ];

        } elseif ($exception instanceof \PDOException) {

            $exceptionArray = json_decode(json_encode($exception), true);

            $data['status'] = StatusCode::HTTP_SERVICE_UNAVAILABLE;
            $data['message'] = 'Request could not complete. Contact administrator.';

            if ($exceptionArray['errorInfo'][1] == 1062) {
                $data['status'] = StatusCode::HTTP_CONFLICT;
                $data['message'] = 'Duplicate entry!';
            } else {
                $logger->critical('PDOException: ' . $exception->getMessage());
            }

        } else {
            $logger->critical($exception->getMessage());

            $data = [
                'status' => StatusCode::HTTP_INTERNAL_SERVER_ERROR,
                'message' => $exception->getMessage()
            ];
        }

        return $container->get('response')->withStatus($data['status'])->withHeader('Content-Type', 'application/json')->write(json_encode($data));
    };
};