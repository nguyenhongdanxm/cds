<?php
function load_functions($path, array $names) {
    $tokens = token_get_all(file_get_contents($path));
    $loaded = [];
    for ($i = 0; $i < count($tokens); $i++) {
        if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
        $j = $i + 1;
        while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
        if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) continue;
        $name = $tokens[$j][1];
        if (!in_array($name, $names, true)) continue;
        $code = ''; $depth = 0; $started = false;
        for ($k = $i; $k < count($tokens); $k++) {
            $token = $tokens[$k];
            $code .= is_array($token) ? $token[1] : $token;
            if ($token === '{') { $depth++; $started = true; }
            if ($token === '}' && --$depth === 0 && $started) break;
        }
        eval($code);
        $loaded[] = $name;
    }
    check(count($loaded) === count($names), 'Missing production functions in fixture harness');
}
