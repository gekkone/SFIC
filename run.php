<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use SFIC\SficScan;

require 'vendor/autoload.php';

log_rotate('logs', 2);
$logFile = 'logs/SFIC_' . date('d-m-Y') . '.log';
$logger = new Logger('SFIC');
$logger->pushHandler(new StreamHandler($logFile, Logger::INFO));
$config = include 'config.php';

$scaner = new SficScan($config, $logger);
$scaner->scan();

/**
 * @param string $logsDir
 * @param int $count
 * @return void
 */
function log_rotate($logsDir, $count)
{
    $it = new FilesystemIterator($logsDir, FilesystemIterator::CURRENT_AS_PATHNAME);
    /**
     * @var DateTime[]
     */
    $dateFiles = [];

    foreach ($it as $filename) {
        if (!$it->isFile()) {
            continue;
        }

        if (preg_match('/SFIC_([0-9]{2}-[0-9]{2}-[0-9]{4})\.log/', $filename, $matches) === false) {
            continue;
        }

        $date = DateTime::createFromFormat('d-m-Y', $matches[1]);
        $date->setTime(0, 0);

        $inserted = false;
        for ($i = 0, $fileCount = count($dateFiles); $i < $fileCount; ++$i) {
            $dateFile = $dateFiles[$i];
            if ($date < $dateFile) {
                $dateFiles = array_splice($dateFiles, $i, 0, [$date]);

                $inserted = true;
                break;
            }
        }

        if (!$inserted) {
            $dateFiles[] = $date;
        }
    }

    $fileCount = count($dateFiles);

    if ($fileCount > $count) {
        for ($i = $fileCount - 1; $i >= $count; --$i) {
            $filename = $logsDir . '/SFIC_' . $dateFiles[$i]->format('d-m-Y') . '.log';
        }
    }
}
