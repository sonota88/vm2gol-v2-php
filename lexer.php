<?php

require "./lib/utils.php";

function puts_token($kind, $str) {
    printf("%s:%s\n", $kind, $str);
}

function tokenize($src) {
    $pos = 0;
    $rest = "";
    $temp = "";

    while ($pos < mb_strlen($src)) {
        $rest = mb_substr($src, $pos);

        if (preg_match("/^([ \n]+)/", $rest, $m)) {
            $temp = $m[1];
            $pos += mb_strlen($temp);
        } elseif (preg_match("/^(\/\/.*)/", $rest, $m)) {
            $temp = $m[1];
            $pos += mb_strlen($temp);
        } elseif (preg_match("/^\"(.*)\"/", $rest, $m)) {
            $temp = $m[1];
            puts_token("str", $temp);
            $pos += mb_strlen($temp) + 2;
        } elseif (preg_match("/^(func|set|var|call_set|call|return|case|while|_cmt)[^a-z_]/", $rest, $m)) {
            $temp = $m[1];
            puts_token("kw", $temp);
            $pos += mb_strlen($temp);
        } elseif (preg_match("/^(-?[0-9]+)/", $rest, $m)) {
            $temp = $m[1];
            puts_token("int", $temp);
            $pos += mb_strlen($temp);
        } elseif (preg_match("/^(==|!=|[(){}=;+*,])/", $rest, $m)) {
            $temp = $m[1];
            puts_token("sym", $temp);
            $pos += mb_strlen($temp);
        } elseif (preg_match("/^([a-z_][a-z0-9_]*)/", $rest, $m)) {
            $temp = $m[1];
            puts_token("ident", $temp);
            $pos += mb_strlen($temp);
        } else {
            throw new Exception("Unexpected pattern (${rest})");
        }
    }
}

$src = read_stdin_all();
tokenize($src);
