<?php

require __DIR__ . '/../vendor/autoload.php';

require_once 'composer.php';
require_once 'utils.php';
require_once 'collect.php';
require_once 'act.php';

if (getenv('RUN_AS') === 'collector') {

    echo "Running as collector\n";
    collect($argv[1], $argv[2]);

} else if (getenv('RUN_AS') === 'actor') {

    echo "Running as actor\n";
    act($argv[1], $argv[2]);

}
