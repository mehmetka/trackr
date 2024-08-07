<?php

namespace App\model;

use App\enum\Sources;
use App\util\EncryptionUtil;
use App\util\MarkdownUtil;
use App\util\StringUtil;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class HighlightModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;
    private $tagModel;
    public const DELETED = 1;
    public const NOT_DELETED = 0;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
        $this->tagModel = new TagModel($container);
    }

    public function getHighlights($limit = null)
    {
        $limit = $limit ?? 500;
        $list = [];

        $sql = 'SELECT h.id, h.highlight, h.author, h.source, h.created, h.updated, h.is_encrypted, h.is_secret
                FROM highlights h
                WHERE h.is_deleted = 0 AND h.user_id = :user_id AND h.type=0
                ORDER BY h.updated DESC LIMIT :limit';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':limit', $limit, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = $this->processHighlightRecord($row);
        }

        return $list;
    }

    public function getHighlightsByGivenField($field, $param, $limit = null)
    {
        $limit = $limit ? $limit : 500;
        $list = [];

        $sql = "SELECT h.id, h.highlight, h.author, h.source, h.created, h.updated, h.is_encrypted, h.is_secret
                FROM highlights h
                WHERE h.is_deleted = 0 AND h.user_id = :user_id AND $field = :param AND h.is_deleted = 0
                ORDER BY h.updated DESC LIMIT :limit";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':limit', $limit, \PDO::PARAM_INT);
        $stm->bindParam(':param', $param, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = $this->processHighlightRecord($row);
        }

        return $list;
    }

    public function getHighlightsByTag($tag, $limit = null)
    {
        $limit = $limit ? $limit : 500;
        $list = [];

        $sql = 'SELECT h.id, h.highlight, h.author, h.source, h.created, h.updated, h.is_encrypted, h.is_secret
                FROM highlights h LEFT JOIN tag_relationships tr ON h.id = tr.source_id
                LEFT JOIN tags t ON tr.tag_id = t.id
                WHERE h.is_deleted = 0 AND h.user_id = :user_id AND t.tag = :tag AND tr.type = 1
                ORDER BY h.updated DESC LIMIT :limit';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':limit', $limit, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':tag', $tag, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = $this->processHighlightRecord($row);
        }

        return $list;
    }

    public function processHighlightRecord($highlight)
    {
        if ($highlight['is_encrypted']) {
            $highlight['highlight'] = EncryptionUtil::decrypt($highlight['highlight']);
            if ($highlight['highlight'] === null) {
                $highlight['highlight'] = 'Could not be decrypted, your encryption key might be broken. Do not update this highlight otherwise you might loss your highlight';
            }
        }

        if (!$highlight['author'] && !$highlight['source']) {
            $source = 'Unknown';
        }

        if ($highlight['author'] && !$highlight['source']) {
            $source = $highlight['author'];
        }

        if (!$highlight['author'] && $highlight['source']) {
            $source = $highlight['source'];
        }

        if ($highlight['author'] && $highlight['source']) {
            $source = $highlight['author'] . ' - ' . $highlight['source'];
        }

        $highlight['ultimate_source'] = $source;

        $highlight['highlight'] = MarkdownUtil::convertToHTML($highlight['highlight']);
        $highlight['highlight'] = str_replace('<img src="', '<img class="lazy" data-src="', $highlight['highlight']);
        $decimalHashtags = StringUtil::getDecimalHashtags($highlight['highlight']);

        if ($decimalHashtags) {
            foreach ($decimalHashtags as $id) {
                $highlight['highlight'] = str_replace('#' . $id, "<a href='/highlights?id=$id' class='highlightToolTip' data-toggle='tooltip' data-id='$id' data-placement='top' title=''>#$id</a>",
                    $highlight['highlight']);
            }
        }

//        $alphaNumericHashtags = StringUtil::getAlphaNumericHashtags($highlight['highlight']);
//
//        if ($alphaNumericHashtags) {
//            foreach ($alphaNumericHashtags as $tag) {
//                $highlight['highlight'] = str_replace('#' . $tag, "<a href='/highlights?tag=$tag'>#$tag</a>",
//                    $highlight['highlight']);
//            }
//        }

        $highlight['version_count'] = $this->getVersionsCountById($highlight['id']);
        $highlight['created_at_formatted'] = date('Y-m-d H:i:s', $highlight['created']);
        $highlight['updated_at_formatted'] = date('Y-m-d H:i:s', $highlight['updated']);
        $tags = $this->tagModel->getTagsBySourceId($highlight['id'], Sources::HIGHLIGHT->value);

        if ($tags) {
            $highlight['tags'] = $tags;
        }

        return $highlight;
    }

    public function getHighlightByID($id)
    {
        $list = [];

        $sql = 'SELECT h.id, h.title, h.highlight, h.author, h.source, h.page, h.location, b.bookmark AS link, h.link AS linkID, h.blog_path, h.type, h.is_secret, h.is_encrypted, h.created, h.updated
                FROM highlights h
                LEFT JOIN bookmarks b ON h.link = b.id
                WHERE h.id = :highlightID AND h.user_id = :user_id AND h.is_deleted = 0';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlightID', $id, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['tags'] = $this->tagModel->getTagsBySourceId($row['id'], Sources::HIGHLIGHT->value);
            $row['is_secret'] = (bool)$row['is_secret'];
            $row['is_encrypted'] = (bool)$row['is_encrypted'];

            $row['highlight'] = $row['is_encrypted'] ? EncryptionUtil::decrypt($row['highlight']) : $row['highlight'];

            if ($row['highlight'] === null) {
                $_SESSION['highlights']['not_editable'][$row['id']] = true;
                $row['not_editable'] = true;
                $row['not_deletable'] = true;
                $row['not_editable_highlight_placeholder'] = 'Could not be decrypted, your encryption key might be broken. Do not update this highlight otherwise you might loss your highlight';
            } else {
                unset($_SESSION['highlights']['not_editable'][$row['id']]);
                $row['highlight'] = html_entity_decode($row['highlight']);
            }

            $list = $row;
        }

        return $list;
    }

    public function getSubHighlightsByHighlightID($highlightID)
    {
        $list = [];

        $sql = 'SELECT h.id, h.highlight, h.author, h.source, h.page, h.location, h.link, h.type, h.created, h.updated, h.is_encrypted
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
            if ($row['is_encrypted']) {
                $row['highlight'] = EncryptionUtil::decrypt($row['highlight']);
            }

            $row['highlight'] = MarkdownUtil::convertToHTML($row['highlight']);
            $tags = $this->tagModel->getTagsBySourceId($row['id'], Sources::HIGHLIGHT->value);

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

//        $params['author'] = $params['author'] ? trim($params['author']) : $_SESSION['userInfos']['username'];
//        $params['source'] = $params['source'] ? trim($params['source']) : 'trackr';
        $params['page'] = $params['page'] ? trim($params['page']) : null;
        $params['location'] = $params['location'] ? trim($params['location']) : null;

        $sql = 'INSERT INTO highlights (title, highlight, author, source, page, blog_path, book_id, is_encrypted, is_secret, created, updated, user_id)
                VALUES(:title, :highlight, :author, :source, :page, :blog_path, :book_id, :is_encrypted, :is_secret, :created, :updated, :user_id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':title', $params['title'], \PDO::PARAM_STR);
        $stm->bindParam(':highlight', $params['highlight'], \PDO::PARAM_STR);
        $stm->bindParam(':author', $params['author'], \PDO::PARAM_STR);
        $stm->bindParam(':source', $params['source'], \PDO::PARAM_STR);
        $stm->bindParam(':page', $params['page'], \PDO::PARAM_INT);
        $stm->bindParam(':blog_path', $params['blogPath'], \PDO::PARAM_STR);
        $stm->bindParam(':book_id', $params['book_id'], \PDO::PARAM_INT);
        $stm->bindParam(':is_encrypted', $params['is_encrypted'], \PDO::PARAM_INT);
        $stm->bindParam(':is_secret', $params['is_secret'], \PDO::PARAM_INT);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);
        $stm->bindParam(':updated', $now, \PDO::PARAM_INT);
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

//        $params['author'] = $params['author'] ? trim($params['author']) : $_SESSION['userInfos']['username'];
//        $params['source'] = $params['source'] ? trim($params['source']) : 'trackr';
        $params['page'] = $params['page'] ? trim($params['page']) : null;
        $params['location'] = $params['location'] ? trim($params['location']) : null;


        $sql = 'UPDATE highlights
                SET title = :title, highlight = :highlight, author = :author, source = :source, page = :page, location = :location, blog_path = :blog_path, is_secret = :is_secret, is_encrypted = :is_encrypted, updated = :updated
                WHERE id = :id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':id', $highlightID, \PDO::PARAM_INT);
        $stm->bindParam(':title', $params['title'], \PDO::PARAM_STR);
        $stm->bindParam(':highlight', $params['highlight'], \PDO::PARAM_STR);
        $stm->bindParam(':author', $params['author'], \PDO::PARAM_STR);
        $stm->bindParam(':source', $params['source'], \PDO::PARAM_STR);
        $stm->bindParam(':page', $params['page'], \PDO::PARAM_INT);
        $stm->bindParam(':location', $params['location'], \PDO::PARAM_STR);
        $stm->bindParam(':blog_path', $params['blogPath'], \PDO::PARAM_STR);
        $stm->bindParam(':is_secret', $params['is_secret'], \PDO::PARAM_INT);
        $stm->bindParam(':is_encrypted', $params['is_encrypted'], \PDO::PARAM_INT);
        $stm->bindParam(':updated', $update, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
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

    public function getHighlightsCount($column = null, $value = null)
    {
        $count = 0;
        $specifiedCondition = '';

        if ($column !== null && $value !== null) {
            $specifiedCondition = " $column = '$value' AND ";
        }

        $sql = "SELECT COUNT(*) AS count
                FROM highlights WHERE is_deleted = 0 AND $specifiedCondition user_id = :user_id";

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
                WHERE id = (SELECT min(id) FROM highlights WHERE id > :id AND user_id = :user_id) AND user_id = :user_id AND is_deleted = 0';

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
                WHERE id = (SELECT max(id) FROM highlights WHERE id < :id AND user_id = :user_id) AND user_id = :user_id AND is_deleted = 0';

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
                WHERE h.is_deleted = 0 AND h.is_encrypted = 0 AND h.highlight LIKE :searchParam AND h.user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':searchParam', $searchParam, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = $this->processHighlightRecord($row);
        }

        return $list;
    }

    public function searchHighlightFulltext($searchParam)
    {
        $list = [];
        $searchParam = '"' . $searchParam . '"';

        $sql = "SELECT h.id, h.highlight, h.author, h.source, h.created
                FROM highlights h
                WHERE h.is_deleted = 0
                  AND h.is_encrypted = 0
                  AND h.user_id = :user_id
                  AND MATCH(h.highlight) AGAINST(:searchParam IN BOOLEAN MODE)";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':searchParam', $searchParam, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = $this->processHighlightRecord($row);
        }

        return $list;
    }

    public function getHighlightAuthors()
    {
        $result = [];

        $sql = 'SELECT count(*) AS highlightCount, author FROM highlights
                WHERE user_id = :user_id
                GROUP BY author';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }

        return $result;
    }

    public function getHighlightSources()
    {
        $result = [];

        $sql = 'SELECT count(*) AS highlightCount, source FROM highlights
                WHERE user_id = :user_id
                GROUP BY source';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $result[] = $row;
        }

        return $result;
    }

    public function getRandomHighlight()
    {
        $minAndMaxID = $_SESSION['highlights']['minMaxID'] ?? $this->getMinMaxIdOfHighlights();
        $randomID = rand($minAndMaxID['minID'], $minAndMaxID['maxID']);
        $tags = $this->tagModel->getTagsBySourceId($randomID, Sources::HIGHLIGHT->value);

        foreach ($tags['tags'] as $tag) {

            if ($tag['tag'] === 'private') {
                return [];
            }

        }

        return $this->getHighlightsByGivenField('id', $randomID);
    }

    public function getMinMaxIdOfHighlights()
    {
        $result = [];

        $sql = 'SELECT MIN(id) AS minID, MAX(id) AS maxID
                FROM highlights
                WHERE user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $result = $row;
        }

        $_SESSION['highlights']['minMaxID'] = $result;

        return $result;
    }

    public function incrementReadCount($highlightID)
    {
        $sql = 'UPDATE highlights
                SET read_count = read_count + 1
                WHERE id = :highlight_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight_id', $highlightID, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateUpdatedFieldByHighlightId($highlightID, $updated = null)
    {
        $updated = $updated ?? time();

        $sql = 'UPDATE highlights
                SET updated = :updated
                WHERE id = :id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':id', $highlightID, \PDO::PARAM_INT);
        $stm->bindParam(':updated', $updated, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getHighlightsByDateRange($from = 0, $to = 0)
    {
        $from = $from ?? strtotime(date('Y-m-d 00:00:00'));
        $to = $to ?? strtotime(date('Y-m-d 00:00:00')) + 86400;
        $highlights = [];

        $sql = 'SELECT h.id
                FROM highlights h
                WHERE h.is_deleted = 0 AND h.user_id = :user_id AND h.created > :from AND h.created < :to';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':from', $from, \PDO::PARAM_INT);
        $stm->bindParam(':to', $to, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $highlights[] = $row;
        }

        return $highlights;
    }

    public function getVersionsById($highlightId)
    {
        $versions = [];

        $sql = 'SELECT h.id, h.highlight, hv.old_highlight, hv.created_at, h.author, h.source, h.created, h.updated
                FROM highlights h
                INNER JOIN highlight_versions hv
                ON h.id = hv.highlight_id
                WHERE h.user_id = :user_id AND hv.highlight_id = :highlight_id AND h.is_deleted = 0 AND h.is_encrypted=0
                ORDER BY hv.id DESC';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':highlight_id', $highlightId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['created_at'] = date('Y-m-d H:i:s', $row['created_at']);
            $versions[] = $row;
        }

        return $versions;
    }

    public function getVersionsCountById($highlightId)
    {
        $versionCount = 0;

        $sql = 'SELECT count(*) AS versionCount
                FROM highlights h
                INNER JOIN highlight_versions hv
                ON h.id = hv.highlight_id
                WHERE h.user_id = :user_id AND hv.highlight_id = :highlight_id AND h.is_deleted = 0 AND h.is_encrypted=0';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);
        $stm->bindParam(':highlight_id', $highlightId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(StatusCode::HTTP_SERVICE_UNAVAILABLE, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $versionCount = $row['versionCount'];
        }

        return $versionCount;
    }
}