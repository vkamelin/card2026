<?php

declare(strict_types=1);

namespace App\Helpers;

use Psr\Http\Message\UploadedFileInterface;
use Random\RandomException;
use RuntimeException;

/**
 * Хелпер для работы с файловым хранилищем
 */
class FileStorageHelper
{
    /**
     * Путь к публичной директории
     */
    private static string $publicPath = __DIR__ . '/../../public/uploads';

    /**
     * Путь к приватной директории
     */
    private static string $privatePath = __DIR__ . '/../../storage';

    /**
     * Базовый URL для публичных файлов
     */
    private static string $publicUrlBase = '/storage';

    /**
     * Максимальный размер файла в байтах
     */
    private static int $maxSize = 10 * 1024 * 1024; // 10MB по умолчанию

    /**
     * Массив разрешенных MIME-типов
     */
    private static array $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/quicktime',
        'application/pdf',
        'text/plain',
    ];

    /**
     * Путь к публичной директории
     */
    private string $instancePublicPath;

    /**
     * Путь к приватной директории
     */
    private string $instancePrivatePath;

    /**
     * Базовый URL для публичных файлов
     */
    private string $instancePublicUrlBase;

    /**
     * Максимальный размер файла в байтах
     */
    private int $instanceMaxSize;

    /**
     * Массив разрешенных MIME-типов
     */
    private array $instanceAllowedMimes;

    public function __construct()
    {
        $this->instancePublicPath = self::$publicPath;
        $this->instancePrivatePath = self::$privatePath;
        $this->instancePublicUrlBase = self::$publicUrlBase;
        $this->instanceMaxSize = self::$maxSize;
        $this->instanceAllowedMimes = self::$allowedMimes;
    }

    /**
     * Настройка параметров хранилища
     *
     * @param array $config Массив конфигурационных параметров
     */
    public static function configure(array $config): void
    {
        if (isset($config['publicPath'])) {
            self::$publicPath = rtrim($config['publicPath'], '/\\');
        }
        if (isset($config['privatePath'])) {
            self::$privatePath = rtrim($config['privatePath'], '/\\');
        }
        if (isset($config['publicUrlBase'])) {
            self::$publicUrlBase = rtrim($config['publicUrlBase'], '/');
        }
        if (isset($config['maxSize'])) {
            self::$maxSize = (int)$config['maxSize'];
        }
        if (isset($config['allowedMimes'])) {
            self::$allowedMimes = (array)$config['allowedMimes'];
        }
    }

    /**
     * Валидация загружаемого файла
     *
     * @param UploadedFileInterface $file Файл для валидации
     * @throws RuntimeException
     */
    private function validateFile(UploadedFileInterface $file): void
    {
        // Проверка на ошибки загрузки
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Ошибка загрузки файла');
        }

        // Проверка размера файла
        if ($this->instanceMaxSize > 0 && $file->getSize() > $this->instanceMaxSize) {
            throw new RuntimeException('Файл слишком большой');
        }

        // Проверка MIME-типа
        if (!empty($this->instanceAllowedMimes)) {
            $mime = $file->getClientMediaType();
            if (!in_array($mime, $this->instanceAllowedMimes, true)) {
                throw new RuntimeException('Недопустимый тип файла');
            }
        }
    }

    /**
     * Загрузка файла
     *
     * @param UploadedFileInterface $file Файл для загрузки
     * @param string $directory Директория для загрузки
     * @param bool $public Флаг публичности файла
     * @return FileInfo Информация о загруженном файле
     * @throws RuntimeException|RandomException
     */
    public function upload(UploadedFileInterface $file, string $directory, bool $public = true): FileInfo
    {
        $this->validateFile($file);

        // Определяем путь для сохранения
        $basePath = $public ? $this->instancePublicPath : $this->instancePrivatePath;
        $fullPath = $basePath . '/' . ltrim($directory, '/\\');

        // Создаем директорию если её нет
        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        // Генерируем уникальное имя файла
        $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . (!empty($extension) ? '.' . $extension : '');
        $filePath = $fullPath . '/' . $filename;

        // Защита от перезаписи
        if (file_exists($filePath)) {
            throw new RuntimeException('Файл с таким именем уже существует');
        }

        // Перемещаем файл
        $file->moveTo($filePath);

        // Создаем объект информации о файле
        $fileInfo = new FileInfo();
        $fileInfo->filename = $filename;
        $fileInfo->originalName = $file->getClientFilename();
        $fileInfo->path = $filePath;
        $fileInfo->url = $public ? $this->instancePublicUrlBase . '/' . ltrim($directory, '/\\') . '/' . $filename : '';
        $fileInfo->size = $file->getSize();
        $fileInfo->mime = $file->getClientMediaType();

        return $fileInfo;
    }

    /**
     * Замена файла
     *
     * @param UploadedFileInterface $file Новый файл
     * @param string $directory Директория
     * @param string $filename Имя файла для замены
     * @param bool $public Флаг публичности файла
     * @return FileInfo Информация о замененном файле
     */
    public function replace(UploadedFileInterface $file, string $directory, string $filename, bool $public = true): FileInfo
    {
        $this->validateFile($file);

        // Определяем путь для сохранения
        $basePath = $public ? $this->instancePublicPath : $this->instancePrivatePath;
        $fullPath = $basePath . '/' . ltrim($directory, '/\\') . '/' . $filename;

        // Удаляем старый файл если он существует
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        // Перемещаем новый файл
        $file->moveTo($fullPath);

        // Создаем объект информации о файле
        $fileInfo = new FileInfo();
        $fileInfo->filename = $filename;
        $fileInfo->originalName = $file->getClientFilename();
        $fileInfo->path = $fullPath;
        $fileInfo->url = $public ? $this->instancePublicUrlBase . '/' . ltrim($directory, '/\\') . '/' . $filename : '';
        $fileInfo->size = $file->getSize();
        $fileInfo->mime = $file->getClientMediaType();

        return $fileInfo;
    }

    /**
     * Удаление файла
     *
     * @param string $directory Директория
     * @param string $filename Имя файла
     * @param bool $public Флаг публичности файла
     * @return bool Результат удаления
     */
    public function delete(string $directory, string $filename, bool $public = true): bool
    {
        $basePath = $public ? $this->instancePublicPath : $this->instancePrivatePath;
        $fullPath = $basePath . '/' . ltrim($directory, '/\\') . '/' . $filename;

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }

    /**
     * Проверка существования файла
     *
     * @param string $directory Директория
     * @param string $filename Имя файла
     * @param bool $public Флаг публичности файла
     * @return bool Результат проверки
     */
    public function exists(string $directory, string $filename, bool $public = true): bool
    {
        $basePath = $public ? $this->instancePublicPath : $this->instancePrivatePath;
        $fullPath = $basePath . '/' . ltrim($directory, '/\\') . '/' . $filename;

        return file_exists($fullPath);
    }

    /**
     * Получение URL файла
     *
     * @param string $directory Директория
     * @param string $filename Имя файла
     * @return string URL файла
     */
    public function getUrl(string $directory, string $filename): string
    {
        return $this->instancePublicUrlBase . '/' . ltrim($directory, '/\\') . '/' . $filename;
    }

    /**
     * Перемещение файла в публичную директорию
     *
     * @param string $directory Директория
     * @param string $filename Имя файла
     * @return bool Результат перемещения
     */
    public function moveToPublic(string $directory, string $filename): bool
    {
        $privatePath = $this->instancePrivatePath . '/' . ltrim($directory, '/\\') . '/' . $filename;
        $publicPath = $this->instancePublicPath . '/' . ltrim($directory, '/\\') . '/' . $filename;

        if (file_exists($privatePath)) {
            // Создаем директорию если её нет
            $publicDir = dirname($publicPath);
            if (!is_dir($publicDir)) {
                mkdir($publicDir, 0755, true);
            }

            return rename($privatePath, $publicPath);
        }

        return false;
    }
}