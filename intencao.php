<?php
function detectarIntencao($mensagem) {
    // Normaliza para minúsculas
    $mensagem = strtolower(trim($mensagem));

    // Perguntas genealógicas típicas
    if (preg_match('/^quem (foi|era)/', $mensagem)) {
        return "genealogia";
    }

    if (preg_match('/^me fale sobre/', $mensagem)) {
        return "genealogia";
    }

    if (preg_match('/^descendentes de/', $mensagem)) {
        return "genealogia";
    }

    if (preg_match('/^ascendentes de/', $mensagem)) {
        return "genealogia";
    }

    if (preg_match('/^qual a relação de/', $mensagem)) {
        return "genealogia";
    }

    // Palavras-chave relacionadas
    if (strpos($mensagem, "família") !== false ||
        strpos($mensagem, "árvore") !== false ||
        strpos($mensagem, "genealogia") !== false ||
        strpos($mensagem, "parentesco") !== false) {
        return "genealogia";
    }

    // Caso contrário, intenção geral
    return "geral";
}
?>
