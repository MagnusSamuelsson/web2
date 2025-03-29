<?php

namespace App\Controllers;

use App\Core\Environment;
use App\Http\Request;
use App\Http\Response;
use App\Models\User;
use App\Repositories\ImageRepository;
use App\Services\ImageProcessorInterface;
use Rammewerk\Component\Hydrator\Hydrator;
use Rammewerk\Router\Foundation\Route;
use App\Repositories\UserRepository;

#[Route('/user')]
class UserController
{
    public function __construct(
        private Response $response,
        private UserRepository $userRepository,
        private ImageRepository $imageRepository,
        private ImageProcessorInterface $imageProcessor,
        private Request $request,
    ) {
    }

    public function getUserProfileInfo(int $id): Response
    {
        if (!$this->request->isGet()) {
            return $this->response->methodNotAllowed();
        }

        $user = $this->userRepository->getPublicUserInfo($id);
        if (!$user) {
            return $this->response->notFound();
        }
        unset($user->password);
        return $this->response->ok($user);
    }

    #[Route('/')]
    public function getCurrentUser(): Response
    {
        if (!$this->request->isGet()) {
            return $this->response->methodNotAllowed();
        }

        $userId = $this->request->getAttribute('userId');
        $user = $this->userRepository->getUserById($userId);
        return $this->response->ok($user);
    }

    #[Route('/*')]
    public function getAuthenticatedUserProfileInfo(int $id): Response
    {
        if (!$this->request->isGet()) {
            return $this->response->methodNotAllowed();
        }
        $userId = $this->request->getAttribute('userId');

        if (!$userId) {
            return $this->response->forbidden();
        }

        $user = $this->userRepository->getAuthenticatedUserInfo($userId, $id);
        if (!$user) {
            return $this->response->notFound();
        }
        unset($user->password);
        return $this->response->ok($user);
    }

    #[Route(('/update'))]
    public function updateUser(): Response
    {
        if (!$this->request->isPut()) {
            return $this->response->methodNotAllowed();
        }

        $userId = $this->request->getAttribute('userId');

        $user = new Hydrator(User::class)->hydrate($this->request->get('user'));

        if ($user->id !== $userId) {
            return $this->response->forbidden();
        }

        if (!$user->validateUsername()) {
            return $this->response->badRequest("Invalid username");
        }

        $user->sanitizeDescription();

        $validInput = $user->validateDescription();
        if ($validInput !== true) {
            $this->response->addError($validInput);
            return $this->response->badRequest();
        }

        if (!$this->userRepository->updateUser($user)) {
            return $this->response->internalServerError();
        }

        return $this->response->ok($user);
    }

    #[Route('/profileimage')]
    public function saveProfileImage(): Response
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

        $imagePath = realpath(Environment::get('PROFILE_IMAGE_PATH'));

        if (!$imagePath) {
            return $this->response->notFound(['message' => 'Image path not found']);
        }

        if (!$imgFile->move($imagePath, $newImageName)) {
            return $this->response->internalServerError("Failed to move image file");
        }

        if (!$this->userRepository->replaceProfileImage($userId, $newImageName)) {
            return $this->response->internalServerError("Failed to save image in db");
        }

        return $this->response->ok($newImageName);
    }

    #[Route('/profileimage/origin')]
    public function getImage(): Response
    {
        return match ($this->request->method()) {
            'POST' => $this->saveOriginalImage(),
            'GET' => $this->getImageOrigin(),
            default => $this->response->methodNotAllowed()
        };

    }
    private function saveOriginalImage(): Response
    {
        if (!$this->request->isStream()) {
            return $this->response->badRequest();
        }

        if (!$this->imageProcessor->isImage()) {
            return $this->response->badRequest();
        }

        $userId = $this->request->getAttribute('userId');

        $imgFile = $this->imageProcessor
            ->createImageFromString()
            ->convertToWebP();
        $file = $imgFile->open("rb")->handler();

        if (
            !$this->userRepository->saveOriginalProfileImage(
                $userId,
                $file,
                $this->imageProcessor->getWidth(),
                $this->imageProcessor->getHeight()
            )
        ) {
            return $this->response->notModified("Failed to save image, probably the same image");
        }
        return $this->response->ok();
    }

    private function getImageOrigin(): Response
    {
        if (!$this->request->isGet()) {
            return $this->response->methodNotAllowed();
        }

        $userId = $this->request->getAttribute('userId');

        $image = $this->imageRepository
            ->getImage($userId);

        if (!$image) {
            return $this->response->notFound();
        }

        $image = $image['image_blob'];
        return $this->response
            ->setContentType("image/webp")
            ->setContent($image);
    }

    #[Route('/profileimage/origin/info')]
    public function profilimageOriginInfo(): Response
    {
        return match ($this->request->method()) {
            'POST' => $this->saveOriginalImageInfo(),
            'GET' => $this->getOriginalImageInfo(),
            default => $this->response->methodNotAllowed()
        };
    }

    private function getOriginalImageInfo(): Response
    {
        if (!$this->request->isGet()) {
            return $this->response->methodNotAllowed();
        }

        $userId = $this->request->getAttribute('userId');

        $imageInfo = $this->userRepository->getOriginalProfileImageInfo($userId);

        if (!$imageInfo) {
            return $this->response->notFound();
        }
        $area = [
            'width' => $imageInfo['width'],
            'height' => $imageInfo['height'],
            'x' => $imageInfo['x'],
            'y' => $imageInfo['y'],
        ];
        $imageInfo = [
            'area' => $area,
            'rotation' => $imageInfo['rotation'],
        ];
        return $this->response->ok($imageInfo);
    }
    private function saveOriginalImageInfo(): Response
    {
        if (!$this->request->isPost()) {
            return $this->response->methodNotAllowed();
        }

        $userId = $this->request->getAttribute('userId');

        $area = $this->request->get('area');
        $rotation = $this->request->get('rotation');

        $status = $this->userRepository->saveOriginalProfileImageInfo(
            $userId,
            $area['width'],
            $area['height'],
            $area['x'],
            $area['y'],
            $rotation
        );

        if (!$status) {
            return $this->response->internalServerError("Failed to save image info");
        }

        return $this->response->ok();
    }

    #[Route('/follow')]
    public function followUser(): Response
    {
        if (!$this->request->isPost()) {
            return $this->response->methodNotAllowed();
        }

        $userId = $this->request->getAttribute('userId');
        $followedId = $this->request->get('followedId');

        if (!$this->userRepository->followUser($userId, $followedId)) {
            return $this->response->internalServerError("Failed to follow user");
        }

        return $this->response->ok();
    }

    #[Route('/unfollow/*')]
    public function unfollowUser(int $followedId): Response
    {
        if (!$this->request->isDelete()) {
            return $this->response->methodNotAllowed();
        }

        $userId = $this->request->getAttribute('userId');

        if (!$this->userRepository->unfollowUser($userId, $followedId)) {
            return $this->response->internalServerError("Failed to unfollow user");
        }

        return $this->response->ok();
    }


}