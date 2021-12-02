<?php

namespace App\model;

use Psr\Container\ContainerInterface;
use App\exception\CustomException;

class TagModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function updateHighlightTags($tags, $highlightID) {
        
        if (strpos($tags, ',') !== false) {
            $tags = explode(',', $tags);

            foreach ($tags as $tag) {
                $this->insertTagByChecking($highlightID, $tag);
            }

        } else {
            $this->insertTagByChecking($highlightID, $tags);
        }

    }

    public function insertTagByChecking($highlightId, $tag)
    {
        $tag = trim($tag);

        if ($tag) {
            $tagExist = $this->getTagByTag($tag);

            if (!$tagExist) {
                $tagId = $this->createTag($tag);
                $this->createHighlightTagRecord($highlightId, $tagId);
            } else {
                $this->createHighlightTagRecord($highlightId, $tagExist['id']);
            }
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
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function createHighlightTagRecord($highlightId, $tagId)
    {
        $now = time();

        $sql = 'INSERT INTO highlight_tags (highlight_id, tag_id, created)
                VALUES(:highlight_id, :tag_id, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight_id', $highlightId, \PDO::PARAM_INT);
        $stm->bindParam(':tag_id', $tagId, \PDO::PARAM_INT);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function deleteTagsByHighlightID($highlightId)
    {
        $sql = 'DELETE FROM highlight_tags
                WHERE highlight_id = :highlight_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight_id', $highlightId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getHighlightTagsAsHTML($tag = null)
    {
        $sql = 'SELECT DISTINCT t.tag, t.id
                FROM highlight_tags ht
                INNER JOIN tags t ON ht.tag_id = t.id';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $row['badge'] = 'info';
            $row['href'] = $row['tag'];

            if ($tag !== null && $tag == $row['tag']) {
                $row['href'] = '';
                $row['badge'] = 'primary';
            }

            $list[] = $row;
        }

        return $list;
    }

    public function getHighlightTagsAsHTMLByHighlightId($highlightId)
    {
        $sql = 'SELECT t.tag
                FROM highlight_tags ht
                INNER JOIN tags t ON ht.tag_id = t.id
                WHERE ht.highlight_id = :highlight_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight_id', $highlightId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['tag'] = '<span class="badge badge-info">' . $row['tag'] . '</span>';
            $list[] = $row;
        }

        return $list;
    }

    public function getHighlightTagsAsStringByHighlightId($highlightId)
    {
        $sql = 'SELECT t.tag
                FROM highlight_tags ht
                INNER JOIN tags t ON ht.tag_id = t.id
                WHERE ht.highlight_id = :highlight_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight_id', $highlightId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = '#' . $row['tag'];
        }

        return implode(' ', $list);
    }

    public function getHighlightTagsByHighlightId($highlightId)
    {
        $sql = 'SELECT t.id, t.tag
                FROM highlight_tags ht
                INNER JOIN tags t ON ht.tag_id = t.id
                WHERE ht.highlight_id = :highlight_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight_id', $highlightId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = $row;
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
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list = $row;
        }

        return $list;
    }

}