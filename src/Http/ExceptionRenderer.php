<?php

declare(strict_types=1);

namespace Marko\Routing\Http;

use JsonException;
use Marko\Core\Exceptions\HttpExceptionInterface;

/**
 * Turns an HttpExceptionInterface into a Response.
 *
 * Renders JSON when the client asks for it (an `Accept` header containing
 * `application/json` or a `+json` type, or a JSON `Content-Type` with no
 * `Accept`), and a minimal HTML page otherwise. Only the exception's
 * getResponseData() is shown — never its message or trace — in every
 * environment.
 *
 * Replace it with a #[Preference] to render branded pages; override
 * renderHtml() or renderJson() to change one format only.
 */
class ExceptionRenderer
{
    /**
     * @throws JsonException
     */
    public function render(
        HttpExceptionInterface $exception,
        Request $request,
    ): Response {
        $statusCode = $exception->getStatusCode();
        $data = $exception->getResponseData();
        $message = $data['message'] ?? null;
        $data = [
            'message' => is_string($message) && $message !== '' ? $message : HttpStatus::reasonPhrase($statusCode),
            ...$data,
        ];

        $response = $this->wantsJson($request)
            ? $this->renderJson($statusCode, $data)
            : $this->renderHtml($statusCode, $data);

        return $response->withHeaders($exception->getHeaders());
    }

    public function wantsJson(
        Request $request,
    ): bool {
        $accept = $request->header('Accept');

        if ($accept !== null && $accept !== '') {
            return $this->isJsonMediaType($accept);
        }

        return $this->isJsonMediaType((string) $request->header('Content-Type'));
    }

    /**
     * @param array<string, mixed> $data Always contains a string `message`
     * @throws JsonException
     */
    protected function renderJson(
        int $statusCode,
        array $data,
    ): Response {
        return Response::json($data, $statusCode);
    }

    /**
     * @param array<string, mixed> $data Always contains a string `message`
     */
    protected function renderHtml(
        int $statusCode,
        array $data,
    ): Response {
        $title = $this->escape($statusCode . ' ' . HttpStatus::reasonPhrase($statusCode));
        $message = $this->escape((string) $data['message']);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>$title</title>
<style>body{font-family:system-ui,sans-serif;margin:0;padding:15vh 24px;text-align:center;color:#222;background:#fafafa}h1{font-size:1.5rem;margin:0 0 .5rem}p{margin:0;color:#555}</style>
</head>
<body>
<h1>$title</h1>
<p>$message</p>
</body>
</html>
HTML;

        return Response::html($html, $statusCode);
    }

    private function isJsonMediaType(
        string $value,
    ): bool {
        $value = strtolower($value);

        return str_contains($value, 'application/json') || str_contains($value, '+json');
    }

    private function escape(
        string $value,
    ): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
