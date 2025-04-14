<?php

namespace App\Http;

/**
 * Hanterar inkommande HTTP-förfrågningar till applikationen.
 *
 * Request-klassen agerar som ett gränssnitt mellan PHP:s globala servervariabler
 * och applikationslogiken. Den samlar information från olika källor såsom
 * GET-parametrar, POST-data, JSON body, byte-strömmar, cookies och headers.
 *
 * Klassen erbjuder även bekväma metoder för att hämta data och kontrollera
 * vilken HTTP-metod som används (t.ex. GET, POST, PUT, DELETE).
 */

class Request
{
    /**
     * @var array $attributes Innehåller interna attribut som kan sättas och hämtas.
     */
    private array $attributes = [];

    /**
     * @var array $jsonData Innehåller JSON-data som tolkats från request body.
     */
    private array $jsonData = [];

    /**
     * @var array $files Innehåller uppladdade filer.
     */
    private array $files = [];

    /**
     * @var string $bytestream Innehåller rå byte-ström från request body.
     */
    private string $bytestream;

    /**
     * @var string $contentType Innehållstypen för request body.
     */
    private string $contentType;

    /**
     * Skapar en ny instans av Request och analyserar inkommande data.
     *
     * Konstruktorfunktionen identifierar vilken innehållstyp som används i förfrågan
     * och tolkar datan därefter. Stöd finns för `application/json` (som konverteras till array),
     * samt `application/octet-stream` (som lagras som rå byte-ström).
     *
     * Vid GET-förfrågningar laddas ingen kroppsinnehåll in.
     */
    public function __construct()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            return;
        }
        if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
            $this->contentType = 'application/json';
            $this->jsonData = json_decode(
                file_get_contents('php://input'),
                true
            ) ?? [];
        } elseif (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/octet-stream') !== false) {
            $this->contentType = 'application/octet-stream';
            $this->bytestream = file_get_contents('php://input');
        }
    }

    /**
     * Hämtar ett värde från förfrågan baserat på angivet nyckelnamn.
     *
     * Metoden söker i följande ordning:
     * 1. Egna attribut (satta via `setAttribute`)
     * 2. JSON-data (om request-body är JSON)
     * 3. $_POST
     * 4. $_GET
     *
     * Om ingen av källorna innehåller nyckeln returneras det angivna standardvärdet.
     *
     * @param string $key Nyckeln att hämta.
     * @param mixed $default Standardvärde som returneras om inget hittas.
     * @return mixed Det hittade värdet eller standardvärdet.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key]
            ?? $this->jsonData[$key]
            ?? $_POST[$key]
            ?? $_GET[$key]
            ?? $default;
    }

    /**
     * Returnerar hela innehållet från förfrågans kropp som en rå byte-ström.
     *
     * Används t.ex. vid bilduppladdningar där datan skickas som `application/octet-stream`.
     * Gäller endast när innehållstypen i förfrågan matchar just den typen.
     *
     * @return string Rå byte-ström från request body.
     */
    public function getByteStream(): string
    {
        return $this->bytestream;
    }

    /**
     * Sätter ett internt attribut i request-objektet.
     *
     * Attribut används ofta för att temporärt lagra data, t.ex. inloggad användares ID,
     * eller route-parametrar som extraherats tidigare i kedjan.
     *
     * @param string $key Namn på attributet.
     * @param mixed $value Värdet som ska sparas.
     * @return void
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Hämtar ett tidigare satt attribut från request-objektet.
     *
     * Användbart för att komma åt t.ex. inloggad användare eller annan kontextuell information
     * som satts under requesthanteringen.
     *
     * @param string $key Namn på attributet.
     * @param mixed $default Standardvärde att returnera om attributet inte finns.
     * @return mixed Värdet för attributet eller standardvärdet.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Returnerar HTTP-metoden som används i förfrågan.
     *
     * Exempel: GET, POST, PUT, DELETE etc.
     * Returneras alltid i versaler.
     *
     * @return string HTTP-metod.
     */
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Hämtar värdet för en specifik HTTP-header från förfrågan.
     *
     * Headern omvandlas internt till formatet som PHP använder i $_SERVER,
     * vilket innebär att exempelvis "Content-Type" översätts till "HTTP_CONTENT_TYPE".
     *
     * @param string $key Headerns namn.
     * @return string|null Headerns värde eller null om den inte finns.
     */
    public function getHeader(string $key): string|null
    {
        $key = strtoupper(str_replace('-', '_', $key));
        return $_SERVER["HTTP_$key"] ?? null;
    }

    /**
     * Returnerar true om förfrågan är av typen POST.
     *
     * @return bool
     */
    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    /**
     * Returnerar true om förfrågan är av typen DELETE.
     *
     * @return bool
     */
    public function isDelete(): bool
    {
        return $this->method() === 'DELETE';
    }

    /**
     * Returnerar true om förfrågan är av typen GET.
     *
     * @return bool
     */
    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    /**
     * Returnerar true om förfrågan är av typen PUT.
     *
     * @return bool
     */
    public function isPut(): bool
    {
        return $this->method() === 'PUT';
    }

    /**
     * Hämtar ett cookie-värde från klientens förfrågan.
     *
     * Letar i PHP:s globala $_COOKIE-array.
     *
     * @param string $key Namn på cookien.
     * @param mixed $default Värde som returneras om cookien inte finns.
     * @return string|null Cookie-värdet eller default.
     */
    public function cookie(string $key, mixed $default = null): string|null
    {
        return $_COOKIE[$key] ?? $default;
    }

    /**
     * Hämtar ett Bearer-token från Authorization-headern.
     *
     * Användbart vid autentisering med t.ex. JWT eller liknande tokens.
     *
     * @return string|null Bearer-token utan prefix, eller null om inget finns.
     */
    public function getBearerToken(): string|null
    {
        $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        return str_replace('Bearer ', '', $authorization);
    }

    /**
     * Returnerar true om innehållet i förfrågan är av typen 'application/octet-stream'.
     *
     * Används ofta vid hantering av binärdata, exempelvis filuppladdningar via stream.
     *
     * @return bool
     */
    public function isStream(): bool
    {
        return $this->contentType === 'application/octet-stream';
    }
}
