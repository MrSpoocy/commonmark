<?php

namespace League\CommonMark\Tests\Unit\Extension\ExtendedTable;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExtendedTable\ExtendedTableProcessor;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use PHPUnit\Framework\TestCase;

class TableColspanRendererTest extends TestCase
{

    public function testSingleColspan(): void {
        $string = <<<TABLE
| Headline 1 | Headline 2  | Headline 3 |
|------------|-------------|------------|
| first cell || colspan cell            |
| last cell  | last cell   |            |
TABLE;

        $expected = <<<HTML
<table>
<thead>
<tr>
<th>Headline 1</th>
<th>Headline 2</th>
<th>Headline 3</th>
</tr>
</thead>
<tbody>
<tr>
<td>first cell</td>
<td colspan="2">colspan cell</td>
</tr>
<tr>
<td>last cell</td>
<td>last cell</td>
<td></td>
</tr>
</tbody>
</table>

HTML;

        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        $environment->addEventListener(DocumentParsedEvent::class, new ExtendedTableProcessor());


        $parser   = new MarkdownParser($environment);
        $renderer = new HtmlRenderer($environment);

        $document = $parser->parse($string);

        $html = (string) $renderer->renderDocument($document);

        $this->assertSame($expected, $html);
    }

    public function testMultipleColspan(): void {
        $string = <<<TABLE
| Headline 1 | Headline 2  | Headline 3 | Headline 4 |
|------------|-------------|------------|------------|
| first cell ||| colspan cell                        |
| last cell  | last cell   |            |            |
|| colspan cell            |            |            |
TABLE;

        $expected = <<<HTML
<table>
<thead>
<tr>
<th>Headline 1</th>
<th>Headline 2</th>
<th>Headline 3</th>
<th>Headline 4</th>
</tr>
</thead>
<tbody>
<tr>
<td>first cell</td>
<td colspan="3">colspan cell</td>
</tr>
<tr>
<td>last cell</td>
<td>last cell</td>
<td></td>
<td></td>
</tr>
<tr>
<td colspan="2">colspan cell</td>
<td></td>
<td></td>
</tr>
</tbody>
</table>

HTML;

        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        $environment->addEventListener(DocumentParsedEvent::class, new ExtendedTableProcessor());


        $parser   = new MarkdownParser($environment);
        $renderer = new HtmlRenderer($environment);

        $document = $parser->parse($string);

        $html = (string) $renderer->renderDocument($document);

        $this->assertSame($expected, $html);
    }

    public function testHeadlineSingleColspan(): void {
        $string = <<<TABLE
| Headline 1 || Headline 2              |
|------------|-------------|------------|
| last cell  | last cell   |            |
TABLE;

        $expected = <<<HTML
<table>
<thead>
<tr>
<th>Headline 1</th>
<th colspan="2">Headline 2</th>
</tr>
</thead>
<tbody>
<tr>
<td>last cell</td>
<td>last cell</td>
<td></td>
</tr>
</tbody>
</table>

HTML;

        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        $environment->addEventListener(DocumentParsedEvent::class, new ExtendedTableProcessor());


        $parser   = new MarkdownParser($environment);
        $renderer = new HtmlRenderer($environment);

        $document = $parser->parse($string);

        $html = (string) $renderer->renderDocument($document);

        $this->assertSame($expected, $html);
    }

}
