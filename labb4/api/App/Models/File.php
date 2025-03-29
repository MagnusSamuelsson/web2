<?php

namespace App\Models;
use resource;

class File
{
    private string $filePath;
    private mixed $handler = null;
    private bool $movable = false;
    public function __construct(
        ?string $path = null,
    ) {
        if ($path) {
            $this->filePath = $path;
        } else {
            $tmpfile = tmpfile();
            $metaData = stream_get_meta_data($tmpfile);
            $this->filePath = $metaData['uri'];
            $this->movable = true;
        }
    }

    public function __destruct()
    {
        if ($this->handler) {
            fclose($this->handler);
        }
    }

    public function move($path, $filename): bool
    {
        if (!$this->movable) {
            return false;
        }

        if (!rename($this->filePath, $path . DIRECTORY_SEPARATOR . $filename)) {
            return false;
        }
        $this->filePath = $path;
        return true;
    }

    public function handler(): mixed
    {
        return $this->handler;
    }

    public function getUniqueName(): string
    {
        $hash = md5_file($this->filePath);
        return $hash . '_' . uniqid();
    }

    public function open(string $mode): self
    {
        $this->handler = fopen($this->filePath, $mode);
        return $this;
    }

    public function path(): string
    {
        return $this->filePath;
    }

    public function delete(): bool
    {
        return unlink($this->filePath);
    }

    public function type(): string
    {
        return filetype($this->filePath);
    }

    public function size(): string
    {
        return filesize($this->filePath);
    }

    public function extension(): string
    {
        return pathinfo($this->filePath, PATHINFO_EXTENSION);
    }

    public function mimeType(): string
    {
        return mime_content_type($this->filePath);
    }

    public function name(): string
    {
        return basename($this->filePath);
    }

    public function updatedAt(): string
    {
        return date('Y-m-d H:i:s', filemtime($this->filePath));
    }

    public function createdAt(): string
    {
        return date('Y-m-d H:i:s', filectime($this->filePath));
    }
}