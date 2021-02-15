<?php

function path_join($base, $path) {
    if ($base === '.') return ltrim($path, '/');
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function runCommand($cmd) {
    echo "Exec: $cmd\n";
    exec($cmd, $output, $return);
    if ($return) {
        var_dump($output);
        throw new Exception("Exception running: $cmd\n\n$output");
    }
    $output = implode("\n", $output);
    echo $output . "\n";
    return $output;
}
