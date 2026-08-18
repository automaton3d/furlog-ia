<?php
function chamarGroq($mensagem, $modelo = "openai/gpt-oss-20b") {
    $apiKey = getenv('GROQ_API_KEY');
    $url = "https://api.groq.com/openai/v1/chat/completions";

    $data = [
        "model" => $modelo,
        "messages" => [
            ["role" => "user", "content" => $mensagem]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $result = curl_exec($ch);
    if ($result === false) {
        return "Erro cURL: " . curl_error($ch);
    }
    curl_close($ch);

    $json = json_decode($result, true);
    return $json['choices'][0]['message']['content'] ?? "Resposta vazia da API";
}
?>
