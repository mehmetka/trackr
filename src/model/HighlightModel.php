<?php

namespace App\model;

use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

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

    public function getHighlights($limit = null)
    {
        $list = [];

        $sql = 'SELECT * 
                FROM highlights ORDER BY id DESC ';

        if ($limit) {
            $sql .= 'LIMIT :limit';
        }

        $stm = $this->dbConnection->prepare($sql);

        if ($limit) {
            $stm->bindParam(':limit', $limit, \PDO::PARAM_INT);
        }

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['highlight'] = str_replace("\n", '<br>', $row['highlight']);
            $row['html'] = $row['html'] ? $row['html'] : $row['highlight'];
            $tags = $this->tagModel->getHighlightTagsAsHTMLByHighlightId($row['id']);

            if ($tags) {
                $row['tags'] = $tags;
            }

            $list[] = $row;
        }

        return $list;
    }

    public function getHighlightsByID($id)
    {
        $list = [];

        $sql = 'SELECT h.id, h.highlight, h.html, h.author, h.source, h.page, h.location, b.bookmark AS link, h.link AS linkID, h.type, h.created, h.updated
                FROM highlights h
                LEFT JOIN bookmarks b ON h.link = b.id
                WHERE h.id = :highlightID';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlightID', $id, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $row['highlight'] = str_replace("\n", '<br>', $row['highlight']);
            $row['html'] = $row['html'] ? $row['html'] : $row['highlight'];
            $row['tags'] = $this->tagModel->getHighlightTagsAsStringByHighlightId($row['id']);

            $list = $row;
        }

        $_SESSION['update']['highlight'] = $list;
        return $list;
    }

    public function getSubHighlightsByHighlightID($highlightID)
    {
        $list = [];

        $sql = 'SELECT h.id, h.highlight, h.author, h.source, h.page, h.location, h.link, h.type, h.created, h.updated
                FROM highlights h
                INNER JOIN sub_highlights sh ON h.id = sh.sub_highlight_id
                WHERE sh.highlight_id = :highlightID';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlightID', $highlightID, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $row['highlight'] = str_replace("\n", '<br>', $row['highlight']);
            $row['html'] = $row['html'] ? $row['html'] : $row['highlight'];
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
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['highlight'] = str_replace("\n", '<br>', $row['highlight']);
            $row['html'] = $row['html'] ? $row['html'] : $row['highlight'];
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
        $rawHighlight = trim($params['highlight']);

        $params['author'] = $params['author'] ? trim($params['author']) : 'trackr';
        $params['source'] = $params['source'] ? trim($params['source']) : 'trackr';
        $params['page'] = $params['page'] ? trim($params['page']) : null;
        $params['location'] = $params['location'] ? trim($params['location']) : null;

        $html = str_replace(' ', '&nbsp;', $rawHighlight);
        $html = str_replace("\n", '<br>', $html);
        $highlight = strip_tags($rawHighlight);

        $sql = 'INSERT INTO highlights (highlight, html, author, source, page, link, created)
                VALUES(:highlight, :html, :author, :source, :page, :link, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight', $highlight, \PDO::PARAM_STR);
        $stm->bindParam(':html', $html, \PDO::PARAM_STR);
        $stm->bindParam(':author', $params['author'], \PDO::PARAM_STR);
        $stm->bindParam(':source', $params['source'], \PDO::PARAM_STR);
        $stm->bindParam(':page', $params['page'], \PDO::PARAM_INT);
        $stm->bindParam(':link', $params['link'], \PDO::PARAM_INT);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function update($highlightID, $params)
    {
        $update = time();

        $params['author'] = $params['author'] ? trim($params['author']) : 'trackr';
        $params['source'] = $params['source'] ? trim($params['source']) : 'trackr';
        $params['page'] = $params['page'] ? trim($params['page']) : null;
        $params['location'] = $params['location'] ? trim($params['location']) : null;

        $html = str_replace(' ', '&nbsp;', trim($params['highlight']));
        $highlight = str_replace('&nbsp;', ' ', trim($params['highlight']));
        $highlight = strip_tags($highlight);

        $sql = 'UPDATE highlights 
                SET highlight = :highlight, html = :html, author = :author, source = :source, page = :page, location = :location, link = :link, updated = :updated
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':id', $highlightID, \PDO::PARAM_INT);
        $stm->bindParam(':highlight', $highlight, \PDO::PARAM_STR);
        $stm->bindParam(':html', $html, \PDO::PARAM_STR);
        $stm->bindParam(':author', $params['author'], \PDO::PARAM_STR);
        $stm->bindParam(':source', $params['source'], \PDO::PARAM_STR);
        $stm->bindParam(':page', $params['page'], \PDO::PARAM_INT);
        $stm->bindParam(':location', $params['location'], \PDO::PARAM_INT);
        $stm->bindParam(':link', $params['link'], \PDO::PARAM_INT);
        $stm->bindParam(':updated', $update, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function createSubHighlight($highlightID, $subHighlightID)
    {
        $now = time();

        $sql = 'INSERT INTO sub_highlights (highlight_id, sub_highlight_id, created)
                VALUES(:highlight_id, :sub_highlight_id, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight_id', $highlightID, \PDO::PARAM_INT);
        $stm->bindParam(':sub_highlight_id', $subHighlightID, \PDO::PARAM_INT);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function getHighlightsCount()
    {
        $count = 0;

        $sql = 'SELECT COUNT(*) AS count
                FROM highlights';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $count = $row['count'];
        }

        return $count;
    }

    public function getNextHighlight($id)
    {
        $next = $id;

        $sql = 'SELECT * FROM highlights 
                WHERE id = (SELECT min(id) FROM highlights WHERE id > :id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $next = $row['id'];
        }

        return $next;
    }

    public function getPreviousHighlight($id)
    {
        $previous = $id;

        $sql = 'SELECT * FROM highlights 
                WHERE id = (SELECT max(id) FROM highlights WHERE id < :id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $previous = $row['id'];
        }

        return $previous;
    }

    public function deleteHighlight($highlightID)
    {
        $sql = 'DELETE FROM highlights
                WHERE id = :highlight_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight_id', $highlightID, \PDO::PARAM_INT);
        
        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

}