<?php

namespace App\model;

use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class ImageModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function insert($fileName)
    {
        $createdAt = time();
        $sha1 = sha1_file($_SERVER['DOCUMENT_ROOT'] . '/img/' . $fileName);

        $sql = 'INSERT INTO images (sha1, filename, created_at, user_id) 
                VALUES(:sha1, :filename, :created_at, :user_id)';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':sha1', $sha1, \PDO::PARAM_STR);
        $stm->bindParam(':filename', $fileName, \PDO::PARAM_STR);
        $stm->bindParam(':created_at', $createdAt, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

}