<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';
require_once 'consultas.php';
require_once 'intencao.php';
require_once 'ia.php';

echo "<h2>Debug do index</h2>";

$mensagem = "Quem foi Mariana?";
$intencao = detectarIntencao($mensagem);
echo "<p>Intenção detectada: $intencao</p>";

$pessoas = buscarPessoaPorNome($mensagem, $conn);
echo "<pre>"; print_r($pessoas); echo "</pre>";

if (!empty($pessoas)) {
    $pid = $pessoas[0]["id"];
    $arvore = montarArvore($pid, $conn);
    echo "<pre>"; print_r($arvore); echo "</pre>";
}

$resposta = chamarGroq($mensagem);
echo "<p>Resposta da IA:</p><pre>$resposta</pre>";
?>
