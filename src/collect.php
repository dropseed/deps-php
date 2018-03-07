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

    $output = array(
        'manifests' => array(
            $composer_json_path => manifestSchemaFromLockfile($dependency_path)
        )
    );

    // if lockfile existed originally, add that to output
    if ($composer_lock_existed) {
        $original_schema = lockfileSchemaFromLockfile($dependency_path);

        $output['lockfiles'] = array(
            $composer_lock_path => array(
                'current' => $original_schema
            )
        );

        composerUpdate($dependency_path);

        $updated_schema = lockfileSchemaFromLockfile($dependency_path);

        // only include in output if the file actually changed
        if ($updated_schema['fingerprint'] !== $original_schema['fingerprint']) {
            $output['lockfiles'][$composer_lock_path]['updated'] = $updated_schema;
        }

        // point the manifest entry to this lockfile
        $output['manifests'][$composer_json_path]['lockfile_path'] = $composer_lock_path;
    }

    $f = tmpfile();
    fwrite($f, json_encode($output));
    runCommand("deps collect " . stream_get_meta_data($f)["uri"]);
    fclose($f);
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
            'available' => $available,
            'source' => 'packagist'
        );
    }

    return array('current' => array('dependencies' => $dependencies));
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
            'is_transitive' => !isset($all_requirements[$name]),
            'source' => 'packagist'
        );
    }

    return array(
        'fingerprint' => getComposerLockFingerprint($dependency_path),
        'dependencies' => $dependencies
    );
}

function getAvailableVersionsForPackage($name, $dependency_path) {
    try {
        $info_output = runCommand("cd $dependency_path && composer show $name --all");
    } catch (Exception $e) {
        return array();
    }

    preg_match('/^versions : (.*)$/m', $info_output, $matches);

    if (count($matches) > 1) {
        $versions_string = $matches[1];
        $versions = explode(', ', $versions_string);
        return array_map(function ($version) {
            // the currently installed version has an * in front
            if (substr($version, 0, 2) === "* ") {
                return substr($version, 2);
            }
            return $version;
        }, $versions);
    }
    echo "No available versions found for \"$name\", skipping\n";
    return array();
}

function shouldSkipPackageName($name) {
    return $name == 'php' || $name == 'hhvm' || $name == 'composer-plugin-api' || substr($name, 0, 4) === 'ext-' || substr($name, 0, 4) === 'lib-';
}
