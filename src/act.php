<?php

function act() {
    $data = json_decode(file_get_contents('/dependencies/input_data.json'), true);
    $job_id = getenv('JOB_ID');
    $commit_message_prefix = getenv('SETTING_COMMIT_MESSAGE_PREFIX');

    $branch_name = "deps/update-job-$job_id";
    createGitBranch($branch_name);

    if ($data['lockfiles']) {
        foreach ($data['lockfiles'] as $path => $lockfile) {
            $lockfile_path = path_join('/repo', $path);
            $dependency_path = dirname($lockfile_path);
            composerUpdate($dependency_path);

            runCommand('git add .');
            runCommand("git commit -m '${commit_message_prefix}Update $path'");

            // set the updated data to exactly what we installed, regardless of what was asked for (could have changed)
            $data['lockfiles'][$path]['updated'] = lockfileSchemaFromLockfile($dependency_path);
        }
    }

    if ($data['manifests']) {
        foreach ($data['manifests'] as $path => $manifest) {
            $composer_json_path = path_join('/repo', $path);
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

                if ($composer_lock_existed) {
                    runCommand("git add $composer_lock_path");
                } else {
                    runCommand("rm $composer_lock_path");
                }

                runCommand("git add $composer_json_path");
                $message = "{$commit_message_prefix}Update $name from $installed to $update";
                runCommand("git commit -m '$message'");
            }
        }
    }

    pushGitBranch($branch_name);
    runCommand("pullrequest --branch " . escapeshellarg($branch_name) . " --dependencies-json " . escapeshellarg(json_encode($data)));
}
