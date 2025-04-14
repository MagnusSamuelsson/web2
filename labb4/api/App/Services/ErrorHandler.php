<?php
namespace App\Services;

use App\Http\Response;

/**
 * Class ErrorHandler
 *
 * Hanterar fel genom att lägga till felmeddelanden i ett Response-objekt.
 * Detta gör det möjligt att centralisera felhantering och kommunikationen av fel till klienten.
 *
 */
class ErrorHandler
{
    /**
     * Konstruktor för ErrorHandler. Initierar objektet med ett Response-objekt som används
     * för att registrera och vidarebefordra felmeddelanden.
     *
     * @param Response $response Ett Response-objekt som hanterar HTTP-svar och lagring av felmeddelanden.
     *
     * @return void
     */
    public function __construct(
        private Response $response
    ) {
    }

    /**
     * Hanterar ett undantag genom att extrahera felmeddelandet från undantaget och lägga till det i Response-objektet.
     * Metoden returnerar alltid false för att indikera att ett fel inträffade.
     *
     * @param \Exception $e Undantaget som ska hanteras.
     *
     * @return bool Returnerar false efter att felmeddelandet har lagts till i Response-objektet.
     */
    public function handleError(\Exception $e): bool
    {
        $this->response->addError($e->getMessage());
        return false;
    }

}
