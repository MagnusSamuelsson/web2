<?php

namespace App\Http;

class Response
{
    private array $cookies = [];
    private mixed $content = '';
    private array $errors = [];
    private int $statusCode = 200;
    private array $headers = [];
    private string $contentType = 'application/json';

    public function __construct()
    {

    }

    public function setCookie(Cookie $cookie): self
    {
        $this->cookies[$cookie->getName()] = $cookie;
        return $this;
    }

    public function deleteCookie(Cookie $cookie): self
    {
        $cookie->setExpirationSeconds(-3600);
        $this->setCookie($cookie);
        return $this;
    }

    public function setContentType(string $contentType): self
    {
        $this->contentType = $contentType;
        return $this;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setContent($content): self
    {
        $this->content = $content;
        return $this;
    }

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
    private function setContentTypeHeader(): void
    {
        $this->setHeader('Content-Type', $this->contentType);
    }
    private function prepareArray(): void
    {
        if ($this->contentType !== 'application/json') {
            return;
        }
        if (!is_array($this->content) && !is_object($this->content)) {
            $this->content = ['message' => $this->content];
        }
    }
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

    public function ok($content = ['message' => 'OK']): self
    {
        $this->setStatusCode(200);

        $this->setContent($content);
        return $this;
    }

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
    public function badRequest(string $content = 'Bad Request'): self
    {
        $this->setStatusCode(400);
        $this->setContent([
            'message' => $content,
            'errors' => $this->errors
        ]);
        return $this;
    }
    public function unauthorized($content = ['message' => 'Unauthorized']): self
    {
        $this->setStatusCode(401);
        $this->setContent($content);
        return $this;
    }

    public function notModified($content = ['message' => 'Not Modified']): self
    {
        $this->setStatusCode(304);
        $this->setContent($content);
        return $this;
    }

    public function forbidden($content = ['message' => 'Forbidden']): self
    {
        $this->setStatusCode(403);
        $this->setContent($content);
        return $this;
    }

    public function notFound($content = ['message' => 'Not Found']): self
    {
        $this->setStatusCode(404);
        $this->setContent($content);
        return $this;
    }



    public function methodNotAllowed($content = ['message' => 'Method Not Allowed']): self
    {
        $this->setStatusCode(405);
        $this->setContent($content);
        return $this;
    }

    public function internalServerError($content = ['message' => 'Internal Server Error']): self
    {
        $this->setStatusCode(500);
        $this->setContent($content);
        return $this;
    }

    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }

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
