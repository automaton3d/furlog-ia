<?php
/**
 * Integração com a API Groq – versão reforçada
 * Força o uso do contexto dos livros e do banco genealógico
 */

function chamarGroq(
    string $mensagemUsuario,
    string $contextoExtra = "",
    array $historico = [],
    string $modelo = "openai/gpt-oss-20b"
): string {
    $apiKey = getenv('GROQ_API_KEY');
    if (empty($apiKey)) {
        return "Erro: GROQ_API_KEY não configurada no ambiente. Defina a variável de ambiente GROQ_API_KEY.";
    }

    $url = "https://api.groq.com/openai/v1/chat/completions";

    $systemPrompt = <<<PROMPT
Você é o assistente oficial de genealogia e memória do clã FURTADO (também chamado FURTADÊS), de Icoaraci / Belém do Pará.

REGRAS OBRIGATÓRIAS:
1. Você SÓ pode usar informações que aparecem no CONTEXTO fornecido abaixo (dados do banco genealógico + trechos dos livros familiares).
2. NÃO invente biografias, datas ou parentescos. Se a informação não estiver no contexto, diga claramente: "Não encontrei essa informação nos registros e livros disponíveis."
3. Quando usar trechos dos livros, cite a fonte de forma natural (ex: "Segundo J.M. Furtado em 'Cenas de minha infância'...", "No livro Furtadês, Dyleli relata que...", "No memorial de Mariana...").
4. Responda sempre em português brasileiro, de forma clara, calorosa e respeitosa com a memória da família.
5. Organize a resposta de forma legível (parágrafos curtos ou tópicos quando fizer sentido).
6. Se o contexto trouxer informações contraditórias ou incompletas, mencione isso.

Livros de referência do clã:
- "Cenas de minha infância" – José Maria Furtado (Tio Zeca)
- "Furtadês" – Dyleli Furtado (colaboração Carminha Furtado)
- Memorial de Mariana (esposa de Pio Furtado)
- "O Efeito Pipoca – Quando a dor nos ensina" – Carminha Furtado

Você NÃO é uma IA genérica. Você é o historiador deste clã específico.
Quando o contexto trouxer a seção "Fatos-chave", use-a com prioridade máxima para responder perguntas de parentesco.
PROMPT;

    $messages = [
        ["role" => "system", "content" => $systemPrompt]
    ];

    // Histórico recente (máx. 4 turnos para não diluir o contexto)
    $historicoRecente = array_slice($historico, -4);
    foreach ($historicoRecente as $item) {
        if (!empty($item['user'])) {
            $messages[] = ["role" => "user", "content" => $item['user']];
        }
        if (!empty($item['ia'])) {
            $messages[] = ["role" => "assistant", "content" => $item['ia']];
        }
    }

    // Mensagem do usuário + contexto em evidência
    $conteudoUsuario = "PERGUNTA DO USUÁRIO:\n" . $mensagemUsuario;

    if (!empty(trim($contextoExtra))) {
        $conteudoUsuario .= "\n\n========== CONTEXTO OBRIGATÓRIO (use somente estas informações) ==========\n";
        $conteudoUsuario .= $contextoExtra;
        $conteudoUsuario .= "\n========== FIM DO CONTEXTO ==========\n";
        $conteudoUsuario .= "\nResponda a pergunta usando EXCLUSIVAMENTE o contexto acima. Se a informação não estiver lá, diga que não encontrou.";
    } else {
        $conteudoUsuario .= "\n\n(Nenhum contexto adicional foi encontrado nos livros ou no banco genealógico para esta pergunta.)";
    }

    $messages[] = ["role" => "user", "content" => $conteudoUsuario];

    $data = [
        "model" => $modelo,
        "messages" => $messages,
        "temperature" => 0.25,   // mais determinístico
        "max_tokens" => 1800,
        "top_p" => 0.85,
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
        $msg = $json['error']['message'] ?? substr($result, 0, 500);
        file_put_contents(__DIR__ . '/groq_log.txt', date('c') . " HTTP $httpCode: $msg\n", FILE_APPEND);
        return "Erro na API Groq (HTTP $httpCode): " . $msg;
    }

    return $json['choices'][0]['message']['content'] ?? "Resposta vazia da API.";
}
