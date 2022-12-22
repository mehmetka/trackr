<?php

namespace App\model;

use App\controller\BookmarkController;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class BookmarkModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;
    private $tagModel;
    public const DELETED = 1;
    public const NOT_DELETED = 0;
    public const TITLE_EDITED = 1;
    public const NOT_TITLE_EDITED = 0;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
        $this->tagModel = new TagModel($container);
    }

    public function getBookmarks($tag = null)
    {
        $bookmarks = [];
        $today = date('d-m-y', time());
        $yesterday = date('d-m-y', strtotime("-1 days"));

        $sql = 'SELECT b.id, b.uid AS bookmarkUID, b.bookmark, IF(ISNULL(bo.title), b.title, bo.title) AS title, bo.note, bo.status, bo.created, bo.started, bo.done
                FROM bookmarks b
                INNER JOIN bookmarks_ownership bo ON b.id = bo.bookmark_id';

        if ($tag) {
            $sql .= ' INNER JOIN tag_relationships tr ON b.id = tr.source_id
                      INNER JOIN tags t ON tr.tag_id = t.id 
                      WHERE bo.is_deleted = 0 AND t.tag = :tag';
        } else {
            $sql .= ' WHERE bo.is_deleted = 0';
        }

        $sql .= ' AND bo.user_id = :user_id';
        $sql .= ' ORDER BY FIELD(bo.status, 1, 0, 2), bo.updated_at DESC, b.id DESC';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if ($tag) {
            $stm->bindParam(':tag', $tag, \PDO::PARAM_STR);
        }

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            if (!$row['title']) {
                $row['title'] = $row['bookmark'];
            }

            if (strlen($row['title']) > 75) {
                $row['rawTitle'] = $row['title'];
                $row['title'] = substr($row['title'], 0, 75);
                $row['title'] .= ' ...';
            }

            $createdAt = date('d-m-y', $row['created']);

            if ($createdAt == $yesterday) {
                $row['created'] = 'Yesterday';
            } elseif ($createdAt == $today) {
                $row['created'] = 'Today';
            } else {
                $row['created'] = $createdAt;
            }

            if (!$row['started']) {
                $row['startAction'] = true;
            } elseif (!$row['done']) {
                $row['doneAction'] = true;
            } else {
                $row['complete'] = true;
            }

            $row['title'] = htmlspecialchars($row['title']);
            $row['note'] = htmlspecialchars($row['note']);
            $row['bookmark'] = htmlspecialchars($row['bookmark']);

            $bookmarks[] = $row;
        }

        return $bookmarks;
    }

    public function getHighlights($bookmarkId)
    {
        $list = [];

        $sql = 'SELECT * 
                FROM highlights
                WHERE link = :link AND user_id = :user_id';


        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':link', $bookmarkId, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['highlight'] = str_replace("\n", '<br>', $row['highlight']);

            $list[] = $row;
        }

        return $list;
    }

    public function getParentBookmarkByBookmark($bookmark)
    {
        $list = [];
        $bookmark = "%$bookmark%";

        $sql = 'SELECT * 
                FROM bookmarks 
                WHERE bookmark LIKE :bookmark';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookmark', $bookmark, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list = $row;
        }

        return $list;
    }

    public function getBookmarkByTitle($title)
    {
        $list = [];
        $title = "%$title%";

        $sql = 'SELECT * 
                FROM bookmarks 
                WHERE title LIKE :title';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':title', $title, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list = $row;
        }

        return $list;
    }

    public function getBookmarkIdByUid($bookmarkUid)
    {
        $id = 0;

        $sql = 'SELECT b.id
                FROM bookmarks b 
                INNER JOIN bookmarks_ownership bo on b.id = bo.bookmark_id
                WHERE b.uid = :uid AND bo.user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $bookmarkUid, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['id'];
        }

        return $id;
    }

    public function getParentBookmarkById($bookmarkId)
    {
        $list = [];

        $sql = 'SELECT b.id, b.uid, b.bookmark, b.title
                FROM bookmarks b
                WHERE b.id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $bookmarkId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list = $row;
        }

        return $list;
    }

    public function getChildBookmarkById($bookmarkId, $userId)
    {
        $list = [];

        $sql = 'SELECT b.id,
                       b.uid,
                       b.bookmark,
                       IF(ISNULL(bo.title), b.title, bo.title)                   AS title,
                       IF(ISNULL(bo.description), b.description, bo.description) AS description,
                       IF(ISNULL(bo.thumbnail), b.thumbnail, bo.thumbnail)       AS thumbnail,
                       b.title AS parentTitle,
                       bo.title AS childTitle,
                       bo.note,
                       bo.status,
                       h.highlight,
                       h.author,
                       h.source,
                       bo.is_title_edited
                FROM bookmarks b
                         LEFT JOIN highlights h ON b.id = h.link
                         INNER JOIN bookmarks_ownership bo on b.id = bo.bookmark_id
                WHERE b.id = :id
                  AND bo.user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $bookmarkId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $userId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $tags = $this->tagModel->getTagsBySourceId($row['id'], BookmarkController::SOURCE_TYPE);

            if ($tags) {
                $row['tags'] = $tags;
            }

            if ($row['status'] == 0) {
                $row['selectedNew'] = true;
            } elseif ($row['status'] == 1) {
                $row['selectedStarted'] = true;
            } elseif ($row['status'] == 2) {
                $row['selectedDone'] = true;
            }

            $list = $row;
        }

        return $list;
    }

    public function getBookmarkByUid($bookmarkUid)
    {
        $list = [];

        $sql = 'SELECT b.id, b.uid, b.bookmark, IF(ISNULL(bo.title), b.title, bo.title) AS title, bo.note, bo.status, h.highlight, h.author, h.source
                FROM bookmarks b
                LEFT JOIN highlights h ON b.id = h.link
                INNER JOIN bookmarks_ownership bo on b.id = bo.bookmark_id
                WHERE b.uid = :uid AND bo.user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $bookmarkUid, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $tags = $this->tagModel->getTagsBySourceId($row['id'], BookmarkController::SOURCE_TYPE);

            if ($tags) {
                $row['tags'] = $tags;
            }

            if ($row['status'] == 0) {
                $row['selectedNew'] = true;
            } elseif ($row['status'] == 1) {
                $row['selectedStarted'] = true;
            } elseif ($row['status'] == 2) {
                $row['selectedDone'] = true;
            }

            $list = $row;
        }

        return $list;
    }

    public function getUncompleteBookmarks()
    {
        $uncompleteCount = 0;

        $sql = 'SELECT COUNT(*) AS uncompleteBookmarksCount
                FROM bookmarks b
                INNER JOIN bookmarks_ownership bo on b.id = bo.bookmark_id
                WHERE bo.is_deleted = 0 AND bo.status < 2 AND bo.user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $uncompleteCount = $row['uncompleteBookmarksCount'];
        }

        return $uncompleteCount;
    }

    public function create($bookmark)
    {
        $now = time();
        $bookmark = htmlspecialchars($bookmark);

        $sql = 'INSERT INTO bookmarks (uid, bookmark, created)
                VALUES(UUID(), :bookmark, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookmark', $bookmark, \PDO::PARAM_STR);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function addHighlight($bookmarkHighlight)
    {
        $now = time();

        $bookmarkHighlight['author'] = $bookmarkHighlight['author'] ?? 'trackr';
        $bookmarkHighlight['source'] = $bookmarkHighlight['source'] ?? 'trackr';
        $highlight = trim($bookmarkHighlight['highlight']);
        $page = null;

        $sql = 'INSERT INTO highlights (highlight, author, source, page, link, created, user_id)
                VALUES(:highlight, :author, :source, :page, :link, :created, :user_id)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight', $highlight, \PDO::PARAM_STR);
        $stm->bindParam(':author', $bookmarkHighlight['author'], \PDO::PARAM_STR);
        $stm->bindParam(':source', $bookmarkHighlight['source'], \PDO::PARAM_STR);
        $stm->bindParam(':page', $page, \PDO::PARAM_INT);
        $stm->bindParam(':link', $bookmarkHighlight['id'], \PDO::PARAM_INT);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $_SESSION['badgeCounts']['highlightsCount'] += 1;
        return $this->dbConnection->lastInsertId();
    }

    public function updateHighlightAuthor($bookmarkId, $title, $userId)
    {
        $sql = 'UPDATE highlights 
                SET author = :author 
                WHERE link = :bookmarkId AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookmarkId', $bookmarkId, \PDO::PARAM_INT);
        $stm->bindParam(':author', $title, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $userId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateParentBookmarkTitleByID($id, $title)
    {
        $sql = 'UPDATE bookmarks 
                SET title = :title
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':title', $title, \PDO::PARAM_STR);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateChildBookmarkTitleByID($id, $title, $userId)
    {
        $sql = 'UPDATE bookmarks_ownership 
                SET title = :title
                WHERE bookmark_id = :bookmark_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':title', $title, \PDO::PARAM_STR);
        $stm->bindParam(':bookmark_id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $userId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateOrderNumber($id)
    {
        $now = time();

        $sql = 'UPDATE bookmarks_ownership 
                SET updated_at = :updated_at 
                WHERE bookmark_id = :bookmark_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookmark_id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':updated_at', $now, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateIsDeletedStatus($id, $status)
    {
        if ($status == self::NOT_DELETED) {
            $now = null;
        } else {
            $now = time();
        }

        $sql = 'UPDATE bookmarks_ownership 
                SET is_deleted = :is_deleted, deleted_at = :deleted_at 
                WHERE bookmark_id = :bookmark_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookmark_id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':deleted_at', $now, \PDO::PARAM_INT);
        $stm->bindParam(':is_deleted', $status, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateDoneDate($id, $doneDate)
    {
        $sql = 'UPDATE bookmarks_ownership 
                SET done = :done 
                WHERE bookmark_id = :bookmark_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookmark_id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':done', $doneDate, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateStartedDate($id, $started)
    {
        $sql = 'UPDATE bookmarks_ownership
                SET started = :started 
                WHERE bookmark_id = :bookmark_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookmark_id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':started', $started, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateParentBookmark($bookmarkID, $details)
    {
        $sql = 'UPDATE bookmarks
                SET site_name = :site_name, title = :title, description = :description, site_type = :site_type, thumbnail = :thumbnail
                WHERE id = :bookmark_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':site_name', $details['site_name'], \PDO::PARAM_STR);
        $stm->bindParam(':title', $details['title'], \PDO::PARAM_STR);
        $stm->bindParam(':site_type', $details['site_type'], \PDO::PARAM_STR);
        $stm->bindParam(':description', $details['description'], \PDO::PARAM_STR);
        $stm->bindParam(':thumbnail', $details['thumbnail'], \PDO::PARAM_STR);
        $stm->bindParam(':bookmark_id', $bookmarkID, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateChildBookmark($bookmarkID, $details, $userId)
    {
        $sql = 'UPDATE bookmarks_ownership
                SET site_name = :site_name, title = :title, description = :description, note = :note, site_type = :site_type, thumbnail = :thumbnail, status = :status
                WHERE bookmark_id = :bookmark_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':site_name', $details['site_name'], \PDO::PARAM_STR);
        $stm->bindParam(':title', $details['title'], \PDO::PARAM_STR);
        $stm->bindParam(':note', $details['note'], \PDO::PARAM_STR);
        $stm->bindParam(':site_type', $details['site_type'], \PDO::PARAM_STR);
        $stm->bindParam(':description', $details['description'], \PDO::PARAM_STR);
        $stm->bindParam(':thumbnail', $details['thumbnail'], \PDO::PARAM_STR);
        $stm->bindParam(':status', $details['status'], \PDO::PARAM_INT);
        $stm->bindParam(':bookmark_id', $bookmarkID, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $userId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateBookmarkStatus($bookmarkID, $status)
    {
        $sql = 'UPDATE bookmarks_ownership 
                SET status = :status
                WHERE bookmark_id = :bookmark_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':bookmark_id', $bookmarkID, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateIsTitleEditedStatus($bookmarkID, $status)
    {
        $sql = 'UPDATE bookmarks_ownership
                SET is_title_edited = :is_title_edited
                WHERE bookmark_id = :bookmark_id AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':is_title_edited', $status, \PDO::PARAM_INT);
        $stm->bindParam(':bookmark_id', $bookmarkID, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function addOwnership($bookmarkId, $userId, $note)
    {
        $now = time();
        $note = htmlspecialchars($note);

        $sql = 'INSERT INTO bookmarks_ownership (bookmark_id, user_id, note, created, updated_at)
                VALUES (:bookmark_id, :user_id, :note, :created, :updated_at)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookmark_id', $bookmarkId, \PDO::PARAM_INT);
        $stm->bindParam(':user_id', $userId, \PDO::PARAM_INT);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);
        $stm->bindParam(':updated_at', $now, \PDO::PARAM_INT);
        $stm->bindParam(':note', $note, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }
}
