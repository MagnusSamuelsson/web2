<?php

namespace App\Controllers;

use App\Core\Environment;
use App\Http\Request;
use App\Http\Response;
use Rammewerk\Router\Foundation\Route;


/**
 * Class PostImageController
 *
 * Denna controller hanterar HTTP-förfrågningar relaterade till postbilder. Den hämtar och returnerar en bildfil
 * baserat på en given sökväg, samtidigt som den säkerställer att begäran inte försöker komma åt otillåtna filer
 * genom att verifiera filens sökväg mot den förväntade bildkatalogen. Dessutom hanteras caching genom att jämföra
 * ETag-värden för att undvika onödig dataöverföring.
 *
 */
#[Route('/postimage')]
class PostImageController
{
    /**
     * __construct
     *
     * Konstruktor för PostImageController. Initierar controller med ett Response-objekt för att bygga HTTP-svar
     * samt ett Request-objekt för att hantera inkommande HTTP-förfrågningar.
     *
     * @param Response $response Objekt för att skapa och hantera HTTP-svar.
     * @param Request  $request  Objekt som representerar den inkommande HTTP-förfrågan.
     *
     * @return void
     */
    public function __construct(
        private Response $response,
        private Request $request,
    ) {
    }

    /**
     * getPicture
     *
     * Hämtar och returnerar en bildfil baserat på den angivna relativa sökvägen. Metoden:
     * - Hämtar den korrekta bildkatalogen från miljövariabler.
     * - Validerar att den begärda filens sökväg utgår från den förväntade katalogen för att förhindra otillåten åtkomst.
     * - Kontrollerar att filen existerar och är en faktisk fil.
     * - Bestämmer filens MIME-typ, sista ändringstid, och genererar ett ETag baserat på filens sökväg och ändringstid.
     * - Jämför sedan ETag med klientens ETag-header och returnerar ett 304 Not Modified-svar om de matchar.
     * - Om inte, sätts korrekta HTTP-headers och filens innehåll returneras i svaret.
     *
     * @param string $path Den relativa sökvägen till den begärda bildfilen.
     *
     * @return Response HTTP-svar innehållande bildfilen och lämpliga headers, eller ett felmeddelande om filen inte hittas
     *                  eller om otillåten åtkomst försöks.
     */
    #[Route('/*')]
    public function getPicture(string $path): Response
    {
        $rootPath = Environment::get('ROOT_DIR');
        $imagePath = realpath(Environment::get('POST_IMAGE_PATH'));

        if (!$imagePath) {
            return $this->response->notFound(['message' => 'Image path not found']);
        }

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
}