<?php

namespace App\model;

use App\util\TimeUtil;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;

class DateTrackingModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function create($name, $start)
    {
        $created = time();

        $sql = 'INSERT INTO date_trackings (name, start, created)
                VALUES(:name, :start, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':name', $name, \PDO::PARAM_STR);
        $stm->bindParam(':start', $start, \PDO::PARAM_STR);
        $stm->bindParam(':created', $created, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getDateTrackings()
    {
        $today = date('m/d/Y');
        $list = [];

        $sql = 'SELECT id, name, start, created
                FROM date_trackings';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['diff'] = TimeUtil::calculateAge($today, $row['start']);
            $row['start'] = date('d/m/Y', strtotime($row['start']));
            $list[] = $row;
        }

        return $list;
    }

}