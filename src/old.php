<?php

use Composer\Semver\Semver;

require __DIR__ . '/vendor/autoload.php';

$collect_transitive = getenv('SETTING_COLLECT_TRANSITIVE') === 'true';
$github_token = getenv('GITHUB_API_TOKEN');
if ($github_token) {
    shell_exec("echo -e \"machine github.com\n  login x-access-token\n  password $github_token\" >> ~/.netrc");
    shell_exec("composer config -g http-basic.github.com x-access-token $github_token");
}

function path_join($base, $path) {
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

$collected = array();
$has_run_composer_install = false;

// the directory the user pointed us to
$composer_dir = path_join('/repo', $argv[1]);

$composer_json_path = path_join($composer_dir, 'composer.json');
if (!file_exists($composer_json_path)) {
    throw new Exception("$composer_json_path does not exist! A composer.json file is required.");
}
$composer_json = json_decode(file_get_contents($composer_json_path), true);
$composer_require = array_key_exists('require', $composer_json) ? $composer_json['require'] : array();
$composer_require_dev = array_key_exists('require-dev', $composer_json) ? $composer_json['require-dev'] : array();

$composer_lock_path = path_join($composer_dir, 'composer.lock');
if (!file_exists($composer_lock_path)) {
    echo "$composer_lock_path not found. Running \"composer install\" to generate one...\n";
    shell_exec("cd $composer_dir && composer install --ignore-platform-reqs --no-scripts");
    $has_run_composer_install = true;
}

$composer_lock = json_decode(file_get_contents($composer_lock_path), true);
$composer_packages = array_key_exists('packages', $composer_lock) ? $composer_lock['packages'] : array();
$composer_packages_dev = array_key_exists('packages-dev', $composer_lock) ? $composer_lock['packages-dev'] : array();

$all_packages = array_merge($composer_packages, $composer_packages_dev);
$all_requirements = array_merge($composer_require, $composer_require_dev);

foreach ($all_packages as $package) {
    $name = $package['name'];
    $is_transitive = !isset($all_requirements[$name]);

    if ($is_transitive && !$collect_transitive) continue;

    if ($name == 'php' || $name == 'hhvm' || $name == 'composer-plugin-api' || substr($name, 0, 4) === 'ext-' || substr($name, 0, 4) === 'lib-') {
        echo "Skipping platform package: \"$name\".\n";
        continue;
    } else {
        $transitive_or_direct = $is_transitive ? 'transitive' : 'direct';
        echo "Collecting $transitive_or_direct dependency: $name\n";
    }

    $info_output = shell_exec("composer show $name --all");
    preg_match('/^versions : (.*)$/m', $info_output, $matches);

    if (count($matches) > 1) {
        $versions_string = $matches[1];
        $versions = explode(', ', $versions_string);
        $available = array_map(function ($version) {
            // the currently installed version has an * in front
            if (substr($version, 0, 2) === "* ") {
                $version = substr($version, 2);
            }
            return $version;
        }, $versions);
    } else {
        echo "No available versions found for \"$name\", skipping\n";
        continue;
    }

    if ($is_transitive) {
        // Only report back versions that satisfy the constraints given by other packages
        $constraints = [];

        foreach ($all_packages as $pkg) {
            if (isset($pkg['require'])) {
                foreach ($pkg['require'] as $requiredPkg => $constraint) {
                    if ($requiredPkg === $name) $constraints[] = $constraint;
                }
            }
        }

        if (!empty($constraints)) {
            $available = array_filter($available, function($version) use($constraints) {
                // have to compare each version to each constraint individually
                foreach ($constraints as $constraint) {
                    // if any version is not satisfied by one of the constraints, it has to be removed
                    if (!Semver::satisfies($version, $constraint)) return false;
                }
                return true;
            });
            $available = array_values($available);  // fix indexes after filter
        }
    }

    echo "Finding installed version of \"$name\" based on composer.lock\n";

    $installed_version = array_filter($all_packages, function($package) use($name) {
        return strtolower($package['name']) == strtolower($name);
    });
    // indexes might be thrown off
    $installed_version = array_values($installed_version)[0]['version'];

    // if the installed version is dev-.* then we will expand that to include
    // the ref installed, and prepend the available list with the latest ref available
    if (substr($installed_version, 0, 4) === 'dev-') {
        $outdated_output = json_decode(shell_exec("composer outdated --all --format json"), true);

        if (empty($outdated_output) && !$has_run_composer_install) {
            // we really want to avoid running composer install unless we have to
            echo "Running composer install once to make sure nothing is outdated\n";
            shell_exec("cd $composer_dir && composer install --ignore-platform-reqs --no-scripts");
            $has_run_composer_install = true;
            $outdated_output = json_decode(shell_exec("composer outdated --all --format json"), true);
        }

        if (array_key_exists('installed', $outdated_output)) {
            $matches = array_filter($outdated_output['installed'], function($i) use($name) {
                return strtolower($i['name']) == strtolower($name);
            });
            if (count($matches) > 0) {
                $outdated_match = array_values($matches)[0];
                $spaces_to_replace = 1;
                $installed_version = str_replace(' ', '#', $outdated_match['version'], $spaces_to_replace);  // update to "dev-master#xxxxx"
                $available = array(str_replace(' ', '#', $outdated_match['latest'], $spaces_to_replace));  // only report 1 available version which is the update
            }
        }
    }

    $schema_output = array(
        'name' => $name,
        'source' => 'packagist',  // TODO any way to tell if it came from another repository?
        'path' => $argv[1],
        'installed' => array('version' => $installed_version),
        'available' => array_map(function ($version) {
            // dependencies.io schema expects it in this form
            return array('version' => $version);
        }, $available)
    );

    array_push($collected, $schema_output);
}

// send the final output to stdout so dependencies.io can pick it up
$final_output = json_encode(array('dependencies' => $collected));
echo('BEGIN_DEPENDENCIES_SCHEMA_OUTPUT>' . $final_output . '<END_DEPENDENCIES_SCHEMA_OUTPUT\n');
