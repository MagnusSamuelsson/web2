<?php

namespace App\Services;

use App\Models\File;
use Jcupitt\Vips\Image;
use App\Services\ImageProcessorInterface;

class VipsImageProcessor implements ImageProcessorInterface
{
    private string $binaryData;
    private Image $image;
    private int $maxWidth = 1500;
    private int $maxHeight = 1500;
    private int $originalWidth;
    private int $originalHeight;
    private int $width;
    private int $height;

    public function __construct()
    {
        $this->binaryData = file_get_contents('php://input');
    }

    public function isImage(): bool
    {
        try {
            Image::newFromBuffer($this->binaryData);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function createImageFromString(): self
    {
        $this->image = Image::newFromBuffer($this->binaryData);

        $this->image->autorot();

        $this->originalWidth = $this->image->width;
        $this->originalHeight = $this->image->height;

        $scaleX = ($this->originalWidth > $this->maxWidth) ? $this->maxWidth / $this->originalWidth : 1;
        $scaleY = ($this->originalHeight > $this->maxHeight) ? $this->maxHeight / $this->originalHeight : 1;
        $scale = min($scaleX, $scaleY);

        if ($scale < 1) {
            $this->image = $this->image->resize($scale);
        }

        $this->width = $this->image->width;
        $this->height = $this->image->height;

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
        return $this->image->width === $this->image->height;
    }

    public function convertToWebP(): File|false
    {
        $imgFile = new File();
        try {
            $this->image->writeToFile($imgFile->path(), ['Q' => 80]); // Q = quality
        } catch (\Exception $e) {
            return false;
        }
        return $imgFile;
    }

    public function destroyImage(): bool
    {
        // libvips använder garbage collection, ingen manuell clear() behövs
        unset($this->image);
        return true;
    }

    public function __destruct()
    {
        $this->destroyImage();
    }
}
