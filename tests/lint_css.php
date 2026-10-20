<?php
$css = file_get_contents(__DIR__ . '/../assets/css/style.css');
$len = strlen($css);
$open_braces = 0;
$in_comment = false;
$in_string = false;
$str_char = '';
$line = 1;
$errors = [];

for ($i = 0; $i < $len; $i++) {
    $c = $css[$i];
    if ($c === "\n") $line++;
    
    if ($in_comment) {
        if ($c === '*' && $i + 1 < $len && $css[$i+1] === '/') {
            $in_comment = false;
            $i++;
        }
        continue;
    }
    
    if ($in_string) {
        if ($c === '\\') {
            $i++;
            continue;
        }
        if ($c === $str_char) {
            $in_string = false;
        }
        continue;
    }
    
    if ($c === '/' && $i + 1 < $len && $css[$i+1] === '*') {
        $in_comment = true;
        $i++;
        continue;
    }
    
    if ($c === '"' || $c === "'") {
        $in_string = true;
        $str_char = $c;
        continue;
    }
    
    if ($c === '{') {
        $open_braces++;
    } elseif ($c === '}') {
        $open_braces--;
        if ($open_braces < 0) {
            $errors[] = "Extra closing brace at line $line";
            $open_braces = 0;
        }
    }
}

if ($open_braces > 0) {
    $errors[] = "Unclosed braces: $open_braces unclosed at EOF";
}
if ($in_comment) {
    $errors[] = "Unclosed comment at EOF";
}

echo "CSS Lint Result: " . (empty($errors) ? "CLEAN (No mismatched braces or comments)" : implode(', ', $errors)) . "\n";
