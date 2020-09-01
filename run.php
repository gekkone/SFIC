<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SFIC\SficScan;

require 'vendor/autoload.php';

log_rotate('SFIC.log', 5 * pow(10, 6), 2);
$logger = new Logger('SFIC');
$logger->pushHandler(new StreamHandler('SFLIC.log', Logger::INFO));
$config = include 'config.php';

$scaner = new SficScan($config, $logger);
$scaner->scan();

/**
 * @param string $filename
 * @param int $size
 * @param int $count
 * @return void
 */
function log_rotate($filename, $size, $count)
{
    $fileSize = @filesize($filename);

    if (false == $filename || $fileSize < $size) {
        return;
    }

    for ($i = $count; $i > 0; --$i) {
        $prevFilename = $filename . '.' . ($i - 1);
        if (file_exists($prevFilename)) {
            rename($prevFilename, "$filename.$i");
        }
    }

    rename($filename, "$filename.1");
}
