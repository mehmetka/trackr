<?php

namespace App\controller;

use App\enum\ChainTypes;
use App\exception\CustomException;
use App\model\ChainModel;
use App\util\ValidatorUtil;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\StatusCode;

class ChainController extends Controller
{
    private $chainModel;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->chainModel = new ChainModel($container);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response)
    {
        $today = date('Y-m-d');
        $data['pageTitle'] = "Chains | trackr";
        $data['activeChains'] = 'active';

        $data['chains'] = $this->chainModel->getChains();

        foreach ($data['chains'] as $key => $chain) {
            $todaysLink = $this->chainModel->getLinkByChainIdAndDate($chain['chainId'], $today);

            if (!$todaysLink) {
                continue;
            }

            if ($chain['chainType'] === ChainTypes::BOOLEAN->value) {
                $data['chains'][$key]['todaysLinkValue'] = $todaysLink['linkValue'] ? 'checked' : '';
            } else {
                $data['chains'][$key]['todaysLinkValue'] = $todaysLink['linkValue'];
            }

            $data['chains'][$key]['link'] = $todaysLink;
        }

        return $this->view->render($response, 'chains/index.mustache', $data);
    }

    public function start(ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = $request->getParsedBody();

        if (!isset($params['chainName']) || !$params['chainName']) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Name is not valid.");
        }

        if (!isset($params['chainType']) || !$params['chainType'] || !in_array((int)$params['chainType'],
                ChainTypes::toArray())) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Type is not valid.");
        }

        $this->chainModel->start($params['chainName'], $params['chainType']);

        $resource['responseCode'] = StatusCode::HTTP_CREATED;
        $resource['message'] = "Started a new chain successfully";

        return $this->response($resource['responseCode'], $resource);
    }

    public function addLink(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $today = date('Y-m-d');
        $params = $request->getParsedBody();
        $chainUid = $args['uid'];
        $chain = $this->chainModel->getChainByUid($chainUid);

        if (!$chain) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Chain not found.");
        }

        $link = $this->chainModel->getLinkByChainIdAndDate($chain['chainId'], $today);

        if (!ValidatorUtil::validateLinkValueByType($chain['chainType'], $params['value'])) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Value is not valid.");
        }

        if ($link) {
            $this->chainModel->updateLink($link['linkId'], $chain['chainId'], $params['value'], $params['note']);
            $resource['message'] = "Link updated successfully.";
        } else {
            $this->chainModel->addLink($chain['chainId'], $today, $params['value'], $params['note']);
            $resource['message'] = "New link created successfully.";
        }

        $resource['responseCode'] = StatusCode::HTTP_CREATED;

        return $this->response($resource['responseCode'], $resource);
    }

    public function getChainGraphicData(ServerRequestInterface $request, ResponseInterface $response, $args)
    {
        $chainUid = $args['uid'];
        $chain = $this->chainModel->getChainByUid($chainUid);

        if (!$chain) {
            throw CustomException::clientError(StatusCode::HTTP_BAD_REQUEST, "Chain not found.");
        }

        $graphicDatas = $this->chainModel->getChainGraphicData($chain);

        $resource['data'] = $graphicDatas;
        $resource['responseCode'] = StatusCode::HTTP_OK;

        return $this->response($resource['responseCode'], $resource);
    }

}