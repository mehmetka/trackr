<?php

namespace App\model;

use Psr\Container\ContainerInterface;
use App\exception\CustomException;

class AuthModel
{
    /** @var \PDO $dbConnection */
    private $dbConnection;

    public function __construct(ContainerInterface $container)
    {
        $this->dbConnection = $container->get('db');
    }

    public function login($username, $password)
    {
        $password = hash('sha512', $password);

        $sql = 'SELECT id
                FROM users
                WHERE username =:username AND password =:password';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':username', $username, \PDO::PARAM_STR);
        $stm->bindParam(':password', $password, \PDO::PARAM_STR);

        if (!$stm->execute()) {
            throw CustomException::dbError(500, 'Something went wrong');
        }

        if (!$stm->rowCount()) {
            throw CustomException::clientError(401, 'Credentials are incorrect!', 'Credentials are incorrect!');
        }

        session_regenerate_id(true);

        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $_SESSION['userInfos']['user_id'] = $row['id'];
        }

        $_SESSION['userInfos']['username'] = $username;

        return true;
    }

    public function register($username, $password)
    {
        $password = hash('sha512', $password);
        $created = time();

        $sql = 'INSERT INTO users (username, password, created) 
                VALUES(:username, :password, :created)';

        $stm = $this->dbConnection->prepare($sql);

        $stm->bindParam(':username', $username, \PDO::PARAM_STR);
        $stm->bindParam(':password', $password, \PDO::PARAM_STR);
        $stm->bindParam(':created', $created, \PDO::PARAM_INT);

        if (!$stm->execute()) {
            throw CustomException::dbError(503, json_encode($stm->errorInfo()));
        }

        return true;
    }

}