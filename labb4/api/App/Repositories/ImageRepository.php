<?php

namespace App\Repositories;

use App\Services\Dbh;
use Rammewerk\Component\Hydrator\Hydrator;

/**
 * Class ImageRepository
 *
 * Hanterar operationer relaterade till användares profilbilder, såsom att hämta, skapa och uppdatera bilddata.
 * Repositoryt interagerar med databasen via Dbh-tjänsten och använder Hydrator-komponenten vid behov.
 *
 */
class ImageRepository
{
    /**
     * Konstruktor för ImageRepository.
     * Initierar repositoryt med en databasanslutning (Dbh) och en Hydrator-komponent för att underlätta hantering av objekthydrering.
     *
     * @param Dbh      $dbh      Databaskoppling tillhandahållen av Dbh-tjänsten.
     * @param Hydrator $hydrator Hydrator-komponenten för att mappa data till objekt.
     *
     * @return void
     */
    public function __construct(
        private Dbh $dbh,
        private Hydrator $hydrator
    ) {
    }

    /**
     * Hämtar en bild för en specifik användare från databasen.
     * Metoden utför en SQL SELECT-fråga mot tabellen "user_original_profile_image" där user_id matchar angivet värde.
     *
     * @param int $user_id Användarens ID vars bild ska hämtas.
     *
     * @return array|false Returnerar en associativ array med bilddata om den hittas, annars false.
     */
    public function getImage(int $user_id): array|false
    {
        $sql = <<<'SQL'
            SELECT
            *
            FROM user_original_profile_image
            WHERE user_id = :user_id;
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('user_id', $user_id, $db::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch($db::FETCH_ASSOC);

    }

    /**
     * Skapar en ny bildpost eller uppdaterar en befintlig post för en användares profilbild.
     * Använder en SQL INSERT-fråga med "ON DUPLICATE KEY UPDATE" för att säkerställa att en befintlig bild
     * uppdateras istället för att dupliceras om en post redan existerar för det angivna användar-ID:t.
     *
     * @param int   $userId Användarens ID för vilken bilden ska skapas eller uppdateras.
     * @param mixed $file   Bildfilen som ska sparas i databasen (hanteras som ett LOB-värde).
     *
     * @return bool Returnerar true om operationen lyckades (skapad eller uppdaterad), annars false.
     */
    public function createOrUpdateImage(int $userId, mixed $file)
    {
        $sql = <<<'SQL'
            INSERT INTO user_original_profile_image (
                user_id,
                image_blob
            )
            VALUES (
                :user_id,
                :image
            )
            ON DUPLICATE KEY UPDATE
            image_blob = VALUES(image_blob)
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('image', $file, $db::PARAM_LOB);
        $stmt->bindParam('user_id', $userId, $db::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Skapar en ny bildpost med ytterligare metadata för en användares profilbild.
     * Metoden infogar bildens blobdata samt metadata såsom bredd, höjd, position (x, y) och rotationsvinkel
     * i databasen genom en SQL INSERT-fråga.
     *
     * @param int    $user_id   Användarens ID.
     * @param string $imageBlob Bilddata i blob-format.
     * @param string $data      Ytterligare bilddata eller metadata i textformat.
     * @param int    $width     Bildens bredd i pixlar.
     * @param int    $height    Bildens höjd i pixlar.
     * @param int    $x         X-koordinat för beskärning eller placering.
     * @param int    $y         Y-koordinat för beskärning eller placering.
     * @param int    $rotation  Bildens rotationsvinkel.
     *
     * @return bool Returnerar true om bildposten skapades framgångsrikt, annars false.
     */
    public function createImage(
        int $user_id,
        string $imageBlob,
        string $data,
        int $width,
        int $height,
        int $x,
        int $y,
        int $rotation,

    ): bool {
        $sql = <<<'SQL'
            INSERT INTO user_original_profile_image (
                user_id,
                image_blob,
                data,
                width,
                height,
                x,
                y,
                rotation
            )
            VALUES (
                :user_id,
                :image,
                :data,
                :width,
                :height,
                :x,
                :y,
                :rotation
            );
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('image', $imageBlob, $db::PARAM_STR);
        $stmt->bindParam('width', $width, $db::PARAM_INT);
        $stmt->bindParam('height', $height, $db::PARAM_INT);
        $stmt->bindParam('x', $x, $db::PARAM_INT);
        $stmt->bindParam('y', $y, $db::PARAM_INT);
        $stmt->bindParam('rotation', $rotation, $db::PARAM_INT);
        $stmt->bindParam('user_id', $user_id, $db::PARAM_INT);
        $stmt->bindParam('data', $data, $db::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount() > 0;

    }
}