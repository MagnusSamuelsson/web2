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

/**
 * Class UserController
 *
 * Denna controller hanterar alla användarrelaterade åtgärder, inklusive hämtning av offentliga och autentiserade
 * användarprofiler, uppdatering av användardata, hantering av profilbilder samt följar-/avföljningsfunktionalitet.
 * Genom att använda repository- och processor-komponenter delas ansvarsområden upp för att säkerställa ren och testbar kod.
 *
 */
#[Route('/user')]
class UserController
{
    /**
     * Konstruktor för UserController. Initierar controllern med nödvändiga tjänster, bland annat ett Response-objekt för
     * att bygga HTTP-svar, UserRepository och ImageRepository för att hantera användardata, en ImageProcessor för bildhantering,
     * samt ett Request-objekt för att hantera inkommande HTTP-förfrågningar.
     *
     * @param Response                $response         Objekt för att skapa och konfigurera HTTP-svar.
     * @param UserRepository          $userRepository   Repository för att hantera användardata.
     * @param ImageRepository         $imageRepository  Repository för att hantera bilddata.
     * @param ImageProcessorInterface $imageProcessor   Tjänst för att processa och manipulera bilder.
     * @param Request                 $request          Objekt som representerar den inkommande HTTP-förfrågan.
     *
     * @return void
     */
    public function __construct(
        private Response $response,
        private UserRepository $userRepository,
        private ImageRepository $imageRepository,
        private ImageProcessorInterface $imageProcessor,
        private Request $request,
    ) {
    }

    /**
     * Hämtar offentligt tillgänglig profilinformation för en användare baserat på angivet användar-ID.
     * Metoden kontrollerar att förfrågan är en GET-begäran och returnerar ett 404-svar om användaren inte hittas.
     * Lösenordet tas bort från användarobjektet innan data returneras för att skydda känslig information.
     *
     * @param int $id Användarens unika ID vars offentliga profilinformation ska hämtas.
     *
     * @return Response HTTP-svar med användardata om användaren hittas, annars ett Not Found-svar.
     */
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

    /**
     * Hämtar den autentiserade användarens information baserat på det användar-ID som extraheras från requestens attribut.
     * Metoden är avsedd att hantera GET-begäranden och returnerar ett metod ej tillåtet-svar om HTTP-metoden inte är GET.
     *
     * @return Response HTTP-svar med information om den aktuella användaren.
     */
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

    /**
     * Hämtar profilinformation för en målanvändare, med hänsyn till relationen till den autentiserade användaren.
     * Metoden kontrollerar att förfrågan är GET, verifierar att en autentiserad användare finns samt returnerar ett Forbidden-svar
     * om autentiseringsuppgifterna saknas. Lösenordet tas bort innan användardatan returneras.
     *
     * @param int $id Målanvändarens unika ID vars profilinformation ska hämtas.
     *
     * @return Response HTTP-svar med den autentiserade profilinformationen eller ett felmeddelande (Forbidden/Not Found).
     */
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

    /**
     * Uppdaterar den autentiserade användarens data baserat på inkommande PUT-begäran.
     * Metoden hydraterar ett User-objekt från request-data, kontrollerar att det angivna användar-ID:t stämmer med det
     * autentiserade användar-ID:t, validerar och sanerar data (såsom användarnamn och beskrivning) innan en uppdatering
     * utförs via UserRepository.
     *
     * @return Response HTTP-svar med det uppdaterade användarobjektet om lyckat, annars ett Bad Request- eller Forbidden-svar.
     */
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

    /**
     * Hanterar uppladdning och bearbetning av en ny profilbild.
     * Metoden kontrollerar att anropet sker via en POST-begäran samt att data levereras som en stream och utgör en giltig bild.
     * Bilden processas med hjälp av ImageProcessor, konverteras till WebP-format, ges ett unikt namn, och flyttas till den
     * angivna profilbildskatalogen. Slutligen uppdateras användardatabasen med det nya bildnamnet.
     *
     * @return Response HTTP-svar med det nya bildnamnet om operationen lyckas, annars felmeddelanden (Bad Request, Not Found, Internal Server Error).
     */
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

    /**
     * Hämtar eller sparar ursprunglig profilbild beroende på HTTP-metoden i begäran.
     * - Vid POST-begäran kallas metoden saveOriginalImage för att spara den ursprungliga bilden.
     * - Vid GET-begäran returneras den ursprungliga bilden via metoden getImageOrigin.
     * För andra metoder returneras ett metod ej tillåtet-svar.
     *
     * @return Response HTTP-svar beroende på den specifika operationen (spara eller hämta ursprunglig bild).
     */
    #[Route('/profileimage/origin')]
    public function getImage(): Response
    {
        return match ($this->request->method()) {
            'POST' => $this->saveOriginalImage(),
            'GET' => $this->getImageOrigin(),
            default => $this->response->methodNotAllowed()
        };

    }
    /**
     * (Privat metod) Sparar den ursprungliga versionen av en profilbild.
     * Metoden kontrollerar att data levereras som en stream och att den utgör en giltig bild. Bildfilen skapas och
     * konverteras till WebP-format innan den sparas i databasen via UserRepository tillsammans med bildens dimensioner.
     * Vid misslyckande returneras ett Not Modified-svar, annars ett OK-svar.
     *
     * @return Response HTTP-svar som indikerar om den ursprungliga bilden sparades korrekt.
     */
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

    /**
     * Hanterar förfrågningar relaterade till ursprunglig profilbilds metadata.
     * Metoden dirigerar begäran baserat på HTTP-metoden:
     * - Vid POST-begäran kallas saveOriginalImageInfo för att spara bildmetadata.
     * - Vid GET-begäran hämtas metadata via getOriginalImageInfo.
     * För andra metoder returneras ett metod ej tillåtet-svar.
     *
     * @return Response HTTP-svar med bildmetadata eller ett felmeddelande.
     */
    #[Route('/profileimage/origin/info')]
    public function profilimageOriginInfo(): Response
    {
        return match ($this->request->method()) {
            'POST' => $this->saveOriginalImageInfo(),
            'GET' => $this->getOriginalImageInfo(),
            default => $this->response->methodNotAllowed()
        };
    }

    /**
     * (Privat metod) Hämtar metadata för den ursprungliga profilbilden för den autentiserade användaren.
     * Metoden anropar UserRepository för att hämta information om beskärningsområde (bredd, höjd, x- och y-koordinat)
     * samt rotationsvinkel. Dessa data packas in i en strukturerad array och returneras i ett OK-svar.
     *
     * @return Response HTTP-svar med en strukturerad array som innehåller bildens metadata, eller ett Not Found-svar om
     *                  ingen metadata finns.
     */
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

    /**
     * (Privat metod) Sparar metadata för den ursprungliga profilbilden, såsom beskärningsområde och rotationsvinkel.
     * Metoden hämtar data från requesten (en area-array och rotationsdata) och uppdaterar databasen via UserRepository.
     * Vid misslyckande returneras ett Internal Server Error-svar, annars ett OK-svar.
     *
     * @return Response HTTP-svar som bekräftar att metadata för profilbilden har sparats korrekt, eller ett felmeddelande vid fel.
     */
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

    /**
     * Skapar en följarrelation genom att låta den autentiserade användaren följa en annan användare.
     * Metoden kontrollerar att förfrågan är en POST-begäran, hämtar det aktuella användar-ID:t och mål-ID:t (followedId)
     * från requesten, och anropar UserRepository för att etablera relationen.
     *
     * @return Response HTTP-svar som indikerar om operationen att följa användaren lyckades eller ej.
     */
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

    /**
     * Avslutar en befintlig följarrelation genom att låta den autentiserade användaren sluta följa en angiven användare.
     * Metoden kontrollerar att förfrågan är en DELETE-begäran, hämtar det aktuella användar-ID:t, och anropar UserRepository
     * för att radera relationen. Vid fel returneras ett Internal Server Error-svar.
     *
     * @param int $followedId Det unika ID:t för den användare som ska avföljas.
     *
     * @return Response HTTP-svar som bekräftar att följaren har tagits bort, eller ett felmeddelande om operationen misslyckades.
     */
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