<?php
/**
 * Klass för att hantera användare
 *
 * Denna klass innehåller användardata.
 */
class User
{
    /**
     * Skapar en ny användarclass
     *
     * @param string $username Användarnamn
     * @param string $password Lösenord
     */
    public function __construct(
        public string $username,
        public string $password
    ) {
    }
    /**
     * Ser till att bara användarnamnet följer med vid var_dump
     * @return array{username: string}
     */
    public function __debugInfo(): array
    {
        return [
            'username' => $this->username,
        ];
    }
}
