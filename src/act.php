<?php

function act() {
    $data = json_decode(file_get_contents('/dependencies/input_data.json'), true);

    runCommand("deps branch");

    if ($data['lockfiles']) {
        foreach ($data['lockfiles'] as $path => $lockfile) {
            $lockfile_path = realpath($path);
            $dependency_path = dirname($lockfile_path);
            composerUpdate($dependency_path);

            runCommand("deps commit -m 'Update $path' .");

            // set the updated data to exactly what we installed, regardless of what was asked for (could have changed)
            $data['lockfiles'][$path]['updated'] = lockfileSchemaFromLockfile($dependency_path);
        }
    }

    if ($data['manifests']) {
        foreach ($data['manifests'] as $path => $manifest) {
            $composer_json_path = realpath($path);
            $dependency_path = dirname($composer_json_path);
            $composer_lock_path = composerLockPath($dependency_path);
            $composer_lock_existed = file_exists($composer_lock_path);

            foreach($manifest['updated']['dependencies'] as $name => $updated_dep_data) {
                $installed = $manifest['current']['dependencies'][$name]['constraint'];
                $update = $updated_dep_data['constraint'];

                composerInstall($dependency_path);

                if (packageIsRequireDev($dependency_path, $name)) {
                    composerRequire($dependency_path, "$name:$update --dev");
                } else {
                    composerRequire($dependency_path, "$name:$update");
                }

                if (!$composer_lock_existed) {
                    runCommand("rm $composer_lock_path");
                }

                $message = "Update $name from $installed to $update";
                runCommand("deps commit -m '$message' .");
            }
        }
    }

    $dependenciesFile = tmpfile();
    fwrite($dependenciesFile, json_encode($data));
    runCommand("deps pullrequest " . stream_get_meta_data($dependenciesFile)["uri"]);
    fclose($dependenciesFile);
}
