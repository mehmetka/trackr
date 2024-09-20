<?php

namespace App\util\markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\DefaultAttributes\DefaultAttributesExtension;
use League\CommonMark\Extension\Mention\MentionExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;

class Markdown
{
    private $client;

    public function __construct()
    {
        $config = [
            'mentions' => [
                'highlight_id' => [
                    'prefix' => '#',
                    'pattern' => '\d+',
                    'generator' => "/highlights?id=%d"
                ],
                'highlight_tag' => [
                    'prefix' => '#',
                    'pattern' => '\w+',
                    'generator' => "/highlights?tag=%s"
                ],
            ],
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
        $environment->addExtension(new MentionExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addRenderer(Link::class, new CustomLinkRenderer());

        $this->client = new MarkdownConverter($environment);
    }

    public function convert($str)
    {
        $str = str_replace("\n", "   \n", $str);
        return $this->client->convert($str);
    }
}