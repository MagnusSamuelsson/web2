<?php

namespace App\Http;

class Cookie
{
    public function __construct(
        private string $name,
        private ?string $value = null,
        private int $expire = 3600,
        private string $path = '/',
        private string $domain = '',
        private bool $secure = true,
        private bool $httpOnly = true,
        private string $sameSite = 'Strict'
    ) {

    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function getExpire(): int
    {
        return $this->expire;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function getSameSite(): string
    {
        return $this->sameSite;
    }

    public function setExpirationDays(int $days): void
    {
        $this->expire = $days * 24 * 60 * 60;
    }

    public function setExpirationHours(int $hours): void
    {
        $this->expire = $hours * 60 * 60;
    }

    public function setExpirationMinutes(int $minutes): void
    {
        $this->expire = $minutes * 60;
    }

    public function setExpirationSeconds(int $seconds): void
    {
        $this->expire = $seconds;
    }

    public static function getFromGlobals($name): ?Cookie
    {
        if (isset($_COOKIE[$name])) {
            return new self($name, $_COOKIE[$name]);
        }
        return null;
    }
}
