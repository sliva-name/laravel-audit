<?php

declare(strict_types=1);

namespace LaravelAudit\Pattern;

final class JsonHttpClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function post(string $url, array $payload, ?string $apiKey = null, int $timeout = 30): ?array
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($apiKey !== null && $apiKey !== '') {
            $headers[] = 'Authorization: Bearer '.$apiKey;
        }

        if ($payload === []) {
            throw new \InvalidArgumentException('LLM request payload cannot be empty.');
        }

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $encoded,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false || ! $this->responseSuccessful($http_response_header)) {
            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  list<string>  $headers
     */
    private function responseSuccessful(array $headers): bool
    {
        if ($headers === []) {
            return false;
        }

        if (preg_match('/\s(\d{3})\s/', $headers[0], $matches) !== 1) {
            return false;
        }

        $status = (int) $matches[1];

        return $status >= 200 && $status < 300;
    }
}
