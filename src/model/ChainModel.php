<?php

namespace App\model;

use App\enum\ChainTypes;
use App\util\UID;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class ChainModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function getChains($status = 0)
    {
        $chains = [];

        $sql = 'SELECT id           AS chainId,
                       uid          AS chainUid,
                       name         AS chainName,
                       type         AS chainType,
                       constant     AS chainConstantType,
                       show_in_logs AS chainShowInLogs,
                       created_at   AS chainCreatedAt,
                       user_id      AS userId
                FROM chains
                WHERE user_id = :user_id AND status = :status';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row = $this->processChainRecord($row);
            $chains[] = $row;
        }

        return $chains;
    }

    public function getChainsByShowInLogs($showInLogs)
    {
        $chains = [];

        $sql = 'SELECT id           AS chainId,
                       uid          AS chainUid,
                       name         AS chainName,
                       type         AS chainType,
                       constant     AS chainConstantType,
                       show_in_logs AS chainShowInLogs,
                       created_at   AS chainCreatedAt,
                       user_id      AS userId
                FROM chains
                WHERE user_id = :user_id AND show_in_logs = :show_in_logs';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':show_in_logs', $showInLogs, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $chains[] = $this->processChainRecord($row);
        }

        return $chains;
    }
    public function getChainByUid($chainUid)
    {
        $chain = [];

        $sql = 'SELECT id           AS chainId,
                       uid          AS chainUid,
                       name         AS chainName,
                       type         AS chainType,
                       show_in_logs AS chainShowInLogs,
                       created_at   AS chainCreatedAt,
                       user_id      AS userId
                FROM chains
                WHERE uid = :chain_uid
                  AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':chain_uid', $chainUid, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $chain = $this->processChainRecord($row);
        }

        return $chain;
    }

    public function getChainsWithLinksByLinkDate($linkDate = null)
    {
        $linkDate = $linkDate ?? date('Y-m-d');
        $chains = [];

        $sql = 'SELECT c.id          AS chainId,
                       c.uid         AS chainUid,
                       c.name        AS chainName,
                       c.type        AS chainType,
                       c.created_at  AS chainCreatedAt,
                       c.user_id     AS userId,
                       cl.id         AS linkId,
                       cl.value      AS linkValue,
                       cl.link_date  AS linkDate,
                       cl.created_at AS linkCreatedAt,
                       cl.updated_at AS linkUpdatedAt
                FROM chains c
                         LEFT JOIN chain_links cl on c.id = cl.chain_id
                WHERE c.user_id = :user_id AND cl.link_date = :link_date';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':link_date', $linkDate, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $chains[] = $this->processJoinedChainLinkRecord($row);
        }

        return $chains;
    }

    public function processChainRecord($chain)
    {
        $chain['chainShowInLogsInputUid'] = UID::generate();
        $chain['chainConstantInputUid'] = UID::generate();
        $chain['chainShowInLogsInputChecked'] = $chain['chainShowInLogs'] ? 'checked' : '';
        $chain['chainConstantInputChecked'] = $chain['chainConstantType'] ? 'checked' : '';
        $typeName = strtolower(ChainTypes::from($chain['chainType'])->name);
        $chain[$typeName] = true;
        $chain['chainTypeName'] = $typeName;
        $chain['chainConstantTypeName'] = $chain['chainConstantType'] ? 'constant' : 'casual';
        $chain['chainCreatedAt'] = date('Y-m-d H:i:s', $chain['chainCreatedAt']);

        return $chain;
    }

    public function processJoinedChainLinkRecord($mix)
    {
        $typeName = strtolower(ChainTypes::from($mix['chainType'])->name);
        $mix[$typeName] = true;
        $mix['chainTypeName'] = $typeName;
        $mix['chainCreatedAt'] = date('Y-m-d H:i:s', $mix['chainCreatedAt']);
        $mix['linkCreatedAt'] = date('Y-m-d H:i:s', $mix['linkCreatedAt']);
        $mix['linkUpdatedAt'] = date('Y-m-d H:i:s', $mix['linkUpdatedAt']);

        return $mix;
    }

    public function getLinksByChainId($chainId)
    {
        $links = [];

        $sql = 'SELECT id         AS linkId,
                       chain_id   AS chainId,
                       value      AS linkValue,
                       link_date  AS linkDate,
                       created_at AS linkCreatedAt,
                       updated_at AS linkUpdatedAt,
                       user_id    AS userId
                FROM chain_links
                WHERE chain_id = :chain_id
                  AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':chain_id', $chainId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $links[] = $this->processLinkRecord($row);
        }

        return $links;
    }

    public function getLinkByChainIdAndDate($chainId, $date)
    {
        $link = [];

        $sql = "SELECT id         AS linkId,
                       chain_id   AS chainId,
                       value      AS linkValue,
                       link_date  AS linkDate,
                       note      AS linkNote,
                       created_at AS linkCreatedAt,
                       updated_at AS linkUpdatedAt,
                       user_id    AS userId
                FROM chain_links
                WHERE chain_id = :chain_id
                  AND link_date = :link_date
                  AND user_id = :user_id";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':chain_id', $chainId, \PDO::PARAM_INT);
        $stm->bindParam(':link_date', $date, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $link = $this->processLinkRecord($row);
        }

        return $link;
    }

    public function getLinksByChainIdAndDate($chainId, $date = false, $fetchAfterGivenDate = false, $limit = 0)
    {
        $operator = $fetchAfterGivenDate ? '>=' : '=';
        $links = [];

        $sql = "SELECT id         AS linkId,
                       chain_id   AS chainId,
                       value      AS linkValue,
                       link_date  AS linkDate,
                       created_at AS linkCreatedAt,
                       updated_at AS linkUpdatedAt,
                       user_id    AS userId
                FROM chain_links
                WHERE chain_id = :chain_id
                  AND user_id = :user_id";

        if ($date) {
            $sql .= " AND link_date $operator :link_date";
        }

        $sql .= ' ORDER BY id DESC';

        if ($limit) {
            $sql .= " LIMIT $limit";
        }

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':chain_id', $chainId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if  ($date) {
            $stm->bindParam(':link_date', $date, \PDO::PARAM_STR);
        }

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $links[] = $this->processLinkRecord($row);
        }

        return $links;
    }

    public function processLinkRecord($link)
    {
        if ($link['linkValue']) {
            $link['linkValueShowInLogsValue'] = '- [x]';
        } else {
            $link['linkValueShowInLogsValue'] = '- [ ]';
        }

        $link['linkCreatedAt'] = date('Y-m-d H:i:s', $link['linkCreatedAt']);
        $link['linkUpdatedAt'] = date('Y-m-d H:i:s', $link['linkUpdatedAt']);

        return $link;
    }

    public function start($chainName, $chainType, $chainConstantType = 0)
    {
        $now = time();
        $uid = UID::generate();

        $sql = 'INSERT INTO chains (uid, name, type, constant, created_at, user_id)
                VALUES(:uid, :name, :type, :constant, :created_at, :user_id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $uid, \PDO::PARAM_STR);
        $stm->bindParam(':name', $chainName, \PDO::PARAM_STR);
        $stm->bindParam(':type', $chainType, \PDO::PARAM_INT);
        $stm->bindParam(':constant', $chainConstantType, \PDO::PARAM_INT);
        $stm->bindParam(':created_at', $now, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function addLink($chainId, $linkDate, $value = 0, $note = null)
    {
        $now = time();

        $sql = 'INSERT INTO chain_links (chain_id, value, link_date, note, created_at, updated_at, user_id)
                VALUES(:chain_id, :value, :link_date, :note, :created_at, :updated_at, :user_id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':chain_id', $chainId, \PDO::PARAM_INT);
        $stm->bindParam(':value', $value, \PDO::PARAM_STR);
        $stm->bindParam(':note', $note, \PDO::PARAM_STR);
        $stm->bindParam(':link_date', $linkDate, \PDO::PARAM_STR);
        $stm->bindParam(':created_at', $now, \PDO::PARAM_INT);
        $stm->bindParam(':updated_at', $now, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function updateLink($linkId, $chainId, $value, $note)
    {
        $now = time();

        $sql = 'UPDATE chain_links SET value = :value, note = :note, updated_at = :updated_at
                WHERE id = :link_id AND chain_id = :chain_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':link_id', $linkId, \PDO::PARAM_INT);
        $stm->bindParam(':chain_id', $chainId, \PDO::PARAM_INT);
        $stm->bindParam(':updated_at', $now, \PDO::PARAM_INT);
        $stm->bindParam(':value', $value, \PDO::PARAM_STR);
        $stm->bindParam(':note', $note, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateChainShowInLogs($chainId, $showInLogs)
    {
        $sql = 'UPDATE chains SET show_in_logs = :show_in_logs
                WHERE id = :chain_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':chain_id', $chainId, \PDO::PARAM_INT);
        $stm->bindParam(':show_in_logs', $showInLogs, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateConstant($chainId, $constant)
    {
        $sql = 'UPDATE chains SET constant = :constant
                WHERE id = :chain_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':chain_id', $chainId, \PDO::PARAM_INT);
        $stm->bindParam(':constant', $constant, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getChainGraphicData($chain)
    {
        $result = [];
        $dates = [];
        $tmpData = [];
        $preparedData = [];

        $links = $this->getLinksByChainIdAndDate($chain['chainId'], false, false, 60);

        foreach ($links as $link) {
            $tmpData[] = $link['linkValue'];
            $dates[] = $link['linkDate'];
        }

        $preparedData['name'] = $chain['chainName'];
        $preparedData['data'] = $tmpData;
        $result['trackings'][] = $preparedData;
        $result['dates'] = $dates;

        return $result;
    }

}