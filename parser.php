<?php

require "./lib/utils.php";
require "./lib/json.php";

# --------------------------------

$tokens = [];
$pos = 0;

# --------------------------------

class Token {
    public $kind;
    public $str;

    public function __construct($kind, $str) {
        $this->kind = $kind;
        $this->str = $str;
    }

    public function kind_eq($kind) {
        return $this->kind === $kind;
    }

    public function str_eq($str) {
        return $this->str === $str;
    }

    public function is($kind, $str) {
        return (
               $this->kind_eq($kind)
            && $this->str_eq($str)
            );
    }

    public function get_value() {
        if ($this->kind_eq("int")) {
            return intval($this->str);
        } elseif ($this->kind_eq("ident")) {
            return $this->str;
        } else {
            throw new Exception("Unexpected kind");
        }
    }
}

# --------------------------------

function puts_fn($msg) {
    # puts_e("    |-->> " . $msg . "()");
}

# --------------------------------

function is_end() {
    global $tokens;
    global $pos;

    return (count($tokens) <= $pos);
}

function peek($offset) {
    global $tokens;
    global $pos;

    return $tokens[$pos + $offset];
}

function assert_value($kind, $str) {
    global $pos;

    $t = peek(0);

    if (! $t->kind_eq($kind)) {
        puts_e("pos ($pos)");
        puts_e("expected ($kind) ($str)");
        puts_e("actual   (" . $t->kind . ") (" . $t->str . ")");
        throw new Exception("Unexpected kind");
    }

    if (! $t->str_eq($str)) {
        puts_e("pos ($pos)");
        puts_e("expected ($kind) ($str)");
        puts_e("actual   (" . $t->kind . ") (" . $t->str . ")");
        throw new Exception("Unexpected str");
    }
}

function consume_kw($str) {
    global $pos;

    assert_value("kw", $str);
    $pos++;
}

function consume_sym($str) {
    global $pos;

    assert_value("sym", $str);
    $pos++;
}

# # --------------------------------

function parse_arg() {
    global $pos;
    # puts_fn("parse_arg");

    $t = peek(0);
    $pos++;

    return $t->get_value();
}

function parse_args() {
    puts_fn("parse_args");

    $args = [];

    if (peek(0)->str == ")") {
        return $args;
    }

    $args[]= parse_arg();

    while (peek(0)->str == ",") {
        consume_sym(",");
        $args[]= parse_arg();
    }

    return $args;
}

function parse_func() {
    global $pos;

    consume_kw("func");

    $t = peek(0);
    $pos++;
    $fn_name = $t->str;

    consume_sym("(");
    $args = parse_args();
    consume_sym(")");

    consume_sym("{");

    $stmts = [];
    while (1) {
        $t = peek(0);
        if ($t->str === "}") {
            break;
        }

        if ($t->str === "var") {
            $stmts[]= parse_var();
        } else {
            $stmts[]= parse_stmt();
        }
    }

    consume_sym("}");

    return [
        "func",
        $fn_name,
        $args,
        $stmts
        ];
}
 
function parse_var_declare() {
    global $pos;
    # puts_fn("parse_var_declare");

    $t = peek(0);
    $pos++;
    $var_name = $t->str;

    consume_sym(";");

    return [
        "var",
        $var_name
        ];
}

function parse_var_init() {
    global $pos;
    # puts_fn("parse_var_init");

    $t = peek(0);
    $pos++;
    $var_name = $t->{"str"};

    consume_sym("=");

    $expr = parse_expr();

    consume_sym(";");

    return [
        "var",
        $var_name,
        $expr
        ];
}

function parse_var() {
    puts_fn("parse_var");

    consume_kw("var");

    $t = peek(1);

    if ($t->is("sym", ";")) {
        return parse_var_declare();
    } else {
        return parse_var_init();
    }
}

function parse_expr_right() {
    puts_fn("parse_expr_right");

    $t = peek(0);

    if ($t->is("sym", "+")) {
        consume_sym("+");
        $expr_r = parse_expr();
        return ["+", $expr_r];

    } elseif ($t->is("sym", "*")) {
        consume_sym("*");
        $expr_r = parse_expr();
        return ["*", $expr_r];

    } elseif ($t->is("sym", "==")) {
        consume_sym("==");
        $expr_r = parse_expr();
        return ["eq", $expr_r];

    } elseif ($t->is("sym", "!=")) {
        consume_sym("!=");
        $expr_r = parse_expr();
        return ["neq", $expr_r];

    } else {
        return NULL;
    }
}

function parse_expr() {
    global $pos;
    puts_fn("parse_expr");

    $tl = peek(0);
    $expr_l;

    if ($tl->kind_eq("int")) {
        $pos++;
        $expr_l = $tl->get_value();
    } elseif ($tl->kind_eq("ident")) {
        $pos++;
        $expr_l = $tl->get_value();
    } elseif ($tl->kind_eq("sym")) {
        consume_sym("(");
        $expr_l = parse_expr();
        consume_sym(")");
    } else {
        throw not_yet_impl("parse_expr");
    }

    $op_right = parse_expr_right();

    if (is_null($op_right)) {
        return $expr_l;
    }

    return [
        $op_right[0], # op
        $expr_l,
        $op_right[1] # expr_r
        ];
}

function parse_set() {
    global $pos;
    puts_fn("parse_set");

    consume_kw("set");

    $t = peek(0);
    $pos++;
    $var_name = $t->str;

    consume_sym("=");
    $expr = parse_expr();
    consume_sym(";");

    return [
        "set",
        $var_name,
        $expr
        ];
}

function parse_funcall() {
    global $pos;
    puts_fn("parse_funcall");

    $t = peek(0);
    $pos++;
    $fn_name = $t->str;

    consume_sym("(");
    $args = parse_args();
    consume_sym(")");

    $list = [$fn_name];
    foreach ($args as $it) {
        $list[]= $it;
    }

    return $list;
}

function parse_call() {
    global $pos;
    puts_fn("parse_call");

    consume_kw("call");

    $funcall = parse_funcall();

    consume_sym(";");

    $list = [
        "call",
        ];
    foreach ($funcall as $it) {
        $list[]= $it;
    }

    return $list;
}

function parse_call_set() {
    global $pos;
    puts_fn("parse_call_set");

    consume_kw("call_set");

    $t = peek(0);
    $pos++;
    $var_name = $t->str;

    consume_sym("=");

    $funcall = parse_funcall();

    consume_sym(";");

    return [
        "call_set",
        $var_name,
        $funcall
        ];
}

function parse_return() {
    puts_fn("parse_return");

    consume_kw("return");

    $expr = parse_expr();

    consume_sym(";");

    return [
        "return",
        $expr
        ];
}

function parse_while() {
    puts_fn("parse_while");

    consume_kw("while");

    consume_sym("(");
    $expr = parse_expr();
    consume_sym(")");

    consume_sym("{");
    $stmts = parse_stmts();
    consume_sym("}");

    return [
        "while",
        $expr,
        $stmts
        ];
}

function parse_when_clause() {
    # puts_fn("parse_when_clause");

    $t = peek(0);
    if ($t->is("sym", "}")) {
        return 0;
    }

    consume_sym("(");
    $expr = parse_expr();
    consume_sym(")");

    consume_sym("{");
    $stmts = parse_stmts();
    consume_sym("}");

    $list = [$expr];
    foreach ($stmts as $stmt) {
        $list[]= $stmt;
    }

    return $list;
}

function parse_case() {
    puts_fn("parse_case");

    consume_kw("case");

    consume_sym("{");

    $when_clauses = [];

    while (1) {
        $when_clause = parse_when_clause();
        if (! $when_clause) {
            break;
        }
        $when_clauses[]= $when_clause;
    }

    consume_sym("}");

    $list = ["case"];
    foreach ($when_clauses as $when_clause) {
        $list[]= $when_clause;
    }

    return $list;
}

function parse_vm_comment() {
    global $pos;
    puts_fn("parse_vm_comment");

    consume_kw("_cmt");
    consume_sym("(");

    $t = peek(0);
    $pos++;
    $cmt = $t->str;

    consume_sym(")");
    consume_sym(";");

    return [
        "_cmt",
        $cmt
        ];
}

function parse_debug() {
    puts_fn("parse_debug");

    consume_kw("_debug");
    consume_sym("(");
    consume_sym(")");
    consume_sym(";");

    return ["_debug"];
}

function parse_stmt() {
    $t = peek(0);

    if ($t->is("sym", "}")) {
        return -1;
    }

    if     ($t->str_eq("set"     )) { return parse_set();        }
    elseif ($t->str_eq("call"    )) { return parse_call();       }
    elseif ($t->str_eq("call_set")) { return parse_call_set();   }
    elseif ($t->str_eq("return"  )) { return parse_return();     }
    elseif ($t->str_eq("while"   )) { return parse_while();      }
    elseif ($t->str_eq("case"    )) { return parse_case();       }
    elseif ($t->str_eq("_cmt"    )) { return parse_vm_comment(); }
    elseif ($t->str_eq("_debug"  )) { return parse_debug();      }
    else {
        throw not_yet_impl($t);
    }
}

function parse_stmts() {
    $stmts = [];

    while (! is_end()) {
        $stmt = parse_stmt();
        if ($stmt === -1) {
            break;
        }
        $stmts[]= $stmt;
    }

    return $stmts;
}

function parse_top_stmt() {
    $t = peek(0);

    if ($t->str === "func") {
        return parse_func();
    } else {
        throw new Exception("Unexpected token ($t)");
    }
}

function parse_top_stmts() {
    $stmts = [];

    while (1) {
        if (is_end()) {
            break;
        }

        $stmts[]= parse_top_stmt();
    }

    return $stmts;
}

function parse() {
    $top_stmts = [
        "top_stmts"
        ];

    $stmts = parse_top_stmts();

    foreach ($stmts as $stmt) {
        $top_stmts[]= $stmt;
    }

    return $top_stmts;
}

# --------------------------------

while ($line = fgets(STDIN)) {
    preg_match("/^(.+?):(.+)$/", $line, $m);
    $tokens[]= new Token($m[1], $m[2]);
}

$ast = parse();
json_print($ast);
