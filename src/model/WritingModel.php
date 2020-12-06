<?php

namespace App\model;

use Psr\Container\ContainerInterface;
use App\exception\CustomException;

class WritingModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function getWritings()
    {
        $list = [];

        $sql = 'SELECT *
                FROM writings
                ORDER BY id DESC';

        $stm = $this->dbConnection->prepare($sql);

        if ($stm->execute()) {

            while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
                $row['text'] = str_replace("\n", '<br>', $row['text']);
                $row['created'] = date('Y-m-d H:i:s', $row['created']);

                if ($row['date'] !== null && $row['date'] !== '') {
                    $row['dateExist'] = true;
                }

                $list[] = $row;
            }

            return $list;
        }

        throw CustomException::dbError(503, json_encode($stm->errorInfo()));
    }

    public function create($writing)
    {
        $now = time();

        $sql = 'INSERT INTO writings (text, created, updated)
                VALUES(:text, :created, :updated)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':text', $writing, \PDO::PARAM_STR);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);
        $stm->bindParam(':updated', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

}