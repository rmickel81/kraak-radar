<?php
/**
 * Analyzer: parsea respuestas crudas a JSON estructurado
 * Usa DeepSeek v4-flash en lugar de Haiku (sin Claude)
 * 
 * Coste típico: ~200-400 tokens por respuesta → fracciones de céntimo
 */

function analyzeAnswer(int $answerId, string $rawText, string $brandName, string $brandDomain, array $aliases, array $competitors): array {
    $systemPrompt = <<<'PROMPT'
Eres un extractor de datos. Analizas respuestas de asistentes de IA sobre marcas y productos.
Devuelves SOLO JSON válido, sin markdown, sin explicaciones, sin prefijo.

Schema JSON esperado:
{
  "brands": [
    {
      "name": "string (nombre exacto de la marca)",
      "is_target": true (si es la marca objetivo) | false (si es competidor),
      "position": 1 (orden de aparición entre marcas, 1=primera, null si sin ranking),
      "sentiment": "positive" | "neutral" | "negative",
      "sentiment_score": 0.50 (float entre -1.0 y 1.0)
    }
  ],
  "sources": [
    {
      "domain": "string (dominio web citado)",
      "url": "string (url completa si aparece) | null"
    }
  ]
}

Reglas:
- Si una marca no se menciona, no la incluyas.
- position = orden relativo entre marcas competidoras. Si solo hay una marca, position=1.
- sentiment_score refleja intensidad: 0.7 positivo fuerte, -0.8 negativo fuerte, 0.1 neutral leve.
- Para fuentes, extrae SOLO dominios/URLs que el modelo cite explícitamente como fuentes.
- Si no hay fuentes, devuelve "sources": [].
- Si no hay marcas, devuelve "brands": [].
PROMPT;

    $aliasesJson = json_encode($aliases, JSON_UNESCAPED_UNICODE);
    $compList = [];
    foreach ($competitors as $c) {
        $compList[] = $c['name'];
    }
    $compStr = json_encode($compList, JSON_UNESCAPED_UNICODE);

    $userPrompt = <<<PROMPT
Marca objetivo: {$brandName}
Aliases: {$aliasesJson}
Competidores: {$compStr}

Respuesta del asistente a analizar:
---
{$rawText}
---
PROMPT;

    $client = new DeepSeekClient(DEEPSEEK_API_KEY, DEEPSEEK_MODEL);
    $result = $client->chat($systemPrompt, $userPrompt);

    if (isset($result['error'])) {
        return ['error' => $result['error']];
    }

    // Intentar parsear JSON
    $text = $result['text'];
    // Limpiar posibles wrappers markdown
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
    $text = preg_replace('/\s*```$/', '', $text);
    $text = trim($text);

    $parsed = json_decode($text, true);
    if (!$parsed) {
        // Reintento con un prompt más explícito
        $retryPrompt = $userPrompt . "\n\nDevuelve ÚNICAMENTE un JSON válido. Sin markdown, sin explicaciones.";
        $result2 = $client->chat($systemPrompt, $retryPrompt);
        if (!isset($result2['error'])) {
            $text2 = preg_replace('/^```(?:json)?\s*/i', '', $result2['text']);
            $text2 = preg_replace('/\s*```$/', '', $text2);
            $text2 = trim($text2);
            $parsed = json_decode($text2, true);
        }
    }

    if (!$parsed || !isset($parsed['brands'])) {
        return ['error' => 'No se pudo extraer JSON estructurado'];
    }

    return [
        'data'         => $parsed,
        'tokens_in'    => $result['tokens_in'],
        'tokens_out'   => $result['tokens_out'],
    ];
}
