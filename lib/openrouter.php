<?php
/**
 * Cliente OpenRouter (proxy para modelos múltiples)
 * BYOK: cada request lleva la API key del usuario
 */
class OpenRouter {
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey) {
        $this->apiKey  = $apiKey;
        $this->baseUrl = OPENROUTER_BASE;
    }

    /**
     * Envía un prompt a un modelo y devuelve la respuesta.
     */
    public function chat(string $model, string $prompt, array $extra = []): array {
        $body = array_merge([
            'model'    => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => $extra['max_tokens'] ?? 1024,
            'temperature' => $extra['temperature'] ?? 0.7,
        ], $extra);

        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: ' . APP_URL,
            ],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 30,
        ]);

        $raw   = curl_exec($ch);
        $error = curl_error($ch);
        $info  = curl_getinfo($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => 'curl: ' . $error];
        }

        $data = json_decode($raw, true);
        if (!$data) {
            return ['error' => 'json_parse: ' . substr($raw, 0, 500)];
        }

        if (isset($data['error'])) {
            $msg = $data['error']['message'] ?? json_encode($data['error']);
            return ['error' => 'api: ' . $msg];
        }

        $choice = $data['choices'][0] ?? null;
        if (!$choice || !isset($choice['message']['content'])) {
            return ['error' => 'no_choice: ' . substr($raw, 0, 500)];
        }

        $usage = $data['usage'] ?? [];
        return [
            'text'       => $choice['message']['content'],
            'tokens_in'  => $usage['prompt_tokens'] ?? 0,
            'tokens_out' => $usage['completion_tokens'] ?? 0,
            'model'      => $data['model'] ?? $model,
        ];
    }

    /**
     * Reintenta con backoff hasta MAX_RETRIES
     */
    public function chatWithRetry(string $model, string $prompt, array $extra = []): array {
        $attempts = 0;
        $lastError = '';
        while ($attempts < MAX_RETRIES) {
            $result = $this->chat($model, $prompt, $extra);
            if (!isset($result['error'])) {
                return $result;
            }
            $lastError = $result['error'];
            $attempts++;
            if ($attempts < MAX_RETRIES) {
                sleep(pow(2, $attempts)); // backoff: 2s, 4s, 8s
            }
        }
        return ['error' => $lastError, 'attempts' => $attempts];
    }
}
