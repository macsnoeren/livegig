<?php
require_once __DIR__ . '/DrumParser.php';

class DrumSvg {
    const BAR_W    = 28;   // px per measure
    const SVG_H    = 44;   // total SVG height
    const BASELINE = 34;   // y of staff baseline
    const BL_TOP   = 22;   // y where barlines start
    const SYM_Y    = 12;   // y-center of symbol zone

    public static function render(string $notation): string {
        $beats = DrumParser::parse($notation);
        $n = count($beats);
        if (!$n) return '';

        $W  = $n * self::BAR_W + 1;
        $H  = self::SVG_H;
        $bl = self::BASELINE;
        $bt = self::BL_TOP;
        $sy = self::SYM_Y;
        $bw = self::BAR_W;

        $o  = '<svg xmlns="http://www.w3.org/2000/svg"';
        $o .= ' width="' . $W . '" height="' . $H . '"';
        $o .= ' viewBox="0 0 ' . $W . ' ' . $H . '"';
        $o .= ' style="display:block;max-width:100%;height:auto">';

        // Staff baseline
        $o .= '<line x1="0" y1="' . $bl . '" x2="' . $W . '" y2="' . $bl . '" stroke="#444" stroke-width="1"/>';

        // Opening bar line
        $o .= self::barLine(0.5, $bt, $bl, '#777', 1.5);

        foreach ($beats as $i => $type) {
            $x  = $i * $bw;
            $cx = $x + $bw / 2.0;
            $rx = $x + $bw;

            switch ($type) {
                case '*':
                    // Rest: gray fill + horizontal dash at symbol zone
                    $o .= '<rect x="' . ($x + 1) . '" y="' . $bt . '" width="' . ($bw - 1) . '" height="' . ($bl - $bt) . '" fill="#1a1a1a"/>';
                    $o .= '<line x1="' . ($x + 5) . '" y1="' . $sy . '" x2="' . ($rx - 5) . '" y2="' . $sy . '" stroke="#555" stroke-width="2"/>';
                    $o .= self::barLine($rx + 0.5, $bt, $bl, '#555', 1);
                    break;

                case '^':
                    // Crash: gold X notehead + stem to baseline
                    $d = 5;
                    $o .= '<line x1="' . ($cx - $d) . '" y1="' . ($sy - $d) . '" x2="' . ($cx + $d) . '" y2="' . ($sy + $d) . '" stroke="#c8a000" stroke-width="2"/>';
                    $o .= '<line x1="' . ($cx + $d) . '" y1="' . ($sy - $d) . '" x2="' . ($cx - $d) . '" y2="' . ($sy + $d) . '" stroke="#c8a000" stroke-width="2"/>';
                    $o .= '<line x1="' . $cx . '" y1="' . ($sy + $d + 1) . '" x2="' . $cx . '" y2="' . $bl . '" stroke="#c8a000" stroke-width="1" opacity="0.35"/>';
                    $o .= self::barLine($rx + 0.5, $bt, $bl, '#555', 1);
                    break;

                case '-':
                    // Break: red tint + asterisk symbol
                    $o .= '<rect x="' . ($x + 1) . '" y="' . $bt . '" width="' . ($bw - 1) . '" height="' . ($bl - $bt) . '" fill="rgba(204,0,0,0.07)"/>';
                    // Asterisk: 3 lines crossing at center
                    $d = 5;
                    $o .= '<line x1="' . $cx . '" y1="' . ($sy - $d) . '" x2="' . $cx . '" y2="' . ($sy + $d) . '" stroke="#cc0000" stroke-width="1.5"/>';
                    $o .= '<line x1="' . ($cx - $d) . '" y1="' . $sy . '" x2="' . ($cx + $d) . '" y2="' . $sy . '" stroke="#cc0000" stroke-width="1.5"/>';
                    $o .= '<line x1="' . ($cx - 3.5) . '" y1="' . ($sy - 3.5) . '" x2="' . ($cx + 3.5) . '" y2="' . ($sy + 3.5) . '" stroke="#cc0000" stroke-width="1.5"/>';
                    $o .= '<line x1="' . ($cx + 3.5) . '" y1="' . ($sy - 3.5) . '" x2="' . ($cx - 3.5) . '" y2="' . ($sy + 3.5) . '" stroke="#cc0000" stroke-width="1.5"/>';
                    // Thick double closing bar for break
                    $o .= self::barLine($rx - 1.5, $bt, $bl, '#cc0000', 3);
                    $o .= self::barLine($rx + 1.5, $bt, $bl, '#cc0000', 1);
                    break;

                case '|':
                default:
                    $o .= self::barLine($rx + 0.5, $bt, $bl, '#555', 1);
                    break;
            }
        }

        $o .= '</svg>';
        return $o;
    }

    private static function barLine(float $x, int $y1, int $y2, string $stroke, float $w): string {
        return '<line x1="' . $x . '" y1="' . $y1 . '" x2="' . $x . '" y2="' . $y2 . '" stroke="' . $stroke . '" stroke-width="' . $w . '"/>';
    }
}
