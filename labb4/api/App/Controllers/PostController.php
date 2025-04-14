<?php
namespace App\Controllers;

use App\Core\Environment;
use App\Models\Post;
use App\Repositories\PostRepository;
use App\Http\Request;
use App\Http\Response;
use App\Services\ImageProcessorInterface;
use Rammewerk\Component\Hydrator\Hydrator;
use Rammewerk\Router\Foundation\Route;

/**
 * Class PostController
 *
 * PostController ansvarar för att ta emot och svara på HTTP-förfrågningar relaterade till inlägg,
 * inklusive att hämta, skapa, uppdatera, gilla/ogilla och radera inlägg, samt hantera
 * bilduppladdningar kopplade till inlägg. Alla åtgärder är skyddade via kontroll av användarens
 * behörighet där det är relevant.
 *
 * Klassen använder beroendeinjektion för att hämta in request- och response-objekt, datalager
 * via PostRepository samt bildbehandling via ImageProcessorInterface.
 *
 * Varje offentlig metod representerar en endpoint och är ofta kopplad till ett specifikt HTTP-verb
 * (GET, POST, PUT, DELETE) och route via attribut-baserad routing.
 */

#[Route('/post')]
class PostController
{
    /**
     * Skapar en ny instans av PostController.
     *
     * Denna controller ansvarar för att hantera HTTP-förfrågningar kopplade till inlägg, inklusive
     * hämtning, skapande, uppdatering, radering och bildhantering. Alla beroenden injiceras via
     * konstruktor för att uppnå lös koppling och bättre testbarhet.
     *
     * @param Request $request Hanterar inkommande HTTP-förfrågningar.
     * @param Response $response Används för att bygga och skicka HTTP-svar.
     * @param PostRepository $postRepository Hanterar all databaslogik för inlägg.
     * @param ImageProcessorInterface $imageProcessor Abstraktion för bildhantering och validering.
     */
    public function __construct(
        private Request $request,
        private Response $response,
        private PostRepository $postRepository,
        private ImageProcessorInterface $imageProcessor,
    ) {
    }

    /**
     * Returnerar alla inlägg utan att filtrera på användare.
     *
     * Stöder paginering genom query-parametrar: `limit`, `beforeId`, `afterId`.
     * Om inga inlägg hittas returneras ett 404-svar.
     *
     * @return Response HTTP-svar med inläggsdata eller felmeddelande.
     */

    public function getAll(): Response
    {
        if (!$this->request->isGet()) {
            return $this->response->methodNotAllowed();
        }

        $limit = $this->request->get('limit');
        $beforeId = $this->request->get('beforeId');
        $afterId = $this->request->get('afterId');


        $posts = $this->postRepository->getPosts(
            limit: $limit,
            beforeId: $beforeId,
            afterId: $afterId
        );
        if (!isset($posts->posts)) {
            return $this->response->notFound();
        }
        return $this->response->ok($posts);
    }

    /**
     * Hämtar alla inlägg för en specifik användare.
     *
     * Bygger på användarens ID som skickas som parameter och stöder paginering via query-parametrar.
     *
     * @param int $id Användarens ID.
     * @return Response HTTP-svar med inlägg eller 404 om inga hittas.
     */

    public function getByUser(int $id): Response
    {
        if (!$this->request->isGet()) {
            return $this->response->methodNotAllowed();
        }

        $limit = $this->request->get('limit');
        $beforeId = $this->request->get('beforeId');
        $afterId = $this->request->get('afterId');

        $posts = $this->postRepository->getPostsByUserId(
            userId: $id,
            limit: $limit,
            beforeId: $beforeId,
            afterId: $afterId
        );

        if (!isset($posts->posts)) {
            return $this->response->notFound();
        }

        return $this->response->ok($posts);
    }

    /**
     * Hämtar alla inlägg tillsammans med information om de gillats av den inloggade användaren.
     *
     * Använder attributet `userId` från request för att avgöra gillningsstatus.
     *
     * @return Response HTTP-svar med inläggsdata eller 404 om inga finns.
     */
    #[Route('/')]
    public function getAllWithLike(): Response
    {
        if (!$this->request->isGet()) {
            return $this->response->methodNotAllowed();
        }

        $limit = $this->request->get('limit');
        $beforeId = $this->request->get('beforeId');
        $afterId = $this->request->get('afterId');
        $currentUserId = $this->request->getAttribute('userId');


        $posts = $this->postRepository->getPostsWithLike(
            currentUserId: $currentUserId,
            limit: $limit,
            beforeId: $beforeId,
            afterId: $afterId
        );

        if (!isset($posts->posts)) {
            return $this->response->notFound();
        }

        return $this->response->ok($posts);
    }

    /**
     * Hämtar ett specifikt inlägg med information om det gillats av inloggad användare.
     *
     * @param int $id Inläggets ID.
     * @return Response HTTP-svar med ett inlägg eller felmeddelande.
     */
    #[Route('/*')]
    public function getById(int $id): Response
    {
        if (!$this->request->isGet()) {
            return $this->response->methodNotAllowed();
        }

        $currentUserId = $this->request->getAttribute('userId');

        $post = $this->postRepository->getPostsWithLikeById(
            postId: $id,
            currentUserId: $currentUserId
        );

        return $this->response->ok($post->posts[0]);
    }

    /**
     * Laddar upp en bild till ett inlägg.
     *
     * Säkerställer att förfrågan är en bild, omvandlar till WebP, sparar filen och registrerar i databasen.
     *
     * @param int $id ID för det inlägg som bilden kopplas till.
     * @return Response HTTP-svar med bildens filnamn eller felmeddelande.
     */
    #[Route('/*/uploadImage')]
    public function uploadImage(int $id): Response
    {
        if (!$this->request->isPost()) {
            return $this->response->methodNotAllowed();
        }

        if (!$this->request->isStream()) {
            return $this->response->badRequest();
        }

        if (!$this->imageProcessor->isImage()) {
            return $this->response->badRequest();
        }

        $userId = $this->request->getAttribute('userId');

        $this->imageProcessor
            ->setMaxHeight(300)
            ->setMaxWidth(300);

        $imgFile = $this->imageProcessor
            ->createImageFromString()
            ->convertToWebP();

        $newImageName = $imgFile->getUniqueName();

        $postImagePath = realpath(Environment::get('POST_IMAGE_PATH'));
        if (!$postImagePath) {
            return $this->response->internalServerError("Post image path not found, $postImagePath");
        }


        if (!$this->postRepository->createPostImage($id, $newImageName)) {
            $imgFile->delete();
            return $this->response->internalServerError("Failed to create post image");
        }

        if (!$imgFile->move($postImagePath, $newImageName)) {
            $imgFile->delete();
            return $this->response->internalServerError("Failed to move image file");
        }

        return $this->response->ok($newImageName);
    }

    /**
     * Hämtar inlägg från en specifik användare och visar om de är gillade av den inloggade användaren.
     *
     * @param int $id ID för användaren vars inlägg ska hämtas.
     * @return Response HTTP-svar med inlägg eller 404 om inga finns.
     */
    #[Route('/user/*')]
    public function getByUserWithLike(int $id): Response
    {
        if (!$this->request->isGet()) {
            return $this->response->methodNotAllowed();
        }

        $limit = $this->request->get('limit');
        $beforeId = $this->request->get('beforeId');
        $afterId = $this->request->get('afterId');
        $currentUserId = $this->request->getAttribute('userId');

        $posts = $this->postRepository->getPostsWithLikeByUserId(
            currentUserId: $currentUserId,
            userId: $id,
            limit: $limit,
            beforeId: $beforeId,
            afterId: $afterId
        );

        if (!isset($posts->posts)) {
            return $this->response->notFound();
        }

        return $this->response->ok($posts);
    }

    /**
     * Skapar ett nytt inlägg baserat på inloggad användare och inkommande data.
     *
     * Validerar innehållet, sätter tidsstämplar och sparar till databasen. Returnerar
     * en `201 Created` med postens URL vid framgång.
     *
     * @return Response HTTP-svar med skapad resurs eller fel.
     */
    #[Route('/create')]
    public function create(): Response
    {
        if (!$this->request->isPost()) {
            return $this->response->methodNotAllowed();
        }

        $post = new Post();
        $post->user_id = $this->request->getAttribute('userId');
        $post->content = $this->request->get('content');
        $post->content = trim(preg_replace('/\n\n+/', "\n", $post->content));

        $validInput = $post->validate();
        if ($validInput !== true) {
            $this->response->addError($validInput);
            return $this->response->badRequest();
        }

        $post->created_at = date('Y-m-d H:i:s');
        $post->updated_at = date('Y-m-d H:i:s');
        if (!$this->postRepository->createPost($post)) {
            $this->response->addError('Failed to create post');
            return $this->response->badRequest();
        }
        $url = "post/{$post->id}";
        return $this->response->created($url, $post);
    }

    /**
     * Raderar ett inlägg, givet att den inloggade användaren äger det.
     *
     * Säkerställer rätt HTTP-metod och rättighet innan inlägget tas bort.
     *
     * @param int $id ID för inlägget som ska tas bort.
     * @return Response HTTP-svar som bekräftar borttagning eller fel.
     */
    #[Route('/*/delete')]
    public function delete(int $id): Response
    {
        if (!$this->request->isDelete()) {
            return $this->response->methodNotAllowed();
        }

        $post = new Post();
        $post->id = $id;
        $post->user_id = $this->request->getAttribute('userId');

        if (!$this->postRepository->deletePost($post)) {
            $this->response->addError('Failed to delete post');
            return $this->response->badRequest();
        }
        return $this->response->ok();
    }

    /**
     * Uppdaterar ett inlägg, givet att användaren är ägare av det.
     *
     * Accepterar PUT-förfrågningar med ett inläggsobjekt i kroppen, och uppdaterar innehållet i databasen.
     *
     * @return Response HTTP-svar med uppdaterat resultat eller fel.
     */
    #[Route('/update')]
    public function update(): Response
    {
        if (!$this->request->isPut()) {
            return $this->response->methodNotAllowed();
        }

        $userId = $this->request->getAttribute('userId');
        $post = $this->request->get('post');

        $post = new Hydrator(Post::class)->hydrate($post);
        ;

        if ($post->user_id !== $userId) {
            return $this->response->forbidden(['message' => 'You are not allowed to edit this post']);
        }

        if (!$this->postRepository->updatePost($post)) {
            $this->response->addError('Failed to update post');
            return $this->response->badRequest();
        }
        return $this->response->ok();
    }

    /**
     * Lägger till en gillning på ett specifikt inlägg för den inloggade användaren.
     *
     * Används i feed eller detaljerad vy när användaren trycker "gilla".
     *
     * @return Response HTTP-svar som bekräftar gillning eller felmeddelande.
     */
    #[Route('/like')]
    public function like(): Response
    {
        if (!$this->request->isPost()) {
            return $this->response->methodNotAllowed();
        }

        $userId = $this->request->getAttribute('userId');
        $postId = $this->request->get('postId');

        if (!$this->postRepository->likePost($postId, $userId)) {
            $this->response->addError('Failed to like post');
            return $this->response->badRequest();
        }
        return $this->response->ok();
    }

    /**
     * Tar bort en gillning från ett inlägg för den inloggade användaren.
     *
     * Säkerställer att korrekt inlägg och användare anges innan borttagning sker.
     *
     * @param int $id ID för det inlägg som inte längre ska vara gillat.
     * @return Response HTTP-svar som bekräftar borttagning eller fel.
     */
    #[Route('/*/unlike')]
    public function unlike(int $id): Response
    {
        if (!$this->request->isDelete()) {
            return $this->response->methodNotAllowed();
        }

        $userId = $this->request->getAttribute('userId');

        if (!$this->postRepository->unlikePost($id, $userId)) {
            $this->response->addError('Failed to unlike post');
            return $this->response->badRequest();
        }
        return $this->response->ok();
    }
}