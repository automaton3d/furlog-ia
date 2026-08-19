<?php
/**
 * Integração com a API Groq – versão melhorada
 * - System prompt especializado no clã Furtado
 * - Histórico de conversa
 * - Contexto de livros + banco genealógico
 */

function chamarGroq(
    string $mensagemUsuario,
    string $contextoExtra = "",
    array $historico = [],
    string $modelo = "llama-3.3-70b-versatile"
): string {
    $apiKey = getenv('GROQ_API_KEY');
    if (empty($apiKey)) {
        return "Erro: GROQ_API_KEY não configurada no ambiente.";
    }

    $url = "https://api.groq.com/openai/v1/chat/completions";

    $systemPrompt = <<<PROMPT
Você é o assistente genealógico e historiador do clã **Furtado** (também chamado "Furtadês"), da região de Icoaraci / Belém do Pará.

Seu papel:
- Responder perguntas sobre parentesco, indivíduos, famílias e histórias do clã.
- Combinar dados estruturados do banco genealógico com os relatos vivos dos quatro livros familiares.
- Ser preciso, caloroso e respeitoso com a memória da família.
- Quando usar informações dos livros, mencionar a fonte de forma natural (ex: "Segundo as memórias de J.M. Furtado...", "No livro Furtadês, Dyleli conta que...").
- Quando a informação vier do banco de dados, pode ser mais objetiva.
- Se não souber ou não houver registro, diga claramente.
- Responda sempre em português brasileiro, de forma clara e bem estruturada.
- Evite inventar fatos. Prefira dizer "não encontrei registro" a especular.

Livros de referência disponíveis:
1. "Cenas de minha infância" – J.M. Furtado (Tio Zeca)
2. "Furtadês" – Dyleli Furtado (colaboração Carminha Furtado)
3. Memorial de Mariana (esposa de Pio Furtado)
4. "O Efeito Pipoca – Quando a dor nos ensina" – Carminha Furtado
PROMPT;

    $messages = [
        ["role" => "system", "content" => $systemPrompt]
    ];

    // Adiciona histórico (últimas 6 interações para não estourar contexto)
    $historicoRecente = array_slice($historico, -6);
    foreach ($historicoRecente as $item) {
        if (!empty($item['user'])) {
            $messages[] = ["role" => "user", "content" => $item['user']];
        }
        if (!empty($item['ia'])) {
            $messages[] = ["role" => "assistant", "content" => $item['ia']];
        }
    }

    // Mensagem atual + contexto
    $conteudoUsuario = $mensagemUsuario;
    if (!empty($contextoExtra)) {
        $conteudoUsuario .= "\n\n---\nContexto adicional para esta pergunta:\n" . $contextoExtra;
    }

    $messages[] = ["role" => "user", "content" => $conteudoUsuario];

    $data = [
        "model" => $modelo,
        "messages" => $messages,
        "temperature" => 0.4,
        "max_tokens" => 2048,
        "top_p" => 0.9,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer " . $apiKey
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 60,
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($result === false) {
        return "Erro de conexão com a Groq: " . $curlError;
    }

    $json = json_decode($result, true);

    if ($httpCode !== 200) {
        $msg = $json['error']['message'] ?? $result;
        // Log simples
        file_put_contents(__DIR__ . '/groq_log.txt', date('c') . " HTTP $httpCode: $msg\n", FILE_APPEND);
        return "Erro na API Groq (HTTP $httpCode): " . $msg;
    }

    return $json['choices'][0]['message']['content'] ?? "Resposta vazia da API.";
}
