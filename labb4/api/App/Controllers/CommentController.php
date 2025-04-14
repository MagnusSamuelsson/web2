<?php
namespace App\Controllers;

use App\Models\Comment;
use App\Repositories\CommentRepository;
use App\Http\Request;
use App\Http\Response;
use Rammewerk\Router\Foundation\Route;
use \Rammewerk\Component\Hydrator\Hydrator;


/**
 * Class CommentController
 *
 * Denna controller hanterar alla kommentarrelaterade operationer på inlägg.
 * Den inkluderar metoder för att hämta kommentarer för ett specifikt inlägg, skapa nya kommentarer,
 * skapa svar på befintliga kommentarer, redigera, radera samt gilla/ogilla kommentarer.
 * Genom att använda en dedikerad CommentRepository hanteras dataåtkomst och logik separat från HTTP-lagret.
 *
 */
#[Route('/comment')]
class CommentController
{
    /**
     * Konstruktor för CommentController.
     * Initierar controller med nödvändiga beroenden:
     * - Request: Hanterar inkommande HTTP-förfrågningar.
     * - Response: Ansvarar för att bygga och skicka HTTP-svar.
     * - CommentRepository: Hanterar interaktionen med databasen för kommentarer.
     *
     * @param Request           $request           Inkommande HTTP-förfrågan.
     * @param Response          $response          Objekt för att bygga HTTP-svar.
     * @param CommentRepository $commentRepository Repository för kommentardata.
     *
     * @return void
     */
    public function __construct(
        private Request $request,
        private Response $response,
        private CommentRepository $commentRepository,
    ) {
    }

    /**
     * Hämtar alla kommentarer för ett specifikt inlägg.
     * Metoden kontrollerar att HTTP-metoden är GET, hämtar det aktuella användar-id:t från requestens attribut,
     * och anropar CommentRepository för att hämta kommentarerna kopplade till det angivna inlägg-id:t.
     * Resultatet returneras som ett OK-svar med en lista över kommentarer.
     *
     * @param int $id Inläggsid för det inlägg vars kommentarer ska hämtas.
     *
     * @return Response HTTP-svar med listan på kommentarer eller ett metod ej tillåtet-svar om fel HTTP-metod används.
     */
    #[Route('/*')]
    public function getByPost(int $id): Response
    {
        if (!$this->request->isGet()) {
            return $this->response->methodNotAllowed();
        }

        $currentUserId = $this->request->getAttribute('userId');

        $comments = $this->commentRepository->getCommentsByPost($id, $currentUserId);
        return $this->response->ok($comments);
    }

    /**
     * Skapar en ny kommentar på ett inlägg.
     * Metoden kontrollerar att anropet görs via POST, och att både post_id och content finns med i förfrågan.
     * Vid saknade parametrar läggs felmeddelanden till i svaret och ett Bad Request-svar returneras.
     * Om indata är korrekta, skapas ett nytt Comment-objekt, valideras, och lagras via CommentRepository.
     * Vid lyckad skapelse returneras ett OK-svar med den skapade kommentaren.
     *
     * @return Response HTTP-svar med den skapade kommentaren, eller Bad Request vid valideringsfel eller saknade indata.
     */
    #[Route('/create')]
    public function createComment(): Response
    {
        if (!$this->request->isPost()) {
            return $this->response->methodNotAllowed();
        }
        $post_id = $this->request->get('post_id');
        if (!$post_id) {
            $this->response->addError('Post id is required');
            return $this->response->badRequest();
        }
        $content = $this->request->get('content');
        if (!$content) {
            $this->response->addError('Content is required');
            return $this->response->badRequest();
        }
        $comment = new Comment();
        $comment->post_id = $post_id;
        $comment->user_id = $this->request->getAttribute('userId');
        $comment->content = $content;
        $comment->created_at = date('Y-m-d H:i:s');
        $comment->updated_at = date('Y-m-d H:i:s');
        $comment->reply_comment_id = null;

        $validationErrors = $comment->validate();
        if ($validationErrors !== true) {
            $this->response->addError($validationErrors);
            return $this->response->badRequest();
        }

        if (!$this->commentRepository->createComment($comment)) {
            $this->response->addError('Failed to create comment');
            return $this->response->badRequest();
        }
        return $this->response->ok($comment);
    }


    /**
     * Skapar ett svar (reply) på en befintlig kommentar.
     * Metoden validerar att indata innehåller nödvändiga parametrar: post_id, reply_comment_id (id på kommentaren som besvaras) och content.
     * Vid felaktiga eller saknade parametrar läggs ett felmeddelande till och ett Bad Request-svar returneras.
     * Vid korrekt indata skapas och valideras ett nytt Comment-objekt, som sedan lagras via CommentRepository.
     *
     * @return Response HTTP-svar med det skapade svaret, eller Bad Request vid felaktiga indata.
     */
    #[Route('/answer')]
    public function createAnswer(): Response
    {
        if (!$this->request->isPost()) {
            return $this->response->methodNotAllowed();
        }
        $post_id = $this->request->get('post_id');
        if (!$post_id) {
            $this->response->addError('Post id is required');
            return $this->response->badRequest();
        }
        $answerToId = $this->request->get('reply_comment_id');
        if (!$answerToId) {
            $this->response->addError('Reply comment id is required');
            return $this->response->badRequest();
        }
        $content = $this->request->get('content');
        if (!$content) {
            $this->response->addError('Content is required');
            return $this->response->badRequest();
        }
        $comment = new Comment();
        $comment->post_id = $post_id;
        $comment->user_id = $this->request->getAttribute('userId');
        $comment->content = $content;
        $comment->reply_comment_id = $answerToId;
        $comment->created_at = date('Y-m-d H:i:s');
        $comment->updated_at = date('Y-m-d H:i:s');

        $validationErrors = $comment->validate();
        if ($validationErrors !== true) {
            $this->response->addError($validationErrors);
            return $this->response->badRequest();
        }

        if (!$this->commentRepository->createComment($comment)) {
            $this->response->addError('Failed to create comment');
            return $this->response->badRequest();
        }
        return $this->response->ok($comment);
    }

    /**
     * Raderar en kommentar baserat på dess id.
     * Metoden kontrollerar att HTTP-förfrågan är av typen DELETE. Den hämtar också det aktuella användar-id:t
     * från requestens attribut för att säkerställa att rätt användare utför raderingen.
     * Beroende på kommentarens deletable-status väljs antingen en hård radering eller en mjuk radering via CommentRepository.
     * Om kommentaren inte hittas returneras ett Not Found-svar, annars ett OK-svar med ett bekräftelsemeddelande.
     *
     * @param int $id Kommentarid för den kommentar som ska raderas.
     *
     * @return Response HTTP-svar med bekräftelse på radering eller Not Found om kommentaren inte finns.
     */
    #[Route('/delete/*')]
    public function deleteComment(int $id): Response
    {
        if (!$this->request->isDelete()) {
            return $this->response->methodNotAllowed();
        }
        $userId = $this->request->getAttribute('userId');

        $deleteMethod = $this->commentRepository->isDeletable($id) ? 'deleteCommentHard' : 'deleteComment';

        if (!$this->commentRepository->$deleteMethod($id, $userId)) {
            return $this->response->notFound(['message' => 'Comment not found']);
        }

        return $this->response->ok(['message' => 'Comment deleted']);
    }

    /**
     * Redigerar en existerande kommentar.
     * Metoden accepterar en PUT-förfrågan som innehåller data för att redigera en kommentar.
     * Inkommende data hydreras till ett Comment-objekt med hjälp av Hydrator-komponenten.
     * Säkerhetskontroll utförs genom att jämföra kommentarisatörens id med det aktuella användar-id:t.
     * Om användaren inte har behörighet returneras ett Forbidden-svar. Vid godkänd kontroll returneras ett OK-svar
     * med det redigerade kommentaren.
     *
     * @return Response HTTP-svar med den redigerade kommentaren, eller ett felmeddelande (Forbidden/Bad Request) vid otillåten åtkomst eller felaktiga indata.
     */
    #[Route('/edit')]
    public function editComment(): Response
    {
        if (!$this->request->isPut()) {
            return $this->response->methodNotAllowed();
        }
        $userId = $this->request->getAttribute('userId');
        $comment = $this->request->get('comment');

        if (!$comment) {
            $this->response->addError('Comment is required');
            return $this->response->badRequest();
        }

        $hydrator = new Hydrator(Comment::class);
        $comment = $hydrator->hydrate($comment);

        if ($comment->user_id !== $userId) {
            return $this->response->forbidden(['message' => 'You are not allowed to edit this comment']);
        }

        return $this->response->ok($comment);
    }

    /**
     * Hanterar inlägg av ett "like" på en kommentar.
     * Metoden kontrollerar att förfrågan görs med HTTP-metoden POST, hämtar nödvändiga parametrar som användar-id och kommentarid,
     * och anropar CommentRepository för att registrera att användaren gillat kommentaren.
     * Vid misslyckande returneras ett Bad Request med ett felmeddelande, annars ett OK-svar.
     *
     * @return Response HTTP-svar med status OK vid lyckad "like" eller Bad Request vid misslyckande.
     */
    #[Route('/like')]
    public function like(): Response
    {
        if (!$this->request->isPost()) {
            return $this->response->methodNotAllowed();
        }

        $userId = $this->request->getAttribute('userId');
        $commentId = $this->request->get('commentId');

        if (!$this->commentRepository->likeComment($commentId, $userId)) {
            $this->response->addError('Failed to like comment');
            return $this->response->badRequest();
        }
        return $this->response->ok();
    }

    /**
     * Tar bort ett "like" från en specifik kommentar.
     * Metoden kontrollerar att HTTP-förfrågan är av typen DELETE, hämtar användar-id från requestens attribut,
     * och anropar CommentRepository för att ta bort "like" från kommentaren som identifieras med dess id.
     * Vid misslyckande returneras ett Bad Request med ett felmeddelande, annars ett OK-svar.
     *
     * @param int $id Kommentarid för den kommentar vars "like" ska tas bort.
     *
     * @return Response HTTP-svar med status OK vid lyckad borttagning eller Bad Request vid fel.
     */
    #[Route('/*/unlike')]
    public function unlike(int $id): Response
    {
        if (!$this->request->isDelete()) {
            return $this->response->methodNotAllowed();
        }

        $userId = $this->request->getAttribute('userId');

        if (!$this->commentRepository->unlikeComment($id, $userId)) {
            $this->response->addError('Failed to unlike post');
            return $this->response->badRequest();
        }
        return $this->response->ok();
    }
}