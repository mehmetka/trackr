<?php

namespace App\model;

use App\enum\Sources;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class FavoriteModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function add($type, $sourceId)
    {
        $now = time();

        $sql = 'INSERT INTO favorites (type, source_id, user_id, created_at)
                VALUES(:type, :source_id, :user_id, :created_at)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':type', $type, \PDO::PARAM_INT);
        $stm->bindParam(':source_id', $sourceId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':created_at', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getHighlightFavorites()
    {
        $type = Sources::HIGHLIGHT->value;

        $sql = 'SELECT f.id AS favoriteId, f.type, f.source_id, f.user_id, f.created_at, h.id AS highlightId, h.title
                FROM favorites f
                INNER JOIN highlights h ON f.source_id = h.id
                WHERE f.user_id = :user_id AND f.type = :type';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':type', $type, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = $row;
        }

        return $list;
    }

}