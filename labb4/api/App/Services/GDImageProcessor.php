<?php

namespace App\Services;

use App\Models\File;
use App\Services\ImageProcessorInterface;

class GdImageProcessor implements ImageProcessorInterface
{
    private string $binaryData;
    private \GdImage $image;
    private int $maxWidth = 1500;
    private int $maxHeight = 1500;
    private int $originalWidth;
    private int $originalHeight;
    private int $width;
    private int $height;

    public function __construct()
    {
        $this->binaryData = file_get_contents("php://input");
    }

    public function isImage(): bool
    {
        return getimagesizefromstring($this->binaryData) !== false;
    }

    public function createImageFromString(): self
    {
        $source = imagecreatefromstring($this->binaryData);
        if (!$source) {
            throw new \RuntimeException('Invalid image data.');
        }

        $this->originalWidth = imagesx($source);
        $this->originalHeight = imagesy($source);

        $scaleX = ($this->originalWidth > $this->maxWidth) ? $this->maxWidth / $this->originalWidth : 1;
        $scaleY = ($this->originalHeight > $this->maxHeight) ? $this->maxHeight / $this->originalHeight : 1;
        $scale = min($scaleX, $scaleY);

        $this->width = (int)round($this->originalWidth * $scale);
        $this->height = (int)round($this->originalHeight * $scale);

        if ($scale < 1) {
            $resized = imagecreatetruecolor($this->width, $this->height);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $this->width, $this->height, $this->originalWidth, $this->originalHeight);
            imagedestroy($source);
            $this->image = $resized;
        } else {
            $this->width = $this->originalWidth;
            $this->height = $this->originalHeight;
            $this->image = $source;
        }

        return $this;
    }

    public function setMaxWidth(int $maxWidth): self
    {
        $this->maxWidth = $maxWidth;
        return $this;
    }

    public function setMaxHeight(int $maxHeight): self
    {
        $this->maxHeight = $maxHeight;
        return $this;
    }

    public function getScaleX(): float
    {
        return $this->width / $this->originalWidth;
    }

    public function getScaleY(): float
    {
        return $this->height / $this->originalHeight;
    }

    public function getOriginalWidth(): int
    {
        return $this->originalWidth;
    }

    public function getOriginalHeight(): int
    {
        return $this->originalHeight;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function isSquare(): bool
    {
        return $this->width === $this->height;
    }

    public function convertToWebP(): File|false
    {
        $imgFile = new File();
        try {
            // 0-100 kvalitet (0 = sämst, 100 = bäst)
            imagewebp($this->image, $imgFile->path(), 80);
        } catch (\Exception $e) {
            return false;
        }
        return $imgFile;
    }

    public function destroyImage(): bool
    {
        return isset($this->image) ? imagedestroy($this->image) : true;
    }

    public function __destruct()
    {
        $this->destroyImage();
    }
}
