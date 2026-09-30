<?php
$content = file_get_contents('backend/controllers/TenderController.php');
preg_match_all('/function\s+([a-zA-Z0-9_]+)/', $content, $m, PREG_OFFSET_CAPTURE);
$funcs = [];
foreach ($m[1] as $match) {
    $name = $match[0];
    $offset = $match[1];
    $line = substr_count(substr($content, 0, $offset), "\n") + 1;
    $funcs[] = [$name, $line];
}
$seen = [];
foreach ($funcs as [$name, $line]) {
    if (isset($seen[$name])) {
        echo "DUPLICATE FUNCTION: {$name} at line {$line} (previously seen at line {$seen[$name]})\n";
    }
    $seen[$name] = $line;
}
