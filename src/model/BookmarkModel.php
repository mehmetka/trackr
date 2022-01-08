<?php

namespace App\model;

use Psr\Container\ContainerInterface;
use App\exception\CustomException;
use Slim\Http\StatusCode;

class BookmarkModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function getBookmarks()
    {
        $bookmarks = [];

        $sql = 'SELECT b.id, b.uid AS bookmarkUID, b.bookmark, b.title, b.note, b.categoryId, c.name AS categoryName, b.status, b.created, b.started, b.done
                FROM bookmarks b
                INNER JOIN categories c ON b.categoryId = c.id
                ORDER BY FIELD(status, 1, 0, 2), orderNumber DESC, id DESC';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            if (!$row['title']) {
                $row['title'] = $row['bookmark'];
            }

            $row['created'] = date('Y-m-d H:i:s', $row['created']);

            if (!$row['started']) {
                $row['startAction'] = true;
            } elseif (!$row['done']) {
                $row['doneAction'] = true;
            } else {
                $row['complete'] = true;
            }

            $bookmarks[] = $row;
        }

        return $bookmarks;
    }

    public function getHighlights($bookmarkId)
    {
        $list = [];

        $sql = 'SELECT * 
                FROM highlights
                WHERE link = :link';


        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':link', $bookmarkId, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['highlight'] = str_replace("\n", '<br>', $row['highlight']);
            $row['html'] = $row['html'] ? $row['html'] : $row['highlight'];

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

        $sql = 'SELECT b.id, b.uid, b.bookmark, b.title, b.note, b.categoryId, b.status, h.highlight, h.author, h.source
                FROM bookmarks b
                LEFT JOIN highlights h ON b.id = h.link
                WHERE b.id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $bookmarkId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

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
                WHERE status < 2';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $uncompleteCount = $row['uncompleteBookmarksCount'];
        }

        return $uncompleteCount;
    }

    public function create($bookmark, $title, $note, $categoryId)
    {
        $now = time();

        $sql = 'INSERT INTO bookmarks (uid, bookmark, title, note, categoryId, orderNumber, created)
                VALUES(UUID(), :bookmark, :title, :note, :categoryId, :orderNumber, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookmark', $bookmark, \PDO::PARAM_STR);
        $stm->bindParam(':note', $note, \PDO::PARAM_STR);
        $stm->bindParam(':title', $title, \PDO::PARAM_STR);
        $stm->bindParam(':categoryId', $categoryId, \PDO::PARAM_INT);
        $stm->bindParam(':orderNumber', $now, \PDO::PARAM_INT);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function createOperations($bookmark, $note, $categoryId)
    {
        if (!$bookmark) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'Bookmark cannot be empty!');
        }

        $bookmarkExist = $this->getBookmarkByBookmark($bookmark);

        if ($bookmarkExist) {
            $this->updateOrderNumber($bookmarkExist['id']);
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, 'Bookmark exist!');
        }

        if (!$categoryId) {
            $categoryId = 6665;
        }

        $title = null;
   
        return $this->create($bookmark, $title, $note, $categoryId);
    }

    public function getBookmarkTitleAsync($bookmarkID)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "http://127.0.0.1/api/bookmarks/$bookmarkID/title");
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HEADER, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_TIMEOUT, 1);
        curl_setopt($curl, CURLOPT_NOSIGNAL, 1);
        curl_exec($curl);
        curl_close($curl);
    }

    public function addHighlight($bookmarkHighlight)
    {
        $now = time();

        $bookmarkHighlight['author'] = $bookmarkHighlight['author'] ? $bookmarkHighlight['author'] : 'trackr';
        $bookmarkHighlight['source'] = $bookmarkHighlight['source'] ? $bookmarkHighlight['source'] : 'trackr';
        $html = str_replace("\n", '<br>', trim($bookmarkHighlight['highlight']));
        $highlight = strip_tags(trim($bookmarkHighlight['highlight']));
        $page = null;

        $sql = 'INSERT INTO highlights (highlight, html, author, source, page, link, created)
                VALUES(:highlight, :html, :author, :source, :page, :link, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':highlight', $highlight, \PDO::PARAM_STR);
        $stm->bindParam(':html', $html, \PDO::PARAM_STR);
        $stm->bindParam(':author', $bookmarkHighlight['author'], \PDO::PARAM_STR);
        $stm->bindParam(':source', $bookmarkHighlight['source'], \PDO::PARAM_STR);
        $stm->bindParam(':page', $page, \PDO::PARAM_INT);
        $stm->bindParam(':link', $bookmarkHighlight['id'], \PDO::PARAM_INT);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $_SESSION['badgeCounts']['highlightsCount'] += 1;
        return $this->dbConnection->lastInsertId();
    }

    public function getTitle($url)
    {
        try {
            $data = @file_get_contents($url);
            $code = $this->getHttpCode($http_response_header);

            if ($code === 404) {
                return '404 Not Found';
            }
        } catch (\Exception $exception) {
            return null;
        }

        if (preg_match('/<title[^>]*>(.*?)<\/title>/ims', $data, $matches)) {
            return mb_check_encoding($matches[1], 'UTF-8') ? $matches[1] : utf8_encode($matches[1]);
        }

        return null;
    }

    function getHttpCode($http_response_header)
    {
        if (is_array($http_response_header)) {
            $parts = explode(' ', $http_response_header[0]);
            if (count($parts) > 1) //HTTP/1.0 <code> <text>
                return intval($parts[1]); //Get code
        }
        return 0;
    }

    public function updateStartedDate($id)
    {
        $now = time();
        $status = 1;

        $sql = 'UPDATE bookmarks 
                SET status = :status, started = :started 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':started', $now, \PDO::PARAM_INT);

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

    public function deleteBookmark($id)
    {
        $sql = 'DELETE FROM bookmarks 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateDoneDate($id)
    {
        $now = time();
        $status = 2;

        $sql = 'UPDATE bookmarks 
                SET status = :status, done = :done 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':done', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateBookmark($bookmarkID, $details)
    {
        $sql = 'UPDATE bookmarks 
                SET bookmark = :bookmark, title = :title, note = :note, categoryId = :categoryId, status = :status
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookmark', $details['bookmark'], \PDO::PARAM_STR);
        $stm->bindParam(':title', $details['title'], \PDO::PARAM_STR);
        $stm->bindParam(':note', $details['note'], \PDO::PARAM_STR);
        $stm->bindParam(':categoryId', $details['categoryId'], \PDO::PARAM_INT);
        $stm->bindParam(':status', $details['status'], \PDO::PARAM_INT);
        $stm->bindParam(':id', $bookmarkID, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }
}
