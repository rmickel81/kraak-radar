<?php
/**
 * Client HTTP simple para APIs de DeepSeek
 * Usado por el analyzer (parseo de respuestas a JSON)
 */
class DeepSeekClient {
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'deepseek-v4-flash') {
        $this->apiKey = $apiKey;
        $this->model  = $model;
    }

    public function chat(string $system, string $user): array {
        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'temperature' => 0.1,
            'max_tokens'  => 2000,
        ];

        $ch = curl_init('https://api.deepseek.com/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);

        $raw   = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) return ['error' => $error];

        $data = json_decode($raw, true);
        if (!$data) return ['error' => 'json_parse: ' . substr($raw, 0, 300)];

        if (isset($data['error'])) {
            $msg = $data['error']['message'] ?? json_encode($data['error']);
            return ['error' => $msg];
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        $usage   = $data['usage'] ?? [];

        return [
            'text'       => $content,
            'tokens_in'  => $usage['prompt_tokens'] ?? 0,
            'tokens_out' => $usage['completion_tokens'] ?? 0,
        ];
    }
}
