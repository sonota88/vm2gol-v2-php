<?php

require "./lib/utils.php";
require "./lib/json.php";

function is_kw($str) {
    return (
        $str == "func"
        || $str == "set"
        || $str == "var"
        || $str == "call_set"
        || $str == "call"
        || $str == "return"
        || $str == "case"
        || $str == "while"
        || $str == "_cmt"
        || $str == "_debug"
        );
}

function puts_token($kind, $str) {
    $lineno = 1; # TODO
    $token = [];
    $token[]= $lineno;
    $token[]= $kind;
    $token[]= $str;
    json_print_oneline($token);
    print("\n");
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
            if (is_kw($temp)) {
                $kind = "kw";
            } else {
                $kind = "ident";
            }
            puts_token($kind, $temp);
            $pos += mb_strlen($temp);
        } else {
            throw new Exception("Unexpected pattern (${rest})");
        }
    }
}

$src = read_stdin_all();
tokenize($src);
