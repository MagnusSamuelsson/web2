<?php

namespace App\Http;


class Request
{
    private array $attributes = [];
    private array $jsonData = [];
    private array $files = [];
    private string $bytestream;
    private string $contentType;

    public function __construct()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            return;
        }
        if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            $this->contentType = 'application/json';
            $this->jsonData = json_decode(
                file_get_contents('php://input'),
                true
            ) ?? [];
        } elseif (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/octet-stream') !== false) {
            $this->contentType = 'application/octet-stream';
            $this->bytestream = file_get_contents('php://input');
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key]
            ?? $this->jsonData[$key]
            ?? $_POST[$key]
            ?? $_GET[$key]
            ?? $default;
    }

    public function getByteStream(): string
    {
        return $this->bytestream;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function getHeader(string $key): string|null
    {
        $key = strtoupper(str_replace('-', '_', $key));
        return $_SERVER["HTTP_$key"] ?? null;
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isDelete(): bool
    {
        return $this->method() === 'DELETE';
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    public function isPut(): bool
    {
        return $this->method() === 'PUT';
    }

    public function cookie(string $key, mixed $default = null): string|null
    {
        return $_COOKIE[$key] ?? $default;
    }

    public function getBearerToken(): string|null
    {
        $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        return str_replace('Bearer ', '', $authorization);
    }

    public function isStream(): bool
    {
        return $this->contentType === 'application/octet-stream';
    }
}
