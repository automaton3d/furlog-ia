<?php
require_once 'db.php';
require_once 'consultas.php';
require_once 'intencao.php';
require_once 'ia.php';

$frase = "quem foi Mariana";
$intencao = detectarIntencao($frase);
echo "Intenção detectada: $intencao\n";

$contexto = "";
if ($intencao === "genealogia") {
    $pessoas = buscarPessoaPorNome($frase, $conn);
    if (!empty($pessoas)) {
        $pid = $pessoas[0]["id"];
        $arvore = montarArvore($pid, $conn);
        $contexto = "Dados genealógicos encontrados:\n" . implode("\n", $arvore);
    } else {
        $contexto = "Não encontrei registros genealógicos para essa busca.";
    }
}

$entrada = $frase . (!empty($contexto) ? "\n\n" . $contexto : "");
echo "Entrada para IA:\n$entrada\n\n";

$resposta = chamarGroq($entrada);
echo "Resposta da IA:\n$resposta\n";
?>
