<?php

namespace Deployer;

require 'recipe/common.php';

// Project name
set('application', 'xySite');

set('drupal_site', 'default');

set('default_stage', 'staging');

set('keep_releases', 5);

set('ssh_multiplexing', true);
set('http_user', 'www-data');
set('writable_mode', 'chmod');

// Project repository
set('repository', '');

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);

// Shared files/dirs between deploys
set('shared_files', [
  '.env',
  '.htaccess', // Basic Auth
  'web/sites/{{drupal_site}}/settings.php',
  'web/sites/{{drupal_site}}/services.yml'
]);
set('shared_dirs', [
  'web/sites/{{drupal_site}}/files',
  'keys',
  'private_files',
  'tmp'
]);

// Writable dirs by web server
set('writable_dirs', [
  'web/sites/{{drupal_site}}/files',
  'tmp'
]);

// Do not share anonymous data
set('allow_anonymous_stats', false);

host('deinbge.dev')
    ->user('deinbged')
    ->forwardAgent(true)
    ->stage('staging')
    ->set('branch', 'develop')
    ->set('deploy_path', '/home/deinbged/web/xySite.dev');

// Drupal
// See more: https://chromatichq.com/blog/drupal-8-deployment-scripts
desc('Drupal: Status');
task('drupal:status', 'cd {{release_path}} && vendor/bin/drush status');

desc('Drupal: Clear Drush Cache');
task('drupal:clear_drush_cache', 'cd {{release_path}} && vendor/bin/drush cc drush');

desc('Drupal: Import Configuration (Sync)');
task('drupal:import_config', 'cd {{release_path}} && vendor/bin/drush cim -y');

desc('Drupal: Update Database');
task('drupal:update_db', 'cd {{release_path}} && vendor/bin/drush updatedb -y');

desc('Drupal: Reset Caches');
task('drupal:reset_caches', 'cd {{release_path}} && vendor/bin/drush cr');

desc('Drupal: Change default folder mode');
task('drupal:chmod_default', 'cd {{release_path}}/web/sites && chmod 0755 default');


// Build NPM
task('build:install', function () {
    runLocally('npm install');
}); // "npm ci" is available from npm 5.7.1
task('build:build', function () {
    runLocally('npm run production');
});
task('build:upload', function () {
    upload('public/build', '{{release_path}}/public');
});
task('testing:run', function () {
    run('ls', ['tty' => false]);
});

// Define deploy task
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',

    // 'build:install',
    // 'build:build',
    // 'build:cleanup',
    // 'build:upload',

    'deploy:vendors',
    'deploy:clear_paths',
    'deploy:symlink',

    'drupal:status',
    'drupal:clear_drush_cache',
    'drupal:import_config',
    'drupal:update_db',
    'drupal:reset_caches',
    //'drupal:chmod_default',

    'deploy:unlock',
    'cleanup',
    'success',
]);

// [Optional] If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');
