<?php

require __DIR__ . '/vendor/autoload.php';

require_once 'composer.php';
require_once 'utils.php';
require_once 'collect.php';
require_once 'act.php';

// make any GitHub repos installable that this token also has access to
$github_token = getenv('GITHUB_API_TOKEN');
if ($github_token) {
    runCommand("echo -e \"machine github.com\n  login x-access-token\n  password $github_token\" >> ~/.netrc");
    runCommand("composer config -g http-basic.github.com x-access-token $github_token");
}

if (getenv('RUN_AS') === 'collector') {

    echo "Running as collector\n";
    collect($argv[1]);

} else if (getenv('RUN_AS') === 'actor') {

    echo "Running as actor\n";
    act();

}
