<?php

namespace App\model;

use App\controller\HighlightController;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class HighlightModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;
    private $tagModel;
    private $parseDown;
    public const DELETED = 1;
    public const NOT_DELETED = 0;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
        $this->tagModel = new TagModel($container);
        $this->parseDown = new \Parsedown();
    }

    public function getHighlights($tag = null, $limit = null)
    {
        $limit = $limit ? $limit : 500;
        $list = [];

        $sql = 'SELECT h.id, h.highlight, h.author, h.source, h.created
                FROM highlights h';

        if ($tag) {
            $sql .= ' LEFT JOIN tag_relationships tr ON h.id = tr.source_id
                LEFT JOIN tags t ON tr.tag_id = t.id';
        }

        $sql .= ' WHERE h.is_deleted = 0 AND h.user_id = :user_id';

        if ($tag) {
            $sql .= ' AND t.tag = :tag AND tr.type = 1';
        }

        $sql .= ' ORDER BY h.id DESC LIMIT :limit';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':limit', $limit, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if ($tag) {
            $stm->bindParam(':tag', $tag, \PDO::PARAM_STR);
        }

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['highlight'] = $this->convertMarkdownToHTML($row['highlight']);
            $row['created_at_formatted'] = date('Y-m-d H:i:s', $row['created']);
            $tags = $this->tagModel->getTagsBySourceId($row['id'], HighlightController::SOURCE_TYPE);

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

        $sql = 'SELECT h.id, h.highlight, h.author, h.source, h.page, h.location, b.bookmark AS link, h.link AS linkID, h.file_name, h.type, h.is_secret, h.created, h.updated
                FROM highlights h
                LEFT JOIN bookmarks b ON h.link = b.id
                WHERE h.id = :highlightID AND h.user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlightID', $id, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['tags'] = $this->tagModel->getTagsBySourceId($row['id'], HighlightController::SOURCE_TYPE);
            $row['is_secret'] = $row['is_secret'] ? true : false;
            $row['highlight'] = html_entity_decode($row['highlight']);

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
                WHERE sh.highlight_id = :highlightID AND h.user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlightID', $highlightID, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['highlight'] = $this->convertMarkdownToHTML($row['highlight']);
            $tags = $this->tagModel->getTagsBySourceId($row['id'], HighlightController::SOURCE_TYPE);

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

        $sql = 'INSERT INTO highlights (highlight, author, source, page, file_name, created, user_id)
                VALUES(:highlight, :author, :source, :page, :file_name, :created, :user_id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight', $rawHighlight, \PDO::PARAM_STR);
        $stm->bindParam(':author', $params['author'], \PDO::PARAM_STR);
        $stm->bindParam(':source', $params['source'], \PDO::PARAM_STR);
        $stm->bindParam(':page', $params['page'], \PDO::PARAM_INT);
        $stm->bindParam(':file_name', $params['filename'], \PDO::PARAM_STR);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function addChangeLog($highlightId, $highlight)
    {
        $now = time();

        $sql = 'INSERT INTO highlight_versions (highlight_id, old_highlight, created_at, user_id)
                VALUES(:highlight_id, :old_highlight, :created_at, :user_id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':old_highlight', $highlight, \PDO::PARAM_STR);
        $stm->bindParam(':highlight_id', $highlightId, \PDO::PARAM_INT);
        $stm->bindParam(':created_at', $now, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function update($highlightID, $params)
    {
        $update = time();
        $highlight = trim($params['highlight']);

        $params['author'] = $params['author'] ? trim($params['author']) : 'trackr';
        $params['source'] = $params['source'] ? trim($params['source']) : 'trackr';
        $params['page'] = $params['page'] ? trim($params['page']) : null;
        $params['location'] = $params['location'] ? trim($params['location']) : null;


        $sql = 'UPDATE highlights 
                SET highlight = :highlight, author = :author, source = :source, page = :page, location = :location, file_name = :file_name, is_secret = :is_secret, updated = :updated
                WHERE id = :id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':id', $highlightID, \PDO::PARAM_INT);
        $stm->bindParam(':highlight', $highlight, \PDO::PARAM_STR);
        $stm->bindParam(':author', $params['author'], \PDO::PARAM_STR);
        $stm->bindParam(':source', $params['source'], \PDO::PARAM_STR);
        $stm->bindParam(':page', $params['page'], \PDO::PARAM_INT);
        $stm->bindParam(':location', $params['location'], \PDO::PARAM_INT);
        $stm->bindParam(':file_name', $params['filename'], \PDO::PARAM_STR);
        $stm->bindParam(':is_secret', $params['is_secret'], \PDO::PARAM_INT);
        $stm->bindParam(':updated', $update, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

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
                FROM highlights WHERE is_deleted = 0 AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

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
                WHERE id = (SELECT min(id) FROM highlights WHERE id > :id AND user_id = :user_id) AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

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
                WHERE id = (SELECT max(id) FROM highlights WHERE id < :id AND user_id = :user_id) AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

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
        $deletedAt = time();

        $sql = 'UPDATE highlights SET is_deleted = 1, deleted_at = :deleted_at
                WHERE id = :highlight_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight_id', $highlightID, \PDO::PARAM_INT);
        $stm->bindParam(':deleted_at', $deletedAt, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function deleteHighlightTagsByHighlightID($highlightID)
    {
        $sql = 'DELETE FROM tag_relationships
                WHERE source_id = :source_id AND type = 1';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':source_id', $highlightID, \PDO::PARAM_INT);

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

    public function searchHighlight($searchParam)
    {
        $searchParam = "%$searchParam%";
        $list = [];

        $sql = 'SELECT h.id, h.highlight, h.author, h.source, h.created
                FROM highlights h
                WHERE h.is_deleted = 0 AND h.highlight LIKE :searchParam AND h.user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':searchParam', $searchParam, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['highlight'] = $this->convertMarkdownToHTML($row['highlight']);
            $row['created_at_formatted'] = date('Y-m-d H:i:s', $row['created']);
            $tags = $this->tagModel->getTagsBySourceId($row['id'], HighlightController::SOURCE_TYPE);

            if ($tags) {
                $row['tags'] = $tags;
            }

            $list[] = $row;
        }

        return $list;
    }

    public function convertMarkdownToHTML($str)
    {
        $str = str_replace("\n", "   \n", $str);
        $this->parseDown->setSafeMode(true);
        return $this->parseDown->text($str);
    }
}
