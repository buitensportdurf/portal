<?php

namespace Deployer;

require_once 'recipe/common.php';

// Project name
set('application', 'durfportal');

// Project repository
set('repository', 'git@github.com:buitensportdurf/portal.git');

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);

// Shared files/dirs between deploys 
set('shared_dirs', ['var/log', 'var/sessions', 'public/uploads']);
set('shared_files', ['.env.local']);
//set('writable_dirs', ['var']);
set('migrations_config', '');
set('allow_anonymous_stats', false);

set('bin/console', fn() => parse('{{release_path}}/bin/console'));

// Hosts
//host('app.bluelinked.nl')
host('durf.loken.nl')
    ->setRemoteUser('www-data')
    ->set('branch', function () {
        return input()->getOption('branch') ?: 'master';
    })
    ->set('deploy_path', '~/durf.loken.nl.nl');

set('console_options', function () {
    return '--no-interaction';
});

desc('Clear cache');
task('cache:clear', function () {
    run('{{bin/php}} {{bin/console}} cache:clear {{console_options}} --no-warmup');
});

desc('Warm up cache');
task('cache:warmup', function () {
    run('{{bin/php}} {{bin/console}} cache:warmup {{console_options}}');
});

desc('Migrate database');
task('database:migrate', function () {
    $options = '--allow-no-migration';
    if (get('migrations_config') !== '') {
        $options = sprintf('%s --configuration={{release_path}}/{{migrations_config}}', $options);
    }

    run(sprintf('{{bin/php}} {{bin/console}} doctrine:migrations:migrate %s {{console_options}}', $options));
//    run('{{bin/php}} {{bin/console}} doctrine:schema:update --force');
});

desc('Transfers information about current git commit to server');
task('deployment:log', function () { //https://stackoverflow.com/questions/59686270/how-to-log-deployments-in-deployer
    $branch = parse('{{branch}}');
    $date = date('Y-m-d H:i:s');
    $commitHashShort = runLocally('git rev-parse --short HEAD');
//    $commitHash = runLocally('git rev-parse HEAD');
    $commit = explode(PHP_EOL, runLocally('git log -1 --pretty="%H%n%ci"'));
    $commitHash = $commit[0];
    $commitDate = $commit[1];

//    $line = sprintf('%s %s branch="%s" hash="%s"', $date, $commitHashShort, $branch, $commitHash);
    $array = [
        'branch' => $branch,
        'date' => $date,
        'commitHashShort' => $commitHashShort,
        'commitHashLong' => $commitHash,
        'commitDate' => $commitDate,
    ];
    $json = json_encode($array, JSON_PRETTY_PRINT);

    runLocally("echo '$json' > release.json");
    upload('release.json', '{{release_path}}/release.json');
});

desc('Shows current deployed version');
task('deploy:current', function () {
    $current = run('readlink {{deploy_path}}/current');
    writeln("Current deployed version: $current");
});

desc('Deploy project');
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'cache:clear',
    'cache:warmup',
    'database:migrate',
    'deployment:log',
    'deploy:symlink',
    'deploy:unlock',
    'deploy:cleanup',
    'deploy:current',
]);

after('deploy', 'deploy:success');

// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');
