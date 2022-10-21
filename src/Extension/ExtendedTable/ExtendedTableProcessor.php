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

    /**
     * Also das ist jetzt nicht gerade die schönste Lösung, 3 fache Schleifen Verschachtelung.
     * Ein anderer Ansatz könnte sein, nur in parseRowspan zu springen und dort immer colspan mitzuverarbeiten.
     *
     * @throws Exception
     */
    private function parseTable(Table $table): void
    {
        /** @var TableSection $section */
        foreach ($table->children() as $section) {
            // Wie verarbeiten die Tabelle von unten nach oben, dass ermöglicht ein korrektes
            // Verarbeiten von rowspan.
            $reverse = array_reverse($section->children());

            /** @var TableRow $row */
            foreach ($reverse as $row) {
                $this->parseColspan($row);
            }

            /** @var TableRow $row */
            foreach ($reverse as $row) {
                $this->parseRowspan($row);
            }
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
            // Ignoriere Tabellenkopf
            if (TableCell::TYPE_HEADER === $cell->getType()) {
                // TODO: Was wenn in der Mitte der Tabelle ein neuer Tabellen Kopf kommt und wir bereits in einer col/row-span Sequenz sind?
                continue;
            }

            // Wir haben ein colspan entdeckt
            if ($this->isColSpan($cell)) {
                $cell->detach();
                ++$colSpan;
            } elseif ($colSpan > 1) {
                // Ende des colspan erreicht

                // Wir erzeugen eine neue Cell (und nutzen nicht die bereits existierende Zelle, damit wir keine
                // Formatierung oder der gleichen übernehmen)
                $replace = new TableCell();
                $replace->data->append('attributes/colspan', $colSpan);

                // Achtung, in dem Augenblick, in dem wir replaceChildren benutzen, wird $cell verändert (die Kindelemente werden detached)
                $replace->replaceChildren($cell->children());

                $cell->replaceWith($replace);
                $colSpan = 1;
            }
        }
    }

    /**
     * @throws Exception
     */
    private function parseRowspan(TableRow $row): void
    {
        /**
         * @var int       $i
         * @var TableCell $cell
         */
        foreach ($row->children() as $cellIndex => $cell) {
            $currentRow = $row;
            $rowSpan    = 1;
            while ($this->isRowSpan($cell)) {
                if (!$cell->parent()) {
                    throw new Exception('Something crazy');
                }

                // Vorherige Zeile
                $currentRow = $currentRow->previous();

                // Wenn z.b. im Header ^ benutzt wurde, dann gibt es keine vorherige Zeile.
                // Oder in der ersten Zeile nach dem Header.
                if (!($currentRow instanceof TableRow)) {
                    // Damit die Tabelle nicht komplett zerstört wird (weil wir die vorherigen Zellen bereits entfernt haben),
                    // brechen wir nur die while-schleife ab und setzen dennoch das rowspan.
                    // Dann steht zwar in der Zelle ein "^" aber die Tabelle passt von ihrer Geometrie noch.
                    break;
                }

                // Alle Zellen der vorherigen Zeile
                $children = $currentRow->children();

                // Äh nö, Tabelle ist unlogisch
                if ($cellIndex >= count($children)) {
                    // Hier das gleiche wie bei der Bedingung zuvor
                    break;
                }

                ++$rowSpan;

                // Wir müssen die Zelle entfernen (egal ob die Zeile darüber ein weiteres rowspan ist oder nicht)
                $cell->detach();

                // Ist in der betroffenen Zelle, auch ein einzelnes "^", gehe eine höher
                $cell = $children[$cellIndex];
            }

            if ($rowSpan > 1) {
                $cell->data->set('attributes/rowspan', (string) $rowSpan);
            }
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
