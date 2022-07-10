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

        $sql = 'SELECT b.id, b.uid AS bookmarkUID, b.bookmark, b.title, b.note, b.status, b.created, b.started, b.done
                FROM bookmarks b';

        if ($tag) {
            $sql .= ' INNER JOIN tag_relationships tr ON b.id = tr.source_id
                      INNER JOIN tags t ON tr.tag_id = t.id 
                      WHERE b.is_deleted = 0 AND t.tag = :tag';
        } else {
            $sql .= ' WHERE b.is_deleted = 0';
        }

        $sql .= ' ORDER BY FIELD(b.status, 1, 0, 2), b.orderNumber DESC, b.id DESC';

        $stm = $this->dbConnection->prepare($sql);

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

            if (strlen($row['title']) > 125) {
                $row['title'] = substr($row['title'], 0, 125);
                $row['title'] .= ' ...';
            }

            $row['created'] = date('Y-m-d H:i:s', $row['created']);

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

    public function getBookmarkByBookmark($bookmark)
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

        $sql = 'SELECT id
                FROM bookmarks
                WHERE uid = :uid';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $bookmarkUid, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['id'];
        }

        return $id;
    }

    public function getBookmarkById($bookmarkId)
    {
        $list = [];

        $sql = 'SELECT b.id, b.uid, b.bookmark, b.title, b.note, b.status, h.highlight, h.author, h.source, b.is_title_edited
                FROM bookmarks b
                LEFT JOIN highlights h ON b.id = h.link
                WHERE b.id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $bookmarkId, \PDO::PARAM_INT);

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

        $sql = 'SELECT b.id, b.uid, b.bookmark, b.title, b.note, b.status, h.highlight, h.author, h.source
                FROM bookmarks b
                LEFT JOIN highlights h ON b.id = h.link
                WHERE b.uid = :uid';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $bookmarkUid, \PDO::PARAM_STR);

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
                FROM bookmarks
                WHERE is_deleted = 0 AND status < 2';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $uncompleteCount = $row['uncompleteBookmarksCount'];
        }

        return $uncompleteCount;
    }

    public function create($bookmark, $note)
    {
        $now = time();
        $note = htmlspecialchars($note);
        $bookmark = htmlspecialchars($bookmark);

        $sql = 'INSERT INTO bookmarks (uid, bookmark, note, orderNumber, created)
                VALUES(UUID(), :bookmark, :note, :orderNumber, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookmark', $bookmark, \PDO::PARAM_STR);
        $stm->bindParam(':note', $note, \PDO::PARAM_STR);
        $stm->bindParam(':orderNumber', $now, \PDO::PARAM_INT);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function createOperations($bookmark, $note)
    {
        if (!$bookmark) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'Bookmark cannot be empty!');
        }

        $bookmarkExist = $this->getBookmarkByBookmark($bookmark);

        if ($bookmarkExist) {
            $this->updateOrderNumber($bookmarkExist['id']);
            $this->updateIsDeletedStatus($bookmarkExist['id'], self::NOT_DELETED);
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'Bookmark exist!');
        }

        return $this->create($bookmark, $note);
    }

    public function addHighlight($bookmarkHighlight)
    {
        $now = time();

        $bookmarkHighlight['author'] = $bookmarkHighlight['author'] ? $bookmarkHighlight['author'] : 'trackr';
        $bookmarkHighlight['source'] = $bookmarkHighlight['source'] ? $bookmarkHighlight['source'] : 'trackr';
        $highlight = htmlentities(trim($bookmarkHighlight['highlight']));
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

    public function updateHighlightAuthor($bookmarkId, $title)
    {
        $sql = 'UPDATE highlights SET author = :author WHERE link = :bookmarkId AND user_id = :user_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookmarkId', $bookmarkId, \PDO::PARAM_INT);
        $stm->bindParam(':author', $title, \PDO::PARAM_STR);
        $stm->bindParam(':user_id', $_SESSION['userInfos']['user_id'], \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateTitleByID($id, $title)
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

    public function updateOrderNumber($id)
    {
        $now = time();

        $sql = 'UPDATE bookmarks 
                SET orderNumber = :orderNumber 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':orderNumber', $now, \PDO::PARAM_INT);

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

        $sql = 'UPDATE bookmarks 
                SET is_deleted = :is_deleted, deleted_at = :deleted_at 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':deleted_at', $now, \PDO::PARAM_INT);
        $stm->bindParam(':is_deleted', $status, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateDoneDate($id, $doneDate)
    {
        $sql = 'UPDATE bookmarks 
                SET done = :done 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':done', $doneDate, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateStartedDate($id, $started)
    {
        $sql = 'UPDATE bookmarks 
                SET started = :started 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':started', $started, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateBookmark($bookmarkID, $details)
    {
        $title = $details['title']; //htmlspecialchars($details['title']);
        $note = htmlspecialchars($details['note']);

        $sql = 'UPDATE bookmarks 
                SET title = :title, note = :note, status = :status
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':title', $title, \PDO::PARAM_STR);
        $stm->bindParam(':note', $note, \PDO::PARAM_STR);
        $stm->bindParam(':status', $details['status'], \PDO::PARAM_INT);
        $stm->bindParam(':id', $bookmarkID, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateBookmarkStatus($bookmarkID, $status)
    {
        $sql = 'UPDATE bookmarks 
                SET status = :status
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':id', $bookmarkID, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateIsTitleEditedStatus($bookmarkID, $status)
    {
        $sql = 'UPDATE bookmarks 
                SET is_title_edited = :is_title_edited
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':is_title_edited', $status, \PDO::PARAM_INT);
        $stm->bindParam(':id', $bookmarkID, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }
}
