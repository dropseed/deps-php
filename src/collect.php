<?php

use Composer\Semver\Semver;
use Composer\Semver\Comparator;
use Composer\Semver\VersionParser;

function collect($dependency_path, $output_path) {
    $composer_json_path = composerJsonPath($dependency_path);
    if (!file_exists($composer_json_path)) {
        throw new Exception("$composer_json_path does not exist! A composer.json file is required.");
    }

    $composer_lock_path = composerLockPath($dependency_path);
    $composer_lock_existed = file_exists($composer_lock_path);

    composerInstall($dependency_path);

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
    } else {
        echo("Removing $composer_lock_path since it didn't exist originally");
        runCommand("rm $composer_lock_path");
    }

    file_put_contents($output_path, json_encode($output));
}

function manifestSchemaFromLockfile($dependency_path) {
    $all_requirements = getAllComposerJsonRequirements($dependency_path);

    $dependencies = array();
    $updatedDependencies = array();
    $composerOutdated = composerOutdated();

    foreach($all_requirements as $name => $constraint) {
        if (shouldSkipPackageName($name)) {
            continue;
        }

        $dependencies[$name] = array(
            'constraint' => $constraint,
            'source' => 'packagist'
        );

        $latest = $composerOutdated[$name]["latest"];
        if ($latest && strpos($latest, "dev-") !== 0 && !Semver::satisfies($latest, $constraint)) {
            $updatedDependencies[$name] = array(
                'constraint' => $latest,  // TODO guess prefix
                'source' => 'packagist'
            );
        }
    }

    $output = array('current' => array('dependencies' => $dependencies));
    if (!empty($updatedDependencies)) {
        $output["updated"] = array("dependencies" => $updatedDependencies);
    }
    return $output;
}

function lockfileSchemaFromLockfile($dependency_path) {
    $all_requirements = getAllComposerJsonRequirements($dependency_path);
    $all_packages = getAllComposerLockPackages($dependency_path);

    $dependencies = array();

    foreach($all_packages as $package) {
        $name = $package['name'];
        $dependencies[$name] = array(
            'version' => array('name' => (string) $package['version']),
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

function shouldSkipPackageName($name) {
    return $name == 'php' || $name == 'hhvm' || $name == 'composer-plugin-api' || substr($name, 0, 4) === 'ext-' || substr($name, 0, 4) === 'lib-';
}

function composerOutdated() {
    $outdated = json_decode(runCommand("composer outdated --no-interaction --direct --format json"), true);
    $by_name = array();
    foreach ($outdated["installed"] as $item) {
        $by_name[$item["name"]] = $item;
    }
    return $by_name;
}
