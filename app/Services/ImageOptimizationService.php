<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImageOptimizationService
{
    private const MAX_DIMENSION = 2000;
    private const JPEG_QUALITY = 85;
    private const WEBP_QUALITY = 85;
    private const PNG_COMPRESSION = 6;
    private const MAX_FILE_SIZE = 5242880;
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function optimizeAndStore(UploadedFile $file, string $directory): string
    {
        $this->validateFile($file);

        $sourcePath = $file->getRealPath();
        $imageInfo = $this->getImageInfo($sourcePath);
        $originalWidth = $imageInfo['width'];
        $originalHeight = $imageInfo['height'];
        $mimeType = $imageInfo['mime'];

        $sourceImage = $this->createImageFromSource($sourcePath, $mimeType);
        if ($sourceImage === null) {
            throw new RuntimeException('Failed to process image.');
        }

        try {
            $this->applyExifOrientation($sourceImage, $sourcePath);

            $needsResize = $originalWidth > self::MAX_DIMENSION || $originalHeight > self::MAX_DIMENSION;
            $processedImage = $sourceImage;

            if ($needsResize) {
                $ratio = min(
                    self::MAX_DIMENSION / $originalWidth,
                    self::MAX_DIMENSION / $originalHeight
                );
                $newWidth = (int) round($originalWidth * $ratio);
                $newHeight = (int) round($originalHeight * $ratio);
                $processedImage = $this->resizeImage($sourceImage, $newWidth, $newHeight);
            }

            $hasAlpha = $this->imageHasTransparency($processedImage);
            $originalSize = filesize($sourcePath);

            if ($hasAlpha && $mimeType === 'image/png') {
                $pngResult = $this->saveAsPng($processedImage, $directory);
                $webpResult = $this->saveAsWebp($sourceImage, $directory, $originalWidth, $originalHeight, $needsResize);

                if ($pngResult['size'] <= $originalSize && $pngResult['size'] <= $webpResult['size']) {
                    $this->deleteStorageFile($webpResult['path']);
                    return $pngResult['path'];
                }

                if ($webpResult['size'] <= $originalSize) {
                    $this->deleteStorageFile($pngResult['path']);
                    return $webpResult['path'];
                }

                $this->deleteStorageFile($pngResult['path']);
                $this->deleteStorageFile($webpResult['path']);
            }

            $webpResult = $this->saveAsWebp($sourceImage, $directory, $originalWidth, $originalHeight, $needsResize);

            if ($webpResult['size'] <= $originalSize) {
                return $webpResult['path'];
            }

            $this->deleteStorageFile($webpResult['path']);

            return $this->saveAsJpeg($sourceImage, $directory, $originalWidth, $originalHeight, $needsResize, $originalSize)['path'];
        } finally {
            if (isset($processedImage) && $processedImage !== $sourceImage) {
                @imagedestroy($processedImage);
            }
            if (isset($sourceImage)) {
                @imagedestroy($sourceImage);
            }
        }
    }

    private function validateFile(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Image exceeds maximum allowed size.');
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException('Unsupported image format.');
        }

        $sourcePath = $file->getRealPath();
        $info = getimagesize($sourcePath);
        if ($info === false) {
            throw new RuntimeException('File is not a valid image.');
        }

        if (!in_array($info['mime'], self::ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException('File content does not match an allowed image type.');
        }

        $width = $info[0];
        $height = $info[1];
        if ($width < 1 || $height < 1 || $width > 10000 || $height > 10000) {
            throw new RuntimeException('Image dimensions are invalid.');
        }
    }

    private function getImageInfo(string $path): array
    {
        $info = getimagesize($path);
        if ($info === false) {
            throw new RuntimeException('Cannot read image dimensions.');
        }

        return [
            'width' => $info[0],
            'height' => $info[1],
            'mime' => $info['mime'],
        ];
    }

    private function createImageFromSource(string $path, string $mimeType)
    {
        return match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => null,
        };
    }

    private function applyExifOrientation($image, string $sourcePath): void
    {
        if (!function_exists('exif_read_data')) {
            return;
        }

        $exif = @exif_read_data($sourcePath);
        if ($exif === false || empty($exif['Orientation'])) {
            return;
        }

        $orientation = (int) $exif['Orientation'];
        switch ($orientation) {
            case 2:
                @imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                @imagerotate($image, 180, 0);
                break;
            case 4:
                @imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5:
                @imagerotate($image, -90, 0);
                @imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                @imagerotate($image, -90, 0);
                break;
            case 7:
                @imagerotate($image, 90, 0);
                @imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 8:
                @imagerotate($image, 90, 0);
                break;
        }
    }

    private function resizeImage($source, int $width, int $height)
    {
        $resized = imagecreatetruecolor($width, $height);

        $bg = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefill($resized, 0, 0, $bg);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);

        imagecopyresampled($resized, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        return $resized;
    }

    private function imageHasTransparency($image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);

        for ($x = 0; $x < $width; $x += max(1, (int) ($width / 100))) {
            for ($y = 0; $y < $height; $y += max(1, (int) ($height / 100))) {
                $color = imagecolorat($image, $x, $y);
                $alpha = ($color >> 24) & 0x7F;
                if ($alpha > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function saveAsPng($image, string $directory): array
    {
        $filename = $this->generateFilename('png');
        $relativePath = $directory . '/' . $filename;
        $tempPath = storage_path('app/temp_' . $filename);

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        imagesavealpha($image, true);
        $success = @imagepng($image, $tempPath, self::PNG_COMPRESSION);
        if (!$success) {
            @unlink($tempPath);
            throw new RuntimeException('Failed to save PNG image.');
        }

        $this->moveToStorage($tempPath, $relativePath);

        return [
            'path' => $relativePath,
            'size' => filesize(storage_path('app/public/' . $relativePath)),
        ];
    }

    private function saveAsWebp($source, string $directory, int $origWidth, int $origHeight, bool $needsResize): array
    {
        $filename = $this->generateFilename('webp');
        $relativePath = $directory . '/' . $filename;
        $tempPath = storage_path('app/temp_' . $filename);

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        if ($needsResize) {
            $ratio = min(self::MAX_DIMENSION / $origWidth, self::MAX_DIMENSION / $origHeight);
            $newWidth = (int) round($origWidth * $ratio);
            $newHeight = (int) round($origHeight * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            $white = imagecolorallocate($resized, 255, 255, 255);
            imagefill($resized, 0, 0, $white);
            imagealphablending($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

            $success = @imagewebp($resized, $tempPath, self::WEBP_QUALITY);
            imagedestroy($resized);
        } else {
            $success = @imagewebp($source, $tempPath, self::WEBP_QUALITY);
        }

        if (!$success) {
            @unlink($tempPath);
            throw new RuntimeException('Failed to save WebP image.');
        }

        $this->moveToStorage($tempPath, $relativePath);

        return [
            'path' => $relativePath,
            'size' => filesize(storage_path('app/public/' . $relativePath)),
        ];
    }

    private function moveToStorage(string $tempPath, string $relativePath): void
    {
        $destinationPath = storage_path('app/public/' . $relativePath);
        $destinationDir = dirname($destinationPath);

        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        if (!@rename($tempPath, $destinationPath)) {
            $content = file_get_contents($tempPath);
            if ($content === false) {
                @unlink($tempPath);
                throw new RuntimeException('Failed to read processed image.');
            }

            $written = file_put_contents($destinationPath, $content);
            @unlink($tempPath);

            if ($written === false) {
                @unlink($destinationPath);
                throw new RuntimeException('Failed to store processed image.');
            }
        }
    }

    private function saveAsJpeg($source, string $directory, int $origWidth, int $origHeight, bool $needsResize, int $originalSize): array
    {
        $filename = $this->generateFilename('jpg');
        $relativePath = $directory . '/' . $filename;
        $tempPath = storage_path('app/temp_' . $filename);

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        if ($needsResize) {
            $ratio = min(self::MAX_DIMENSION / $origWidth, self::MAX_DIMENSION / $origHeight);
            $newWidth = (int) round($origWidth * $ratio);
            $newHeight = (int) round($origHeight * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            $white = imagecolorallocate($resized, 255, 255, 255);
            imagefill($resized, 0, 0, $white);
            imagealphablending($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

            $success = @imagejpeg($resized, $tempPath, self::JPEG_QUALITY);
            imagedestroy($resized);
        } else {
            $success = @imagejpeg($source, $tempPath, self::JPEG_QUALITY);
        }

        if (!$success) {
            @unlink($tempPath);
            throw new RuntimeException('Failed to save JPEG image.');
        }

        $this->moveToStorage($tempPath, $relativePath);
        $size = filesize(storage_path('app/public/' . $relativePath));

        if ($size > $originalSize) {
            $this->deleteStorageFile($relativePath);
            throw new RuntimeException('Processed image is larger than original.');
        }

        return [
            'path' => $relativePath,
            'size' => $size,
        ];
    }

    private function deleteStorageFile(string $relativePath): void
    {
        $fullPath = storage_path('app/public/' . $relativePath);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function generateFilename(string $extension): string
    {
        return Str::uuid()->toString() . '.' . $extension;
    }
}
