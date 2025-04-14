<?php

namespace App\Core;

use Dotenv\Dotenv;

class Environment
{
    /**
     * @var bool $isLoaded Anger om miljövariablerna har laddats.
     */
    private static bool $isLoaded = false;

    /**
     * Ladda miljövariabler från .env-filen (om den finns).
     *
     * Denna metod laddar miljövariabler från en .env-fil i den angivna sökvägen.
     * Om .env-filen inte finns, laddas inga variabler.
     * Om den redan har laddats, görs inget.
     *
     * @return void
     */
    public static function load(string $envDir): void
    {
            $dotenv = Dotenv::createImmutable($envDir);
            $dotenv->safeLoad();
    }

    /**
     * Hämta en miljövariabel.
     *
     * @param string $key Nyckeln för miljövariabeln.
     * @param mixed $default Standardvärde om nyckeln inte finns.
     * @return mixed Värdet av miljövariabeln eller standardvärdet.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? $default;
    }
}
