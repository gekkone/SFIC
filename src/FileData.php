<?php

namespace SFIC;

class FileData
{
    const ATTRIBUTE_MODE = 'attribute';
    const CONTENT_MODE = 'content';

    /**
     * Полный путь расположения файла
     * @var string
     */
    private $path;
    /**
     * Размер файла в байтах
     * @var int
     */
    private $size;
    /**
     * Дата последнего изменения файла
     * @var int
     */
    private $dateChanged;
    /**
     * Проверочный хеш файла
     * @var string
     */
    private $checkHash;

    /**
     * @return string
     */
    public function path()
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function pathHash()
    {
        return md5($this->path);
    }

    /**
     * @return string
     */
    public function name()
    {
        return basename($this->path);
    }

    /**
     * @return int
     */
    public function size()
    {
        return $this->size;
    }

    /**
     * @return int
     */
    public function dateChanged()
    {
        return $this->dateChanged;
    }

    /**
     * @return string
     */
    public function ext()
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    /**
     * @return string
     */
    public function checkHash()
    {
        return $this->checkHash;
    }

    /**
     * @param string $filePath - путь к файлу
     * @param string $mode - режим проверки изменений
     * @return bool - true если удалось установить информацию о файла, false в противном случае
     */
    public function setFileData($filePath, $mode)
    {
        if (!is_file($filePath)) {
            return false;
        }

        $this->path = $filePath;
        $this->size = filesize($filePath);
        $this->dateChanged = filemtime($filePath);

        switch ($mode) {
            case self::CONTENT_MODE:
                $this->checkHash = md5_file($filePath);
                break;
            case self::ATTRIBUTE_MODE:
            default:
                $this->checkHash = md5(filesize($filePath) . filemtime($filePath));
        }

        return true;
    }

    /**
     * @param string $fileData
     * @param string $separator
     * @return bool - true, если значения были установлены, false в противном случае
     */
    public function setFileDataFromString($fileData, $separator = ';')
    {
        /**
         * @var string[] $data
         */
        $data = explode($separator, $fileData);

        if (count($data) < 4) {
            return false;
        }

        $this->path = $data[0];
        $this->size = is_numeric($data[1]) ? (int)$data[1] : 0;
        $this->dateChanged = is_numeric($data[2]) ? (int)$data[2] : 0;
        $this->checkHash = $data[3];

        return true;
    }

    /**
     * @param string $separator
     * @return string
     */
    public function toString($separator = ';')
    {
        return $this->path . $separator
            . $this->size . $separator
            . $this->dateChanged . $separator
            . $this->checkHash . $separator;
    }

    /**
     * @param FileData $otherData
     * @param string $reason - описание различий в файлах
     * @return bool
     */
    public function compare(FileData $otherData, &$reason)
    {
        if ($this->checkHash == $otherData->checkHash) {
            return true;
        }

        $reason = '(' . date('Y-m-d H:i:s') . ') ';

        if ($this->size != $otherData->size) {
            $reason .= 'Изменился размер файла ' . $this->readableFileSize($this->size)
                . ' -> ' . $this->readableFileSize($otherData->size);
        } else {
            $reason .= 'Размер файла не изменился';
        }

        return false;
    }

    /**
     * Возвращает разрмер файла в читаемом для пользователя формате
     * @param int $size
     * @return string
     */
    private function readableFileSize($size)
    {
        if ($size > pow(10, 9)) {
            $size /= pow(10, 9);
            return $size . ' Гб';
        } elseif ($size > pow(10, 6)) {
            $size /= pow(10, 6);
            return $size . ' Мб';
        } elseif ($size > pow(10, 3)) {
            $size /= pow(10, 3);
            return $size . ' Кб';
        }

        return $size . ' Б';
    }
}
