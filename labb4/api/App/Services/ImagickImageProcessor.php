<?php

namespace App\Services;

use App\Models\File;
use Imagick;
use App\Services\ImageProcessorInterface;

class ImagickImageProcessor implements ImageProcessorInterface
{
    private string $binaryData;
    private Imagick $image;
    private int $maxWidth = 1500;
    private int $maxHeight = 1500;
    private int $originalWidth;
    private int $originalHeight;
    private int $width;
    private int $height;


    public function __construct(
    ) {
        $this->binaryData = file_get_contents("php://input");
    }

    public function isImage(): bool
    {
        return getimagesizefromstring($this->binaryData) !== false;
    }

    public function createImageFromString(
    ): self {
        $this->image = new Imagick();
        $this->image->readImageBlob($this->binaryData);
        $this->originalWidth = $this->image->getImageWidth();
        $this->originalHeight = $this->image->getImageHeight();
        if ($this->image->getImageWidth() > $this->maxWidth) {
            $this->image->scaleImage($this->maxWidth, 0);
        }

        if ($this->image->getImageHeight() > $this->maxHeight) {
            $this->image->scaleImage(0, $this->maxHeight);
        }

        $orientation = $this->image->getImageOrientation();
        if ($orientation !== Imagick::ORIENTATION_TOPLEFT) {
            $this->normalizeExif($orientation);
        }

        $this->width = $this->image->getImageWidth();
        $this->height = $this->image->getImageHeight();

        $this->image->setImageFormat('webp');
        $this->image->setImageCompressionQuality(80);

        return $this;
    }

    private function normalizeExif(int $orientation): void
    {
        switch ($orientation) {
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $this->image->rotateImage("#000", 180);
                break;
            case Imagick::ORIENTATION_RIGHTTOP:
                $this->image->rotateImage("#000", 90);
                break;
            case Imagick::ORIENTATION_LEFTBOTTOM:
                $this->image->rotateImage("#000", 270);
                break;
            case Imagick::ORIENTATION_BOTTOMLEFT:
                $this->image->rotateImage("#000", 180);
                $this->image->flopImage();
                break;
            case Imagick::ORIENTATION_LEFTTOP:
                $this->image->rotateImage("#000", 90);
                $this->image->flopImage();
                break;
            case Imagick::ORIENTATION_RIGHTBOTTOM:
                $this->image->rotateImage("#000", 270);
                $this->image->flopImage();
                break;
            case Imagick::ORIENTATION_TOPRIGHT:
                $this->image->flopImage();
                break;
            default:
                break;
        }
        $this->image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
        $this->image->stripImage();
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
        $width = $this->image->getImageWidth();
        $height = $this->image->getImageHeight();
        return $width === $height;
    }


    public function convertToWebP(): File|false
    {
        $imgFile = new File();
        try {
            $this->image->setImageFormat('webp');
            $this->image->writeImage($imgFile->path());
        } catch (\Exception $e) {
            return false;
        }
        return $imgFile;
    }

    public function destroyImage(): bool
    {
        return isset($this->image) ? $this->image->clear() : true;
    }

    public function __destruct()
    {
        $this->destroyImage();
    }
}