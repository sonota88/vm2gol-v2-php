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

function to_fn_arg_ref($names, $name) {
    $i = arr_index($names, $name);
    if ($i === -1) {
        throw new Exception("fn arg not found");
    }
    return "[bp:" . ($i + 2) . "]";
}

function to_lvar_ref($names, $name) {
    $i = arr_index($names, $name);
    if ($i === -1) {
        throw new Exception("lvar not found");
    }
    return "[bp:-" . ($i + 1) . "]";
}

function to_asm_arg($fn_arg_names, $lvar_names, $val) {
    if (is_array($val)) {
        return null;
    } elseif (is_int($val)) {
        return $val;
    } elseif (is_string($val)) {
        $str = $val;
        if (0 <= arr_index($fn_arg_names, $str)) {
            return to_fn_arg_ref($fn_arg_names, $str);
        } elseif (0 <= arr_index($lvar_names, $str)) {
            return to_lvar_ref($lvar_names, $str);
        } else {
            return null;
        }
    } else {
        return null;
    }
}

function codegen_var($fn_arg_names, $lvar_names, $stmt_rest) {
    print("  sub_sp 1\n");

    if (count($stmt_rest) == 2) {
        codegen_set($fn_arg_names, $lvar_names, $stmt_rest);
    }
}

function codegen_expr_push($fn_arg_names, $lvar_names, $val) {
    if (is_int($val)) {
        $push_arg = $val;
    } elseif (is_string($val)) {
        $str = $val;
        if (0 <= arr_index($fn_arg_names, $str)) {
            $push_arg = to_fn_arg_ref($fn_arg_names, $str);
        } elseif (0 <= arr_index($lvar_names, $str)) {
            $push_arg = to_lvar_ref($lvar_names, $str);
        } else {
            throw not_yet_impl($val);
        }
    } elseif (is_array($val)) {
        codegen_expr($fn_arg_names, $lvar_names, $val);
        $push_arg = "reg_a";
    } else {
        throw not_yet_impl($val);
    }

    printf("  push %s\n", $push_arg);
}

function codegen_expr_add() {
    printf("  pop reg_b\n");
    printf("  pop reg_a\n");
    printf("  add_ab\n");
}

function codegen_expr_mult() {
    printf("  pop reg_b\n");
    printf("  pop reg_a\n");
    printf("  mult_ab\n");
}

function codegen_expr_eq() {
    $label_id = get_label_id();

    $then_label = "then_$label_id";
    $end_label = "end_eq_$label_id";

    printf("  pop reg_b\n");
    printf("  pop reg_a\n");

    printf("  compare\n");
    printf("  jump_eq %s\n", $then_label);

    printf("  set_reg_a 0\n");
    printf("  jump %s\n", $end_label);

    printf("label %s\n", $then_label);
    printf("  set_reg_a 1\n");
    printf("label %s\n", $end_label);
}

function codegen_expr_neq() {
    $label_id = get_label_id();

    $then_label = "then_$label_id";
    $end_label = "end_neq_$label_id";

    printf("  pop reg_b\n");
    printf("  pop reg_a\n");

    printf("  compare\n");
    printf("  jump_eq %s\n", $then_label);

    printf("  set_reg_a 1\n");
    printf("  jump %s\n", $end_label);

    printf("label %s\n", $then_label);
    printf("  set_reg_a 0\n");
    printf("label %s\n", $end_label);
}

function codegen_expr($fn_arg_names, $lvar_names, $expr) {
    $op   = head($expr);
    $args = rest($expr);

    $term_l = $args[0];
    $term_r = $args[1];

    codegen_expr_push($fn_arg_names, $lvar_names, $term_l);
    codegen_expr_push($fn_arg_names, $lvar_names, $term_r);

    if ($op === "+") {
        codegen_expr_add();
    } elseif ($op === "*") {
        codegen_expr_mult();
    } elseif ($op === "eq") {
        codegen_expr_eq();
    } elseif ($op === "neq") {
        codegen_expr_neq();
    } else {
        throw not_yet_impl($op);
    }
}

function codegen_call_push_fn_arg($fn_arg_names, $lvar_names, $fn_arg) {
    if (is_int($fn_arg)) {
        $push_arg = $fn_arg;
    } elseif (is_string($fn_arg)) {
        $str = $fn_arg;
        if (0 <= arr_index($fn_arg_names, $str)) {
            $push_arg = to_fn_arg_ref($fn_arg_names, $str);
        } elseif (0 <= arr_index($lvar_names, $str)) {
            $push_arg = to_lvar_ref($lvar_names, $str);
        } else {
            throw not_yet_impl($fn_arg);
        }
    } else {
        throw not_yet_impl($fn_arg);
    }

    printf("  push %s\n", $push_arg);
}

function codegen_call($fn_arg_names, $lvar_names, $stmt_rest) {
    $fn_name = head($stmt_rest);
    $fn_args = rest($stmt_rest);

    foreach (array_reverse($fn_args) as $fn_arg) {
        codegen_call_push_fn_arg($fn_arg_names, $lvar_names, $fn_arg);
    }

    codegen_vm_comment("call  $fn_name");
    printf("  call %s\n", $fn_name);

    printf("  add_sp %d\n", count($fn_args));
}

function codegen_call_set($fn_arg_names, $lvar_names, $stmt_rest) {
    puts_fn("codegen_call_set");

    $lvar_name = $stmt_rest[0];
    $fn_temp   = $stmt_rest[1];

    $fn_name = head($fn_temp);
    $fn_args = rest($fn_temp);

    foreach (array_reverse($fn_args) as $fn_arg) {
        codegen_call_push_fn_arg($fn_arg_names, $lvar_names, $fn_arg);
    }

    codegen_vm_comment("call_set  " . $fn_name);
    printf("  call %s\n", $fn_name);
    printf("  add_sp %d\n", count($fn_args));

    $ref = to_lvar_ref($lvar_names, $lvar_name);
    printf("  cp reg_a %s\n", $ref);
}

function codegen_set($fn_arg_names, $lvar_names, $rest) {
    puts_fn("codegen_set");

    $dest = $rest[0];
    $expr = $rest[1];

    if (is_int($expr)) {
        $arg_src = $expr;
    } elseif (is_string($expr)) {
        $str = $expr;
        if (0 <= arr_index($fn_arg_names, $str)) {
            $arg_src = to_fn_arg_ref($fn_arg_names, $str);
        } elseif (0 <= arr_index($lvar_names, $str)) {
            $arg_src = to_lvar_ref($lvar_names, $str);
        } else {
            if (preg_match("/^vram\[(.+?)\]/", $expr, $m)) {
                $vram_arg = $m[1];
                if (preg_match("/^[0-9]+$/", $vram_arg)) {
                    printf("  get_vram %s reg_a\n", $vram_arg);
                } else {

                    $vram_ref = to_asm_arg($fn_arg_names, $lvar_names, $vram_arg);
                    if ($vram_ref !== null) {
                        printf("  get_vram %s reg_a\n", $vram_ref);
                    } else {
                        throw not_yet_impl($expr);
                    }

                }
                $arg_src = "reg_a";
            } else {
                throw not_yet_impl($expr);
            }
        }
    } elseif (is_array($expr)) {
        codegen_expr($fn_arg_names, $lvar_names, $expr);
        $arg_src = "reg_a";
    } else {
        throw not_yet_impl($expr);
    }

    $arg_dest = to_asm_arg($fn_arg_names, $lvar_names, $dest);
    if ($arg_dest !== null) {
        printf("  cp ${arg_src} ${arg_dest}\n");
    } else {
        if (is_string($dest)) {

            if (preg_match("/^vram\[(.+?)\]/", $dest, $m)) {
                $vram_arg = $m[1];
                if (preg_match("/^[0-9]+$/", $vram_arg)) {
                    printf("  set_vram %s %s\n", $vram_arg, $arg_src);
                } else {

                    $vram_ref = to_asm_arg($fn_arg_names, $lvar_names, $vram_arg);
                    if ($vram_ref !== null) {
                        printf("  set_vram %s %s\n", $vram_ref, $arg_src);
                    } else {
                        throw not_yet_impl($dest);
                    }

                }
            } else {
                throw not_yet_impl($dest);
            }

        } else {
            throw not_yet_impl($dest);
        }
    }
}

function codegen_return($lvar_names, $stmt_rest) {
    $retval = head($stmt_rest);

    $arg_retval = to_asm_arg([], $lvar_names, $retval);
    if ($arg_retval !== null) {
        printf("  cp %s reg_a\n", $arg_retval);
    } else {

        if (is_string($retval)) {
            $str = $retval;
            if (preg_match("/^vram\[(.+?)\]/", $str, $m)) {
                $vram_arg = $m[1];

                if (preg_match("/^[0-9]+$/", $vram_arg)) {
                    throw not_yet_impl($retval);
                } else {
                    $vram_ref = to_asm_arg([], $lvar_names, $vram_arg);
                    if ($vram_ref !== null) {
                        printf("  get_vram %s reg_a\n", $vram_ref);
                    } else {
                        throw not_yet_impl($retval);
                    }
                }
            } else {
                throw not_yet_impl($retval);
            }
        } else {
            throw not_yet_impl($retval);
        }

    }
}

function codegen_vm_comment($cmt) {
    $cmt = preg_replace("/ /", "~", $cmt);

    printf("  _cmt %s\n", $cmt);
}

function codegen_while($fn_arg_names, $lvar_names, $stmt_rest) {
    puts_fn("codegen_while");

    $cond_expr = $stmt_rest[0];
    $body      = $stmt_rest[1];

    $label_id = get_label_id();
    $label_begin = "while_$label_id";
    $label_end = "end_while_$label_id";
    $label_true = "true_$label_id";

    printf("\n");

    printf("label %s\n", $label_begin);

    codegen_expr($fn_arg_names, $lvar_names, $cond_expr);

    printf("  set_reg_b 1\n");
    printf("  compare\n");

    printf("  jump_eq %s\n", $label_true);
    printf("  jump %s\n", $label_end);
    printf("label %s\n", $label_true);

    codegen_stmts($fn_arg_names, $lvar_names, $body);

    printf("  jump %s\n", $label_begin);

    printf("label %s\n", $label_end);
    printf("\n");
}

function codegen_case($fn_arg_names, $lvar_names, $when_blocks) {
    puts_fn("codegen_case");

    $label_id = get_label_id();
    $when_idx = -1;

    $label_end = "end_case_${label_id}";
    $label_when_head = "when_${label_id}";
    $label_end_when_head = "end_when_${label_id}";

    printf("\n");
    printf("  # -->> case_%d\n", $label_id);

    foreach ($when_blocks as $when_block) {
        $when_idx++;

        $cond = head($when_block);
        $rest = rest($when_block);

        $cond_head = head($cond);
        $cond_rest = rest($cond);

        printf("  # when_%d_%d\n", $label_id, $when_idx);

        if ($cond_head === "eq") {
            printf("  # -->> expr\n");
            codegen_expr($fn_arg_names, $lvar_names, $cond);
            printf("  # <<-- expr\n");

            printf("  set_reg_b 1\n");

            printf("  compare\n");
            printf("  jump_eq %s_%d\n", $label_when_head, $when_idx);
            printf("  jump %s_%d\n", $label_end_when_head, $when_idx);

            printf("label %s_%d\n", $label_when_head, $when_idx);

            codegen_stmts($fn_arg_names, $lvar_names, $rest);

            printf("  jump %s\n", $label_end);
            printf("label %s_%d\n", $label_end_when_head, $when_idx);
        } else {
            throw not_yet_impl($cond_head);
        }
    }

    printf("label end_case_%d\n", $label_id);
    printf("  # <<-- case_%d\n", $label_id);
    printf("\n");
}

function codegen_stmt($fn_arg_names, $lvar_names, $stmt) {
    puts_fn("codegen_stmt");

    $stmt_head = head($stmt);
    $stmt_rest = rest($stmt);

    if     ($stmt_head === "set"     ) { codegen_set(       $fn_arg_names, $lvar_names, $stmt_rest); }
    elseif ($stmt_head === "call"    ) { codegen_call(      $fn_arg_names, $lvar_names, $stmt_rest); }
    elseif ($stmt_head === "call_set") { codegen_call_set(  $fn_arg_names, $lvar_names, $stmt_rest); }
    elseif ($stmt_head === "return"  ) { codegen_return(                   $lvar_names, $stmt_rest); }
    elseif ($stmt_head === "while"   ) { codegen_while(     $fn_arg_names, $lvar_names, $stmt_rest); }
    elseif ($stmt_head === "case"    ) { codegen_case(      $fn_arg_names, $lvar_names, $stmt_rest); }
    elseif ($stmt_head === "_cmt"    ) { codegen_vm_comment($stmt_rest[0]); }
    else {
        throw new Exception("Unsupported statement (${stmt_head})");
    }
}

function codegen_stmts($fn_arg_names, $lvar_names, $stmts) {
    foreach ($stmts as $stmt) {
        codegen_stmt($fn_arg_names, $lvar_names, $stmt);
    }
}

function codegen_func_def($rest) {
    $fn_name     = $rest[0];
    $fn_arg_vals = $rest[1];
    $body        = $rest[2];

    $fn_arg_names = [];
    foreach ($fn_arg_vals as $val) {
        $fn_arg_names[]= $val;
    }

    $lvar_names = [];

    print("label ${fn_name}\n");
    print("  push bp\n");
    print("  cp sp bp\n");

    foreach ($body as $stmt) {
        $stmt_rest = rest($stmt);
        if ($stmt[0] === "var") {
            $var_name = $stmt_rest[0];
            $lvar_names[]= $var_name;
            codegen_var($fn_arg_names, $lvar_names, $stmt_rest);
        } else {
            codegen_stmt($fn_arg_names, $lvar_names, $stmt);
        }
    }

    print("  cp bp sp\n");
    print("  pop bp\n");
    print("  ret\n");
}

function codegen_top_stmts($top_stmts) {
    foreach ($top_stmts as $top_stmt) {
        $stmt_head = $top_stmt[0];
        $stmt_rest = rest($top_stmt);

        if ($stmt_head === "func") {
            codegen_func_def($stmt_rest);
        } else {
            throw not_yet_impl("codegen_top_stmts");
        }
    }
}
 
# --------------------------------

$src = read_stdin_all();

$tree = parse_json($src);

print("  call main\n");
print("  exit\n");
 
$top_stmts = rest($tree);

codegen_top_stmts($top_stmts);
