<?php
/**
 * Klass för att hantera POST-data
 *
 * Denna klass innehåller metoder för att hantera POST-data.
 */
class PostData
{
    /**
     * Hämtar ett värde från POST-data med angiven nyckel.
     * Om nyckeln inte finns returneras ett standardvärde.
     *
     * @param mixed $key Nyckeln för värdet som ska hämtas.
     * @param mixed $default Standardvärdet som returneras om nyckeln inte finns.
     * @return mixed Värdet från POST-data eller standardvärdet.
     */
    public static function get($key, $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }
    /**
     * Hämtar ett värde från POST-data med angiven nyckel och rensar det från HTML-kod.
     * Om nyckeln inte finns returneras ett standardvärde.
     *
     * @param mixed $key
     * @param mixed $default
     * @return mixed
     */
    public static function getClean($key, $default = null): mixed
    {
        if (self::get($key, $default) !== null) {
            return htmlspecialchars($_POST[$key]);
        } else {
            return $default;
        }
    }
    /**
     * Rensar POST-data från alla värden.
     */
    public static function clear(): void
    {
        $_POST = [];
    }
}
