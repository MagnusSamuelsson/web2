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

#[Route('/post')]
class PostController
{
    public function __construct(
        private Request $request,
        private Response $response,
        private PostRepository $postRepository,
        private ImageProcessorInterface $imageProcessor,
    ) {
    }
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

        if (!$imgFile->move( $postImagePath, $newImageName)) {
            $imgFile->delete();
            return $this->response->internalServerError("Failed to move image file");
        }

        return $this->response->ok($newImageName);
    }

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

    #[Route('/update')]
    public function update(): Response
    {
        if (!$this->request->isPut()) {
            return $this->response->methodNotAllowed();
        }

        $userId = $this->request->getAttribute('userId');
        $post = $this->request->get('post');

        $post = new Hydrator(Post::class)->hydrate($post);;

        if ($post->user_id !== $userId) {
            return $this->response->forbidden(['message' => 'You are not allowed to edit this post']);
        }

        if (!$this->postRepository->updatePost($post)) {
            $this->response->addError('Failed to update post');
            return $this->response->badRequest();
        }
        return $this->response->ok();
    }

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