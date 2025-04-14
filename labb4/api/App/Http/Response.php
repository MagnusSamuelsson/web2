<?php

namespace App\Http;

/**
 * Hanterar HTTP-responsen som skickas tillbaka till klienten.
 *
 * Denna klass ansvarar för att bygga en komplett HTTP-respons inklusive innehåll,
 * statuskod, headers, cookies och felmeddelanden. Den har färdiga metoder för vanliga
 * statuskoder som 200 OK, 404 Not Found, etc. samt hantering av JSON-format.
 */
class Response
{
    /**
     * @var array $cookies Innehåller cookies som ska skickas med svaret.
     */
    private array $cookies = [];

    /**
     * @var mixed $content Innehållet som ska skickas tillbaka till klienten.
     */
    private mixed $content = '';

    /**
     * @var array $errors Innehåller eventuella felmeddelanden som ska skickas med svaret.
     */
    private array $errors = [];

    /**
     * @var int $statusCode HTTP-statuskod för svaret.
     */
    private int $statusCode = 200;

    /**
     * @var array $headers Innehåller anpassade headers som ska skickas med svaret.
     */
    private array $headers = [];

    /**
     * @var string $contentType Innehållstyp (MIME type) för svaret.
     */
    private string $contentType = 'application/json';

    /**
     * Lägger till en cookie i responsen.
     *
     * @param Cookie $cookie Ett Cookie-objekt som ska skickas med svaret.
     * @return self
     */
    public function setCookie(Cookie $cookie): self
    {
        $this->cookies[$cookie->getName()] = $cookie;
        return $this;
    }

    /**
     * Tar bort en cookie genom att sätta ett förfalletid i det förflutna.
     *
     * @param Cookie $cookie Ett Cookie-objekt som ska markeras som utgånget.
     * @return self
     */
    public function deleteCookie(Cookie $cookie): self
    {
        $cookie->setExpirationSeconds(-3600);
        $this->setCookie($cookie);
        return $this;
    }

    /**
     * Sätter innehållstypen (MIME type) för svaret.
     *
     * Exempel på MIME-typer är 'application/json', 'text/html', 'application/pdf' etc.
     *
     * @param string $contentType MIME-typ som beskriver innehållets format.
     * @return self
     */
    public function setContentType(string $contentType): self
    {
        $this->contentType = $contentType;
        return $this;
    }

    /**
     * Lägger till en anpassad HTTP-header.
     *
     * @param string $name Namnet på headern.
     * @param string $value Värdet som ska sättas.
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Sätter innehållet för svaret.
     *
     * @param mixed $content Innehåll som ska skickas till klienten.
     * @return self
     */
    public function setContent($content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Omvandlar innehållet till JSON om content-type är inställd till 'application/json'.
     */
    private function prepareJson(): void
    {
        if ($this->contentType !== 'application/json') {
            return;
        }

        $this->content = json_encode($this->content);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->statusCode = 500;
            $this->content = json_encode(['error' => 'Failed to encode response as JSON']);
        }
    }

    /**
     * Sätter header för innehållstyp.
     */
    private function setContentTypeHeader(): void
    {
        $this->setHeader('Content-Type', $this->contentType);
    }

    /**
     * Omvandlar enkla strängar till enhetligt JSON-format.
     */
    private function prepareArray(): void
    {
        if ($this->contentType !== 'application/json') {
            return;
        }
        if (!is_array($this->content) && !is_object($this->content)) {
            $this->content = ['message' => $this->content];
        }
    }

    /**
     * Skickar responsen till klienten.
     * Innehåller statuskod, headers, cookies och innehåll.
     * Om innehållet är en array eller objekt, serialiseras det till JSON.
     * Statuskoden sätts till 500 om det uppstår ett fel vid serialisering.
     * @return void
     */
    public function send(): void
    {
        $this->setContentTypeHeader();
        $this->prepareArray();
        $this->prepareJson();

        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        foreach ($this->errors as $key => $error) {
            header("X-Error-$key: $error");
        }

        $this->sendCookies();
        echo $this->content;
    }

    /**
     * Skickar alla cookies som är tillagda i responsen.
     */
    private function sendCookies(): void
    {
        foreach ($this->cookies as $cookie) {
            setcookie(
                $cookie->getName(),
                $cookie->getValue() ?? '',
                [
                    'expires' => time() + $cookie->getExpire(),
                    'path' => $cookie->getPath(),
                    'domain' => $cookie->getDomain(),
                    'secure' => $cookie->isSecure(),
                    'httponly' => $cookie->isHttpOnly(),
                    'samesite' => $cookie->getSameSite()
                ]
            );
        }
    }

    /**
     * Skickar ett 200 OK-svar med valfritt innehåll.
     *
     * @param mixed $content Innehåll som ska skickas tillbaka.
     * @return self
     */
    public function ok(mixed $content = ['message' => 'OK']): self
    {
        $this->setStatusCode(200);

        $this->setContent($content);
        return $this;
    }

    /**
     * Skickar ett 201 Created-svar med valfri data och Location-header.
     *
     * @param string $url URL till den skapade resursen.
     * @param mixed $data Eventuell data att inkludera i svaret.
     * @return self
     */
    public function created(string $url, mixed $data = []): self
    {
        $content = [
            'message' => 'Resource created successfully',
            'data' => $data
        ];

        $this->setStatusCode(201);
        $this->setHeader('Location', $url);
        $this->setContent($content);

        return $this;
    }
    /**
     * Skickar ett 304 Not Modified-svar med valfritt innehåll.
     *
     * @param array|string $content
     * @return self
     */
    public function notModified(array|string $content = ['message' => 'Not Modified']): self
    {
        $this->setStatusCode(304);
        $this->setContent($content);
        return $this;
    }

    /**
     * Skickar ett 400 Bad Request-svar med valfritt innehåll.
     *
     * @param string $content
     * @return self
     */
    public function badRequest(string $content = 'Bad Request'): self
    {
        $this->setStatusCode(400);
        $this->setContent([
            'message' => $content,
            'errors' => $this->errors
        ]);
        return $this;
    }

    /**
     * Skickar ett 401 Unauthorized-svar med valfritt innehåll.
     *
     * @param array $content
     * @return self
     */
    public function unauthorized(array $content = ['message' => 'Unauthorized']): self
    {
        $this->setStatusCode(401);
        $this->setContent($content);
        return $this;
    }

    /**
     * Skickar ett 403 Forbidden-svar med valfritt innehåll.
     *
     * @param array $content
     * @return self
     */
    public function forbidden(array $content = ['message' => 'Forbidden']): self
    {
        $this->setStatusCode(403);
        $this->setContent($content);
        return $this;
    }

    /**
     * Skickar ett 404 Not Found-svar med valfritt innehåll.
     *
     * @param array $content
     * @return self
     */
    public function notFound(array $content = ['message' => 'Not Found']): self
    {
        $this->setStatusCode(404);
        $this->setContent($content);
        return $this;
    }

    /**
     * Skickar ett 405 Method Not Allowed-svar med valfritt innehåll.
     *
     * @param array $content
     * @return self
     */
    public function methodNotAllowed(array $content = ['message' => 'Method Not Allowed']): self
    {
        $this->setStatusCode(405);
        $this->setContent($content);
        return $this;
    }

    /**
     * Skickar ett 500 Internal Server Error-svar med valfritt innehåll.
     *
     * @param array|string $content
     * @return self
     */
    public function internalServerError(array|string $content = ['message' => 'Internal Server Error']): self
    {
        $this->setStatusCode(500);
        $this->setContent($content);
        return $this;
    }

    /**
     * Sätter valfri HTTP-statuskod för svaret.
     *
     * @param int $statusCode HTTP-statuskod, t.ex. 200, 404, 500.
     * @return void
     */
    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

    /**
     * Lägger till ett eller flera felmeddelanden i svaret.
     * Felmeddelandet kommer synas i headern som X-Error-1, X-Error-2 osv.
     * Används i första hand för debugging under utveckling.
     *
     * @param string|array $message Felmeddelande eller lista av fel.
     * @return void
     */
    public function addError(string|array $message): void
    {
        if (is_array($message)) {
            $this->errors = array_merge($this->errors, $message);
            return;
        }
        $this->errors[] = $message;
        return;
    }
}
