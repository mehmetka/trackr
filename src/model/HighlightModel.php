<?php

namespace App\model;

use Psr\Container\ContainerInterface;
use App\exception\CustomException;

class HighlightModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;
    private $tagModel;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
        $this->tagModel = new TagModel($container);
    }

    public function getHighlights()
    {
        $list = [];

        $sql = 'SELECT * 
                FROM highlights ORDER BY id DESC LIMIT 100';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['highlight'] = str_replace("\n", '<br>', $row['highlight']);
            $tags = $this->tagModel->getHighlightTagsAsHTMLByHighlightId($row['id']);

            if ($tags) {
                $row['tags'] = $tags;
            }

            $list[] = $row;
        }

        return $list;
    }

    public function getHighlightsByTag($tag)
    {
        $list = [];

        $sql = 'SELECT h.id, h.highlight, h.author, h.source
                FROM highlights h
                INNER JOIN highlight_tags ht ON h.id = ht.highlight_id
                INNER JOIN tags t ON ht.tag_id = t.id
                WHERE t.tag = :tag
                ORDER BY h.id DESC
                LIMIT 100';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':tag', $tag, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['highlight'] = str_replace("\n", '<br>', $row['highlight']);
            $tags = $this->tagModel->getHighlightTagsAsHTMLByHighlightId($row['id']);

            if ($tags) {
                $row['tags'] = $tags;
            }

            $list[] = $row;
        }

        return $list;
    }

    public function create($params)
    {
        $now = time();

        $params['author'] = $params['author'] == null ? 'trackr' : $params['author'];
        $params['source'] = $params['source'] == null ? 'trackr' : $params['source'];
        $params['page'] = $params['page'] == null ? null : $params['page'];
        $params['link'] = $params['link'] == null ? null : $params['link'];
        $params['tags'] = $params['tags'] == null ? null : $params['tags'];

        $sql = 'INSERT INTO highlights (highlight, author, source, page, link, created)
                VALUES(:highlight, :author, :source, :page, :link, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight', $params['highlight'], \PDO::PARAM_STR);
        $stm->bindParam(':author', $params['author'], \PDO::PARAM_STR);
        $stm->bindParam(':source', $params['source'], \PDO::PARAM_STR);
        $stm->bindParam(':page', $params['page'], \PDO::PARAM_INT);
        $stm->bindParam(':link', $params['link'], \PDO::PARAM_STR);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

}