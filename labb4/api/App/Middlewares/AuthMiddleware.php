<?php

namespace App\Middlewares;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\AuthRepository;
use Closure;

/**
 * Class AuthMiddleware
 *
 * Denna middleware hanterar autentisering för inkommande HTTP-förfrågningar.
 * Middleware extraherar ett Bearer-token från requesten och validerar tokenet med hjälp av AuthRepository.
 * Om tokenet är giltigt sätts ett userId-attribut i requesten och nästa middleware eller kontrollant anropas.
 * Vid ogiltigt eller saknat token returneras ett felaktigt HTTP-svar med relevant felmeddelande.
 *
 */
class AuthMiddleware
{
    /**
     * Konstruktor för AuthMiddleware.
     * Initierar objektet med ett Response-objekt för att bygga HTTP-svar samt ett AuthRepository för tokenvalidering.
     *
     * @param Response       $response       Ett Response-objekt för att konfigurera och skicka svar.
     * @param AuthRepository $authRepository Ett AuthRepository-objekt för att hantera autentisering och tokenvalidering.
     *
     * @return void
     */
    public function __construct(
        private Response $response,
        private AuthRepository $authRepository
    ) {
    }

    /**
     * Bearbetar den inkommande förfrågan genom att:
     * - Extrahera ett Bearer-token från requesten.
     * - Validera tokenet med AuthRepository.
     * - Om tokenet är giltigt, sätts användarens ID som ett attribut i requesten.
     * - Vid fel (saknat eller ogiltigt token) läggs ett felmeddelande till och ett felaktigt svar returneras.
     * - Om tokenet är giltigt, skickas förfrågan vidare till nästa middleware eller kontrollant.
     *
     * @param Request $request Den inkommande HTTP-förfrågan.
     * @param Closure $next    Nästa middleware eller åtgärd som ska exekveras om autentisering lyckas.
     *
     * @return Response|Closure Returnerar ett Response-objekt som svar, beroende på autentiseringens utfall.
     */
    public function handle(Request $request, Closure $next): Response|Closure
    {

        $jwt = $request->getBearerToken();
        if (!$jwt) {
            $this->response->addError('No token found');
            return $this->response->badRequest();
        }

        $jwt = $this->authRepository->validateAccessToken($jwt);

        if (!$jwt) {
            $this->response->addError('Invalid token');
            return $this->response->unauthorized();
        }

        $request->setAttribute('userId', (int) $jwt->user_id);

        return $next($request);
    }
}