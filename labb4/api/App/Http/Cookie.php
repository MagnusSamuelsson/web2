<?php

namespace App\Http;

/**
 * Representerar en HTTP-cookie och dess egenskaper.
 *
 * Används för att kapsla in inställningar relaterade till en cookie, såsom namn, värde,
 * giltighetstid, domän, säkerhetsflagga (Secure), åtkomstbegränsning (HttpOnly) samt SameSite-policy.
 * Objektet kan sedan användas i exempelvis Response-klassen för att sätta headers.
 */
class Cookie
{
    /**
     * Skapar en ny cookie med valfria konfigurationsinställningar.
     *
     * @param string $name Namnet på cookien.
     * @param string|null $value Cookiens värde (valfritt).
     * @param int $expire Giltighetstid i sekunder (standard 3600).
     * @param string $path Giltig URL-path för cookien (standard '/').
     * @param string $domain Domän cookien ska vara giltig för (valfritt).
     * @param bool $secure Om cookien bara ska skickas via HTTPS.
     * @param bool $httpOnly Om cookien inte ska vara åtkomlig via JavaScript.
     * @param string $sameSite SameSite-policy, t.ex. 'Strict', 'Lax' eller 'None'.
     */
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

    /**
     * Hämtar cookiens namn.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Hämtar cookiens värde eller null.
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * Hämtar antalet sekunder till utgång.
     */
    public function getExpire(): int
    {
        return $this->expire;
    }

    /**
     * Hämtar vilken path cookien är giltig för.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Hämtar domänen cookien är kopplad till.
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Returnerar true om cookien kräver HTTPS.
     */
    public function isSecure(): bool
    {
        return $this->secure;
    }

    /**
     * Returnerar true om cookien är HttpOnly.
     */
    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    /**
     * Returnerar inställd SameSite-policy.
     */
    public function getSameSite(): string
    {
        return $this->sameSite;
    }

    /**
     * Sätter cookiens förfalletid i antal dagar.
     *
     * Denna metod används för att ange hur många dagar cookien ska vara giltig från nuvarande tidpunkt.
     *
     * @param int $days Antal dagar tills cookien förfaller.
     */
    public function setExpirationDays(int $days): void
    {
        $this->expire = $days * 24 * 60 * 60;
    }

    /**
     * Sätter cookiens förfalletid i sekunder.
     *
     * Används för att sätta ett exakt antal sekunder tills cookien förfaller.
     *
     * @param int $seconds Antal sekunder tills cookien förfaller.
     */
    public function setExpirationSeconds(int $seconds): void
    {
        $this->expire = $seconds;
    }
}
