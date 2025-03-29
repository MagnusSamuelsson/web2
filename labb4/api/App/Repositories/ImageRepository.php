<?php

namespace App\Repositories;

use App\Services\Dbh;
use Rammewerk\Component\Hydrator\Hydrator;

class ImageRepository
{
    public function __construct(
        private Dbh $dbh,
        private Hydrator $hydrator
    ) {
    }

    public function getImage(int $user_id): array | false
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