<?php

declare(strict_types=1);

namespace App\Handlers\Telegram\Messages;

use App\Helpers\FileService;
use App\Telegram\UpdateHelper;
use Longman\TelegramBot\Entities\File;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\ServerResponse;
use PDO;

/**
 * Обработчик медиа сообщений (фото, видео, документы)
 */
class MediaMessageHandler extends AbstractMessageHandler
{
    protected PDO $db;
    
    private FileService $fileService;

    public function __construct()
    {
        parent::__construct();
        $this->fileService = new FileService();
    }

    /**
     * Метод для обработки медиа сообщений
     *
     * @param Update $update
     */
    public function handle(Update $update): void
    {
        $message = $update->getMessage();
        $userId = UpdateHelper::getUserId($update);
        
        // Обработка фото
        if ($message->getPhoto()) {
            $this->handlePhoto($message, $userId);
        }
        
        // Обработка видео
        if ($message->getVideo()) {
            $this->handleVideo($message, $userId);
        }
    }
    
    /**
     * Обработка фото
     */
    private function handlePhoto(Message $message, int $userId): void
    {
        // Получаем фото самого высокого качества (последний элемент в массиве)
        $photos = $message->getPhoto();
        $photo = end($photos);
        
        if ($photo) {
            $fileId = $photo->getFileId();
            $fileName = 'photo_' . time() . '.jpg';
            $originalFileName = $fileName;
            
            // Сохраняем файл
            $this->saveMediaFile($fileId, $fileName, $userId, 'photo');
        }
    }
    
    /**
     * Обработка видео
     */
    private function handleVideo(Message $message, int $userId): void
    {
        $video = $message->getVideo();
        
        if ($video) {
            $fileId = $video->getFileId();
            $fileName = $video->getFileName() ?: 'video_' . time() . '.' . ($video->getMimeType() ? explode('/', $video->getMimeType())[1] : 'mp4');
            
            // Сохраняем файл
            $this->saveMediaFile($fileId, $fileName, $userId, 'video');
        }
    }
    
    /**
     * Сохранение медиа файла
     */
    private function saveMediaFile(string $fileId, string $fileName, int $userId, string $type): void
    {
        /** @var ServerResponse $response */
        $response = Request::getFile(['file_id' => $fileId]);

        if (!$response || !$response->isOk()) {
            return;
        }

        /** @var File $file */
        $file = $response->getResult();

        // Загружаем файл через FileService
        $filePath = $this->fileService->downloadTelegramFile($file, $fileName);
        
        if ($filePath) {
            $size = $file->getFileSize();
            $mimeType = $this->fileService->getMimeType($filePath);

            // Сохраняем информацию о файле в базу данных
            $stmt = $this->db->prepare("
                INSERT INTO telegram_files (user_id, type, file_name, file_path, mime_type, size, file_id)
                VALUES (:user_id, :type, :file_name, :file_path, :mime_type, :size, :file_id)
            ");

            $this->db->beginTransaction();

            $fileSaveResult = false;
            $newFileId = 0;

            if ($stmt) {
                $fileSaveResult = $stmt->execute([
                    'user_id' => $userId,
                    'type' => $type,
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'mime_type' => $mimeType,
                    'size' => $size,
                    'file_id' => $fileId
                ]);

                if ($fileSaveResult) {
                    $newFileId = $this->db->lastInsertId();
                }
            }

            if ($fileSaveResult) {
                $queueStmt = $this->db->prepare("INSERT INTO `cards_queue` (`file_id`) VALUES (:file_id)");

                if ($queueStmt) {
                    $queueStmt->execute([
                        'file_id' => $newFileId,
                    ]);
                }
            }

            if ($fileSaveResult === false && $newFileId === 0) {
                $this->db->rollBack();
            } else {
                $this->db->commit();
            }
        }
    }
}