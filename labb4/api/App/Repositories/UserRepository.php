<?php

namespace App\Repositories;

use App\Core\Environment;
use App\Http\Response;
use App\Services\Dbh;
use App\Models\User;
use Rammewerk\Component\Hydrator\Hydrator;

/**
 * Class UserRepository
 *
 * Detta repository hanterar all användarrelaterad databaslogik, såsom att hämta, skapa och uppdatera användare,
 * hantering av profilbilder och att hantera följarrelationer.
 *
 */
class UserRepository
{
    /**
     * Konstruktor för UserRepository som injicerar nödvändiga beroenden, nämligen en databasanslutning (Dbh)
     * och ett Response-objekt för att hantera HTTP-svar. Dessa används för att exekvera SQL-frågor samt att
     * skapa och modifiera användarrelaterade data.
     *
     * @param Dbh      $dbh      Databaskopplingen som möjliggör exekvering av SQL-frågor.
     * @param Response $response Objekt för att hantera och bygga HTTP-svar.
     *
     * @return void
     */
    public function __construct(
        private Dbh $dbh,
        private Response $response
    ) {
    }

    /**
     * Hämtar en användare baserat på ett angivet användarnamn. Möjligheten finns att ta bort lösenordet från
     * det returnerade användarobjektet för att undvika att känslig data exponeras.
     *
     * @param string $username       Användarnamnet att söka efter.
     * @param bool   $removePassword Om true tas lösenordsegenskapen bort från det returnerade objektet.
     *
     * @return User|null Returnerar ett User-objekt om en användare hittas, annars null.
     */
    public function getUserByUsername(string $username, bool $removePassword = true): ?User
    {
        $sql = <<<'SQL'
            SELECT *
            FROM users
            WHERE username = :username;
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('username', $username);
        $stmt->execute();
        $user = $stmt->fetchObject(User::class);
        if ($user && $removePassword) {
            unset($user->password);
        }

        return $user ?: null;
    }

    /**
     * Hämtar en användare baserat på ett specifikt användar-ID. Lösenordet kan tas bort från objektet om så önskas.
     *
     * @param int  $id             Användarens unika ID.
     * @param bool $removePassword Om true tas lösenordsegenskapen bort från det returnerade objektet.
     *
     * @return User|null Returnerar ett User-objekt om användaren existerar, annars null.
     */
    public function getUserById(int $id, bool $removePassword = true): ?User
    {
        $sql = <<<'SQL'
            SELECT *
            FROM users
            WHERE id = :id;
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('id', $id, $db::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetchObject(User::class);
        if ($user && $removePassword) {
            unset($user->password);
        }

        return $user ?: null;
    }

    /**
     * Skapar en ny användare i databasen med de angivna egenskaperna hos ett User-objekt. Efter en lyckad insättning
     * sätts det genererade ID:t på objektet och lösenordet tas bort för att säkerställa att känslig data inte exponeras.
     *
     * @param User $user Det User-objekt som innehåller uppgifter om den nya användaren.
     *
     * @return bool Returnerar true om användaren skapades framgångsrikt, annars false.
     */
    public function createUser(User $user): bool
    {
        $sql = <<<'SQL'
            INSERT INTO users (
                username,
                password,
                description
            )
            VALUES (
                :username,
                :password,
                ''
            );
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('username', $user->username);
        $stmt->bindParam('password', $user->password);
        if ($stmt->execute()) {
            $user->id = $db->lastInsertId();
            unset($user->password);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Uppdaterar en befintlig användares data, såsom användarnamn och beskrivning, i databasen.
     *
     * @param User $user Det User-objekt med uppdaterade värden som ska sparas.
     *
     * @return bool Returnerar true om uppdateringen lyckades, annars false.
     */
    public function updateUser(User $user): bool
    {
        $sql = <<<'SQL'
            UPDATE users
            SET
                username = :username,
                description = :description
            WHERE id = :id;
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('id', $user->id, $db::PARAM_INT);
        $stmt->bindParam('username', $user->username);
        $stmt->bindParam('description', $user->description);
        return $stmt->execute();
    }

    /**
     * Uppdaterar en användares profilbild med ett nytt filnamn. Om en tidigare profilbild finns, som inte är standardbilden,
     * och filen existerar på servern, raderas den gamla bilden för att frigöra utrymme.
     *
     * @param int    $userId               Användarens unika ID.
     * @param string $profileImageFileName Det nya filnamnet för profilbilden.
     *
     * @return bool Returnerar true om uppdateringen lyckades, annars false.
     */
    public function replaceProfileImage(int $userId, string $profileImageFileName): bool
    {
        $user = $this->getUserById($userId);
        if (!$user) {
            return false;
        }

        $sql = <<<'SQL'
            UPDATE users
            SET profile_image = :profile_image
            WHERE id = :id;
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('id', $userId, $db::PARAM_INT);
        $stmt->bindParam('profile_image', $profileImageFileName);
        if (!$stmt->execute()) {
            return false;
        }
        if (
            $stmt->rowCount() > 0
            && $user->profile_image
            && !str_starts_with($user->profile_image, 'default')
            && file_exists(Environment::get('PROFILE_IMAGE_PATH') . DIRECTORY_SEPARATOR . $user->profile_image)
        ) {
            unlink(Environment::get('PROFILE_IMAGE_PATH') . DIRECTORY_SEPARATOR . $user->profile_image);
        }
        return true;
    }

    /**
     * Sparar eller uppdaterar en användares ursprungliga profilbilds binära data samt dess dimensioner i databasen.
     * Om en post redan existerar för användaren används "ON DUPLICATE KEY UPDATE" för att uppdatera den befintliga posten.
     *
     * @param int   $userId      Användarens unika ID.
     * @param mixed $imageBlob   Bilddata i blob-format.
     * @param int   $imageWidth  Bildens bredd i pixlar.
     * @param int   $imageHeight Bildens höjd i pixlar.
     *
     * @return bool Returnerar true om operationen lyckades, annars false.
     */
    public function saveOriginalProfileImage(
        int $userId,
        mixed $imageBlob,
        int $imageWidth,
        int $imageHeight
    ): bool {
        $sql = <<<'SQL'
            INSERT INTO user_original_profile_image (
                user_id,
                image_blob,
                image_width,
                image_height
            )
            VALUES (
                :user_id,
                :image_blob,
                :image_width,
                :image_height
            )
            ON DUPLICATE KEY UPDATE
            image_blob = VALUES(image_blob),
            image_width = VALUES(image_width),
            image_height = VALUES(image_height)
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('user_id', $userId, $db::PARAM_INT);
        $stmt->bindParam('image_width', $imageWidth, $db::PARAM_INT);
        $stmt->bindParam('image_height', $imageHeight, $db::PARAM_INT);
        $stmt->bindParam('image_blob', $imageBlob, $db::PARAM_LOB);
        return $stmt->execute();

    }

    /**
     * Sparar eller uppdaterar metadata för den ursprungliga profilbilden, såsom beskärningsområde (area width, height, x, y)
     * och rotationsvinkel. Detta görs med en SQL-fråga som använder "ON DUPLICATE KEY UPDATE" för att hantera befintliga poster.
     *
     * @param int   $userId      Användarens unika ID.
     * @param float $area_width  Bredden på beskärningsområdet.
     * @param float $area_height Höjden på beskärningsområdet.
     * @param float $area_x      X-koordinat för beskärningsområdet.
     * @param float $area_y      Y-koordinat för beskärningsområdet.
     * @param int   $rotation    Rotationsvinkeln för bilden.
     *
     * @return bool Returnerar true om operationen lyckades, annars false.
     */
    public function saveOriginalProfileImageInfo(
        int $userId,
        float $area_width,
        float $area_height,
        float $area_x,
        float $area_y,
        int $rotation
    ): bool {
        $sql = <<<'SQL'
            INSERT INTO user_original_profile_image (
                user_id,
                area_width,
                area_height,
                area_x,
                area_y,
                rotation
            )
            VALUES (
                :user_id,
                :area_width,
                :area_height,
                :area_x,
                :area_y,
                :rotation
            )
            ON DUPLICATE KEY UPDATE
            area_width = VALUES(area_width),
            area_height =  VALUES(area_height),
            area_x = VALUES(area_x),
            area_y = VALUES(area_y),
            rotation = VALUES(rotation)
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('user_id', $userId, $db::PARAM_INT);
        $stmt->bindParam('area_width', $area_width, $db::PARAM_STR);
        $stmt->bindParam('area_height', $area_height, $db::PARAM_STR);
        $stmt->bindParam('area_x', $area_x, $db::PARAM_STR);
        $stmt->bindParam('area_y', $area_y, $db::PARAM_STR);
        $stmt->bindParam('rotation', $rotation, $db::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Hämtar metadata för en användares ursprungliga profilbild, inklusive beskärningsområde och rotationsinformation.
     *
     * @param int $userId Användarens unika ID.
     *
     * @return array|false Returnerar en associativ array med metadata om bilden om den hittas, annars false.
     */
    public function getOriginalProfileImageInfo(int $userId): array|false
    {
        $sql = <<<'SQL'
            SELECT
                area_width AS width,
                area_height AS height,
                area_x AS x,
                area_y AS y,
                rotation
            FROM user_original_profile_image
            WHERE user_id = :user_id;
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('user_id', $userId, $db::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch($db::FETCH_ASSOC);
    }

    /**
     * Hämtar offentligt tillgänglig information om en användare inklusive antal inlägg, antal följare och antal användare
     * som användaren följer. Informationen grupperas per användare för att möjliggöra sammanställning av statistiska data.
     *
     * @param int $userId Användarens unika ID.
     *
     * @return User Returnerar ett hydratiserat User-objekt med de offentliga uppgifterna.
     */
    public function getPublicUserInfo(int $userId): User
    {
        $sql = <<<'SQL'
            SELECT
                u.*,
                COUNT(DISTINCT p.id) AS number_of_posts,
                COUNT(DISTINCT f1.user_id) AS number_of_followers,
                COUNT(DISTINCT f2.follower_user_id) AS number_of_following
            FROM users u
            LEFT JOIN posts p
                ON p.user_id = u.id
            LEFT JOIN followers f1
                ON f1.user_id = u.id
            LEFT JOIN followers f2
                ON f2.follower_user_id = u.id
            WHERE u.id = :id
            GROUP BY u.id;
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('id', $userId, $db::PARAM_INT);
        $stmt->execute();
        return new Hydrator(User::class)->hydrate($stmt->fetch($db::FETCH_ASSOC));
    }

    /**
     * Hämtar detaljerad användarinformation för en målanvändare, kompletterad med relationell data angående
     * hur den aktuella användaren relaterar till målanvändaren (t.ex. om den aktuella användaren följer målanvändaren
     * eller omvänt).
     *
     * @param int $currentUserId Det aktuella (autentiserade) användarens ID.
     * @param int $targetUserId  Målanvändarens ID vars information ska hämtas.
     *
     * @return User Returnerar ett hydratiserat User-objekt med detaljerad information.
     */
    public function getAuthenticatedUserInfo(int $currentUserId, int $targetUserId): User
    {
        $sql = <<<'SQL'
        SELECT
        u.*,
        COUNT(DISTINCT p.id) AS number_of_posts,
        COUNT(DISTINCT f1.user_id) AS number_of_followers,
        COUNT(DISTINCT f2.follower_user_id) AS number_of_following,
        EXISTS (
            SELECT 1
            FROM followers f
            WHERE f.user_id = :currentUserId AND f.follower_user_id = u.id
        ) AS is_following_current_user,
        EXISTS (
            SELECT 1
            FROM followers f
            WHERE f.user_id = u.id AND f.follower_user_id = :currentUserId
        ) AS is_followed_by_current_user
        FROM users u
        LEFT JOIN posts p ON p.user_id = u.id
        LEFT JOIN followers f1 ON f1.user_id = u.id
        LEFT JOIN followers f2 ON f2.follower_user_id = u.id
        WHERE u.id = :targetUserId
        GROUP BY u.id;
        SQL;

        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('currentUserId', $currentUserId, $db::PARAM_INT);
        $stmt->bindParam('targetUserId', $targetUserId, $db::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch($db::FETCH_ASSOC);
        return new Hydrator(User::class)->hydrate($result);
    }

    /**
     * Skapar en följarrelation genom att lägga till en rad i tabellen "followers" där den aktuella användaren
     * följer en specifik målanvändare.
     *
     * @param int $currentUserId Det ID för den användare som vill följa.
     * @param int $targetUserId  Det ID för den användare som ska följas.
     *
     * @return bool Returnerar true om följarrelationen skapades framgångsrikt, annars false.
     */
    public function followUser($currentUserId, $targetUserId)
    {
        $sql = <<<'SQL'
            INSERT INTO followers (
                user_id,
                follower_user_id
            )
            VALUES (
                :user_id,
                :follower_user_id
            );
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('user_id', $targetUserId, $db::PARAM_INT);
        $stmt->bindParam('follower_user_id', $currentUserId, $db::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Tar bort en följare-relation mellan den aktuella användaren och en specifik målanvändare.
     *
     * @param int $currentUserId Det ID för den användare som vill sluta följa.
     * @param int $targetUserId  Det ID för den användare vars relation ska raderas.
     *
     * @return bool Returnerar true om följarrelationen togs bort, annars false.
     */
    public function unFollowUser($currentUserId, $targetUserId)
    {
        $sql = <<<'SQL'
            DELETE FROM followers
            WHERE user_id = :user_id AND follower_user_id = :follower_user_id;
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('user_id', $targetUserId, $db::PARAM_INT);
        $stmt->bindParam('follower_user_id', $currentUserId, $db::PARAM_INT);
        return $stmt->execute();
    }
}