<?php
// Test helper, run from the command line against a scratch copy of the site.
//   php ratelimit_file.php <site dir> dir                   private folder in use ('' if none)
//   php ratelimit_file.php <site dir> file <bucket> <who>   path of that bucket's file
//   php ratelimit_file.php <site dir> limit <bucket> <perIp 0|1>   prints allowed or refused
$site = $argv[1]; $mode = $argv[2];
require $site . '/hp-lib.php';
if ($mode === 'dir') { echo hp_data_dir(), "\n"; exit; }
if ($mode === 'file') {
    $lib = dirname((new ReflectionFunction('hp_rate_limit'))->getFileName());
    echo hp_data_dir() . '/rl-' . md5($lib . '|' . $argv[3] . '|' . $argv[4]), "\n";
    exit;
}
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
echo hp_rate_limit($argv[3], 5, 3600, $argv[4] === '1') ? "allowed\n" : "refused\n";
