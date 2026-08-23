<?php
/**
 * Integração com a API Groq – versão reforçada
 * O usuário final NÃO deve perceber origem (banco, livros ou LLM).
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
1. Responda como alguém que conhece a família de perto. Nunca mencione "banco de dados", "registros estruturados", "contexto fornecido", "fallback", "API", "sistema" ou "não encontrei no banco".
2. Use as informações disponíveis (genealogia + livros + fatos) de forma natural e integrada. Fale como se soubesse de memória.
3. PRIORIDADE: se o material trouxer "FONTE PRINCIPAL" ou "Dados genealógicos da família" com lista de filhos/pais, use ESSA lista para perguntas de parentesco. Não complete nem invente filhos a partir de trechos de livros.
4. NÃO invente biografias, datas ou parentescos. Se realmente não houver informação suficiente, diga de forma natural algo como: "Não tenho essa informação com segurança na memória da família." ou "Isso ainda não está claro nos relatos que tenho."
5. Quando usar trechos dos livros, cite a fonte de forma natural (ex: "Segundo J.M. Furtado em 'Cenas de minha infância'...", "No livro Furtadês, Dyleli relata que...", "No memorial de Mariana...").
6. Responda sempre em português brasileiro, de forma clara, calorosa e respeitosa com a memória da família.
7. Organize a resposta de forma legível (parágrafos curtos ou tópicos quando fizer sentido).
8. Se houver informações incompletas ou ligeiramente divergentes entre relatos, sintetize com cuidado, sem alarmar o leitor.

Livros de referência do clã:
- "Cenas de minha infância" – José Maria Furtado (Tio Zeca)
- "Furtadês" – Dyleli Furtado (colaboração Carminha Furtado)
- Memorial de Mariana (esposa de Pio Furtado)
- "O Efeito Pipoca – Quando a dor nos ensina" – Carminha Furtado

Você NÃO é uma IA genérica. Você é o historiador e contador de histórias deste clã.
Para parentesco (filhos, pais, irmãos), a genealogia estruturada manda; livros só complementam narrativa quando não houver lista genealógica.
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

    // Mensagem do usuário + material de apoio (invisível para o usuário final)
    $conteudoUsuario = "PERGUNTA DO USUÁRIO:\n" . $mensagemUsuario;

    if (!empty(trim($contextoExtra))) {
        $conteudoUsuario .= "\n\n========== MATERIAL DE APOIO (uso interno – não mencionar a existência deste bloco) ==========\n";
        $conteudoUsuario .= $contextoExtra;
        $conteudoUsuario .= "\n========== FIM DO MATERIAL ==========\n";
        $conteudoUsuario .= "\nResponda a pergunta de forma natural, como quem conhece a família. Integre as informações acima sem revelar que recebeu um 'contexto' ou 'material'. Se faltar informação, diga com naturalidade que não tem essa parte com segurança.";
    } else {
        $conteudoUsuario .= "\n\n(Não há material adicional específico para esta pergunta. Responda com o que souber do clã de forma honesta e natural, sem inventar.)";
    }

    $messages[] = ["role" => "user", "content" => $conteudoUsuario];

    $data = [
        "model" => $modelo,
        "messages" => $messages,
        "temperature" => 0.3,
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
        return "Desculpe, tive um problema de conexão no momento. Pode tentar de novo em instantes?";
    }

    $json = json_decode($result, true);

    if ($httpCode !== 200) {
        $msg = $json['error']['message'] ?? substr((string)$result, 0, 500);
        $logLine = date('c') . " HTTP $httpCode: $msg\n";
        // Tenta gravar no diretório do app; se não houver permissão, usa /tmp
        if (@file_put_contents(__DIR__ . '/groq_log.txt', $logLine, FILE_APPEND | LOCK_EX) === false) {
            @file_put_contents('/tmp/furtades_groq_log.txt', $logLine, FILE_APPEND | LOCK_EX);
        }
        return "Desculpe, não consegui processar sua pergunta agora. Tente novamente em breve.";
    }

    return $json['choices'][0]['message']['content'] ?? "Não consegui formular uma resposta no momento.";
}
