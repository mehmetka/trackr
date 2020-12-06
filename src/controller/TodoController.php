<?php

namespace App\controller;

use App\model\TodoModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;

class TodoController extends Controller
{
    private $todosModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->todosModel = new TodoModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $otherTodoList = $this->todosModel->todoList();
        $todoListInTodos = $this->todosModel->getTodos();
        $otherTodoList = array_merge($todoListInTodos, $otherTodoList);

        $data = [
            'data' => $otherTodoList,
            'todos' => $todoListInTodos,
            'activeOneListToRuleThemAll' => 'active'
        ];

        return $this->view->render($response, 'todos.mustache', $data);
    }

    public function get(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $todoId = $args['id'];

        $todo = $this->todosModel->getTodo($todoId);

        $resource = [
            "todo" => $todo,
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $todoId = $args['id'];

        $todo = $this->todosModel->updateTodo($todoId, $params);

        $resource = [
            "todo" => $todo,
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

    public function add(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();
        $this->todosModel->create($params['todo'], $params['description']);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

    public function changeStatus(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $todoId = $args['id'];

        if ($params['status'] == 1) {
            $this->todosModel->updateStartedDate($todoId);
        } elseif ($params['status'] == 2) {
            $this->todosModel->updateDoneDate($todoId);
        }

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(200, $resource);
    }

}