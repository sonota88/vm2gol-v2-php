<?php

require "./lib/utils.php";
require "./lib/json.php";

$g_label_id = 0;

# --------------------------------

function puts_fn($msg) {
    # puts_e("    |-->> " . $msg . "()");
}

# --------------------------------

function get_label_id() {
    global $g_label_id;
    $g_label_id++;
    return $g_label_id;
}

function head($list) {
    return $list[0];
}

function rest($list) {
    $rest = [];

    for ($i = 1; $i < count($list); $i++) {
        $rest[]= $list[$i];
    }

    return $rest;
}

# --------------------------------

function asm_prologue() {
    puts("  push bp");
    puts("  cp sp bp");
}

function asm_epilogue() {
    puts("  cp bp sp");
    puts("  pop bp");
}

# --------------------------------

function to_fn_arg_disp($names, $name) {
    $i = arr_index($names, $name);
    if ($i === -1) {
        throw new Exception("fn arg not found");
    }
    return $i + 2;
}

function to_lvar_disp($names, $name) {
    $i = arr_index($names, $name);
    if ($i === -1) {
        throw new Exception("lvar not found");
    }
    return -($i + 1);
}

# --------------------------------

function gen_expr_add() {
    printf("  pop reg_b\n");
    printf("  pop reg_a\n");
    printf("  add_ab\n");
}

function gen_expr_mult() {
    printf("  pop reg_b\n");
    printf("  pop reg_a\n");
    printf("  mult_ab\n");
}

function gen_expr_eq() {
    $label_id = get_label_id();

    $then_label = "then_$label_id";
    $end_label = "end_eq_$label_id";

    printf("  pop reg_b\n");
    printf("  pop reg_a\n");

    printf("  compare\n");
    printf("  jump_eq %s\n", $then_label);

    printf("  cp 0 reg_a\n");
    printf("  jump %s\n", $end_label);

    printf("label %s\n", $then_label);
    printf("  cp 1 reg_a\n");
    printf("label %s\n", $end_label);
}

function gen_expr_neq() {
    $label_id = get_label_id();

    $then_label = "then_$label_id";
    $end_label = "end_neq_$label_id";

    printf("  pop reg_b\n");
    printf("  pop reg_a\n");

    printf("  compare\n");
    printf("  jump_eq %s\n", $then_label);

    printf("  cp 1 reg_a\n");
    printf("  jump %s\n", $end_label);

    printf("label %s\n", $then_label);
    printf("  cp 0 reg_a\n");
    printf("label %s\n", $end_label);
}

function _gen_expr_binary($fn_arg_names, $lvar_names, $expr) {
    $op   = head($expr);
    $args = rest($expr);

    $term_l = $args[0];
    $term_r = $args[1];

    gen_expr($fn_arg_names, $lvar_names, $term_l);
    printf("  push reg_a\n");
    gen_expr($fn_arg_names, $lvar_names, $term_r);
    printf("  push reg_a\n");

    if ($op === "+") {
        gen_expr_add();
    } elseif ($op === "*") {
        gen_expr_mult();
    } elseif ($op === "eq") {
        gen_expr_eq();
    } elseif ($op === "neq") {
        gen_expr_neq();
    } else {
        throw not_yet_impl($op);
    }
}

function gen_expr($fn_arg_names, $lvar_names, $expr) {
    if (is_int($expr)) {
        printf("  cp " . $expr . " reg_a\n");
    } elseif (is_string($expr)) {
        $str = $expr;
        if (0 <= arr_index($fn_arg_names, $str)) {
            $disp = to_fn_arg_disp($fn_arg_names, $str);
            printf("  cp [bp:%d] reg_a\n", $disp);
        } elseif (0 <= arr_index($lvar_names, $str)) {
            $disp = to_lvar_disp($lvar_names, $str);
            printf("  cp [bp:%d] reg_a\n", $disp);
        } else {
            throw not_yet_impl($expr);
        }
    } elseif (is_array($expr)) {
        _gen_expr_binary($fn_arg_names, $lvar_names, $expr);
    } else {
        throw not_yet_impl($expr);
    }
}

function gen_call($fn_arg_names, $lvar_names, $stmt_rest) {
    $fn_name = head($stmt_rest);
    $fn_args = rest($stmt_rest);

    foreach (array_reverse($fn_args) as $fn_arg) {
        gen_expr($fn_arg_names, $lvar_names, $fn_arg);
        printf("  push reg_a\n");
    }

    gen_vm_comment("call  $fn_name");
    printf("  call %s\n", $fn_name);

    printf("  add_sp %d\n", count($fn_args));
}

function gen_call_set($fn_arg_names, $lvar_names, $stmt_rest) {
    puts_fn("gen_call_set");

    $lvar_name = $stmt_rest[0];
    $funcall   = $stmt_rest[1];

    gen_call($fn_arg_names, $lvar_names, $funcall);

    $disp = to_lvar_disp($lvar_names, $lvar_name);
    printf("  cp reg_a [bp:%d]\n", $disp);
}

function gen_set($fn_arg_names, $lvar_names, $rest) {
    puts_fn("gen_set");

    $dest = $rest[0];
    $expr = $rest[1];

    gen_expr($fn_arg_names, $lvar_names, $expr);
    $arg_src = "reg_a";

    if (is_int($dest)) {
        $arg_dest = $dest;
        printf("  cp ${arg_src} ${arg_dest}\n");
    } elseif (is_string($dest)) {
        $str = $dest;
        if (0 <= arr_index($lvar_names, $str)) {
            $disp = to_lvar_disp($lvar_names, $str);
            printf("  cp ${arg_src} [bp:%d]\n", $disp);
        } else {
            throw not_yet_impl($dest);
        }
    } else {
        throw not_yet_impl($dest);
    }
}

function gen_return($lvar_names, $stmt_rest) {
    $retval = head($stmt_rest);

    gen_expr([], $lvar_names, $retval);
}

function gen_while($fn_arg_names, $lvar_names, $stmt_rest) {
    puts_fn("gen_while");

    $cond_expr = $stmt_rest[0];
    $body      = $stmt_rest[1];

    $label_id = get_label_id();
    $label_begin = "while_$label_id";
    $label_end = "end_while_$label_id";
    $label_true = "true_$label_id";

    printf("\n");

    printf("label %s\n", $label_begin);

    gen_expr($fn_arg_names, $lvar_names, $cond_expr);

    printf("  cp 1 reg_b\n");
    printf("  compare\n");

    printf("  jump_eq %s\n", $label_true);
    printf("  jump %s\n", $label_end);
    printf("label %s\n", $label_true);

    gen_stmts($fn_arg_names, $lvar_names, $body);

    printf("  jump %s\n", $label_begin);

    printf("label %s\n", $label_end);
    printf("\n");
}

function gen_case($fn_arg_names, $lvar_names, $when_clauses) {
    puts_fn("gen_case");

    $label_id = get_label_id();
    $when_idx = -1;

    $label_end = "end_case_${label_id}";
    $label_when_head = "when_${label_id}";
    $label_end_when_head = "end_when_${label_id}";

    printf("\n");
    printf("  # -->> case_%d\n", $label_id);

    foreach ($when_clauses as $when_clause) {
        $when_idx++;

        $cond = head($when_clause);
        $rest = rest($when_clause);

        printf("  # when_%d_%d\n", $label_id, $when_idx);

        printf("  # -->> expr\n");
        gen_expr($fn_arg_names, $lvar_names, $cond);
        printf("  # <<-- expr\n");

        printf("  cp 1 reg_b\n");

        printf("  compare\n");
        printf("  jump_eq %s_%d\n", $label_when_head, $when_idx);
        printf("  jump %s_%d\n", $label_end_when_head, $when_idx);

        printf("label %s_%d\n", $label_when_head, $when_idx);

        gen_stmts($fn_arg_names, $lvar_names, $rest);

        printf("  jump %s\n", $label_end);
        printf("label %s_%d\n", $label_end_when_head, $when_idx);
    }

    printf("label end_case_%d\n", $label_id);
    printf("  # <<-- case_%d\n", $label_id);
    printf("\n");
}

function gen_vm_comment($cmt) {
    $cmt = preg_replace("/ /", "~", $cmt);

    printf("  _cmt %s\n", $cmt);
}

function gen_stmt($fn_arg_names, $lvar_names, $stmt) {
    puts_fn("gen_stmt");

    $stmt_head = head($stmt);
    $stmt_rest = rest($stmt);

    if     ($stmt_head === "set"     ) { gen_set(       $fn_arg_names, $lvar_names, $stmt_rest); }
    elseif ($stmt_head === "call"    ) { gen_call(      $fn_arg_names, $lvar_names, $stmt_rest); }
    elseif ($stmt_head === "call_set") { gen_call_set(  $fn_arg_names, $lvar_names, $stmt_rest); }
    elseif ($stmt_head === "return"  ) { gen_return(                   $lvar_names, $stmt_rest); }
    elseif ($stmt_head === "while"   ) { gen_while(     $fn_arg_names, $lvar_names, $stmt_rest); }
    elseif ($stmt_head === "case"    ) { gen_case(      $fn_arg_names, $lvar_names, $stmt_rest); }
    elseif ($stmt_head === "_cmt"    ) { gen_vm_comment($stmt_rest[0]); }
    else {
        throw new Exception("Unsupported statement (${stmt_head})");
    }
}

function gen_stmts($fn_arg_names, $lvar_names, $stmts) {
    foreach ($stmts as $stmt) {
        gen_stmt($fn_arg_names, $lvar_names, $stmt);
    }
}

function gen_var($fn_arg_names, $lvar_names, $stmt_rest) {
    print("  sub_sp 1\n");

    if (count($stmt_rest) == 2) {
        gen_set($fn_arg_names, $lvar_names, $stmt_rest);
    }
}

function gen_func_def($rest) {
    $fn_name     = $rest[0];
    $fn_arg_vals = $rest[1];
    $body        = $rest[2];

    $fn_arg_names = [];
    foreach ($fn_arg_vals as $val) {
        $fn_arg_names[]= $val;
    }

    $lvar_names = [];

    print("label ${fn_name}\n");
    asm_prologue();

    foreach ($body as $stmt) {
        $stmt_rest = rest($stmt);
        if ($stmt[0] === "var") {
            $var_name = $stmt_rest[0];
            $lvar_names[]= $var_name;
            gen_var($fn_arg_names, $lvar_names, $stmt_rest);
        } else {
            gen_stmt($fn_arg_names, $lvar_names, $stmt);
        }
    }

    asm_epilogue();
    print("  ret\n");
}

function gen_top_stmts($top_stmts) {
    foreach ($top_stmts as $top_stmt) {
        $stmt_head = $top_stmt[0];
        $stmt_rest = rest($top_stmt);

        if ($stmt_head === "func") {
            gen_func_def($stmt_rest);
        } else {
            throw not_yet_impl("gen_top_stmts");
        }
    }
}

function gen_builtin_set_vram() {
    puts("");
    puts("label set_vram");
    asm_prologue();

    puts("  set_vram [bp:2] [bp:3]"); # vram_addr value

    asm_epilogue();
    puts("  ret");
}

function gen_builtin_get_vram() {
    puts("");
    puts("label get_vram");
    asm_prologue();

    puts("  get_vram [bp:2] reg_a"); # vram_addr dest

    asm_epilogue();
    puts("  ret");
}

function codegen($ast) {
    print("  call main\n");
    print("  exit\n");
    
    $top_stmts = rest($ast);

    gen_top_stmts($top_stmts);

    puts("#>builtins");
    gen_builtin_set_vram();
    gen_builtin_get_vram();
    puts("#<builtins");
}
 
# --------------------------------

$src = read_stdin_all();
$ast = json_parse($src);
codegen($ast);
