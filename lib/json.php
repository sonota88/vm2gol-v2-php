<?php

function _parse ($json) {
    $pos = 1;
    $xs = [];

    while ($pos < mb_strlen($json)) {
        $rest = mb_substr($json, $pos);

        if (preg_match("/^\\[/", $rest)) {
            list($nl, $size) = _parse($rest);
            $xs[]= $nl;
            $pos += $size + 1;
        } elseif (preg_match("/^\\]/", $rest)) {
            $pos++;
            break;
        } elseif (preg_match("/^[ ,\\n]/", $rest)) {
             $pos++;
        } elseif (preg_match("/^(-?[0-9]+)/", $rest, $matches)) {
            $str = $matches[1];
            $xs[] = intval($str);
            $pos += mb_strlen($str) ;
        } elseif (preg_match('/^"(.*?)"/', $rest, $matches)) {
            $str = $matches[1];
            $xs[] = $str;
            $pos += mb_strlen($str) + 2;
        } else {
            throw new Exception("Unexpected pattern");
        }
    }

    return [$xs, $pos];
}

function json_parse ($json) {
    list($xs, $size) = _parse($json);
    return $xs;
}

function print_indent ($lv) {
    for ($i = 0; $i < $lv; $i++) {
        print("  ");
    }
}

function _json_print ($tree, $lv, $pretty) {
    if ($pretty) { print_indent($lv); }
    print("[");

    for ($i = 0; $i < count($tree); $i++) {
        $el = $tree[$i];

        if ($i >= 1) {
            print(",");
            if (! $pretty) {
                print(" ");
            }
        }
        if ($pretty) { print("\n"); }

        if (is_array($el)) {
            _json_print($el, $lv + 1, $pretty);
        } elseif (is_int($el)) {
            if ($pretty) { print_indent($lv + 1); }
            print($el);
        } elseif (is_string($el)) {
            if ($pretty) { print_indent($lv + 1); }
            print('"' . $el . '"');
        } else {
            die;
        }
    }
    if ($pretty) { print("\n"); }

    print_indent($lv);
    print("]");
}

function json_print ($tree) {
    _json_print($tree, 0, true);
    print("\n");
}

function json_print_oneline ($tree) {
    _json_print($tree, 0, false);
}
