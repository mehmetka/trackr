<?php

$container['db'] = function ($container) {

    $settings = $container->get('settings')['db'];

    $dsn = $settings['driver'] . ":host=" . $settings['host'] . ";dbname=" . $settings['database'] . ";charset=" . $settings['charset'];
    try {
        $db = new \PDO($dsn, $settings['user'], $settings['password']);
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
    $logger->pushHandler(new Monolog\Handler\StreamHandler(dirname(__DIR__) . '/logs/skeleton.log', \Monolog\Logger::DEBUG));
    return $logger;
};

$container['notFoundHandler'] = function ($container) {
    return function ($request, $response) use ($container) {
        $json['message'] = 'Not Found';
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json')->write(json_encode($json));
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

        } else {
            $logger->critical($exception->getMessage());
            $withStatus = 500;

            $data = [
                'status' => $withStatus,
                'message' => $exception->getMessage()
            ];
        }

        return $container->get('response')->withStatus($data['status'])->withHeader('Content-Type', 'application/json')->write(json_encode($data));
    };
};