<?php

namespace App\model;

use Psr\Container\ContainerInterface;
use App\exception\CustomException;

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
                ORDER BY FIELD(status, 1, 0, 2), id DESC';

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

        $sql = 'SELECT * 
                FROM bookmarks 
                WHERE bookmark = :bookmark';

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

    public function getBookmarkById($bookmarkId)
    {
        $list = [];

        $sql = 'SELECT b.id, b.uid, b.bookmark, b.title, b.note, b.categoryId, h.highlight, h.author, h.source
                FROM bookmarks b
                LEFT JOIN highlights h ON b.id = h.link
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

    public function create($bookmark, $note, $categoryId)
    {
        $now = time();

        $title = $this->getTitle($bookmark);

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

        return preg_match('/<title[^>]*>(.*?)<\/title>/ims', $data, $matches) ? $matches[1] : null;
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
}