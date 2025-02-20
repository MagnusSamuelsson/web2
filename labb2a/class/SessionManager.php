<?php
/**
 * Klass för att hantera sessioner
 *
 * Klassen SessionManager ansvarar för att hantera alla sessionrelaterade operationer.
 * Den tillhandahåller metoder för att starta, hantera och förstöra sessioner, samt att sätta och hämta sessionsvariabler.
 *
 */
class SessionManager
{
    private const COOKIE_PARAMS = [
        'httponly' => true,
        'secure' => true,
        'samesite' => 'Strict'
    ];
    /**
     * Startar sessionen om ingen session är startad.
     */
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(self::COOKIE_PARAMS);
            session_start();
        }
    }
    /**
     * Sätter en sessionsvariabel.
     *
     * @param string $key   Nyckel för sessionsvariabeln
     * @param mixed  $value Värdet att lagra
     */
    public function __set($key, $value): void
    {
        $_SESSION[$key] = $value;
    }
    /**
     * Hämtar en sessionsvariabel.
     *
     * @param string $key Nyckeln för sessionsvariabeln
     * @return mixed Värdet eller null om nyckeln saknas
     */
    public function __get($key): mixed
    {
        return $_SESSION[$key] ?? null;
    }
    /**
     * Kontrollerar om en sessionsvariabel är satt.
     *
     * @param string $key Nyckeln att kontrollera
     * @return bool true om den är satt, annars false
     */
    public function __isset($key): bool
    {
        return isset($_SESSION[$key]);
    }
    /**
     * Tar bort en sessionsvariabel.
     *
     * @param string $key Nyckeln att ta bort
     */
    public function __unset($key): void
    {
        unset($_SESSION[$key]);
    }
    /**
     * Tömmer alla sessionsvariabler.
     *
     * @return self
     */
    public function clear(): self
    {
        session_unset();
        return $this;
    }
    /**
     * Förstör sessionen helt.
     *
     * @return self
     */
    public function destroy(): self
    {
        session_destroy();
        return $this;
    }
    /**
     * Genererar ett nytt sessionid.
     *
     * @return self
     */
    public function regenerate(): self
    {
        session_regenerate_id();
        return $this;
    }
}
