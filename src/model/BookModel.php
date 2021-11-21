<?php

namespace App\model;

use App\util\Util;
use App\exception\CustomException;
use Psr\Container\ContainerInterface;

class BookModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;
    private $pathStatusInfos;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
        $this->pathStatusInfos = [
            'not_started' => [
                'status_label' => 'badge-secondary',
                'status_label_text' => 'Not Started',
                'id' => 0
            ],
            'reading' => [
                'status_label' => 'badge-warning',
                'status_label_text' => 'Reading',
                'id' => 1

            ],
            'done' => [
                'status_label' => 'badge-success',
                'status_label_text' => 'Done',
                'id' => 2
            ],
            'list_out' => [
                'status_label' => 'badge-danger',
                'status_label_text' => 'List Out',
                'id' => 3
            ]
        ];
    }

    public function getStartOfReadings()
    {
        $start = null;

        $sql = 'SELECT record_date 
                FROM book_trackings 
                ORDER BY id ASC
                LIMIT 1';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $start = $row['record_date'];
        }

        return $start;
    }

    public function getReadingTotal()
    {
        $total = 0;

        $sql = 'SELECT SUM(amount) AS total 
                FROM book_trackings';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $total = $row['total'];
        }

        return $total;
    }

    public function getPathIdByUid($uid)
    {
        $id = 0;

        $sql = 'SELECT id 
                FROM paths
                WHERE uid = :uid';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $uid, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['id'];
        }

        return $id;
    }

    public function getPublishers()
    {
        $sql = 'SELECT id, name
                FROM publishers';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $list[] = $row;
        }

        return $list;
    }

    public function getBookIdByUid($uid)
    {
        $id = 0;

        $sql = 'SELECT id 
                FROM books
                WHERE uid = :uid';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $uid, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $id = $row['id'];
        }

        return $id;
    }

    public function readingAverage()
    {
        $start = $this->getStartOfReadings();
        $start = $start ? $start : time();
        $from = time();
        $diff = Util::epochDateDiff($from, $start);
        $diff = $diff ? $diff : 1;
        $total = $this->getReadingTotal();

        $result['average'] = $total / $diff;
        $result['total'] = $total;
        $result['diff'] = $diff;

        return $result;
    }

    public function getBookPaths()
    {
        $today = time();
        $list = [];
        $paths = $this->getPathsList();

        foreach ($paths as $path) {

            if ($today > $path['finish_epoch']) {
                $this->changePathStatus($path['path_id'], 1);
                $path['status'] = 1;
                $path['expired'] = true;
                $path['today_processed_text'] = 'EXPIRED!';
                $path['ratio'] = 'X';
                $path['ratioBadgeColor'] = 'danger';
                $list[] = $path;
                continue;
            }

            $path['remaining_page'] = $this->getBooksRemainingPageCount($path['path_id']);
            $path['day_diff'] = Util::epochDateDiff($path['finish_epoch'], $today);
            $path['path_day_count'] = Util::epochDateDiff($path['finish_epoch'], $path['start_epoch']);
            $path['ratio'] = '%' . round((($path['path_day_count'] - $path['day_diff']) / $path['path_day_count']) * 100);
            $path['ratioBadgeColor'] = 'warning';
            $path['today_processed'] = $this->getBookPathsDailyRemainings($path['path_id']);

            $dailyAmount = $path['remaining_page'] / $path['day_diff'];
            $path['daily_amount'] = round($dailyAmount, 0, PHP_ROUND_HALF_UP);

            if ($path['day_diff'] <= 3) {
                $path['remaining_day_warning'] = true;
            }

            if ($path['day_diff'] > 1) {
                $path['day_diff_text'] = "<span class=\"badge badge-primary\">{$path['day_diff']} days left</span>";
            } else {
                $path['day_diff_text'] = "<span class=\"badge badge-danger\">Last day!</span>";
            }

            if (!$path['today_processed']) {
                $pages = $path['daily_amount'] > 1 ? 'pages' : 'page';
                $path['today_processed_text'] = "You haven't read today :( You have to read {$path['daily_amount']} $pages";
            } else {
                $pages = $path['today_processed'] > 1 ? 'pages' : 'page';
                $tmpDailyAmount = $path['daily_amount'] - $path['today_processed'];

                if ($tmpDailyAmount > 0) {
                    $path['today_processed_text'] = "You read {$path['today_processed']} $pages today. Read $tmpDailyAmount more pages";
                } else {
                    $path['today_processed_text'] = "<span class=\"text-success\">Congrats, you read'em all today! \m/</span>";
                }

            }

            $list[] = $path;
        }

        return $list;
    }

    public function getBooksRemainingPageCount($pathId)
    {
        $total = 0;

        $sql = 'SELECT b.id, b.page_count
                FROM books b
                INNER JOIN path_books pb ON b.id = pb.book_id
                WHERE pb.path_id = :path_id AND pb.status < 2';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $readAmount = $this->getReadAmount($row['id'], $pathId);
            $pageCount = $row['page_count'];
            $diff = $pageCount - $readAmount;

            if ($pageCount != 0) {
                if ($diff > 0) {
                    $total += $diff;
                } else {
                    $this->insertNewReadRecord($pathId, $row['id']);
                    $this->setBookPathStatus($pathId, $row['id'], $this->pathStatusInfos['done']['id']);
                }
            }

        }

        return $total;
    }

    public function getReadAmount($bookId, $pathId)
    {
        $total = 0;

        $sql = 'SELECT sum(amount) AS total 
                FROM book_trackings 
                WHERE book_id=:book_id AND path_id=:path_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $total = $row['total'];
        }

        return $total;
    }

    public function getBookDetailByBookIdAndPathId($bookId, $pathId)
    {
        $detail = [];

        $sql = 'SELECT *
                FROM path_books 
                WHERE book_id=:book_id AND path_id=:path_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $detail = $row;
        }

        return $detail;
    }

    public function insertNewReadRecord($pathId, $bookId)
    {
        $now = date("Y-m-d H:i:s");

        $sql = 'INSERT INTO books_finished (book_id, path_id, start_date, finish_date)
                VALUES(:book_id,:path_id,:start_date,:finish_date)';

        $startDate = $this->findStartDateOfBook($bookId);
        $startDate = date("Y-m-d H:i:s", $startDate);

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':start_date', $startDate, \PDO::PARAM_STR);
        $stm->bindParam(':finish_date', $now, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function findStartDateOfBook($bookId)
    {
        $startDate = 0;

        $sql = 'SELECT record_date 
                FROM book_trackings 
                WHERE book_id=:book_id 
                ORDER BY record_date ASC LIMIT 1;';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $startDate = $row['record_date'];
        }

        return $startDate;
    }

    public function setBookPathStatus($pathId, $bookId, $status)
    {
        $sql = 'UPDATE path_books
                SET status=:status
                WHERE book_id=:book_id AND path_id = :path_id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function changePathStatus($pathId, $status)
    {
        $sql = 'UPDATE paths 
                SET status = :status 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':id', $pathId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getBookPathsDailyRemainings($pathId)
    {
        $today = date('Y-m-d 00:00:00');
        $today = strtotime($today);
        $tomorrow = $today + 86400;
        $total = 0;

        $sql = 'SELECT sum(bt.amount) AS amount
                FROM book_trackings bt
                INNER JOIN path_books pb ON bt.book_id = pb.book_id
                WHERE pb.path_id = :path_id AND (bt.record_date > :today && bt.record_date < :tomorrow)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':today', $today, \PDO::PARAM_INT);
        $stm->bindParam(':tomorrow', $tomorrow, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $total = $row['amount'];
        }

        return $total == NULL ? 0 : $total;
    }

    public function getAuthors()
    {
        $sql = 'SELECT id,author 
                FROM author';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $list[] = $row;
        }

        return $list;
    }

    public function getAllBooks()
    {
        $paths = $this->getPathsList();

        $sql = "SELECT b.id, b.uid AS bookUID,
                       CONCAT((SELECT GROUP_CONCAT(a.author SEPARATOR ', ')
                               FROM book_authors ba
                                        INNER JOIN author a ON ba.author_id = a.id
                               WHERE ba.book_id = b.id)) AS author,
                       b.title, b.page_count, b.own, b.added_date
                FROM books b";

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            if (!$row['own']) {
                $row['add_to_library'] = true;
            }

            $row['paths'] = $paths;
            $row['added_date'] = date("Y-m-d", $row['added_date']);

            $list[] = $row;
        }

        return $list;
    }

    public function getPathsList()
    {
        $sql = 'SELECT id AS path_id, uid AS pathUID, name AS path_name, start, finish 
                FROM paths ';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $paths = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $row['start_epoch'] = $row['start'];
            $row['finish_epoch'] = $row['finish'];
            $row['start'] = date('Y-m-d', $row['start']);
            $row['finish'] = date('Y-m-d', $row['finish']);

            $paths[] = $row;
        }

        return $paths;
    }

    public function getMyBooks()
    {
        $sql = "SELECT b.id,
                       CONCAT((SELECT GROUP_CONCAT(a.author SEPARATOR ', ')
                               FROM book_authors ba
                                        INNER JOIN author a ON ba.author_id = a.id
                               WHERE ba.book_id = b.id)) AS author,
                       b.title, b.page_count, b.own, b.added_date
                FROM books b
                WHERE b.own = 1";

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['added_date'] = date("Y-m-d", $row['added_date']);
            $row['remaining'] = 0;
            $list[] = $row;
        }

        return $list;
    }

    public function finishedBooks()
    {
        $sql = "SELECT bf.id, bf.book_id, b.title, b.page_count, b.status, bf.start_date, bf.finish_date, 
                        CONCAT((SELECT GROUP_CONCAT(a.author SEPARATOR ', ') FROM book_authors ba INNER JOIN author a ON ba.author_id = a.id WHERE ba.book_id = b.id)) AS author
                FROM books_finished bf
                LEFT JOIN books b ON bf.book_id = b.id
                ORDER BY finish_date DESC";
        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $books = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $books[] = $row;
        }

        return $books;
    }

    public function getBooksPathInside($pathId, $status = false)
    {
        $sql = "SELECT b.uid AS bookUID, b.title, b.id, b.page_count, b.status AS book_status, pb.status AS path_status, pb.path_id, p.uid AS pathUID, CONCAT((SELECT GROUP_CONCAT(a.author SEPARATOR ', ') FROM book_authors ba INNER JOIN author a ON ba.author_id = a.id WHERE ba.book_id = b.id)) AS author
                FROM books b
                INNER JOIN path_books pb ON b.id = pb.book_id
                INNER JOIN paths p ON pb.path_id = p.id
                WHERE pb.path_id = :path_id
                ORDER BY pb.status DESC";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            if ($row['path_status'] == 2) {

                if (!$status) {
                    $row['status_label'] = 'bg-success-dark';
                    $row['cardBodyBg'] = 'bg-success-light';
                    $row['readStatus'] = '<i class="fe fe-check fe-16"></i>';
                    $row['amount'] = true;
                    $row['remove'] = true;
                    $list[] = $row;
                }

                continue;
            }

            $readAmount = $this->getReadAmount($row['id'], $row['path_id']);
            $readAmount = $readAmount ? $readAmount : 0;

            $pageCount = $row['page_count'];
            $diff = $pageCount - $readAmount;

            if ($pageCount != 0 && $diff <= 0) {
                $this->insertNewReadRecord($row['path_id'], $row['id']);
                $this->setBookPathStatus($row['path_id'], $row['id'], $this->pathStatusInfos['done']['id']);
                $this->setBookStatus($row['id'], $this->pathStatusInfos['done']['id']);
                unset($_SESSION['badgeCounts']);
                continue;
            }

            $row['divId'] = "div-{$row['id']}-" . uniqid();
            $row['status_label'] = 'bg-secondary-dark';
            $row['readStatus'] = "$readAmount / {$row['page_count']}";

            if ($readAmount) {
                $row['status_label'] = 'bg-warning-dark';
                array_unshift($list, $row);
            } else {
                $list[] = $row;
            }
        }

        return $list;
    }

    public function setBookStatus($bookId, $status)
    {
        $sql = 'UPDATE books
                SET status=:status
                WHERE id=:id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function insertProgressRecord($bookId, $pathId, $amount)
    {
        $now = time();

        $this->setBookPathStatus($pathId, $bookId, $this->pathStatusInfos['reading']['id']);

        $sql = 'INSERT INTO book_trackings (book_id, path_id, record_date, amount)
                VALUES(:book_id,:path_id,:record_date,:amount)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':record_date', $now, \PDO::PARAM_INT);
        $stm->bindParam(':amount', $amount, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function createAuthor($author)
    {
        $sql = 'INSERT INTO author (author) 
                VALUES(:author)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':author', $author, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function addToLibrary($bookId)
    {
        $addedDate = time();

        $sql = 'UPDATE books
                SET own = 1, added_date = :addedDate
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':addedDate', $addedDate, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function addBookToPath($pathId, $bookId)
    {
        $status = 0;
        $now = time();

        $sql = 'INSERT INTO path_books (path_id, book_id, status, created, updated) 
                VALUES(:path_id, :book_id, :status, :created, :updated)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':path_id', $pathId, \PDO::PARAM_INT);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':created', $now, \PDO::PARAM_INT);
        $stm->bindParam(':updated', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()), 'Could not add book to path!');
        }

        return $this->dbConnection->lastInsertId();
    }

    public function deleteBookTrackings($bookId)
    {
        $sql = 'DELETE FROM book_trackings
                WHERE BOOK_ID = :bookId';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookId', $bookId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function deleteBookRecordsFromPaths($bookId)
    {
        $sql = 'DELETE FROM path_books
                WHERE book_id = :bookId';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookId', $bookId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getPathByUid($pathUid)
    {
        $sql = 'SELECT id, name, start, finish, status
                FROM paths 
                WHERE uid = :uid';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':uid', $pathUid, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $path = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $row['start'] = date('Y-m-d', $row['start']);
            $row['finish'] = date('Y-m-d', $row['finish']);

            $path = $row;
        }

        return $path;
    }

    public function extendFinishDate($pathUid, $extendedFinishDate)
    {
        $sql = 'UPDATE paths 
                SET finish = :finish 
                WHERE uid = :uid';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':finish', $extendedFinishDate, \PDO::PARAM_INT);
        $stm->bindParam(':uid', $pathUid, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function saveBook($params)
    {
        $now = time();
        $status = $this->pathStatusInfos['not_started']['id'];

        $sql = 'INSERT INTO books (uid, title, publisher, pdf, epub, notes, category, added_date, own, page_count, status)
                VALUES(UUID(), :title,:publisher,:pdf,:epub,:notes,:category,:added_date,:own,:page_count, :status)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':title', $params['bookTitle'], \PDO::PARAM_STR);
        $stm->bindParam(':publisher', $params['publisher'], \PDO::PARAM_STR);
        $stm->bindParam(':pdf', $params['pdf'], \PDO::PARAM_INT);
        $stm->bindParam(':epub', $params['epub'], \PDO::PARAM_INT);
        $stm->bindParam(':notes', $params['notes'], \PDO::PARAM_STR);
        $stm->bindParam(':category', $params['category'], \PDO::PARAM_INT);
        $stm->bindParam(':added_date', $now, \PDO::PARAM_INT);
        $stm->bindParam(':own', $params['own'], \PDO::PARAM_INT);
        $stm->bindParam(':page_count', $params['pageCount'], \PDO::PARAM_INT);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function insertBookAuthor($bookId, $authorId)
    {
        $sql = 'INSERT INTO book_authors (author_id, book_id) 
                VALUES(:author_id, :book_id)';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':author_id', $authorId, \PDO::PARAM_INT);
        $stm->bindParam(':book_id', $bookId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function createPath($name, $finish)
    {
        $now = time();
        $finish = strtotime($finish);

        $sql = 'INSERT INTO paths (uid, name, start, finish)
                VALUES(UUID(), :name, :start, :finish)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':name', $name, \PDO::PARAM_STR);
        $stm->bindParam(':start', $now, \PDO::PARAM_INT);
        $stm->bindParam(':finish', $finish, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function deleteBookTrackingsByPath($bookId, $pathId)
    {
        $sql = 'DELETE FROM book_trackings
                WHERE BOOK_ID = :bookId AND PATH_ID = :pathId';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookId', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':pathId', $pathId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function deleteBookFromPath($bookId, $pathId)
    {
        $sql = 'DELETE FROM path_books
                WHERE book_id = :bookId AND path_id = :pathId';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':bookId', $bookId, \PDO::PARAM_INT);
        $stm->bindParam(':pathId', $pathId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function changeBooksCategoryByGivenCategory($oldCategory, $newCategory)
    {
        $sql = 'UPDATE books 
                SET category = :newCategory
                WHERE category = :oldCategory';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':oldCategory', $oldCategory, \PDO::PARAM_INT);
        $stm->bindParam(':newCategory', $newCategory, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getBookTrackingsGraphicData()
    {
        $paths = $this->getPathsList();
        $result = [];

        foreach ($paths as $path) {
            $graphicDatas = $this->getBookTrackingsByPathID($path['path_id']);
            $preparedData = $this->prepareBookTrackingsGraphicData($graphicDatas);
            $preparedData['series']['name'] = $path['path_name'];
            $result[] = $preparedData;
        }

        return $result;
    }

    public function getBookTrackingsByPathID($pathID)
    {
        $trackings = [];

        $sql = "SELECT FROM_UNIXTIME(record_date, '%m/%d/%Y GMT') AS date, amount
                FROM book_trackings
                WHERE path_id = :pathID";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':pathID', $pathID, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $trackings[] = $row;
        }

        return $trackings;
    }

    public function prepareBookTrackingsGraphicData($bookTrackings)
    {
        $result = ['series' => ['data' => []], 'xaxisCategories' => []];
        $tmp = [];
        $dayCount = 1;

        foreach ($bookTrackings as $bookTracking) {

            if (isset($tmp[$bookTracking['date']])) {

                if ($dayCount > 10) {
                    break;
                }

                $tmp[$bookTracking['date']] += $bookTracking['amount'];

                $dayCount += 1;
            } else {
                $tmp[$bookTracking['date']] = $bookTracking['amount'];
            }

        }

        foreach ($tmp as $key => $value) {
            $result['series']['data'][] = $value;
            $result['xaxisCategories'][] = $key;
        }

        return $result;
    }

    public function getMyBooksCount()
    {
        $myBookCount = 0;

        $sql = "SELECT COUNT(*) AS myBookCount
                FROM books
                WHERE own = 1";

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $myBookCount = $row['myBookCount'];
        }

        return $myBookCount;
    }

    public function getAllBookCount()
    {
        $allBookCount = 0;

        $sql = "SELECT COUNT(*) AS allBookCount
                FROM books";

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $allBookCount = $row['allBookCount'];
        }

        return $allBookCount;
    }

    public function getFinishedBookCount()
    {
        $finishedBookCount = 0;

        $sql = "SELECT COUNT(*) AS finishedBookCount
                FROM books_finished";

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $finishedBookCount = $row['finishedBookCount'];
        }

        return $finishedBookCount;
    }
}