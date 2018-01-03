<?php

use Composer\Semver\Semver;
use Composer\Semver\Comparator;

function collect($dependency_path) {
    $composer_json_path = composerJsonPath($dependency_path);
    if (!file_exists($composer_json_path)) {
        throw new Exception("$composer_json_path does not exist! A composer.json file is required.");
    }

    $composer_lock_path = composerLockPath($dependency_path);
    $composer_lock_existed = file_exists($composer_lock_path);

    if (!$composer_lock_existed) {
        echo "$composer_lock_path not found. Running \"composer install\" to generate one...\n";
        composerInstall($dependency_path);
    }

    $composer_json_repo_path = pathInRepo($composer_json_path);

    $output = array(
        'manifests' => array(
            $composer_json_repo_path => manifestSchemaFromLockfile($dependency_path)
        )
    );

    // if lockfile existed originally, add that to output
    if ($composer_lock_existed) {
        $original_schema = lockfileSchemaFromLockfile($dependency_path);
        $lockfile_repo_path = pathInRepo($composer_lock_path);

        $output['lockfiles'] = array(
            $lockfile_repo_path => array(
                'current' => $original_schema
            )
        );

        composerUpdate($dependency_path);

        $updated_schema = lockfileSchemaFromLockfile($dependency_path);

        // only include in output if the file actually changed
        if ($updated_schema['checksum'] !== $original_schema['checksum']) {
            $output['lockfiles'][$lockfile_repo_path]['updated'] = $updated_schema;
        }

        // point the manifest entry to this lockfile
        $output['manifests'][$composer_json_repo_path]['lockfile_path'] = $lockfile_repo_path;
    }

    outputSchema($output);
}

function composerInstall($dependency_path) {
    shell_exec("cd $dependency_path && composer install --ignore-platform-reqs --no-scripts");
}

function composerUpdate($dependency_path) {
    shell_exec("cd $dependency_path && composer update --ignore-platform-reqs --no-scripts");
}

function manifestSchemaFromLockfile($dependency_path) {
    $all_requirements = getAllComposerJsonRequirements($dependency_path);
    $all_packages = getAllComposerLockPackages($dependency_path);

    $getInstalledVersion = function($name) use($all_packages) {
        foreach ($all_packages as $p) {
            if ($p['name'] === $name) return $p['version'];
        }
    };

    $getAvailableVersionsForManifest = function($name, $installed, $constraint) use($dependency_path) {
        $versions = getAvailableVersionsForPackage($name, $dependency_path);

        // manifest only wants versions outside (and above) of their range
        $versions = array_filter($versions, function($v) use($installed, $constraint) {
            if (Semver::satisfies($v, $constraint)) return false;  // don't want anything that can already be installed with constraint
            if (Comparator::lessThan($v, $installed)) return false;  // don't want anything semver less than what we already have
            return true;
        });

        $versions = array_values($versions);

        return $versions;
    };

    $dependencies = array();

    foreach($all_requirements as $name => $constraint) {
        if (shouldSkipPackageName($name)) {
            continue;
        }

        $installed = $getInstalledVersion($name);
        $available = array_map(function($v) {
            return array('name' => $v);
        }, $getAvailableVersionsForManifest($name, $installed, $constraint));

        $dependencies[$name] = array(
            'constraint' => $constraint,
            'installed' => array('name' => $installed),
            'available' => $available,
            'source' => 'packagist'
        );
    }

    return array('dependencies' => $dependencies);
}

function lockfileSchemaFromLockfile($dependency_path) {
    $all_requirements = getAllComposerJsonRequirements($dependency_path);
    $all_packages = getAllComposerLockPackages($dependency_path);

    $dependencies = array();

    foreach($all_packages as $package) {
        $name = $package['name'];
        $dependencies[$name] = array(
            'installed' => array('name' => $package['version']),
            // 'constraint' => 'get from all other requires and requires-dev?'
            'relationship' => isset($all_requirements[$name]) ? 'direct' : 'transitive',
            'source' => 'packagist'
        );
    }

    return array(
        'checksum' => md5_file(composerLockPath($dependency_path)),
        'dependencies' => $dependencies
    );
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

function getAvailableVersionsForPackage($name, $dependency_path) {
    $info_output = shell_exec("cd $dependency_path && composer show $name --all");
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
        return $versions;
    }
    echo "No available versions found for \"$name\", skipping\n";
    return array();
}

function composerJsonPath($dependency_path) {
    return path_join($dependency_path, 'composer.json');
}

function composerLockPath($dependency_path) {
    return path_join($dependency_path, 'composer.lock');
}

function shouldSkipPackageName($name) {
    return $name == 'php' || $name == 'hhvm' || $name == 'composer-plugin-api' || substr($name, 0, 4) === 'ext-' || substr($name, 0, 4) === 'lib-';
}
