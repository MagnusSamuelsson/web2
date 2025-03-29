<?php

namespace App\Repositories;

use App\Core\Environment;
use App\Http\Response;
use App\Services\Dbh;
use App\Models\User;
use Rammewerk\Component\Hydrator\Hydrator;

class UserRepository
{
    public function __construct(
        private Dbh $dbh,
        private Response $response
    ) {
    }

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