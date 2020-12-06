<?php

namespace App\model;

use App\util\Util;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;

class TrackingModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function getWorkTrackings()
    {
        $list = [];

        $sql = 'SELECT id, work AS amount, description, date 
                FROM work_trackings';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['description'] = str_replace("\n", '<br>', $row['description']);
            $list[] = $row;
        }

        return $list;
    }

    public function getStartOfWorkings()
    {
        $start = null;

        $sql = 'SELECT date 
                FROM work_trackings 
                ORDER BY id ASC
                LIMIT 1';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $start = $row['date'];
        }

        return $start;
    }

    public function average()
    {
        $from = $this->getStartOfWorkings();
        $to = date('Y-m-d', time());
        $dateDiff = Util::getDayDifference($from, $to);
        $total = 0;
        // TODO day off - status=1 olan kayit sayisini toplam gun sayisindan cikar.

        $sql = 'SELECT SUM(work) AS total
                FROM work_trackings';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $total = $row['total'];
        }

        return $total / $dateDiff;
    }

    public function getWorkTrackingByDate($date)
    {
        $tracking = [];

        $sql = 'SELECT id, work AS amount, description, date 
                FROM work_trackings
                WHERE date = :date';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':date', $date, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $tracking = $row;
        }

        return $tracking;
    }

    public function create($amount, $description)
    {
        $date = date('Y-m-d');

        $sql = 'INSERT INTO work_trackings (work, description, date)
                VALUES(:work, :description, :date)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':work', $amount, \PDO::PARAM_INT);
        $stm->bindParam(':description', $description, \PDO::PARAM_STR);
        $stm->bindParam(':date', $date, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function update($amount, $description, $date)
    {
        $sql = 'UPDATE work_trackings 
                SET work = :work, description = :description
                WHERE date = :date';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':work', $amount, \PDO::PARAM_INT);
        $stm->bindParam(':description', $description, \PDO::PARAM_STR);
        $stm->bindParam(':date', $date, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

}