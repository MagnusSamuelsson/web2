<?php
namespace App\Repositories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\Comments;
use App\Services\Dbh;
use App\Repositories\PostRepository;
use App\Repositories\UserRepository;
use Rammewerk\Component\Hydrator\Hydrator;

class CommentRepository
{
    public function __construct(
        private Comment $comment,
        private Dbh $dbh,
        private Post $post,
        private User $user,
        private CommentRepository $commentRepository,
        private PostRepository $postRepository,
        private UserRepository $userRepository
    ) {
    }

    public function getCommentsByPost(int $postId): Comments
    {
        $db = $this->dbh->getConnection();
        $sql = <<<'SQL'
            SELECT
                c.*,
            COALESCE(u.username, 'Deleted User') AS username,
            COALESCE(u.profile_image, 'asdsasdasasd') AS profile_image
            FROM comments c
            JOIN posts p
                ON c.post_id = p.id
            LEFT JOIN users u
                ON c.user_id = u.id
            WHERE
                c.post_id = :postId
                AND p.deleted_at IS NULL
            ORDER BY c.id DESC;
            SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue('postId', $postId);
        $stmt->execute();
        $commentsContainer = new Comments();
        while ($row = $stmt->fetch($db::FETCH_ASSOC)) {
            $comment = new Hydrator(Comment::class)->hydrate($row);
            if ($row['user_id']) {
                $comment->user = new Hydrator(User::class)->hydrate($row);
                $comment->user->id = $row['user_id'];
            } else {
                $row['user_id'] = 0;
                $comment->user = new Hydrator(User::class)->hydrate($row);
                $comment->user->id = $row['user_id'];
            }
            $commentsContainer->comments[] = $comment;
        }
        return $commentsContainer ?? null;
    }

    public function createComment(Comment $comment): bool
    {
        $db = $this->dbh->getConnection();
        $sql = <<<'SQL'
            INSERT INTO comments (
                post_id,
                user_id,
                content,
                reply_comment_id
            )
            VALUES (
                :postId,
                :userId,
                :content,
                :replyCommentId
            );
            SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue('postId', $comment->post_id);
        $stmt->bindValue('userId', $comment->user_id);
        $stmt->bindValue('content', $comment->content);
        $stmt->bindValue('replyCommentId', $comment->reply_comment_id ?? null);
        $stmt->execute();
        $comment->id = $db->lastInsertId();
        return true;
    }

    public function isDeletable(int $commentId): bool
    {
        $db = $this->dbh->getConnection();
        $sql = <<<'SQL'
            SELECT c.id
            FROM comments c
            JOIN posts p ON c.post_id = p.id
            WHERE
                c.reply_comment_id = :commentId
                AND p.deleted_at IS NULL;
            SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue('commentId', $commentId);
        $stmt->execute();
        return $stmt->rowCount() === 0;
    }

    public function deleteComment(int $commentId, int $userId): bool
    {
        $db = $this->dbh->getConnection();
        $sql = <<<SQL
            UPDATE comments
            SET deleted_at = NOW()
            WHERE
                id = :commentId
                AND user_id = :userId
                AND deleted_at IS NULL;
            SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue('commentId', $commentId);
        $stmt->bindValue('userId', $userId);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function deleteCommentHard(int $commentId, int $userId): bool
    {
        $db = $this->dbh->getConnection();
        $sql = <<<'SQL'
            DELETE FROM comments
            WHERE
                id = :commentId
                AND user_id = :userId;
            SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue('commentId', $commentId);
        $stmt->bindValue('userId', $userId);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function updateComment(Comment $comment): bool
    {
        $db = $this->dbh->getConnection();
        $sql = <<<'SQL'
            UPDATE comments
            SET content = :content
            WHERE
                id = :id
                AND user_id = :userId
                AND deleted_at IS NULL;
            SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue('content', $comment->content);
        $stmt->bindValue('id', $comment->id);
        $stmt->bindValue('userId', $comment->user_id);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}