<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Класс для хранения информации о файле
 */
class FileInfo
{
    public string $filename;      // например: a1b2c3d4e5f6.jpg
    public string $originalName;
    public string $path;          // полный серверный путь
    public string $url;           // публичный URL (если public)
    public int $size;
    public string $mime;
}