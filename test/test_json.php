<?php

require "./lib/utils.php";
require "lib/json.php";

$json = read_stdin_all();
# print($json);
$tree = parse_json($json);
# var_dump($tree);

print_as_json($tree);
