<?php
namespace App\Controllers;

use App\Models\Comment;
use App\Repositories\CommentRepository;
use App\Http\Request;
use App\Http\Response;
use Rammewerk\Router\Foundation\Route;
use \Rammewerk\Component\Hydrator\Hydrator;

#[Route('/comment')]
class CommentController
{
    public function __construct(
        private Request $request,
        private Response $response,
        private CommentRepository $commentRepository,
    ) {
    }
    #[Route('/*')]
    public function getByPost(int $id): Response
    {
        if (!$this->request->isGet()) {
            return $this->response->methodNotAllowed();
        }
        $comments = $this->commentRepository->getCommentsByPost($id);
        return $this->response->ok($comments);
    }
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
}