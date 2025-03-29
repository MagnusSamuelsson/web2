<?php
namespace App\Services;

use App\Core\Environment;
use PDO;
use PDOException;

class Dbh
{
    private ?PDO $connection = null;

    public function __construct(
    ) {
        $this->connect();
    }

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
    public function getConnection(): ?PDO
    {
        return $this->connection;
    }
}
