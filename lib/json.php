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

function parse_json ($json) {
    list($xs, $size) = _parse($json);
    return $xs;
}

function print_indent ($lv) {
    for ($i = 0; $i < $lv; $i++) {
        print("  ");
    }
}

function _json_print ($tree, $lv) {
    print_indent($lv);
    print("[");

    for ($i = 0; $i < count($tree); $i++) {
        $el = $tree[$i];

        if ($i >= 1) {
            print(",");
        }
        print("\n");

        if (is_array($el)) {
            _json_print($el, $lv + 1);
        } elseif (is_int($el)) {
            print_indent($lv + 1);
            print($el);
        } elseif (is_string($el)) {
            print_indent($lv + 1);
            print('"' . $el . '"');
        } else {
            die;
        }
    }
    print("\n");

    print_indent($lv);
    print("]");
}

function json_print ($tree) {
    _json_print($tree, 0);
    print("\n");
}
