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
         * @var int       $i
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

    /**
     * Leider müssen es mehrere Schleifen sein, es sind aber alles Objekte, weswegen sie als Referenz übergeben werden.
     *
     *
     */
    private function parseTable(Table $table): void
    {
        /** @var TableSection $section */
        foreach ($table->children() as $section) {

            if ($section->isHead()) {
                $this->parseHeaderSection($section);
            } else {


                /** @var TableRow $row */
                foreach ($section->children() as $rowIndex => $row) {

                    if (!$row->hasChildren()) {
                        continue;
                    }

                    $offset = 0;

                    foreach ($row->children() as $cellIndex => $cell) {
                        if ($this->isColSpan($cell)) {
                            $this->parseColspan($row);
                        }

                        // $cell kann bereits ein colspan sein!
                        if (!$this->isRowSpan($cell)) {
                            continue;
                        }

                        /** @var TableRow|null $parent */
                        $previous = $row->previous();
                        // Keine übergeordnete Zeile (trifft auch zu, wenn man vom body Teil einer Tabelle in den
                        // header wechseln würde)
                        if (null === $previous) {
                            if ($this->strictMode) {
                                continue;
                            } else {
                                throw new Exception('No parent row available to use rowspan');
                            }
                        }

                        // An dieser Stelle ist es bereits eine Zelle über 2 Zeilen.
                        $rowSpan = 2;

                        // Gehe allen nachfolgenden Zeilen durch, um die größe des rowspan zu ermitteln, und
                        // entferne die Zelle.
                        $next = $row;
                        while ($next = $next->next()) {

                            if (!$next->hasChildren()) {
                                break;
                            }

                            $nextChildren = $next->children();
                            if (!isset($nextChildren[$cellIndex])) {
                                break;
                            }

                            // Zum besseren debug, eigene if Bedingung
                            if (!($child = $nextChildren[$cellIndex]->firstChild()) || !($child instanceof Text) || '^' !== trim($child->getLiteral())) {
                                break;
                            }

                            $nextChildren[$cellIndex]->detach();
                            $rowSpan++;
                        }

                        // Entferne auch $cell
                        $cell->detach();

                        // An dieser Stelle, kann der $cellIndex falsch sein, wenn es in der Tabelle bereits,
                        // zuvor ein rowspan gab. Deswegen müssen wir einen offset mit einfließen lassen.
                        $previous->children()[$cellIndex]->data->set('attributes/rowspan', (string) $rowSpan);
                        ++$offset;
                    }
                }
            }
        }
    }

    /**
     * Im header darf es kein rowspan geben, wohl gleich es aber colspan geben kann!
     *
     * @return void
     */
    private function parseHeaderSection(TableSection $section): void {

        // iterator_apply($section->children(), fn($row) =>  $this->parseColspan($row));

         /** @var TableRow $row */
        foreach ($section->children() as $row) {
            $this->parseColspan($row);
        }
    }

    private function isRowSpan(TableCell $cell): bool
    {
        /*
        return (
            // Mehr als 1 Kind-element, bedeutet das mehr als "^" im Text stehen würde
            count($cell->children()) !== 1
            // Die Zuweisung kann an sich niemals fehlschlagen (aufgrund der Bedingung zuvor)
            || !($child = $cell->firstChild())
            // Es muss ein einfaches Text-Element sein
            || !($child instanceof Text)
            // trim ist nötig, weil der Benutzer die Tabelle formatiert haben könnte
            || \trim($child->getLiteral()) != '^'
        );
        */

        // Wir müssen noch prüfen, ob ein ^ in der Zelle steht für rowspan
        if (1 !== count($cell->children()) || !($child = $cell->firstChild()) || !($child instanceof Text)) {
            return false;
        }

        // trim ist nötig, weil der Benutzer die Tabelle formatiert haben könnte
        return '^' === trim($child->getLiteral());
    }

    private function isColSpan(TableCell $cell): bool
    {
        return !$cell->hasChildren();
    }
}
