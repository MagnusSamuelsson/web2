<?php
/**
 * Klass för att generera och validera CSRF-token
 *
 * Denna klass innehåller metoder för att generera och validera CSRF-token
 * som används för att skydda mot CSRF-attacker (Cross-Site Request Forgery).
 */
class CsrfToken
{
    /**
     * Nyckel för CSRF-token i sessionen
     */
    private const SESSION_KEY_NAME = 'csrf_token';
    /**
     * Genererar en CSRF-token
     *
     * Genererar en CSRF-token och sparar den i sessionen.
     *
     * @return string Den genererade CSRF-token
     */
    public static function generate(): string
    {
        $token = bin2hex(random_bytes(16));
        $_SESSION[self::SESSION_KEY_NAME] = $token;
        return $token;
    }
    /**
     * Validerar en CSRF-token
     *
     * Validerar den angivna CSRF-token genom att jämföra den med den token som är sparad i sessionen.
     *
     * @param string $token Den CSRF-token som ska valideras
     * @return bool True om token är giltig, annars false
     */
    public static function validate(string $token): bool
    {
        return isset($_SESSION[self::SESSION_KEY_NAME]) && hash_equals($_SESSION[self::SESSION_KEY_NAME], $token);
    }
}
