<?php

function getComposerOptions() {
    $from_settings = getenv('SETTING_COMPOSER_OPTIONS');
    if ($from_settings) return $from_settings;
    return "--no-progress --no-suggest";
}

function composerInstall($dependency_path) {
    runCommand("cd $dependency_path && composer install " . getComposerOptions());
}

function composerUpdate($dependency_path) {
    runCommand("cd $dependency_path && composer update " . getComposerOptions());
}

function composerRequire($dependency_path, $args) {
    runCommand("cd $dependency_path && composer require $args " . getComposerOptions());
}

function composerJsonPath($dependency_path) {
    return path_join($dependency_path, 'composer.json');
}

function composerLockPath($dependency_path) {
    return path_join($dependency_path, 'composer.lock');
}

function packageIsRequireDev($dependency_path, $name) {
    $composer_json_path = composerJsonPath($dependency_path);
    $composer_json = json_decode(file_get_contents($composer_json_path), true);
    $composer_require = array_key_exists('require', $composer_json) ? $composer_json['require'] : array();
    $composer_require_dev = array_key_exists('require-dev', $composer_json) ? $composer_json['require-dev'] : array();

    if (array_key_exists($name, $composer_require)) {
        return false;
    } else if (array_key_exists($name, $composer_require_dev)) {
        return true;
    } else {
        throw new Exception("Didn't find $name in $composer_json_path require or require-dev.");
    }
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
