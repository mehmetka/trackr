<?php

namespace App\util\markdown;

use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

class CustomLinkRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer)
    {
        if (!($node instanceof Link)) {
            throw new \InvalidArgumentException('Incompatible node type: ' . get_class($node));
        }

        $attrs = [
            'href' => $node->getUrl(),
            'target' => '_blank',
        ];

        if (str_starts_with($node->getUrl(), '/highlights?id=')) {
            $parsedUrl = parse_url($node->getUrl());
            parse_str($parsedUrl['query'], $queryParams);

            if (isset($queryParams['id']) && filter_var($queryParams['id'], FILTER_VALIDATE_INT)) {
                $attrs['class'] = 'highlightToolTip';
                $attrs['data-toggle'] = 'tooltip';
                $attrs['data-placement'] = 'top';
                $attrs['data-id'] = $queryParams['id'];
                $attrs['title'] = '';
            }
        }

        $content = $childRenderer->renderNodes($node->children());

        return new HtmlElement('a', $attrs, $content);
    }
}