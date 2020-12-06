<?php

namespace App\model;

use Psr\Container\ContainerInterface;
use App\exception\CustomException;

class VideoModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function getVideos()
    {
        $list = [];

        $sql = 'SELECT v.id, v.title, c.name as categoryName, v.length, v.created, v.started, v.done
                FROM videos v
                INNER JOIN categories c
                ON v.category_id = c.id';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['type'] = '';

            if (!$row['started']) {
                $row['startAction'] = true;
            } elseif (!$row['done']) {
                $row['doneAction'] = true;
            } else {
                $row['complete'] = true;
            }

            $list[] = $row;
        }

        return $list;

    }

    public function create($params)
    {
        $created = time();
        $length = round($params['length'] / 60, 3);

        $sql = 'INSERT INTO videos (title, category_id, length, link, created)
                VALUES (:title, :category_id, :length, :link, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':title', $params['title'], \PDO::PARAM_STR);
        $stm->bindParam(':category_id', $params['category'], \PDO::PARAM_INT);
        $stm->bindParam(':length', $length, \PDO::PARAM_STR);
        $stm->bindParam(':link', $params['link'], \PDO::PARAM_STR);
        $stm->bindParam(':created', $created, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateStartedDate($id)
    {
        $now = time();
        $status = 1;

        $sql = 'UPDATE videos 
                SET status = :status, started = :started 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':started', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateDoneDate($id)
    {
        $now = time();
        $status = 2;

        $sql = 'UPDATE videos 
                SET status = :status, done = :done 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':done', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

}