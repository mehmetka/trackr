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
    private $parseDown;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
        $this->tagModel = new TagModel($container);
        $this->parseDown = new \Parsedown();
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
            $row['highlight'] = $this->convertMarkdownToHTML($row['highlight']);
            $row['created_at_formatted'] = date('Y-m-d H:i:s', $row['created']);
            $tags = $this->tagModel->getHighlightTagsByHighlightId($row['id']);

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

        $sql = 'SELECT h.id, h.highlight, h.author, h.source, h.created
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
            $row['highlight'] = $this->convertMarkdownToHTML($row['highlight']);
            $row['created_at_formatted'] = date('Y-m-d H:i:s', $row['created']);
            $tags = $this->tagModel->getHighlightTagsByHighlightId($row['id']);

            if ($tags) {
                $row['tags'] = $tags;
            }

            $list[] = $row;
        }

        return $list;
    }

    public function getHighlightByID($id)
    {
        $list = [];

        $sql = 'SELECT h.id, h.highlight, h.author, h.source, h.page, h.location, b.bookmark AS link, h.link AS linkID, h.type, h.is_secret, h.created, h.updated
                FROM highlights h
                LEFT JOIN bookmarks b ON h.link = b.id
                WHERE h.id = :highlightID';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlightID', $id, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {;
            $row['tags'] = $this->tagModel->getHighlightTagsByHighlightId($row['id']);
            $row['is_secret'] = $row['is_secret'] ? true : false;

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
            $row['highlight'] = $this->convertMarkdownToHTML($row['highlight']);
            $tags = $this->tagModel->getHighlightTagsByHighlightId($row['id']);

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
        $rawHighlight = strip_tags(trim($params['highlight']));

        $params['author'] = $params['author'] ? trim($params['author']) : 'trackr';
        $params['source'] = $params['source'] ? trim($params['source']) : 'trackr';
        $params['page'] = $params['page'] ? trim($params['page']) : null;
        $params['location'] = $params['location'] ? trim($params['location']) : null;
        
        $sql = 'INSERT INTO highlights (highlight, author, source, page, link, created)
                VALUES(:highlight, :author, :source, :page, :link, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight', $rawHighlight, \PDO::PARAM_STR);
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
        $rawHighlight = trim($params['highlight']);

        $params['author'] = $params['author'] ? trim($params['author']) : 'trackr';
        $params['source'] = $params['source'] ? trim($params['source']) : 'trackr';
        $params['page'] = $params['page'] ? trim($params['page']) : null;
        $params['location'] = $params['location'] ? trim($params['location']) : null;

        $highlight = strip_tags($rawHighlight);

        $sql = 'UPDATE highlights 
                SET highlight = :highlight, author = :author, source = :source, page = :page, location = :location, link = :link, is_secret = :is_secret, updated = :updated
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':id', $highlightID, \PDO::PARAM_INT);
        $stm->bindParam(':highlight', $highlight, \PDO::PARAM_STR);
        $stm->bindParam(':author', $params['author'], \PDO::PARAM_STR);
        $stm->bindParam(':source', $params['source'], \PDO::PARAM_STR);
        $stm->bindParam(':page', $params['page'], \PDO::PARAM_INT);
        $stm->bindParam(':location', $params['location'], \PDO::PARAM_INT);
        $stm->bindParam(':link', $params['link'], \PDO::PARAM_INT);
        $stm->bindParam(':is_secret', $params['is_secret'], \PDO::PARAM_INT);
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

    public function deleteHighlightTagsByHighlightID($highlightID)
    {
        $sql = 'DELETE FROM highlight_tags
                WHERE highlight_id = :highlight_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight_id', $highlightID, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function deleteSubHighlightByHighlightID($highlightID)
    {
        $sql = 'DELETE FROM sub_highlights 
                WHERE highlight_id = :highlight_id OR sub_highlight_id = :highlight_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight_id', $highlightID, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function searchHighlight($highlight)
    {
        $result = [];
        $highlight = "%$highlight%";

        $sql = 'SELECT * 
                FROM highlights 
                WHERE highlight LIKE :highlight';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight', $highlight, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }

        return $result;
    }

    public function convertMarkdownToHTML($str)
    {
        $str = str_replace("\n", "   \n", $str);
        $this->parseDown->setSafeMode(true);
        return $this->parseDown->text($str);
    }
}
