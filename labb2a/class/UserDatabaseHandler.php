<?php
/**
 * Klass för att hantera användare i databasfil.
 *
 * Klassen UserDatabaseHandler ansvarar för att hantera alla användare i databasen.
 * Den tillhandahåller metoder för att lägga till och hämta användare.
 *
 * Databasfilen är en JSON-fil som innehåller en array med användare.
 * Username måste vara unikt för varje användare.
 *
 * För att klara av högre belastning så behöver funktionerna för filhantering
 * bytas ut mot funktioner (fopen) som kan låsa filen under skrivning.
 * Men det är inget som spelar så stor roll, då vi hade använt en riktig databas
 * i en skarp applikation.
 */
class UserDatabaseHandler
{
    /**
     * Filnamn för databasfilen
     */
    private const FILENAME = 'users.txt';
    /**
     * Användare
     * @var array
     */
    private $users;
    /**
     * Skapar en ny instans av UserDatabaseHandler
     */
    public function __construct()
    {
        if (!file_exists(self::FILENAME)) {
            file_put_contents(self::FILENAME, '[]');
        }
        $this->users = json_decode(file_get_contents(self::FILENAME));
    }
    /**
     * Lägger till en användare i databasen
     *
     * @param User $user Användaren som ska läggas till
     */
    public function addUser($user): void
    {
        $this->users[] = $user;
        file_put_contents(self::FILENAME, json_encode($this->users));
    }
    /**
     * Hämtar en användare från databasen med angivet användarnamn
     *
     * @param string $name Användarnamn
     * @return User|null Användaren eller null om användaren inte finns
     */
    public function getUserByName($name): User|null
    {
        foreach ($this->users as $user) {
            if (strcasecmp($user->username, $name) === 0) {
                return new User($user->username, $user->password);
            }
        }
        return null;
    }
}
