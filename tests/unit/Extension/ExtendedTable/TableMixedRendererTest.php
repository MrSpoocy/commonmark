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

class TableMixedRendererTest extends TestCase
{
    public function testSingleRowspan(): void {
        $string = <<<TABLE
| Headline 1 | Headline 2  | Headline 3 |
|------------|-------------|------------|
| first cell | cell 2      | cell 3     |
|^           || cell 6                  |
|^           | cell 8      | cell 9     |
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
<td rowspan="3">first cell</td>
<td>cell 2</td>
<td>cell 3</td>
</tr>
<tr>
<td colspan="2">cell 6</td>
</tr>
<tr>
<td>cell 8</td>
<td>cell 9</td>
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
