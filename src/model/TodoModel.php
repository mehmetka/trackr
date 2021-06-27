<?php

namespace App\model;

use App\util\Util;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;

class TodoModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function getTodos()
    {
        $list = [];

        $sql = "SELECT * 
                FROM todos 
                ORDER BY orderNumber";

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $row['todo'] = Util::hashtagsToLabel($row['todo']);
            $row['description'] = str_replace("\n", '<br>', $row['description']);
            $row['accordionId'] = 'accordion' . uniqid();
            $row['cardBodyId'] = 'cb' . uniqid();
            $row['escalateAction'] = true;
            $row['editAction'] = true;

            if ($row['canceled']) {
                $row['cardHeaderBg'] = 'bg-danger';
                $row['canceled'] = date('Y-m-d H:i', $row['canceled']);
                $row['escalateAction'] = false;
                $row['editAction'] = false;
            } else {
                if (!$row['started']) {
                    $row['started'] = date('Y-m-d H:i', $row['started']);
                    $row['startAction'] = true;
                    $row['cancelAction'] = true;
                } elseif (!$row['done']) {
                    $row['doneAction'] = true;
                    $row['cancelAction'] = true;
                } else {
                    $row['done'] = date('Y-m-d H:i', $row['done']);
                    $row['complete'] = true;
                    $row['escalateAction'] = false;
                    $row['editAction'] = false;
                }
            }

            if (!$row['description']) {
                unset($row['description']);
            }

            if ($row['status'] == 2) {
                $row['cardHeaderBg'] = 'bg-info-dark';
                $list[] = $row;
            } elseif ($row['status'] == 3) {
                $row['cardHeaderBg'] = 'bg-danger';
                $list[] = $row;
            } else {
                $row['cardHeaderBg'] = 'bg-dark';
                array_unshift($list, $row);
            }

        }

        return $list;
    }

    public function getTodo($todoId)
    {
        $todo = [];

        $sql = "SELECT id, todo, description, created, started, done, status 
                FROM todos
                WHERE id = :id";

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $todoId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $todo = $row;
        }

        return $todo;
    }

    public function getAllTodos()
    {
        $list = [];
        $done = [];

        $sql = "SELECT t.id AS typeTableId, t.todo AS todoName, t.status AS status, 'todo' AS todoType, 'primary' AS badge
                FROM todos t
                UNION ALL
                SELECT b.id AS typeTableId,
                       CONCAT((SELECT GROUP_CONCAT(a.author SEPARATOR ', ')
                               FROM book_authors ba
                                        INNER JOIN author a ON ba.author_id = a.id
                               WHERE ba.book_id = b.id), ' - ', b.title) AS todoName,
                       pb.status AS status,
                       'book' AS todoType,
                       'warning' AS badge
                FROM books b
                         LEFT JOIN books_finished bf ON b.id = bf.book_id
                         LEFT JOIN path_books pb ON b.ID = pb.book_id
                GROUP BY b.id
                UNION ALL
                SELECT b.id AS typeTableId,
                       CONCAT('<a href=\"', b.bookmark, '\" target=\"_blank\">', IFNULL(b.title,b.bookmark), '</a>') AS todoName,
                       b.status AS status,
                       'bookmark' AS todoType,
                       'info' AS badge
                FROM bookmarks b
                         INNER JOIN categories c ON b.categoryID = c.id
                UNION ALL
                SELECT v.id AS typeTableId,
                       CONCAT(v.title,' (',v.length, ') ') AS todoName,
                       v.status AS status,
                       'video' AS todoType,
                       'danger' AS badge
                FROM videos v INNER JOIN categories c ON v.category_id = c.id";

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            if ($row['status'] == 0 || $row['status'] == "") {
                $row['status'] = '<span class="badge badge-secondary">to do</span>';
                $list[] = $row;
            } elseif ($row['status'] == 1) {
                $row['status'] = '<span class="badge badge-warning">in progress</span>';
                array_unshift($list, $row);
            } elseif ($row['status'] == 2) {
                $row['status'] = '<span class="badge badge-success">done</span>';
                $done[] = $row;
            } else {
                $row['status'] = '<span class="badge badge-dark">list out</span>';
                $list[] = $row;
            }

        }

        $list = array_merge($list, $done);
        return $list;
    }

    public function create($todo, $description)
    {
        $date = time();

        $sql = 'INSERT INTO todos (todo, description, created)
                VALUES(:todo, :description, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':todo', $todo, \PDO::PARAM_STR);
        $stm->bindParam(':description', $description, \PDO::PARAM_STR);
        $stm->bindParam(':created', $date, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateStartedDate($id)
    {
        $now = time();
        $status = 1;

        $sql = 'UPDATE todos 
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

    public function updateTodo($id, $todo)
    {
        $sql = 'UPDATE todos 
                SET todo = :title, description = :description
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':title', $todo['title'], \PDO::PARAM_STR);
        $stm->bindParam(':description', $todo['description'], \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function updateDoneDate($id)
    {
        $now = time();
        $status = 2;

        $sql = 'UPDATE todos 
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

    public function updateCancelDate($id)
    {
        $now = time();
        $status = 3;

        $sql = 'UPDATE todos 
                SET status = :status, canceled = :canceled 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':status', $status, \PDO::PARAM_INT);
        $stm->bindParam(':id', $id, \PDO::PARAM_INT);
        $stm->bindParam(':canceled', $now, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function escalateTodo($todoId)
    {
        $now = time();

        $sql = 'UPDATE todos 
                    SET orderNumber = :orderNumber 
                    WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':orderNumber', $now, \PDO::PARAM_INT);
        $stm->bindParam(':id', $todoId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

    }

    public function getUncompleteTodoCount()
    {
        $count = 0;

        $sql = 'SELECT COUNT(*) AS count
                FROM todos
                WHERE status < 2';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $count = $row['count'];
        }

        return $count;
    }
}