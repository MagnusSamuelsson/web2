<?php
namespace App\Controllers;

use App\Core\Environment;
use App\Http\Cookie;
use App\Repositories\AuthRepository;
use App\Repositories\UserRepository;
use App\Models\User;
use App\Http\Request;
use App\Http\Response;
use Rammewerk\Router\Foundation\Route;
use Rammewerk\Router\Error\InvalidRoute;

/**
 * Class AuthController
 *
 * Denna controller hanterar alla autentiseringsrelaterade endpoints för applikationen under prefixet "/auth".
 * Den ansvarar för att hantera användarens inloggning, registrering, token-förnyelse samt utloggning.
 * Controller-klassen kopplar samman HTTP-förfrågningar med lämpliga tjänster (AuthRepository och UserRepository)
 * och ansvarar för att skicka korrekt HTTP-svar med lämpliga statuskoder och data.
 */
#[Route('/auth')]
class AuthController
{
    /**
     * Konstruktor för AuthController.
     * Initierar controller-objektet med nödvändiga beroenden:
     * - Request: för att hantera inkommande HTTP-förfrågningar.
     * - Response: för att konstruera och skicka HTTP-svar.
     * - AuthRepository: hanterar autentiseringslogik och token-relaterade operationer.
     * - UserRepository: hanterar användardata, exempelvis hämtning och skapande av användare.
     *
     * @param Request        $request         HTTP-förfrågan som innehåller data från klienten.
     * @param Response       $response        HTTP-svar som ska byggas och returneras till klienten.
     * @param AuthRepository $authRepository  Repository med logik för hantering av tokens.
     * @param UserRepository $userRepository  Repository med logik för hantering av användardata.
     *
     * @return void
     */
    public function __construct(
        private Request $request,
        private Response $response,
        private AuthRepository $authRepository,
        private UserRepository $userRepository
    ) {
    }

    /**
     * Hanterar en GET-förfrågan för att verifiera att en användare är autentiserad.
     * Metoden kontrollerar att förfrågan är av typen GET, hämtar användar-ID från requestens attribut,
     * och anropar UserRepository för att hämta motsvarande användare. Ett framgångsrikt svar returneras med
     * användarens data om autentiseringen är giltig.
     *
     * @throws InvalidRoute Om HTTP-metoden inte är GET.
     *
     * @return Response Ett HTTP-svar med status OK och det hämtade användarobjektet.
     */
    public function checkAuth(): Response
    {
        if (!$this->request->isGet()) {
            throw new InvalidRoute('Method not allowed');
        }
        $userId = $this->request->getAttribute('userId');
        $user = $this->userRepository->getUserById($userId);

        return $this->response->ok([
            'user' => $user
        ]);
    }

    /**
     * Hanterar en GET-förfrågan på "/auth/token" för att förnya tokens.
     * Metoden kontrollerar att anropet är en GET-begäran, hämtar refresh token från cookies,
     * och verifierar dess giltighet via AuthRepository. Vid en giltig token uppdateras den genom
     * att sätta ett nytt utgångsdatum, generera en ny tokensträng och uppdatera databasposten.
     * En ny access token genereras därefter och en uppdaterad refresh-token-cookie bifogas i svaret.
     *
     * @throws InvalidRoute Om HTTP-metoden inte är GET.
     *
     * @return Response Ett HTTP-svar med status OK och den genererade access token,
     *                  eller ett unauthorized-svar om token saknas/är ogiltig.
     */
    #[Route('/token')]
    public function token(): Response
    {
        if (!$this->request->isGet()) {
            throw new InvalidRoute('Method not allowed');
        }

        $refreshToken = $this->request->cookie(Environment::get('REFRESH_TOKEN_COOKIE_NAME'));

        if (!$refreshToken) {
            return $this->response->unauthorized([
                'message' => 'No refresh token found'
            ]);
        }

        $refreshToken = $this->authRepository->getRefreshToken($refreshToken);

        if (!$refreshToken) {
            return $this->response->unauthorized([
                'message' => 'Invalid refresh token'
            ]);
        }

        $accessToken = $this->authRepository
            ->setRefreshTokenExpiration($refreshToken)
            ->generateRefreshToken($refreshToken)
            ->updateRefreshToken($refreshToken)
            ->generateAccessToken($refreshToken->user_id);

        $refreshTokenCookie = new Cookie(
            name: Environment::get('REFRESH_TOKEN_COOKIE_NAME'),
            value: $refreshToken->token
        );
        $refreshTokenCookie->setExpirationDays(Environment::get('REFRESH_TOKEN_EXPIRATION_DAYS'));

        return $this->response
            ->setCookie($refreshTokenCookie)
            ->ok([
                'access_token' => $accessToken
            ]);
    }

    /**
     * Hanterar användarregistrering via en POST-förfrågan på "/auth/register".
     * Metoden validerar att både användarnamn och lösenord är angivna. Vid felaktiga eller saknade uppgifter
     * läggs felmeddelanden till och ett bad request-svar returneras. Om indata är korrekta skapas ett nytt
     * User-objekt, valideras mot regler för lösenord och användarnamn, och kontrolleras mot existerande användare
     * innan det lagras med UserRepository. Slutligen returneras ett created-svar med information om den nyregistrerade användaren.
     *
     * @throws InvalidRoute Om HTTP-metoden inte är POST.
     *
     * @return Response Ett HTTP-svar med status Created med den nya användarens data, eller Bad Request vid valideringsfel.
     */
    #[Route('register')]
    public function register(): Response
    {
        if (!$this->request->isPost()) {
            throw new InvalidRoute('Method not allowed');
        }

        $username = $this->request->get('username');
        $password = $this->request->get('password');


        if (!$username || !$password) {
            $this->response->addError('Username and password are required');
            return $this->response->badRequest();
        }

        $user = new User();
        if (!$user->setPassword($password)) {
            $this->response->addError('Password does not meet requirements');
            return $this->response->badRequest();
        }

        $user->username = $username;

        if (!$user->validateUsername()) {
            $this->response->addError('Username does not meet requirements');
            return $this->response->badRequest();
        }

        if ($this->userRepository->getUserByUsername($username)) {
            $this->response->addError('Username already exists');
            return $this->response->badRequest();
        }

        $this->userRepository->createUser($user);

        return $this->response->created(
            url: "user/{$user->id}",
            data: ['user' => $user],
        );
    }

    /**
     * Hanterar inloggning via en POST-förfrågan på "/auth/login".
     * Metoden kontrollerar att inloggningsuppgifterna (användarnamn och lösenord) skickats med.
     * Om uppgifterna saknas eller är felaktiga returneras ett unauthorized-svar.
     * Vid en lyckad verifiering genereras en ny refresh token och access token via AuthRepository,
     * en refresh token-cookie sätts och både access token samt användarinformation returneras i ett OK-svar.
     *
     * @throws InvalidRoute Om HTTP-metoden inte är POST.
     *
     * @return Response Ett HTTP-svar med status OK innehållande access token och användardata,
     *      eller Unauthorized vid felaktiga inloggningsuppgifter.
     */
    #[Route('login')]
    public function login(): Response
    {
        if (!$this->request->isPost()) {
            throw new InvalidRoute('Method not allowed');
        }

        $username = $this->request->get('username');
        $password = $this->request->get('password');

        if (!$username || !$password) {
            $this->response->addError('Username and password are required');
            return $this->response->badRequest();
        }

        $user = $this->userRepository->getUserByUsername($username, false);

        if (!$user) {
            return $this->response->unauthorized([
                'message' => 'Invalid username or password'
            ]);
        }

        if (!$user->verifyPassword($password)) {
            return $this->response->unauthorized([
                'message' => 'Invalid username or password'
            ]);
        }

        $refreshToken = $this->authRepository->createNewRefreshToken($user->id);

        $refreshCookie = new Cookie(
            name: Environment::get('REFRESH_TOKEN_COOKIE_NAME'),
            value: $refreshToken->token
        );
        $refreshCookie->setExpirationDays(Environment::get('REFRESH_TOKEN_EXPIRATION_DAYS'));

        $this->response->setCookie($refreshCookie);

        $accessToken = $this->authRepository->generateAccessToken($user->id);
        unset($user->password);

        return $this->response->ok([
            'access_token' => $accessToken,
            'user' => $user
        ]);
    }

    /**
     * Hanterar utloggning via en POST-förfrågan på "/auth/logout".
     * Metoden kontrollerar först att anropet är en POST-begäran. Därefter hämtas refresh token från cookies.
     * Om ingen token hittas returneras ett unauthorized-svar. Vid en giltig token raderas den ur databasen
     * via AuthRepository och refresh token-cookien tas bort från klienten. Avslutningsvis returneras ett OK-svar
     * med ett meddelande som bekräftar att utloggningen har genomförts.
     *
     * @throws InvalidRoute Om HTTP-metoden inte är POST.
     *
     * @return Response Ett HTTP-svar med status OK och ett meddelande om att användaren har loggats ut,
     *                  eller Unauthorized om refresh token saknas.
     */
    #[Route('logout')]
    public function logout(): Response
    {
        if (!$this->request->isPost()) {
            throw new InvalidRoute('Method not allowed');
        }

        $refreshToken = $this->request->cookie(Environment::get('REFRESH_TOKEN_COOKIE_NAME'));

        if (!$refreshToken) {
            return $this->response->unauthorized([
                'message' => 'No refresh token found'
            ]);
        }

        $this->authRepository->deleteRefreshToken($refreshToken);

        $refreshCookie = new Cookie(
            name: Environment::get('REFRESH_TOKEN_COOKIE_NAME')
        );

        $this->response->deleteCookie($refreshCookie);

        return $this->response->ok([
            'message' => 'Logged out'
        ]);
    }

}