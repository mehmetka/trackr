<?php

namespace App\util;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Extension\DefaultAttributes\DefaultAttributesExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;

class Markdown
{
    private $client;

    public function __construct()
    {
        $config = [
            'table' => [
                'wrap' => [
                    'enabled' => false,
                    'tag' => 'div',
                    'attributes' => [],
                ],
                'alignment_attributes' => [
                    'left' => ['align' => 'left'],
                    'center' => ['align' => 'center'],
                    'right' => ['align' => 'right'],
                ],
            ],
            'default_attributes' => [
                Table::class => [
                    'class' => 'table table-striped table-bordered',
                ],
                Link::class => [
                    'target' => '_blank',
                ],
                Image::class => [
                    'loading' => 'lazy',
                ],
            ],
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TaskListExtension());
        $environment->addExtension(new StrikethroughExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new DefaultAttributesExtension());

        $this->client = new MarkdownConverter($environment);
    }

    public function convert($str)
    {
        $str = str_replace("\n", "   \n", $str);
        return $this->client->convert($str);
    }
}