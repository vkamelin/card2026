<?php

declare(strict_types=1);

namespace App\Handlers\Telegram\Messages;

use App\Helpers\Database;
use App\Helpers\FileService;
use App\Telegram\UpdateHelper;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Entities\Message;
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
        $this->db = Database::getInstance();
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
        $chatId = $message->getChat()->getId();
        $userId = UpdateHelper::getUserId($update);
        
        // Обработка фото
        if ($message->getPhoto()) {
            $this->handlePhoto($message, $chatId, $userId);
        }
        
        // Обработка видео
        if ($message->getVideo()) {
            $this->handleVideo($message, $chatId, $userId);
        }
        
        // Обработка документов (включая видео, отправленные как файл)
        if ($message->getDocument()) {
            $this->handleDocument($message, $chatId, $userId);
        }
    }
    
    /**
     * Обработка фото
     */
    private function handlePhoto(Message $message, int $chatId, ?int $userId): void
    {
        // Получаем фото самого высокого качества (последний элемент в массиве)
        $photos = $message->getPhoto();
        $photo = end($photos);
        
        if ($photo) {
            $fileId = $photo->getFileId();
            $fileName = 'photo_' . time() . '.jpg';
            
            // Сохраняем файл
            $this->saveMediaFile($fileId, $fileName, $chatId, $userId, 'photo');
        }
    }
    
    /**
     * Обработка видео
     */
    private function handleVideo(Message $message, int $chatId, ?int $userId): void
    {
        $video = $message->getVideo();
        
        if ($video) {
            $fileId = $video->getFileId();
            $fileName = $video->getFileName() ?: 'video_' . time() . '.' . ($video->getMimeType() ? explode('/', $video->getMimeType())[1] : 'mp4');
            
            // Сохраняем файл
            $this->saveMediaFile($fileId, $fileName, $chatId, $userId, 'video');
        }
    }
    
    /**
     * Обработка документов
     */
    private function handleDocument(Message $message, int $chatId, ?int $userId): void
    {
        $document = $message->getDocument();
        
        if ($document) {
            $fileId = $document->getFileId();
            $fileName = $document->getFileName() ?: 'document_' . time() . '.' . ($document->getFileExt() ?: 'bin');
            
            // Определяем тип медиа по MIME типу
            $mimeType = $document->getMimeType();
            $mediaType = 'document';
            
            if ($mimeType) {
                if (str_starts_with($mimeType, 'image/')) {
                    $mediaType = 'photo';
                } elseif (str_starts_with($mimeType, 'video/')) {
                    $mediaType = 'video';
                }
            }
            
            // Сохраняем файл
            $this->saveMediaFile($fileId, $fileName, $chatId, $userId, $mediaType);
        }
    }
    
    /**
     * Сохранение медиа файла
     */
    private function saveMediaFile(string $fileId, string $fileName, int $chatId, ?int $userId, string $mediaType): void
    {
        // Загружаем файл через FileService
        $filePath = $this->fileService->downloadTelegramFile($fileId, $fileName);
        
        if ($filePath) {
            // Сохраняем информацию о файле в базу данных
            $stmt = $this->db->prepare("
                INSERT INTO telegram_files (user_id, chat_id, file_id, file_name, file_path, media_type, created_at)
                VALUES (:user_id, :chat_id, :file_id, :file_name, :file_path, :media_type, NOW())
            ");
            
            if ($stmt) {
                $stmt->execute([
                    ':user_id' => $userId,
                    ':chat_id' => $chatId,
                    ':file_id' => $fileId,
                    ':file_name' => $fileName,
                    ':file_path' => $filePath,
                    ':media_type' => $mediaType
                ]);
            }
        }
    }
}