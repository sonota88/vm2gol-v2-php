<?php

function puts_e($msg) {
    fputs(STDERR, $msg . "\n");
}

function p_e($arg) {
    # var_dump($arg);
    if ($arg === null) {
        fputs(STDERR, "null");
    } else {
        fputs(STDERR, print_r($arg, true));
    }
    fputs(STDERR, "\n");
}

function read_stdin_all () {
    $src = "";

    while ($line = fgets(STDIN)) {
        $src = "${src}${line}";
    }

    return $src;
}

function not_yet_impl($arg) {
    return new Exception("not yet impl (" . print_r($arg, true) . ")");
}

function arr_index($xs, $x) {
    $ret = array_search($x, $xs);
    if ($ret === FALSE) {
        return -1;
    } else {
        return $ret;
    }
}
