<?php

namespace App\controller;

use App\model\ImageModel;
use Slim\Http\StatusCode;
use Psr\Container\ContainerInterface;
use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\ServerRequestInterface;

class ImageController extends Controller
{
    private $imageModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->imageModel = new ImageModel($container);
    }

    public function upload(ServerRequestInterface $request, ResponseInterface $response)
    {
        $directory = $_SERVER['DOCUMENT_ROOT'] . '/img'; // public/img

        $uploadedFiles = $request->getUploadedFiles();

        $uploadedFile = $uploadedFiles['file'];
        if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
            $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
            $basename = bin2hex(random_bytes(8));
            $filename = sprintf('%s.%0.8s', $basename, $extension);

            $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
            $this->imageModel->insert($filename);
        }

        $data = [
            'filename' => '/img/' . $filename
        ];

        return $this->response(StatusCode::HTTP_OK, $data);
    }
}
