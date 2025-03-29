<?php

namespace App\Repositories;

use App\Services\Dbh;
use App\Models\Posts;
use App\Models\Post;
use App\Models\User;

use Rammewerk\Component\Hydrator\Hydrator;
use PDOStatement;
use PDO;

class PostRepository
{
    public function __construct(
        private Dbh $dbh
    ) {
    }

    public function createPost(Post $post): bool
    {
        $sql = <<<'SQL'
            INSERT INTO posts (
                user_id,
                content
            )
            VALUES (
                :userId,
                :content
            );
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('userId', $post->user_id, $db::PARAM_INT);
        $stmt->bindParam('content', $post->content, $db::PARAM_STR);
        $stmt->execute();
        return $post->id = $db->lastInsertId() ?: false;

    }

    public function deletePost(Post $post): bool
    {
        $sql = <<<'SQL'
            DELETE FROM posts
            WHERE
                id = :id
                AND user_id = :userId
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('id', $post->id, $db::PARAM_INT);
        $stmt->bindParam('userId', $post->user_id, $db::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function updatePost(Post $post): bool
    {
        $sql = <<<'SQL'
            UPDATE posts
            SET content = :content
            WHERE
                id = :id
                AND deleted_at
                IS NULL AND user_id = :userId;
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('content', $post->content, $db::PARAM_STR);
        $stmt->bindParam('id', $post->id, $db::PARAM_INT);
        $stmt->bindParam('userId', $post->user_id, $db::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function getPostsByUserId(
        int $userId,
        ?int $limit = null,
        ?int $afterId = null,
        ?int $beforeId = null
    ): ?Posts {
        return $this->getPostsWithParams(
            userId: $userId,
            limit: $limit,
            afterId: $afterId,
            beforeId: $beforeId
        );
    }

    public function getPosts(
        ?int $limit = null,
        ?int $afterId = null,
        ?int $beforeId = null
    ): ?Posts {
        return $this->getPostsWithParams(
            limit: $limit,
            afterId: $afterId,
            beforeId: $beforeId
        );
    }

    public function getPostsWithLikeByUserId(
        int $userId,
        int $currentUserId,
        ?int $limit = null,
        ?int $afterId = null,
        ?int $beforeId = null
    ): ?Posts {
        return $this->getPostsWithParams(
            userId: $userId,
            limit: $limit,
            afterId: $afterId,
            beforeId: $beforeId,
            currentUserId: $currentUserId
        );
    }
    public function getPostsWithLikeById(
        int $postId,
        int $currentUserId
    ): ?Posts {
        return $this->getPostsWithParams(
            postId: $postId,
            currentUserId: $currentUserId
        );
    }
    public function getPostsWithLike(
        int $currentUserId,
        ?int $limit = null,
        ?int $afterId = null,
        ?int $beforeId = null
    ): ?Posts {
        return $this->getPostsWithParams(
            limit: $limit,
            afterId: $afterId,
            beforeId: $beforeId,
            currentUserId: $currentUserId
        );
    }

    private function getPostsWithParams(
        ?int $userId = null,
        ?int $limit = null,
        ?int $afterId = null,
        ?int $beforeId = null,
        ?int $currentUserId = null,
        ?int $postId = null
    ): ?Posts {
        $params = [];

        $whereUserId = '';
        if ($userId) {
            $params['userId'] = $userId;
            $whereUserId = 'AND p.user_id = :userId';
        }

        $wherePostId = '';
        if ($postId) {
            $params['postId'] = $postId;
            $wherePostId = 'AND p.id = :postId';
        }

        $whereAfterOrBefore = '';
        if ($afterId) {
            $whereAfterOrBefore = "AND p.id < :afterId";
            $params['afterId'] = $afterId;
        } elseif ($beforeId) {
            $whereAfterOrBefore = "AND p.id > :beforeId";
            $params['beforeId'] = $beforeId;
        }

        $limitStr = '';
        if ($limit) {
            $limitStr = "LIMIT :limit";
            $params['limit'] = $limit;
        }

        $likeSelect = '';
        $likeJoin = '';
        if ($currentUserId) {
            $likeSelect = ', IF(lu.user_id IS NULL, 0, 1) AS liked_by_current_user';
            $likeJoin = 'LEFT JOIN likes lu ON p.id = lu.post_id AND lu.user_id = :currentUserId';
            $params['currentUserId'] = $currentUserId;
        }

        $sql = <<<SQL
            SELECT
                p.id,
                p.user_id,
                p.content,
                p.created_at,
                p.updated_at,
                GROUP_CONCAT(pi.image) AS imagesString,
                u.username,
                u.profile_image,
                COUNT(DISTINCT c.id) AS number_of_comments,
                COUNT(DISTINCT l.id) AS number_of_likes
                $likeSelect
            FROM posts p
            JOIN users u
                ON p.user_id = u.id
            LEFT JOIN comments c
                ON p.id = c.post_id
            LEFT JOIN likes l
                ON p.id = l.post_id
            LEFT JOIN post_images pi
                ON p.id = pi.post_id
            $likeJoin
            WHERE
                p.deleted_at IS NULL
                $whereUserId
                $whereAfterOrBefore
                $wherePostId
            GROUP BY
                p.id,
                p.user_id,
                p.content,
                p.created_at,
                u.username
            ORDER BY p.id DESC
            $limitStr;
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $db::PARAM_INT);
        }
        $stmt->execute();

        return $this->hydratePostsFromStatement($stmt, $db);
    }

    private function hydratePostsFromStatement(PDOStatement $stmt, PDO $db): ?Posts
    {
        $postsContainer = new Posts();
        while ($row = $stmt->fetch($db::FETCH_ASSOC)) {
            $post = new Hydrator(Post::class)->hydrate($row);
            $post->images = $row['imagesString'] !== null ? explode(',', $row['imagesString']) : [];
            $post->user = new Hydrator(User::class)->hydrate($row);
            $post->user->id = $row['user_id'];
            $postsContainer->posts[] = $post;
        }
        return $postsContainer ?? null;
    }

    public function likePost(int $postId, int $userId): bool
    {
        $sql = <<<'SQL'
            INSERT IGNORE likes (
                post_id,
                user_id
            )
            VALUES (
                :postId,
                :userId
            );
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('postId', $postId, $db::PARAM_INT);
        $stmt->bindParam('userId', $userId, $db::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function unlikePost(int $postId, int $userId): bool
    {
        $sql = <<<'SQL'
            DELETE FROM likes
            WHERE
                post_id = :postId
                AND user_id = :userId;
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('postId', $postId, $db::PARAM_INT);
        $stmt->bindParam('userId', $userId, $db::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function createPostImage(int $postId, string $imagePath): bool
    {
        $sql = <<<'SQL'
            INSERT INTO post_images (
                post_id,
                image
            )
            VALUES (
                :postId,
                :imagePath
            );
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('postId', $postId, $db::PARAM_INT);
        $stmt->bindParam('imagePath', $imagePath, $db::PARAM_STR);
        return $stmt->execute();
    }
}