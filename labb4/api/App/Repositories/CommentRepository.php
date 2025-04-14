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

/**
 * Class CommentRepository
 *
 * Detta repository hanterar databasanrop relaterade till kommentarer, såsom hämtning, skapande, uppdatering,
 * borttagning samt hantering av "likes" för kommentarer. Klassen använder Dbh för att erhålla en databasanslutning
 * och Hydrator-komponenten för att omvandla databaserader till objekt av modellerna Comment och User.
 * Dessutom injiceras instanser av Post, User, PostRepository och UserRepository vilket möjliggör interaktion
 * med relaterade entiteter och logik utanför kommentarens kontext.
 *
 */
class CommentRepository
{
    /**
     * Konstruktor för CommentRepository som injicerar nödvändiga beroenden.
     * Dessa inkluderar en Comment-modell, en databasanslutning (Dbh), en Post-modell, en User-modell samt
     * repository-instanser för kommentarer, inlägg och användare. Detta möjliggör att repositoryt kan
     * hantera komplexa operationer som involverar flera datamodeller och sammankopplade entiteter.
     *
     * @param Comment           $comment           En instans av Comment-modellen.
     * @param Dbh               $dbh               Databaskopplingen som hanteras via Dbh.
     * @param Post              $post              En instans av Post-modellen.
     * @param User              $user              En instans av User-modellen.
     * @param PostRepository    $postRepository    Repository för hantering av inlägg.
     * @param UserRepository    $userRepository    Repository för hantering av användardata.
     *
     * @return void
     */
    public function __construct(
        private Comment $comment,
        private Dbh $dbh,
        private Post $post,
        private User $user,
        private PostRepository $postRepository,
        private UserRepository $userRepository
    ) {
    }

    /**
     * Hämtar en lista med kommentarer för ett specifikt inlägg från databasen.
     * Metoden kör en SQL-fråga som hämtar alla kommentarer (med användardata och information om "likes")
     * associerade med ett specifikt post-id. Den använder en LEFT JOIN för att hantera kommentarsgilla tillstånd
     * och COALESCE-funktioner för att hantera fall med borttagna användare.
     * Varje rad hydrateras till ett Comment-objekt, och användardata hydratiseras separat till ett User-objekt.
     * Resultatet packas in i en Comments-container.
     *
     * @param int $postId         Det inläggs-ID vars kommentarer ska hämtas.
     * @param int $currentUserId  Det aktuella användarens ID, som används för att avgöra om användaren har gillat en kommentar.
     *
     * @return Comments En container som innehåller en lista med hydratiserade Comment-objekt.
     */
    public function getCommentsByPost(
        int $postId,
        int $currentUserId
    ): Comments {
        $db = $this->dbh->getConnection();
        $sql = <<<'SQL'
            SELECT
                c.*,
                COALESCE(u.username, 'Deleted User') AS username,
                COALESCE(u.profile_image, 'asdsasdasasd') AS profile_image,
                IF(cl.user_id IS NULL, 0, 1) AS liked_by_current_user
            FROM comments c
            JOIN posts p
                ON c.post_id = p.id
            LEFT JOIN users u
                ON c.user_id = u.id
            LEFT JOIN comment_likes cl
                ON c.id = cl.comment_id
                AND cl.user_id = :currentUserId
            WHERE
                c.post_id = :postId
                AND p.deleted_at IS NULL
            ORDER BY c.id DESC;
            SQL;
        $stmt = $db->prepare($sql);
        $stmt->bindValue('postId', $postId);
        $stmt->bindValue('currentUserId', $currentUserId);
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

    /**
     * Skapar en ny kommentar i databasen baserat på ett Comment-objekt.
     * Metoden kör en SQL INSERT-fråga där nödvändiga fält såsom post_id, user_id, content och eventuellt reply_comment_id
     * lagras. Efter insättningen sätts det genererade ID:t på Comment-objektet.
     *
     * @param Comment $comment Det Comment-objekt som ska sparas i databasen.
     *
     * @return bool Returnerar true om kommentaren skapades framgångsrikt.
     */
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

    /**
     * Avgör om en kommentar kan raderas permanent.
     * Metoden kontrollerar via en SQL-fråga om det finns några svar (reply comments) kopplade till den angivna kommentaren.
     * Om inga svar finns, anses kommentaren vara deletable.
     *
     * @param int $commentId Det ID för den kommentar som ska kontrolleras.
     *
     * @return bool Returnerar true om kommentaren är deletable, annars false.
     */
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

    /**
     * Utför en "mjuk" radering av en kommentar genom att sätta ett borttagningsdatum (deleted_at) på kommentaren.
     * Metoden kontrollerar att den angivna kommentaren tillhör användaren (via userId) och att kommentaren inte redan är raderad.
     * Vid en lyckad uppdatering returneras true, annars false.
     *
     * @param int $commentId Det ID för den kommentar som ska tas bort.
     * @param int $userId    Det ID för den användare som utför borttagningen.
     *
     * @return bool Returnerar true om kommentaren raderades (mjuk radering) framgångsrikt.
     */
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

    /**
     * Utför en "hård" radering av en kommentar, vilket innebär att kommentaren tas bort permanent från databasen.
     * Metoden kör en SQL DELETE-fråga där den säkerställer att den angivna kommentaren tillhör användaren (via userId).
     *
     * @param int $commentId Det ID för den kommentar som ska tas bort.
     * @param int $userId    Det ID för den användare som utför borttagningen.
     *
     * @return bool Returnerar true om den hårda raderingen lyckades.
     */
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

    /**
     * Uppdaterar innehållet för en befintlig kommentar i databasen.
     * Metoden kör en SQL UPDATE-fråga för att ändra commentens content, förutsatt att kommentaren inte är raderad
     * och att den tillhör den aktuella användaren. Detta säkerställer att endast ägaren kan uppdatera sitt innehåll.
     *
     * @param Comment $comment Det Comment-objekt med uppdaterat innehåll, inklusive id och user_id.
     *
     * @return bool Returnerar true om uppdateringen lyckades, annars false.
     */
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

    /**
     * Registrerar att en användare gillat en specifik kommentar.
     * Metoden använder en SQL INSERT IGNORE-fråga för att lägga till en rad i tabellen comment_likes, vilket förhindrar
     * dubbletter om samma användare försöker gilla kommentaren flera gånger.
     *
     * @param int $commentId Det ID för den kommentar som ska gillas.
     * @param int $userId    Det ID för den användare som gillar kommentaren.
     *
     * @return bool Returnerar true om "like" registrerades framgångsrikt, annars false.
     */
    public function likeComment(int $commentId, int $userId): bool
    {
        $sql = <<<'SQL'
            INSERT IGNORE comment_likes (
                comment_id,
                user_id
            )
            VALUES (
                :commentId,
                :userId
            );
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('commentId', $commentId, $db::PARAM_INT);
        $stmt->bindParam('userId', $userId, $db::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    /**
     * Tar bort en tidigare registrerad "like" från en kommentar.
     * Metoden kör en SQL DELETE-fråga mot tabellen comment_likes, vilket tar bort raden där både comment_id och user_id matchar.
     *
     * @param int $commentId Det ID för den kommentar vars "like" ska tas bort.
     * @param int $userId    Det ID för den användare som vill ta bort sin "like".
     *
     * @return bool Returnerar true om borttagningen lyckades, annars false.
     */
    public function unlikeComment(int $commentId, int $userId): bool
    {
        $sql = <<<'SQL'
            DELETE FROM comment_likes
            WHERE
                comment_id = :commentId
                AND user_id = :userId;
            SQL;
        $db = $this->dbh->getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam('commentId', $commentId, $db::PARAM_INT);
        $stmt->bindParam('userId', $userId, $db::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}