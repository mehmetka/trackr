<?php

use App\exception\CustomException;
use Slim\Http\StatusCode;

$container['db'] = function ($container) {

    $dsn = "mysql:host=" . $_ENV['MYSQL_HOST'] . ";dbname=" . $_ENV['MYSQL_DATABASE'] . ";charset=utf8mb4";
    try {
        $db = new \PDO($dsn, $_ENV['MYSQL_USER'], $_ENV['MYSQL_PASSWORD']);
    } catch (\Exception $e) {
        throw new Exception("Database access problem : " . $e->getMessage(), StatusCode::HTTP_INTERNAL_SERVER_ERROR);
    }

    return $db;
};

$container['view'] = function ($container) {

    return new \Slim\Views\Mustache([
        'template' => [
            'paths' => [
                __DIR__ . '/../views/highlights/reusable',
                __DIR__ . '/../views/modal',
                __DIR__ . '/../views/include',
                __DIR__ . '/../views'
            ],
            'extension' => 'mustache',
            'charset' => 'utf-8'
        ]
    ]);

};

$container['logger'] = function ($container) {
    $logger = new Monolog\Logger('trackr');
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler(__DIR__ . '/../logs/application.log',
        \Monolog\Logger::DEBUG));
    return $logger;
};

$container['notFoundHandler'] = function ($container) {
    return function ($request, $response) use ($container) {
        $data = [
            'status' => StatusCode::HTTP_NOT_FOUND,
            'message' => 'Not Found'
        ];
        return $container->get('response')->withStatus($data['status'])->withHeader('Content-Type',
            'application/json')->write(json_encode($data));
    };
};

//$container['notAllowedHandler'] = function ($container) {
//    return function ($request, $response) use ($container) {
//        $data = [
//            'status' => StatusCode::HTTP_METHOD_NOT_ALLOWED,
//            'message' => 'Not Allowed'
//        ];
//        return $container->get('response')->withStatus($data['status'])->withHeader('Content-Type', 'application/json')->write(json_encode($data));
//    };
//};

//$container['phpErrorHandler'] = function ($container) {
//    return function ($request, $response, $exception) use ($container) {
//        $data = [
//            'status' => StatusCode::HTTP_INTERNAL_SERVER_ERROR,
//            'message' => 'PHP Error Occured'
//        ];
//        error_log($exception);
//        return $container->get('response')->withStatus($data['status'])->withHeader('Content-Type', 'application/json')->write(json_encode($data));
//    };
//};

$container['errorHandler'] = function ($container) {

    return function ($request, $response, $exception) use ($container) {

        /** @var Monolog\Logger $logger */
        $logger = $container->get('logger');

        if ($exception instanceof CustomException) {

            $errorMessage = $exception->getMessage() . " detail:" . $exception->getErrorDetail() . ' trace:' . $exception->getBackTrace();

            if ($exception->getErrorType() == 'client_error') {
                $logger->warning($errorMessage);
            }

            if ($exception->getErrorType() == 'server_error') {
                $logger->error($errorMessage);
            }

            if ($exception->getErrorType() == 'db_error') {
                $logger->critical($errorMessage);
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

        return $container->get('response')->withStatus($data['status'])->withHeader('Content-Type',
            'application/json')->write(json_encode($data));
    };
};