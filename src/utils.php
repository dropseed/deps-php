<?php

function outputSchema($schema) {
    $json = json_encode($schema);
    echo "<DependenciesSchema>$json</DependenciesSchema>";
}

function pathInRepo($path) {
    $real = realpath($path);
    return substr($real, 6);  // remove /repo/
}

function path_join($base, $path) {
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}