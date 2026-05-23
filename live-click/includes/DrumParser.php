<?php
class DrumParser {
    const VALID = ['|', '*', '^', '-'];

    /**
     * Parse notation string into ordered array of beat-type chars.
     * Unknown characters are ignored silently.
     */
    public static function parse(string $notation): array {
        $beats = [];
        $len   = strlen($notation);
        for ($i = 0; $i < $len; $i++) {
            $c = $notation[$i];
            if (in_array($c, self::VALID, true)) {
                $beats[] = $c;
            }
        }
        return $beats;
    }
}
