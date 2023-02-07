<?php

declare(strict_types=1);

namespace League\CommonMark\Extension\ExtendedTable;

use Exception;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Extension\Table\TableCell;
use League\CommonMark\Extension\Table\TableRow;
use League\CommonMark\Extension\Table\TableSection;
use League\CommonMark\Node\Inline\Text;

use function trim;

final class ExtendedTableProcessor
{
    /**
     * Ist der strict mode gesetzt, löst eine ungültige Tabelle eine Exception aus
     */
    private bool $strictMode = false;

    /**
     * @throws Exception
     */
    public function __invoke(DocumentParsedEvent $event): void
    {
        $document = $event->getDocument();
        $walker   = $document->walker();
        while ($event = $walker->next()) {
            $node = $event->getNode();

            // Nur in Tabellen gehen, die wir noch nicht verarbeitet haben
            if (!($node instanceof Table) || !$event->isEntering()) {
                continue;
            }

            $this->parseTable($node);
        }
    }

    private function parseColspan(TableRow $row): void
    {
        // Gehe von links nach rechts, die einzelnen Zellen durch und prüfe, ob diese NULL sind (dann wurde die Pipe's direkt aneinander gelegt)
        $colSpan = 1;
        /**
         * @var TableCell $cell
         */
        foreach ($row->children() as $cell) {
            // Wir haben ein colspan entdeckt
            if ($this->isColSpan($cell)) {
                $cell->detach();
                ++$colSpan;
            } elseif ($colSpan > 1) {
                // Ende des colspan erreicht

                // Wir erzeugen eine neue Cell (und nutzen nicht die bereits existierende Zelle, damit wir keine
                // Formatierung oder der gleichen übernehmen)
//                $replace = new TableCell();
//                $replace->data->append('attributes/colspan', $colSpan);
//
//                // Achtung, in dem Augenblick, in dem wir replaceChildren benutzen, wird $cell verändert (die Kindelemente werden detached)
//                $replace->replaceChildren($cell->children());
//
//                $cell->replaceWith($replace);

                $cell->data->append('attributes/colspan', $colSpan);
                $colSpan = 1;
            }
        }
    }

    private function parseRowSpan(TableRow $row, int $cellIndex): int
    {
        $rowSpan = 0;

        // Gehe aktuelle und allen nachfolgenden Zeilen durch, um zu prüfen, ob es sich um ein
        // rowspan handelt und wie groß dieser ist. Gleichzeitig entferne die rowspan Zellen.
        do {
            if (!$row->hasChildren()) {
                break;
            }

            /** @var TableCell[] $nextChildren */
            $nextChildren = $row->children();
            if (
                // Gib in der nächsten Zeile keine Zelle (z.B. weil das Ende reicht wurde)
                !isset($nextChildren[$cellIndex])
                // Es gibt kein Kindelement (und damit kein ^ möglich)
                || !($child = $nextChildren[$cellIndex]->firstChild())
                // Kindelement ist nicht vom Typ Text
                || !($child instanceof Text)
                // Kein rowspan Zeichen
                || '^' !== trim($child->getLiteral())
            ) {
                break;
            }

            // Beim ersten durchlauf prüfen wir zusätzlich, ob es eine übergeordnete Zeile gibt
            if ($rowSpan === 0 && null === $row->previous()) {
                // Keine übergeordnete Zeile (trifft auch zu, wenn man vom body Teil einer Tabelle
                // in den header wechseln würde)
                if ($this->strictMode) {
                    break;
                } else {
                    throw new \RuntimeException('No parent row available to use rowspan');
                }
            }

            $nextChildren[$cellIndex]->detach();
            $rowSpan++;
        } while ($row = $row->next());

        return $rowSpan;
    }
    /**
     * Leider müssen es mehrere Schleifen sein, es sind aber alles Objekte, weswegen sie als Referenz übergeben werden.
     */
    private function parseTable(Table $table): void
    {
        /** @var TableSection $section */
        foreach ($table->children() as $section) {

            /** @var TableRow $row */
            foreach ($section->children() as $row) {

                if ($section->isHead()) {
                    $this->parseColspan($row);
                    // Im header darf es kein rowspan geben, wohl gleich es aber colspan geben kann!
                    continue;
                }

                $cellOffset = 0;
                /**
                 * @var int $cellIndex
                 * @var TableCell $cell
                 */
                foreach ($row->children() as $cellIndex => $cell) {

                    if ($this->isColSpan($cell)) {
                        $this->parseColspan($row);
                    }

                    $rowSpan = $this->parseRowSpan($row, $cellIndex);
                    if ($rowSpan) {
                        // An dieser Stelle, kann der $cellIndex falsch sein, wenn es in der Tabelle bereits,
                        // zuvor ein rowspan gab. Deswegen müssen wir einen offset mit einfließen lassen.
                        $row->previous()->children()[$cellIndex + $cellOffset]->data->set('attributes/rowspan', (string) ++$rowSpan);
                        ++$cellOffset;
                    }
                }
            }
        }
    }

    private function isColSpan(TableCell $cell): bool
    {
        return !$cell->hasChildren();
    }
}
