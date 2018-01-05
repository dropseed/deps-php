<?php

function act() {
    $schema = json_decode(file_get_contents('/dependencies/schema.json'), true);
    $job_id = getenv('JOB_ID');

    if ($schema['lockfiles']) {
        foreach ($schema['lockfiles'] as $path => $data) {
            $branch_name = "lockfile-update-job-$job_id";
            createGitBranch($branch_name);

            $lockfile_path = path_join('/repo', $path);
            $dependency_path = dirname($lockfile_path);
            composerUpdate($dependency_path);

            runCommand('git add .');
            runCommand("git commit -m 'Update $path'");

            pushGitBranch($branch_name);

            // pullrequest

            outputActions(array(
                $path => array(
                    'metadata' => array(
                        'git_branch' => $branch_name
                    ),
                    'dependencies' => array(
                        'lockfiles' => array(
                            $path => array(
                                'current' => lockfileSchemaFromLockfile($dependency_path)
                            )
                        )
                    )
                )
            ));
        }
    }

    if ($schema['manifests']) {

    }
}
