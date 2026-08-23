<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['turn_id'], $_POST['feedback'])) {
    $turn_id = $_POST['turn_id'];
    $feedback = in_array($_POST['feedback'], ['up', 'down']) ? $_POST['feedback'] : null;

    if ($feedback) {
        // 1. Atualiza a sessão para refletir na UI imediatamente
        if (isset($_SESSION["chat"])) {
            foreach ($_SESSION["chat"] as &$linha) {
                if ($linha["turn_id"] === $turn_id) {
                    $linha["feedback"] = $feedback;
                    break;
                }
            }
        }

        // 2. Registra o feedback no arquivo de log, vinculado ao ID da pergunta
        $logFile = __DIR__ . '/perguntas_log.txt';
        $feedbackLine = date('Y-m-d H:i:s') . " | FEEDBACK | ID: $turn_id | Avaliacao: $feedback\n";
        
        if (@file_put_contents($logFile, $feedbackLine, FILE_APPEND | LOCK_EX) === false) {
            @file_put_contents('/tmp/furtades_perguntas_log.txt', $feedbackLine, FILE_APPEND | LOCK_EX);
        }

        header('Content-Type: application/json');
        echo json_encode(["status" => "success"]);
        exit;
    }
}

header('Content-Type: application/json');
echo json_encode(["status" => "error", "message" => "Requisição inválida"]);
exit;
?>
