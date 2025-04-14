<?php
namespace App\Services;

use App\Core\Environment;
use PDO;
use PDOException;

/**
 * Class Dbh
 *
 * Hanterar databasanslutningen via PDO. Klassen etablerar en anslutning till MySQL-databasen
 * vid instansiering, baserat på miljövariabler, och möjliggör återanvändning av anslutningen.
 *
 */
class Dbh
{
    /**
     * @var \PDO|null Håller en instans av PDO-anslutningen. Är null om ingen anslutning har etablerats.
     */
    private ?PDO $connection = null;

    /**
     * __construct
     *
     * Konstruktor som initierar databasanslutningen genom att anropa metoden connect().
     *
     * @return void
     */
    public function __construct(
    ) {
        $this->connect();
    }

    /**
     * connect
     *
     * Etablerar en anslutning till databasen via PDO om en anslutning inte redan finns.
     * DSN, användarnamn och lösenord hämtas från miljövariabler. Vid fel kastas en PDOException
     * och skriptet avslutas med ett felmeddelande.
     *
     * @return void
     */
    private function connect(): void
    {
        if ($this->connection) {
            return;
        }
        try {
            $dsn = "mysql:host=" . Environment::get('DB_HOST') . ";dbname=" . Environment::get('DB_DATABASE');
            $this->connection = new PDO($dsn, Environment::get('DB_USER'), Environment::get('DB_PWD'));
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * getConnection
     *
     * Returnerar den etablerade PDO-anslutningen om den existerar.
     *
     * @return PDO|null Returnerar en instans av PDO om anslutningen är etablerad, annars null.
     */
    public function getConnection(): ?PDO
    {
        return $this->connection;
    }
}
