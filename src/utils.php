<?php

function outputDependencies($schema) {
    $json = json_encode($schema);
    echo "<Dependencies>$json</Dependencies>";
}

function outputActions($schema) {
    $json = json_encode($schema);
    echo "<Actions>$json</Actions>";
}

function pathInRepo($path) {
    $real = realpath($path);
    return substr($real, 6);  // remove /repo/
}

function path_join($base, $path) {
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function createGitBranch($branch) {
    $git_sha = getenv('GIT_SHA');
    runCommand("git checkout $git_sha");
    runCommand("git checkout -b $branch");
}

function pushGitBranch($branch) {
    if (isInTestMode()) {
        echo "Not pushing branch in test mode";
        return;
    }
    runCommand("git push --set-upstream origin $branch");
}

function runCommand($cmd) {
    echo "Exec: $cmd\n";
    exec($cmd, $output, $return);
    if ($return) {
        var_dump($output);
        throw new Exception("Exception running: $cmd\n\n$output");
    }
    return implode($output, "\n");
}

function isInTestMode() {
    return getenv('DEPENDENCIES_ENV') == 'test';
}
