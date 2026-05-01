<?php

declare(strict_types=1);

namespace Xestify\core;

/**
 * Helper de respuestas HTTP en formato JSON envelopado.
 *
 * Formato de ├®xito:  { "ok": true,  "data": {...}, "meta": {...} }
 * Formato de error:  { "ok": false, "error": { "code": 422, "message": "...", "details": {...} } }
 */
class Response
{
    private int    $statusCode = 200;
    private array  $headers    = ['Content-Type' => 'application/json'];

    // -----------------------------------------------------------------------
    // Factories
    // -----------------------------------------------------------------------

    public static function make(): self
    {
        return new self();
    }

    // -----------------------------------------------------------------------
    // Fluent setters
    // -----------------------------------------------------------------------

    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    // -----------------------------------------------------------------------
    // Respuestas envelopadas
    // -----------------------------------------------------------------------

    /**
     * Respuesta de ├®xito.
     *
     * @param mixed $data  Payload principal
     * @param array $meta  Metadatos opcionales (paginaci├│n, etc.)
     */
    public function json(mixed $data = null, array $meta = []): void
    {
        $envelope = ['ok' => true, 'data' => $data];
        if (!empty($meta)) {
            $envelope['meta'] = $meta;
        }

        $this->send($envelope);
    }

    /**
     * Respuesta de error.
     *
     * @param int    $code     C├│digo HTTP
     * @param string $message  Mensaje legible
     * @param array  $details  Errores de validaci├│n por campo u otros detalles
     */
    public function error(int $code, string $message, array $details = []): void
    {
        $this->statusCode = $code;

        $envelope = [
            'ok'    => false,
            'error' => ['code' => $code, 'message' => $message],
        ];

        if (!empty($details)) {
            $envelope['error']['details'] = $details;
        }

        $this->send($envelope);
    }

    // -----------------------------------------------------------------------
    // Shortcuts de errores comunes
    // -----------------------------------------------------------------------

    public function notFound(string $message = 'Not Found'): void
    {
        $this->error(404, $message);
    }

    public function unauthorized(string $message = 'Unauthorized'): void
    {
        $this->error(401, $message);
    }

    public function forbidden(string $message = 'Forbidden'): void
    {
        $this->error(403, $message);
    }

    public function unprocessable(string $message, array $details = []): void
    {
        $this->error(422, $message, $details);
    }

    public function serverError(string $message = 'Internal Server Error'): void
    {
        $this->error(500, $message);
    }

    // -----------------------------------------------------------------------
    // Emisi├│n
    // -----------------------------------------------------------------------

    private function send(array $envelope): void
    {
        if (PHP_SAPI !== 'cli') {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        $json = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo $json !== false ? $json : '{"ok":false,"error":{"code":500,"message":"Encoding error"}}';
    }
}
