<?php
require_once __DIR__ . '/DrumParser.php';

/**
 * DrumSvg — renders multi-section drum notation as a crisp inline SVG.
 *
 * Visual zones within each row (from top):
 *   0 … STAFF_T  : annotation zone  — rest line, crash caret, brake asterisk
 *   STAFF_T … BL : staff zone       — bar lines
 *   BL … ROW_H   : below margin
 *
 * Grouping is shown by a clearly wider gap between groups (GROUP_GAP > BAR_W).
 */
class DrumSvg {
    // ── Layout ──────────────────────────────────────────────────────────
    const LABEL_W   = 90;   // px — label column width
    const BAR_W     = 18;   // px — width of one measure cell
    const GROUP_GAP = 30;   // px — gap between phrase groups (> BAR_W → clearly visible)
    const ROW_H     = 52;   // px — height per section row
    const ROW_GAP   = 8;    // px — vertical gap between rows
    const PAD_X     = 14;   // px — right padding
    const PAD_Y     = 10;   // px — top/bottom padding

    // ── Vertical positions within a row (relative to row top) ───────────
    const ANNOT_Y   = 12;   // y-centre of annotation zone (caret / rest / brake)
    const STAFF_T   = 26;   // y where bar lines begin
    const STAFF_B   = 44;   // y where bar lines end (= baseline)

    // ── Colors ──────────────────────────────────────────────────────────
    const C_BAR     = '#888';    // internal bar line
    const C_BAR_OPN = '#aaa';    // opening / closing bar of group or section
    const C_BASELINE= '#2e2e2e'; // horizontal baseline
    const C_LABEL   = '#777';    // section label text
    const C_REST    = '#cc0000'; // rest / quiet  (-)  → RED
    const C_CRASH   = '#c8a000'; // cymbal / crash (^) → GOLD
    const C_BRAKE   = '#ff6600'; // brake / break  (*) → ORANGE

    // ────────────────────────────────────────────────────────────────────

    public static function render(string $notation): string {
        $sections = DrumParser::parse($notation);
        if (empty($sections)) return '';

        // Calculate SVG dimensions
        $maxContentW = 0;
        foreach ($sections as $sec) {
            $w = self::contentWidth($sec['groups']);
            if ($w > $maxContentW) $maxContentW = $w;
        }

        $totalW = self::LABEL_W + $maxContentW + self::PAD_X;
        $n      = count($sections);
        $totalH = self::PAD_Y * 2 + $n * self::ROW_H + ($n - 1) * self::ROW_GAP;

        $o  = '<svg xmlns="http://www.w3.org/2000/svg"';
        $o .= ' width="' . $totalW . '" height="' . $totalH . '"';
        $o .= ' viewBox="0 0 ' . $totalW . ' ' . $totalH . '"';
        $o .= ' style="display:block">';

        $ry = self::PAD_Y;
        foreach ($sections as $sec) {
            $o .= self::renderRow($sec, $ry, $totalW);
            $ry += self::ROW_H + self::ROW_GAP;
        }

        $o .= '</svg>';
        return $o;
    }

    // ── Private helpers ──────────────────────────────────────────────────

    private static function contentWidth(array $groups): int {
        $bars = 0;
        foreach ($groups as $g) $bars += count($g);
        return $bars * self::BAR_W + max(0, count($groups) - 1) * self::GROUP_GAP;
    }

    private static function renderRow(array $sec, int $ry, int $totalW): string {
        $o      = '';
        $label  = $sec['label'] ?? '';
        $groups = $sec['groups'] ?? [];
        if (empty($groups)) return '';

        // Absolute vertical coords
        $bt  = $ry + self::STAFF_T;
        $bl  = $ry + self::STAFF_B;
        $ay  = $ry + self::ANNOT_Y;

        // ── Label ──
        if ($label !== '') {
            $o .= '<text'
                . ' x="' . (self::LABEL_W - 5) . '"'
                . ' y="' . ($bl - 3) . '"'
                . ' text-anchor="end"'
                . ' font-family="system-ui,Arial,sans-serif"'
                . ' font-size="11"'
                . ' fill="' . self::C_LABEL . '">'
                . htmlspecialchars($label, ENT_XML1) . ':</text>';
        }

        // ── Thin baseline extending to the right ──
        $o .= '<line'
            . ' x1="' . self::LABEL_W . '" y1="' . $bl . '"'
            . ' x2="' . ($totalW - self::PAD_X) . '" y2="' . $bl . '"'
            . ' stroke="' . self::C_BASELINE . '" stroke-width="1"/>';

        // ── Build flat measure map (type + absolute x positions) ──
        // needed for: bar-line loop, rest-span detection, symbol rendering
        $flat = []; // [['type'=>char, 'lx'=>float, 'rx'=>float], ...]
        $x    = (float)self::LABEL_W;
        foreach ($groups as $gi => $group) {
            if ($gi > 0) $x += self::GROUP_GAP;
            foreach ($group as $type) {
                $flat[] = ['type' => $type, 'lx' => $x, 'rx' => $x + self::BAR_W];
                $x += self::BAR_W;
            }
        }

        // ── Detect consecutive rest ('-') spans (can cross group gap) ──
        $restSpans = [];
        $spanLx    = null;
        $spanRx    = null;
        foreach ($flat as $m) {
            if ($m['type'] === '-') {
                if ($spanLx === null) $spanLx = $m['lx'];
                $spanRx = $m['rx'];
            } else {
                if ($spanLx !== null) {
                    $restSpans[] = [$spanLx, $spanRx];
                    $spanLx      = null;
                }
            }
        }
        if ($spanLx !== null) $restSpans[] = [$spanLx, $spanRx];

        // ── Draw bar lines ──
        $x   = (float)self::LABEL_W;
        $ngr = count($groups);
        $o  .= self::vLine($x, $bt, $bl, self::C_BAR_OPN, 2.0); // section opening bar

        foreach ($groups as $gi => $group) {
            if ($gi > 0) {
                $x += self::GROUP_GAP;
                $o .= self::vLine($x, $bt, $bl, self::C_BAR_OPN, 2.0); // group opening bar
            }
            $isLastGroup = ($gi === $ngr - 1);
            $nm          = count($group);

            foreach ($group as $mi => $type) {
                $rx     = $x + self::BAR_W;
                $isLast = $isLastGroup && ($mi === $nm - 1);

                if ($type === '*') {
                    // Brake: replace closing bar with double bar (thick + thin)
                    $o .= self::vLine($rx - 1.5, $bt, $bl, self::C_BRAKE, 3.0);
                    $o .= self::vLine($rx + 1.5, $bt, $bl, self::C_BRAKE, 1.0);
                } else {
                    $o .= self::vLine($rx, $bt, $bl,
                        $isLast ? self::C_BAR_OPN : self::C_BAR,
                        $isLast ? 2.0            : 1.5);
                }
                $x = $rx;
            }
        }

        // ── Draw rest spans — one continuous RED line per consecutive run ──
        foreach ($restSpans as [$x1, $x2]) {
            $o .= '<line'
                . ' x1="' . $x1 . '" y1="' . $ay . '"'
                . ' x2="' . $x2 . '" y2="' . $ay . '"'
                . ' stroke="' . self::C_REST . '" stroke-width="3"'
                . ' stroke-linecap="square"/>';
        }

        // ── Draw crash (^) and brake (*) symbols ──
        foreach ($flat as $m) {
            $cx = $m['lx'] + self::BAR_W / 2.0;

            if ($m['type'] === '^') {
                // Gold inverted-V caret (dakje)
                $hw = 7; $hh = 7;
                $o .= '<polyline'
                    . ' points="' . ($cx - $hw) . ',' . ($ay + $hh)
                    . ' '         . $cx          . ',' . ($ay - $hh)
                    . ' '         . ($cx + $hw)  . ',' . ($ay + $hh) . '"'
                    . ' fill="none"'
                    . ' stroke="' . self::C_CRASH . '"'
                    . ' stroke-width="2.5"'
                    . ' stroke-linejoin="miter"/>';

            } elseif ($m['type'] === '*') {
                // Orange 4-spoke asterisk
                $d  = 6.0;
                $d2 = 4.2;
                foreach ([
                    [$cx,       $ay - $d,  $cx,       $ay + $d ],
                    [$cx - $d,  $ay,       $cx + $d,  $ay      ],
                    [$cx - $d2, $ay - $d2, $cx + $d2, $ay + $d2],
                    [$cx + $d2, $ay - $d2, $cx - $d2, $ay + $d2],
                ] as [$x1, $y1, $x2, $y2]) {
                    $o .= '<line'
                        . ' x1="' . $x1 . '" y1="' . $y1 . '"'
                        . ' x2="' . $x2 . '" y2="' . $y2 . '"'
                        . ' stroke="' . self::C_BRAKE . '" stroke-width="2"'
                        . ' stroke-linecap="round"/>';
                }
            }
        }

        return $o;
    }

    private static function vLine(float $x, int $y1, int $y2, string $stroke, float $w): string {
        return '<line'
            . ' x1="' . $x . '" y1="' . $y1 . '"'
            . ' x2="' . $x . '" y2="' . $y2 . '"'
            . ' stroke="' . $stroke . '" stroke-width="' . $w . '"/>';
    }
}
