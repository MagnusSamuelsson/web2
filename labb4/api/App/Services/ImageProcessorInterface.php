<?php

namespace App\Services;

use App\Models\File;

interface ImageProcessorInterface
{
    public function isImage(): bool;
    public function createImageFromString(): self;
    public function setMaxWidth(int $maxWidth): self;
    public function setMaxHeight(int $maxHeight): self;
    public function getScaleX(): float;
    public function getScaleY(): float;
    public function getOriginalWidth(): int;
    public function getOriginalHeight(): int;
    public function getWidth(): int;
    public function getHeight(): int;
    public function isSquare(): bool;
    public function convertToWebP(): File|false;
    public function destroyImage(): bool;
}
