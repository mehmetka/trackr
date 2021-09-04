<?php

namespace App\controller;

use App\model\TodoModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class TodoController extends Controller
{
    private $todoModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->todoModel = new TodoModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $todoListInTodos = $this->todoModel->getTodos();

        $data = [
            'todos' => $todoListInTodos,
            'activeTodos' => 'active'
        ];

        return $this->view->render($response, 'todos.mustache', $data);
    }

    public function allTodos(ServerRequestInterface $request, ResponseInterface $response)
    {
        $allTodos = $this->todoModel->getAllTodos();

        $data = [
            'data' => $allTodos,
            'activeOneListToRuleThemAll' => 'active'
        ];

        return $this->view->render($response, 'all-todos.mustache', $data);
    }

    public function get(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $todoId = $args['id'];

        $todo = $this->todoModel->getTodo($todoId);

        $resource = [
            "todo" => $todo,
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $todoId = $args['id'];

        $todo = $this->todoModel->updateTodo($todoId, $params);

        $resource = [
            "todo" => $todo,
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $todoId = $args['id'];

        $this->todoModel->deleteTodo($todoId, $params);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function add(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();
        
        if(isset($params['todo']) && $params['todo']){
            $this->todoModel->create($params['todo'], $params['description']);
            unset($_SESSION['badgeCounts']);
            $resource['message'] = "Success";
            $resource['statusCode'] = StatusCode::HTTP_CREATED;
        } else {
            $resource['message'] = "Todo cannot be null!";
            $resource['statusCode'] = StatusCode::HTTP_BAD_REQUEST;
        }
        
        return $this->response($resource['statusCode'], $resource);
    }

    public function changeStatus(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $todoId = $args['id'];

        if ($params['status'] == 1) {
            $this->todoModel->updateStartedDate($todoId);
        } elseif ($params['status'] == 2) {
            $this->todoModel->updateDoneDate($todoId);
            unset($_SESSION['badgeCounts']);
        } elseif ($params['status'] == 3) {
            $this->todoModel->updateCancelDate($todoId);
            unset($_SESSION['badgeCounts']);
        } elseif ($params['status'] == 4) {
            $this->todoModel->updateStatus($todoId, 4);
            unset($_SESSION['badgeCounts']);
        }

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function escalateTodo(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $todoId = $args['id'];

        $this->todoModel->escalateTodo($todoId);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

}