<?php

namespace App\Repositories;

use App\Services\Dbh;
use App\Models\Posts;
use App\Models\Post;
use App\Models\User;

use Rammewerk\Component\Hydrator\Hydrator;
use PDOStatement;
use PDO;

/**
 * Class PostRepository
 *
 * Detta repository hanterar all inläggsrelaterad databaslogik, såsom att hämta, skapa och uppdatera inlägg,
 * samt att hantera gillningar och bilder kopplade till inlägg.
 * Den använder sig av en databasanslutning via Dbh-tjänsten och Hydrator-komponenten för att mappa
 * databasresultat till objekt.
 *
 */
class PostRepository
{
    /**
     * Skapar en ny instans av PostRepository.
     *
     * Denna klass använder Dependency Injection för att få tillgång till databasanslutningen
     * via tjänsten `Dbh`. Detta möjliggör bättre testbarhet och separation av ansvar.
     *
     * @param Dbh $dbh En instans av databasanslutningshanteraren (Dbh).
     */
    public function __construct(
        private Dbh $dbh
    ) {
    }

    /**
     * Lagrar ett nytt inlägg i databasen.
     *
     * Tar emot ett Post-objekt och sparar dess information i databastabellen `posts`.
     * Efter att inlägget har skapats sätts dess ID automatiskt baserat på den insatta raden.
     *
     * @param Post $post Inlägget som ska skapas.
     * @return bool True om inlägget kunde sparas, annars false.
     */
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

    /**
     * Raderar ett inlägg som tillhör en specifik användare.
     *
     * Säkerställer att endast inlägg som tillhör den angivna användaren kan raderas.
     *
     * @param Post $post Inlägget som ska tas bort.
     * @return bool True om ett inlägg raderades, annars false.
     */
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

    /**
     * Uppdaterar innehållet i ett befintligt inlägg.
     *
     * Metoden säkerställer att endast aktiva (ej raderade) inlägg kan uppdateras, och
     * att uppdateringen endast sker om användaren äger inlägget.
     *
     * @param Post $post Inlägget som ska uppdateras.
     * @return bool True om inlägget uppdaterades, annars false.
     */
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

    /**
     * Hämtar alla inlägg som tillhör en specifik användare.
     *
     * Resultatet kan begränsas med valfria parametrar som antal, samt filtrering före eller efter ett visst inläggs-ID.
     *
     * @param int $userId ID för användaren vars inlägg ska hämtas.
     * @param int|null $limit Max antal inlägg att returnera (valfritt).
     * @param int|null $afterId Hämta inlägg med ID lägre än detta (valfritt).
     * @param int|null $beforeId Hämta inlägg med ID högre än detta (valfritt).
     * @return Posts|null Ett Posts-objekt med inlägg, eller null om inga hittades.
     */
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

    /**
     * Hämtar alla tillgängliga inlägg från databasen.
     *
     * Resultatet kan pagineras med hjälp av valfria parametrar som limit, beforeId och afterId.
     *
     * @param int|null $limit Max antal inlägg att hämta (valfritt).
     * @param int|null $afterId Hämta inlägg med ID mindre än detta (valfritt).
     * @param int|null $beforeId Hämta inlägg med ID större än detta (valfritt).
     * @return Posts|null Ett objekt med inlägg eller null om inga hittades.
     */
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

    /**
     * Hämtar inlägg från en specifik användare och visar om dessa har gillats av en annan användare.
     *
     * Används t.ex. i en vy där en inloggad användare ser en annan användares inlägg
     * och får information om vilka som redan är gillade.
     *
     * @param int $userId ID för användaren vars inlägg hämtas.
     * @param int $currentUserId ID för den användare som är inloggad (används för att visa gillningar).
     * @param int|null $limit Max antal inlägg att returnera (valfritt).
     * @param int|null $afterId Filtrera efter äldre inlägg (valfritt).
     * @param int|null $beforeId Filtrera efter nyare inlägg (valfritt).
     * @return Posts|null Posts-objekt eller null om inga inlägg hittades.
     */
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

    /**
     * Hämtar ett specifikt inlägg och visar om det är gillat av en viss användare.
     *
     * Användbart t.ex. på en detaljerad inläggssida.
     *
     * @param int $postId ID för det inlägg som ska hämtas.
     * @param int $currentUserId ID för användaren som kontrollerar gillning.
     * @return Posts|null Ett Posts-objekt med ett inlägg, eller null om det inte hittades.
     */
    public function getPostsWithLikeById(
        int $postId,
        int $currentUserId
    ): ?Posts {
        return $this->getPostsWithParams(
            postId: $postId,
            currentUserId: $currentUserId
        );
    }

    /**
     * Hämtar alla tillgängliga inlägg och inkluderar information om de är gillade av en viss användare.
     *
     * Detta används t.ex. i en feed där man vill markera vilka inlägg som redan har gillats.
     *
     * @param int $currentUserId ID för den användare som är inloggad.
     * @param int|null $limit Max antal inlägg att returnera (valfritt).
     * @param int|null $afterId Hämta inlägg med lägre ID än detta (valfritt).
     * @param int|null $beforeId Hämta inlägg med högre ID än detta (valfritt).
     * @return Posts|null Posts-objekt eller null om inga hittades.
     */
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

    /**
     * Intern hjälpfunktion som används för att hämta inlägg med olika filtreringsparametrar.
     *
     * Stödjer filtrering på användar-ID, post-ID, paginering (after/before ID), samt om det ska inkluderas
     * information om inlägget är gillat av en viss användare. Returnerar ett `Posts`-objekt med tillhörande
     * användar- och bildinformation, samt antal kommentarer och gillningar.
     *
     * @param int|null $userId ID för användare vars inlägg ska hämtas (valfritt).
     * @param int|null $limit Max antal inlägg att hämta (valfritt).
     * @param int|null $afterId Hämta inlägg äldre än detta ID (valfritt).
     * @param int|null $beforeId Hämta inlägg nyare än detta ID (valfritt).
     * @param int|null $currentUserId ID för användare vars gillningar ska kontrolleras (valfritt).
     * @param int|null $postId Om ett specifikt inlägg ska hämtas (valfritt).
     * @return Posts|null Ett objekt med inlägg eller null om inga träffar finns.
     */
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
                GROUP_CONCAT(DISTINCT pi.image) AS imagesString,
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

    /**
     * Skapar Post-objekt från ett PDOStatement-resultat.
     *
     * Itererar över varje rad i resultatet och använder Hydrator för att mappa fälten till
     * Post- och User-objekt. Hanterar även bifogade bilder och summerar kommentarer och likes.
     *
     * @param PDOStatement $stmt Resultat från en körd databasfråga.
     * @param PDO $db PDO-anslutning för fetch-konfiguration.
     * @return Posts|null Ett Posts-objekt med hydratiserade inlägg, eller null om tomt.
     */
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

    /**
     * Lägger till en gillning för ett specifikt inlägg.
     *
     * Använder `INSERT IGNORE` för att förhindra dubbletter om användaren redan har gillat inlägget.
     *
     * @param int $postId ID för inlägget som ska gillas.
     * @param int $userId ID för användaren som gillar inlägget.
     * @return bool True om en ny gillning lades till, annars false (t.ex. om den redan fanns).
     */
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

    /**
     * Tar bort en användares gillning från ett specifikt inlägg.
     *
     * Säkerställer att rätt användare tas bort från rätt inlägg i likes-tabellen.
     *
     * @param int $postId ID för inlägget.
     * @param int $userId ID för användaren vars gillning ska tas bort.
     * @return bool True om en gillning togs bort, annars false.
     */
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

    /**
     * Lagrar en bild kopplad till ett specifikt inlägg.
     *
     * Bilden sparas i tabellen `post_images`, kopplad till det angivna inläggets ID.
     *
     * @param int $postId ID för det inlägg bilden hör till.
     * @param string $imagePath Sökväg eller namn på bilden.
     * @return bool True om bilden kunde sparas, annars false.
     */
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