<?php

namespace App\model;

use App\util\TimeUtil;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class DateTrackingModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function create($name, $date)
    {
        $created = time();

        $sql = 'INSERT INTO date_trackings (name, date, created, user_id)
                VALUES(:name, :date, :created, :user_id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':name', $name, \PDO::PARAM_STR);
        $stm->bindParam(':date', $date, \PDO::PARAM_STR);
        $stm->bindParam(':created', $created, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getDateTrackings()
    {
        $dateFormat = 'd-m-Y';
        $today = date($dateFormat);
        $list = [];

        $sql = 'SELECT id, name, date, created
                FROM date_trackings
                WHERE user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $row['diff'] = TimeUtil::calculateAge($today, $row['date']);
            $row['detailedDiff'] = TimeUtil::calculateAgeV2($today, $row['date']);

            $result = TimeUtil::calculateTimeRemaining($row['date']);
            $row['minutes'] = $result['minutes'];
            $row['hours'] = $result['hours'];
            $row['days'] = $result['days'];
            $row['weeks'] = $result['weeks'];
            $row['months'] = $result['months'];
            $row['start'] = date($dateFormat, $row['created']);
            $row['info'] = $row['created'] < strtotime($row['date']) ? 'Left' : 'Passed';

            $list[] = $row;
        }

        return $list;
    }

}