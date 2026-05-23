<?php
require_once __DIR__ . '/DrumParser.php';

/**
 * DrumSvg — render parsed drum notation as an inline SVG.
 *
 * Layout per section row:
 *   [LABEL 90px] [opening bar] [measure cells] [phrase gap] [measure cells] ...
 *
 * Each measure cell (BAR_W px wide):
 *   Top zone  (0..BL_TOP)     : symbol area   – dash / X / asterisk
 *   Staff zone (BL_TOP..BASE) : bar lines
 */
class DrumSvg {
    // Layout
    const LABEL_W   = 90;   // px reserved for section label
    const BAR_W     = 24;   // px per measure cell
    const GROUP_GAP = 8;    // extra px between phrase groups
    const ROW_H     = 50;   // px per section row
    const ROW_GAP   = 4;    // px between rows
    const PAD_X     = 10;   // right padding
    const PAD_Y     = 6;    // top/bottom padding

    // Vertical positions within a row (relative to row top)
    const BL_TOP    = 22;   // top of bar lines
    const BASELINE  = 38;   // bottom of bar lines / staff baseline
    const SYM_Y     = 11;   // vertical centre of symbol zone

    public static function render(string $notation): string {
        $sections = DrumParser::parse($notation);
        if (empty($sections)) return '';

        // Find the widest content row to set a uniform SVG width
        $maxW = 0;
        foreach ($sections as $sec) {
            $w = self::rowContentWidth($sec['groups']);
            if ($w > $maxW) $maxW = $w;
        }

        $totalW = self::LABEL_W + $maxW + self::PAD_X;
        $totalH = count($sections) * (self::ROW_H + self::ROW_GAP)
                  - self::ROW_GAP + self::PAD_Y * 2;

        $o  = '<svg xmlns="http://www.w3.org/2000/svg"';
        $o .= ' width="' . $totalW . '" height="' . $totalH . '"';
        $o .= ' viewBox="0 0 ' . $totalW . ' ' . $totalH . '"';
        $o .= ' style="display:block">';

        $y = self::PAD_Y;
        foreach ($sections as $sec) {
            $o .= self::renderRow($sec, $y, $totalW);
            $y += self::ROW_H + self::ROW_GAP;
        }

        $o .= '</svg>';
        return $o;
    }

    // -----------------------------------------------------------------------

    private static function rowContentWidth(array $groups): int {
        $bars = 0;
        foreach ($groups as $g) { $bars += count($g); }
        return $bars * self::BAR_W + max(0, count($groups) - 1) * self::GROUP_GAP;
    }

    private static function renderRow(array $sec, int $ry, int $totalW): string {
        $o      = '';
        $label  = $sec['label'] ?? '';
        $groups = $sec['groups'] ?? [];

        $bt = $ry + self::BL_TOP;
        $bl = $ry + self::BASELINE;
        $sy = $ry + self::SYM_Y;

        // Row label
        if ($label !== '') {
            $o .= '<text x="' . (self::LABEL_W - 5) . '" y="' . ($bl - 2) . '"'
                . ' text-anchor="end"'
                . ' font-family="system-ui,Arial,sans-serif" font-size="11" fill="#777">'
                . htmlspecialchars($label, ENT_XML1) . ':</text>';
        }

        // Faint baseline spanning the whole content area
        $o .= '<line x1="' . self::LABEL_W . '" y1="' . $bl . '"'
            . ' x2="' . ($totalW - self::PAD_X) . '" y2="' . $bl . '"'
            . ' stroke="#252525" stroke-width="1"/>';

        // Render groups
        $x = self::LABEL_W;

        // Opening bar line for the section
        $o .= self::vLine($x, $bt, $bl, '#666', 1.5);

        $lastGroup = count($groups) - 1;
        foreach ($groups as $gi => $group) {
            $lastBar = count($group) - 1;
            foreach ($group as $mi => $type) {
                $cx  = (float)$x + self::BAR_W / 2.0;
                $rx  = $x + self::BAR_W;
                $isLast = ($gi === $lastGroup && $mi === $lastBar);

                switch ($type) {
                    case '-':
                        // Rest / quiet: gray fill + horizontal dash above
                        $o .= self::fill($x, $bt, $bl, '#1a1a1a');
                        $o .= '<line x1="' . ($x + 5) . '" y1="' . $sy
                            . '" x2="' . ($rx - 5) . '" y2="' . $sy
                            . '" stroke="#555" stroke-width="2"/>';
                        $o .= self::vLine($rx, $bt, $bl, '#555', 1);
                        break;

                    case '^':
                        // Cymbal / crash: gold X notehead + thin stem to baseline
                        $d = 5;
                        $o .= '<line x1="' . ($cx - $d) . '" y1="' . ($sy - $d)
                            . '" x2="' . ($cx + $d) . '" y2="' . ($sy + $d)
                            . '" stroke="#c8a000" stroke-width="2"/>';
                        $o .= '<line x1="' . ($cx + $d) . '" y1="' . ($sy - $d)
                            . '" x2="' . ($cx - $d) . '" y2="' . ($sy + $d)
                            . '" stroke="#c8a000" stroke-width="2"/>';
                        $o .= '<line x1="' . $cx . '" y1="' . ($sy + $d + 1)
                            . '" x2="' . $cx . '" y2="' . $bl
                            . '" stroke="#c8a000" stroke-width="1" opacity="0.3"/>';
                        $o .= self::vLine($rx, $bt, $bl, '#555', 1);
                        break;

                    case '*':
                        // Brake / break: red tint + 4-spoke asterisk + double closing bar
                        $o .= self::fill($x, $bt, $bl, 'rgba(204,0,0,0.07)');
                        $d  = 5.0;
                        $d2 = 3.5;
                        foreach ([
                            [$cx,      $sy - $d,  $cx,      $sy + $d ],
                            [$cx - $d, $sy,       $cx + $d, $sy      ],
                            [$cx - $d2,$sy - $d2, $cx + $d2,$sy + $d2],
                            [$cx + $d2,$sy - $d2, $cx - $d2,$sy + $d2],
                        ] as [$x1,$y1,$x2,$y2]) {
                            $o .= '<line x1="' . $x1 . '" y1="' . $y1
                                . '" x2="' . $x2 . '" y2="' . $y2
                                . '" stroke="#cc0000" stroke-width="1.5"/>';
                        }
                        // Double bar (thick + thin) at the right edge
                        $o .= self::vLine($rx - 2, $bt, $bl, '#cc0000', 3.0);
                        $o .= self::vLine($rx + 1.5, $bt, $bl, '#cc0000', 1.0);
                        break;

                    case '|':
                    default:
                        // Normal bar: closing bar line, slightly thicker if last in section
                        $o .= self::vLine($rx, $bt, $bl,
                            $isLast ? '#777' : '#555',
                            $isLast ? 1.5   : 1.0);
                        break;
                }

                $x = $rx;
            }

            // After each group (except the last): gap + opening bar of next group
            if ($gi < $lastGroup) {
                $x += self::GROUP_GAP;
                $o .= self::vLine($x, $bt, $bl, '#555', 1.0);
            }
        }

        return $o;
    }

    // -----------------------------------------------------------------------

    private static function vLine(float $x, int $y1, int $y2, string $stroke, float $w): string {
        return '<line x1="' . $x . '" y1="' . $y1 . '" x2="' . $x . '" y2="' . $y2
             . '" stroke="' . $stroke . '" stroke-width="' . $w . '"/>';
    }

    private static function fill(int $x, int $bt, int $bl, string $color): string {
        $h = $bl - $bt;
        return '<rect x="' . ($x + 0.5) . '" y="' . $bt . '" width="' . (self::BAR_W - 0.5)
             . '" height="' . $h . '" fill="' . $color . '"/>';
    }
}
