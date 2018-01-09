<?php

function act() {
    $schema = json_decode(file_get_contents('/dependencies/schema.json'), true);
    $job_id = getenv('JOB_ID');
    $commit_message_prefix = getenv('SETTING_COMMIT_MESSAGE_PREFIX');

    if ($schema['lockfiles']) {
        foreach ($schema['lockfiles'] as $path => $lockfile) {
            $branch_name = "lockfile-update-job-$job_id";
            createGitBranch($branch_name);

            $lockfile_path = path_join('/repo', $path);
            $dependency_path = dirname($lockfile_path);
            composerUpdate($dependency_path);

            runCommand('git add .');
            runCommand("git commit -m '${commit_message_prefix}Update $path'");

            pushGitBranch($branch_name);

            $results = array(
                'lockfiles' => array(
                    $path => array(
                        'current' => $lockfile['current'],
                        'updated' => lockfileSchemaFromLockfile($dependency_path)
                    )
                )
            );

            runCommand("pullrequest --branch " . escapeshellarg($branch_name) . " --dependencies-json " . escapeshellarg(json_encode($results)));
        }
    }

    if ($schema['manifests']) {
        foreach ($schema['manifests'] as $path => $manifest) {
            $composer_json_path = path_join('/repo', $path);
            $dependency_path = dirname($composer_json_path);
            $composer_lock_path = composerLockPath($dependency_path);
            $composer_lock_existed = file_exists($composer_lock_path);

            foreach($manifest['current']['dependencies'] as $name => $dep_data) {
                // figure out if is dev or not
                // composer install
                $highest = $dep_data['available'][count($dep_data['available']) - 1];
                $installed = $dep_data['installed']['name'];
                $update = $highest['name'];

                $branch_name = "$name-$update-$job_id";
                createGitBranch($branch_name);

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

                pushGitBranch($branch_name);

                // set manifest.updated field
                if (!$manifeset['updated']) $manifest['updated'] = array('dependencies' => array());
                $manifest['updated']['dependencies'][$name] = array(
                    'installed' => array('name' => $update),
                    'constraint' => $update,
                    'source' => $dep_data['source']
                );

                $results = array(
                    'manifests' => array(
                        $path => array(
                            'current' => array(
                                'dependencies' => array(
                                    $name => $dep_data
                                )
                            ),
                            'updated' => array(
                                'dependencies' => array(
                                    $name => $manifest['updated']['dependencies'][$name]
                                )
                            )
                        )
                    )
                );
                runCommand("pullrequest --branch " . escapeshellarg($branch_name) . " --dependencies-json " . escapeshellarg(json_encode($results)));
            }
        }
    }
}
