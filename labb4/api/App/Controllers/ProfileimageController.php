<?php

namespace App\Controllers;

use App\Core\Environment;
use App\Http\Request;
use App\Http\Response;
use Rammewerk\Router\Foundation\Route;


/**
 * Class ProfileimageController
 *
 * Denna controller hanterar förfrågningar relaterade till profilbilder. Den erbjuder funktionalitet för att hämta
 * en bildfil baserat på en angiven sökväg, samt en speciell route för att hämta standardbilder (default images).
 * Controller-klassens metodik säkerställer att endast filer inom den auktoriserade bildkatalogen nås, vilket
 * motverkar försök att komma åt otillåtna resurser. Dessutom implementeras caching genom användning av ETag.
 *
 */
#[Route('/profileimage')]
class ProfileimageController
{
    public function __construct(
        private Response $response,
        private Request $request,
    ) {
    }

    /**
     * Konstruktor för ProfileimageController. Initierar controllern med ett Response-objekt för att bygga och
     * returnera HTTP-svar samt ett Request-objekt för att hantera inkommande HTTP-förfrågningar.
     *
     * @param Response $response Objekt för att skapa och konfigurera HTTP-svar.
     * @param Request  $request  Objekt som hanterar informationen från inkommande HTTP-förfrågningar.
     *
     * @return void
     */
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

    /**
     * Hämtar och returnerar en profilbild från servern baserat på den givna relativa sökvägen.
     * Metoden utför följande steg:
     *  - Hämtar den auktoriserade sökvägen till profilbilder från miljövariabeln 'PROFILE_IMAGE_PATH'.
     *  - Konstruerar den fullständiga filvägen med hjälp av realpath och kontrollerar att den ligger
     *    inom den förväntade bildkatalogen för att förhindra otillåten åtkomst (t.ex. path traversal).
     *  - Verifierar att filen existerar och är en giltig fil.
     *  - Beräknar filens MIME-typ, dess sista ändringstid och skapar ett unikt ETag baserat på dessa.
     *  - Om klientens 'If-None-Match'-header matchar det genererade ETag, returneras ett 304 Not Modified-svar.
     *  - I annat fall returneras ett HTTP OK-svar med rätt Content-Type, Content-Length, ETag och Cache-Control headers,
     *    tillsammans med filens innehåll.
     *
     * @param string $path Den relativa sökvägen till den efterfrågade bildfilen.
     *
     * @return Response HTTP-svar med bilddata och lämpliga headers, eller ett felmeddelande om filen inte hittas
     *                  eller om åtkomsten är otillåten.
     */
    #[Route('/default/*')]
    public function getDefaultPicture(string $path): Response
    {
        return $this->getPicture("default" . DIRECTORY_SEPARATOR . $path);

    }
}