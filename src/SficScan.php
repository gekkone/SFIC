<?php

namespace SFIC;

use Monolog\Logger;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SficScan
{
    const ATTRIBUTE_MODE = 'attribute';
    const CONTENT_MODE = 'content';

    /**
     * @var array[]
     */
    private $config = [];
    /**
     * @var LoggerInterface
     */
    private $logger = null;
    /**
     * @var float
     */
    private $timeStart = 0.0;
    /**
     * Название файла который хранит информацию о файлах, сохраняется в сканируемой папке
     * @var string
     */
    private $dataFile = "data.sfic";

    /**
     * @param array[] $config - массив с конфигурацией
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(array $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->timeStart = microtime(true);
    }

    /**
     * @return void
     */
    public function scan()
    {
        $commonReport = '';

        if (!isset($this->config['scan']) || !is_array($this->config['scan'])) {
            $this->logger->critical('В конфигурации не указан параметр "scan" - конфигурации сканируемых папок');
            return;
        }

        for ($i = 0, $count = count($this->config['scan']); $i < $count; ++$i) {
            $report = $this->scanDir($this->config['scan'][$i]);
            if ($report === false) {
                $commonReport .= 'Не удалось просканировать папку указанную '
                    . "в $i по порядку конфиге, см подробности в логе" . PHP_EOL;
            } else {
                $commonReport .= $report . PHP_EOL;
            }
        }

        $commonReport = 'Сканирование завершено за ' . (microtime(true) - $this->timeStart)
            . ' секунд' . PHP_EOL . $commonReport;

        $this->logger->info($commonReport);

        $to = isset($this->config['notify']['to']) ? $this->config['notify']['to'] : null;
        $from = isset($this->config['notify']['from']) ? $this->config['notify']['from'] : null;

        if (!empty($to) && !empty($from)) {
            if (!empty($commonReport)) {
                $this->emailNotify($to, $from, $commonReport);
            }
        } else {
            $this->logger->error('Уведомление не было отпправлно на почту, '
                . 'не установлены email адрес отправителя или получателя');
        }
    }

    /**
     * Undocumented function
     * @param array<string, mixed> $config
     * @return string|false отчёт о сканировании
     */
    private function scanDir($config)
    {
        if (!isset($config['dir'])) {
            $this->logger->warning("В одном из наборов конфигурации не задан"
                . "параметр 'dir' - папка для сканирования");
            return false;
        }

        $scandir = $config['dir'];
        if (empty($scandir) || !file_exists($scandir)) {
            $this->logger->error("Не найдена папка $scandir");
            return false;
        }

        if (substr($scandir, strlen($scandir) - 1) !== \DIRECTORY_SEPARATOR) {
            $scandir .= DIRECTORY_SEPARATOR;
        }

        $mode = isset($config['mode']) ? $config['mode'] : self::ATTRIBUTE_MODE;
        $extensions = !empty($config['extensions']) ? explode(',', $config['extensions']) : ['php'];

        $this->logger->info("Сканирование $scandir");

        $oldData = $this->oldData($scandir, $extensions);

        $added   = [];
        $changed = [];
        $deleted = [];
        $scanCount = 0;

        $dataFile = @fopen($scandir . $this->dataFile, 'w');
        if (!$dataFile) {
            $this->logger->critical("Не удалось создать файл $scandir$this->dataFile с данными для "
                . 'сохраннеия результата сканирования');
            return false;
        }

        $dirIt = new RecursiveDirectoryIterator($scandir);
        $it = new RecursiveIteratorIterator($dirIt, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD);

        foreach ($it as $filename) {
            $ext = mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($ext, $extensions)) {
                continue;
            }

            $data = new FileData();
            if (!$data->setFileData($filename, $mode)) {
                $this->logger->error("Для $filename не удалось сформировать данные с иформацией");
                continue;
            }

            fwrite($dataFile, $data->toString() . PHP_EOL);
            ++$scanCount;

            $dataPathHash = $data->pathHash();

            if (isset($oldData[$dataPathHash])) {
                $oldDataFile = $oldData[$dataPathHash];

                if (!$oldDataFile->compare($data, $info)) {
                    $changed[] = [$data->path(), $info];
                }

                unset($oldData[$dataPathHash]);
            } else {
                $added[] = $data->path();
            }
        }

        foreach ($oldData as $pathHash => $data) {
            if (in_array($data->ext(), $extensions)) {
                $deleted[] = $data->path();
            }
        }

        fclose($dataFile);

        if (!empty($added) || !empty($changed) || !empty($deleted)) {
            return $this->formReport($added, $changed, $deleted, $scandir, $scanCount);
        }

        return '';
    }

    /**
     * Возвращает данные предыдущего сканирования папки
     * @param string $scanDir
     * @param string[] $extensions
     * @return array<string, FileData>
     */
    private function oldData($scanDir, $extensions)
    {
        $dataFile = $scanDir . $this->dataFile;
        if (!file_exists($dataFile)) {
            return [];
        }
        $file = fopen($dataFile, 'r');
        if ($file === false) {
            return [];
        }

        $oldData = [];

        while (($buffer = fgets($file)) !== false) {
            $data = new FileData();
            if (!$data->setFileDataFromString($buffer)) {
                $this->logger->error("Сохранённые данные прошлого сканирования повреждены (mayble <5)");
                continue;
            }

            if (in_array($data->ext(), $extensions)) {
                $oldData[$data->pathHash()] = $data;
            }
        }

        fclose($file);

        return $oldData;
    }

    /**
     * Формирует отчёт о проверке
     * @param string[] $added
     * @param array<string[]> $changed
     * @param string[] $deleted
     * @param string $scanDir
     * @param int $scanCount
     * @return string
     */
    private function formReport($added, $changed, $deleted, $scanDir, $scanCount)
    {
        $date = date('Y-m-d H:i:s', time());
        $addedCount = count($added);
        $changedCount = count($changed);
        $deletedCount = count($deleted);

        $report = "Результаты сканирования $scanDir ($date):" . PHP_EOL
            . "Проверено $scanCount файлов, новых: $addedCount, изменённых: $changedCount, удалённых: $deletedCount" . PHP_EOL;

        if (!empty($changed)) {
            $report .= 'Изменённые:' . PHP_EOL;

            foreach ($changed as $data) {
                $report .= "{$data[0]}: {$data[1]}" . PHP_EOL;
            }
        }

        if (!empty($added)) {
            $report .= 'Добавленные:' . PHP_EOL . join(PHP_EOL, $added) . PHP_EOL;
        }

        if (!empty($deleted)) {
            $report .= 'Удалённые:' . PHP_EOL . join(PHP_EOL, $deleted);
        }

        return $report;
    }

    /**
     * Отправляет уведомление на почту
     * @param string $to - кому
     * @param string $from - от кого
     * @param string $notify - текст уведомления
     * @return void
     */
    private function emailNotify($to, $from, $notify)
    {
        $headers = "Content-Type: text/plain\r\n";
        $headers .= "From: $from\r\n";
        $headers .= "X-Priority: 3\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        mail($to, "Мониторинг файлов", $notify, $headers);
    }
}
