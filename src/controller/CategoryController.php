<?php

namespace App\controller;

use App\model\CategoryModel;
use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\Http\StatusCode;

class CategoryController extends Controller
{
    private $categoryModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->categoryModel = new CategoryModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $categories = $this->categoryModel->getCategories();
        $defaultCategory = $this->categoryModel->getDefaultCategory();

        if (!$defaultCategory) {
            $defaultCategoryId = $this->categoryModel->createCategory('default');
            $this->categoryModel->resetCategoriesDefaultStatus();
            $this->categoryModel->setDefaultCategory($defaultCategoryId, 1);
        }

        $data = [
            'title' => 'Categories | trackr',
            'categories' => $categories,
            'activeCategories' => 'active'
        ];

        return $this->view->render($response, 'categories.mustache', $data);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $params = $request->getParsedBody();
        $this->categoryModel->createCategory($params['category']);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $defaultCategory = $this->categoryModel->getDefaultCategory();
        $this->categoryModel->deleteCategory($args['categoryId']);

        if ($defaultCategory['id'] == $args['categoryId']) {
            $defaultCategoryId = $this->categoryModel->createCategory('default');
            $this->categoryModel->resetCategoriesDefaultStatus();
            $this->categoryModel->setDefaultCategory($defaultCategoryId, 1);
            // $this->categoryModel->changeBooksCategoryByGivenCategory($args['categoryId'], $defaultCategoryId);
        } else {
            // $this->categoryModel->changeBooksCategoryByGivenCategory($args['categoryId'], $defaultCategory['id']);
        }

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

    public function setDefault(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $categoryId = $args['categoryId'];
        $active = 1;

        $this->categoryModel->resetCategoriesDefaultStatus();
        $this->categoryModel->setDefaultCategory($categoryId, $active);

        $resource = [
            "message" => "Success!"
        ];

        return $this->response(StatusCode::HTTP_OK, $resource);
    }

}