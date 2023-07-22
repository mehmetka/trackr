<?php

namespace App\model;

use App\enum\Sources;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class TagModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function updateSourceTags($tags, $sourceId, $sourceType)
    {

        if (strpos($tags, ',') !== false) {
            $tags = explode(',', $tags);

            foreach ($tags as $tag) {
                $this->insertTagByChecking($sourceId, $tag, $sourceType);
            }

        } else {
            $this->insertTagByChecking($sourceId, $tags, $sourceType);
        }

    }

    public function insertTagByChecking($sourceId, $tag, $sourceType)
    {
        $tag = strtolower(strip_tags(trim($tag)));

        if ($tag) {
            $tagExist = $this->getTagByTag($tag);
            $tagId = $tagExist['id'];

            if (!$tagExist) {
                $tagId = $this->createTag($tag);
            }

            $this->createTagRelationship($sourceId, $tagId, $sourceType);
        }
    }

    public function createTag($tag)
    {
        $now = time();

        $sql = 'INSERT INTO tags (tag, created)
                VALUES(:tag, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':tag', $tag, \PDO::PARAM_STR);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function createTagRelationship($sourceId, $tagId, $sourceType)
    {
        $now = time();

        $sql = 'INSERT INTO tag_relationships (source_id, tag_id, type, created, user_id)
                VALUES(:source_id, :tag_id, :type, :created, :user_id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':source_id', $sourceId, \PDO::PARAM_INT);
        $stm->bindParam(':type',$sourceType, \PDO::PARAM_INT);
        $stm->bindParam(':tag_id', $tagId, \PDO::PARAM_INT);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function deleteTagsBySourceId($sourceId, $type)
    {
        $sql = 'DELETE FROM tag_relationships
                WHERE source_id = :source_id AND type = :type AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':source_id', $sourceId, \PDO::PARAM_INT);
        $stm->bindParam(':type', $type, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getSourceTagsByType($type, $tag = null)
    {
        $sql = 'SELECT t.tag,
                       t.id,
                       count(*) AS tag_count
                FROM tag_relationships tr
                         INNER JOIN tags t ON tr.tag_id = t.id
                WHERE tr.type = :sourceType
                  AND tr.is_deleted = 0
                  AND tr.user_id = :user_id
                GROUP BY t.tag
                ORDER BY t.tag';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':sourceType', $type, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $row['badge'] = 'secondary';
            $row['href'] = $row['tag'];

            if ($tag !== null && $tag == $row['tag']) {
                $row['href'] = '';
                $row['badge'] = 'primary';
            }

            $list[] = $row;
        }

        return $list;
    }

    public function getTagsBySourceId($sourceId, $sourceType)
    {
        $tags = [];
        $hashtags = [];
        $list = [];

        $sql = "SELECT t.id, t.tag, CONCAT('#', t.tag) AS hashtag, CONCAT('<span class=\"badge badge-info\">', t.tag, '</span>') AS html
                FROM tag_relationships tr
                INNER JOIN tags t ON tr.tag_id = t.id
                WHERE tr.source_id = :source_id AND tr.type = :source_type AND tr.user_id = :user_id";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':source_id', $sourceId, \PDO::PARAM_INT);
        $stm->bindParam(':source_type', $sourceType, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $tags[] = $row['tag'];
            $hashtags[] = $row['hashtag'];
            $list['tags'][] = $row;
        }

        if ($tags) {
            $list['imploded_blank'] = implode(' ', $tags);
            $list['imploded_comma'] = implode(', ', $tags);
            $list['imploded_hashtag_blank'] = implode(' ', $hashtags);
            $list['imploded_hashtag_comma'] = implode(', ', $hashtags);
        }

        return $list;
    }

    public function getTagByTag($tag)
    {
        $sql = 'SELECT id, tag
                FROM tags
                WHERE tag = :tag';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':tag', $tag, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list = $row;
        }

        return $list;
    }

    public function updateIsDeletedStatusBySourceId($type, $sourceId, $status)
    {
        if($status){
            $now = time();
        } else {
            $now = null;
        }

        $sql = 'UPDATE tag_relationships 
                SET is_deleted = :status, deleted_at = :deleted_at 
                WHERE source_id = :source_id AND type = :type AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':deleted_at', $now, \PDO::PARAM_INT);
        $stm->bindParam(':source_id', $sourceId, \PDO::PARAM_INT);
        $stm->bindParam(':type', $type, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

}