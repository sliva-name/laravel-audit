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

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => json_encode($payload) ?: '{}',
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }
}
