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
    private const THUMBNAIL_WIDTH = 480;
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
        $details = $this->process($file, $directory);

        return $details['path'];
    }

    /**
     * Optimize an uploaded image and return the processed details,
     * including the generated WebP thumbnail used for responsive `srcset` delivery.
     *
     * @return array{path: string, mime: string, width: int, height: int, thumbnail: string|null}
     */
    public function optimizeAndStoreDetailed(UploadedFile $file, string $directory): array
    {
        return $this->process($file, $directory);
    }

    private function process(UploadedFile $file, string $directory): array
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

            $finalMime = null;
            $finalRelativePath = null;
            $finalSize = null;

            if ($hasAlpha && $mimeType === 'image/png') {
                $pngResult = $this->saveAsPng($processedImage, $directory);
                $webpResult = $this->saveAsWebp($sourceImage, $directory, $originalWidth, $originalHeight, $needsResize);

                if ($pngResult['size'] <= $originalSize && $pngResult['size'] <= $webpResult['size']) {
                    $this->deleteStorageFile($webpResult['path']);
                    $finalMime = 'image/png';
                    $finalRelativePath = $pngResult['path'];
                    $finalSize = $pngResult['size'];
                } elseif ($webpResult['size'] <= $originalSize) {
                    $this->deleteStorageFile($pngResult['path']);
                    $finalMime = 'image/webp';
                    $finalRelativePath = $webpResult['path'];
                    $finalSize = $webpResult['size'];
                } else {
                    $this->deleteStorageFile($pngResult['path']);
                    $this->deleteStorageFile($webpResult['path']);
                }
            }

            if ($finalRelativePath === null) {
                $webpResult = $this->saveAsWebp($sourceImage, $directory, $originalWidth, $originalHeight, $needsResize);

                if ($webpResult['size'] <= $originalSize) {
                    $finalMime = 'image/webp';
                    $finalRelativePath = $webpResult['path'];
                    $finalSize = $webpResult['size'];
                } else {
                    $this->deleteStorageFile($webpResult['path']);

                    $finalMime = 'image/jpeg';
                    $finalRelativePath = $this->saveAsJpeg(
                        $sourceImage,
                        $directory,
                        $originalWidth,
                        $originalHeight,
                        $needsResize,
                        $originalSize
                    )['path'];
                    $finalSize = filesize(storage_path('app/public/' . $finalRelativePath));
                }
            }

            $finalSource = $finalMime === 'image/png' ? $processedImage : $sourceImage;
            $finalWidth = (int) $originalWidth;
            $finalHeight = (int) $originalHeight;
            if ($needsResize) {
                $ratio = min(self::MAX_DIMENSION / $originalWidth, self::MAX_DIMENSION / $originalHeight);
                $finalWidth = (int) round($originalWidth * $ratio);
                $finalHeight = (int) round($originalHeight * $ratio);
            }

            $thumbnail = null;
            if ($finalWidth > self::THUMBNAIL_WIDTH) {
                $thumbnail = $this->generateThumbnail($finalSource, $finalMime, $directory);
            }

            return [
                'path' => $finalRelativePath,
                'mime' => $finalMime,
                'width' => $finalWidth,
                'height' => $finalHeight,
                'thumbnail' => $thumbnail,
            ];
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

    private function generateThumbnail($source, string $mime, string $directory): string
    {
        $extension = match ($mime) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            default => 'webp',
        };
        $filename = Str::uuid()->toString() . '-' . self::THUMBNAIL_WIDTH . '.' . $extension;
        $relativePath = $directory . '/' . $filename;
        $tempPath = storage_path('app/temp_' . $filename);

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $width = (int) min(self::THUMBNAIL_WIDTH, imagesx($source));
        $height = max(1, (int) round((imagesy($source) * $width) / imagesx($source)));
        $resized = imagecreatetruecolor($width, $height);

        $bg = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefill($resized, 0, 0, $bg);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        $success = match ($mime) {
            'image/png' => @imagepng($resized, $tempPath, self::PNG_COMPRESSION),
            'image/webp' => @imagewebp($resized, $tempPath, self::WEBP_QUALITY),
            default => @imagejpeg($resized, $tempPath, self::JPEG_QUALITY),
        };
        imagedestroy($resized);

        if (!$success) {
            @unlink($tempPath);
            throw new RuntimeException('Failed to save thumbnail image.');
        }

        $this->moveToStorage($tempPath, $relativePath);

        return $relativePath;
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
