<?php

namespace App\Controllers;

use App\Core\Environment;
use App\Http\Request;
use App\Http\Response;
use Rammewerk\Router\Foundation\Route;


#[Route('/profileimage')]
class ProfileimageController
{
    public function __construct(
        private Response $response,
        private Request $request,
    ) {
    }

    #[Route('/*')]
    public function getPicture(string $path): Response
    {
        $imagePath = Environment::get('PROFILE_IMAGE_PATH');

        $filePath = realpath($imagePath . DIRECTORY_SEPARATOR . $path);
        $expectedPath = realpath($imagePath);

        if (!str_starts_with($filePath, $expectedPath)) {
            return $this->response->notFound(['message' => "Don't try to hack me!"]);
        }

        if (!is_file($filePath)) {
            return $this->response->notFound(['message' => 'Image not found']);
        }

        $mime = mime_content_type($filePath);
        $lastModified = filemtime($filePath);
        $etag = md5($filePath . $lastModified);

        $clientEtag = $this->request->getHeader('If-None-Match');
        if ($clientEtag && $clientEtag === $etag) {
            return $this->response->notModified();
        }

        return $this->response
            ->setContentType($mime)
            ->setHeader('ETag', $etag)
            ->setHeader('Content-Length', filesize($filePath))
            ->setHeader('Cache-Control', 'max-age=31536000')
            ->setContent(file_get_contents($filePath));
    }

    #[Route('/default/*')]
    public function getDefaultPicture(string $path): Response
    {
        return $this->getPicture("default" . DIRECTORY_SEPARATOR . $path);

    }
}