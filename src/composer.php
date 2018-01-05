<?php

function composerInstall($dependency_path) {
    runCommand("cd $dependency_path && composer install --ignore-platform-reqs --no-scripts --no-progress --no-suggest --no-autoloader");
}

function composerUpdate($dependency_path) {
    runCommand("cd $dependency_path && composer update --ignore-platform-reqs --no-scripts --no-progress --no-suggest --no-autoloader");
}

function getAllComposerJsonRequirements($dependency_path) {
    $composer_json = json_decode(file_get_contents(composerJsonPath($dependency_path)), true);
    $composer_require = array_key_exists('require', $composer_json) ? $composer_json['require'] : array();
    $composer_require_dev = array_key_exists('require-dev', $composer_json) ? $composer_json['require-dev'] : array();

    $all_requirements = array_merge($composer_require, $composer_require_dev);

    return $all_requirements;
}

function getAllComposerLockPackages($dependency_path) {
    $composer_lock = json_decode(file_get_contents(composerLockPath($dependency_path)), true);
    $composer_packages = array_key_exists('packages', $composer_lock) ? $composer_lock['packages'] : array();
    $composer_packages_dev = array_key_exists('packages-dev', $composer_lock) ? $composer_lock['packages-dev'] : array();

    $all_packages = array_merge($composer_packages, $composer_packages_dev);

    return $all_packages;
}

function getComposerLockFingerprint($dependency_path) {
    // $composer_lock = json_decode(file_get_contents(composerLockPath($dependency_path)), true);
    // return $composer_lock['content-hash'];
    return md5_file(composerLockPath($dependency_path));
}
