<?php

require __DIR__ . '/vendor/autoload.php';

require_once 'collect.php';
require_once 'utils.php';

if (getenv('RUN_AS') === 'collector') {
    echo "Running as collector\n";

    $composer_dir = path_join('/repo', $argv[1]);
    collect($composer_dir);

} else if (getenv('RUN_AS') === 'actor') {
    echo "Running as actor\n";
}
