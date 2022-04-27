<?php

namespace App\model;

use App\util\Util;
use Psr\Container\ContainerInterface;
use App\exception\CustomException;

class CategoryModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function getCategories($selectedID = null)
    {
        $sql = 'SELECT id, name, defaultStatus 
                FROM categories';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            if($selectedID && $selectedID == $row['id']){
                $row['selected'] = true;
            }

            if (!$selectedID && $row['defaultStatus']) {
                $row['selected'] = true;
            }

            $list[] = $row;
        }

        return $list;
    }

    public function getBookmarkCategoriesAsHTML($category = null)
    {
        $sql = 'SELECT DISTINCT c.id, c.name
                FROM bookmarks b
                INNER JOIN categories c ON b.categoryId = c.id';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        $list = [];

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {

            $row['badge'] = 'info';
            $row['href'] = $row['name'];

            if ($category !== null && $category == $row['name']) {
                $row['href'] = '';
                $row['badge'] = 'primary';
            }

            $list[] = $row;
        }

        return $list;
    }

    public function createCategory($categoryName)
    {
        $created = time();

        $sql = 'INSERT INTO categories (name, created) 
                VALUES(:name, :created)';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':name', $categoryName, \PDO::PARAM_STR);
        $stm->bindParam(':created', $created, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return $this->dbConnection->lastInsertId();
    }

    public function deleteCategory($categoryId)
    {
        $sql = 'DELETE FROM categories WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $categoryId, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function resetCategoriesDefaultStatus()
    {
        $sql = 'UPDATE categories 
                SET defaultStatus = 0';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function setDefaultCategory($categoryId, $defaultStatus)
    {
        $sql = 'UPDATE categories 
                SET defaultStatus = :default 
                WHERE id = :id';

        $stm = $this->dbConnection->prepare($sql);
        $stm->bindParam(':id', $categoryId, \PDO::PARAM_INT);
        $stm->bindParam(':default', $defaultStatus, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

    public function getDefaultCategory()
    {
        $defaultCategory = [];

        $sql = 'SELECT id, name, defaultStatus, created
                FROM categories 
                WHERE defaultStatus = 1';

        $stm = $this->dbConnection->prepare($sql);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $defaultCategory = $row;
        }

        return $defaultCategory;
    }
}
