<?php

function act($input_path, $output_path) {
    $data = json_decode(file_get_contents($input_path), true);

    if ($data['lockfiles']) {
        foreach ($data['lockfiles'] as $path => $lockfile) {
            $lockfile_path = realpath($path);
            $dependency_path = dirname($lockfile_path);
            composerUpdate($dependency_path);

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
                $update = $updated_dep_data['constraint'];

                if (packageIsRequireDev($dependency_path, $name)) {
                    composerRequire($dependency_path, "$name:$update --dev");
                } else {
                    composerRequire($dependency_path, "$name:$update");
                }
            }

            if (!$composer_lock_existed) {
                runCommand("rm $composer_lock_path");
            }
        }
    }

    file_put_contents($output_path, json_encode($data));
}
