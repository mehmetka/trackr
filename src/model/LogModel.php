<?php

namespace App\model;

use App\util\Markdown;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class LogModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function getLogs($limit = 30)
    {
        $markdownClient = new Markdown();
        $logs = [];

        $sql = 'SELECT *
                FROM logs
                WHERE user_id = :user_id ORDER BY id DESC LIMIT :limit';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':limit', $limit, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['log'] = $markdownClient->convert($row['log']);
            $row['versionCount'] = $this->getVersionCountByLogId($row['id']);
            $logs[] = $row;
        }

        return $logs;
    }

    public function getLog($date)
    {
        $log = [];

        $sql = 'SELECT *
                FROM logs
                WHERE date = :date AND user_id = :user_id ORDER BY id DESC LIMIT 1';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':date', $date, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $log = $row;
        }

        return $log;
    }

    public function getVersionsByDate($date)
    {
        $versions = [];

        $sql = 'SELECT *
                FROM log_versions lv
                INNER JOIN logs l
                ON lv.log_id = l.id
                WHERE l.user_id = :user_id AND l.date = :date 
                ORDER BY lv.id DESC';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':date', $date, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['created_at'] = date('Y-m-d H:i:s', $row['created_at']);
            $versions[] = $row;
        }

        return $versions;
    }

    public function getVersionCountByLogId($logId)
    {
        $versionCount = 0;

        $sql = 'SELECT count(*) AS versionCount
                FROM log_versions lv
                INNER JOIN logs l
                ON lv.log_id = l.id
                WHERE l.user_id = :user_id AND lv.log_id = :logId
                ORDER BY lv.id DESC';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':logId', $logId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $versionCount = $row['versionCount'];
        }

        return $versionCount;
    }

    public function insert($date, $log)
    {
        $sql = 'INSERT INTO logs (date, log, user_id)
                VALUES (:date, :log, :user_id)';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':date', $date, \PDO::PARAM_STR);
        $stm->bindParam(':log', $log, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function update($date, $log)
    {
        $sql = 'UPDATE logs
                SET log = :log
                WHERE user_id = :user_id AND date = :date';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':date', $date, \PDO::PARAM_STR);
        $stm->bindParam(':log', $log, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function saveOldVersion($logId, $oldLog)
    {
        $now = time();

        $sql = 'INSERT INTO log_versions (log_id, old, created_at) 
                VALUES (:log_id, :old_log, :created_at)';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':log_id', $logId, \PDO::PARAM_INT);
        $stm->bindParam(':old_log', $oldLog, \PDO::PARAM_STR);
        $stm->bindParam(':created_at', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

}