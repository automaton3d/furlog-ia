<?php
require_once 'db.php';
require_once 'consultas.php';

echo "<h2>Teste de árvore genealógica</h2>";

$nomeBusca = "Mariana"; // você pode trocar por outro nome
$pessoas = buscarPessoaPorNome($nomeBusca, $conn);

if (!empty($pessoas)) {
    echo "<p>Resultados para <b>$nomeBusca</b>:</p>";
    echo "<ul>";
    foreach ($pessoas as $p) {
        echo "<li>ID: " . htmlspecialchars($p["id"]) . " — Nome: " . htmlspecialchars($p["nome"]) . "</li>";

        // Monta árvore para o primeiro resultado
        $arvore = montarArvore($p["id"], $conn);
        if (!empty($arvore)) {
            echo "<ul>";
            foreach ($arvore as $linha) {
                echo "<li>" . htmlspecialchars($linha) . "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>Nenhuma família encontrada para este indivíduo.</p>";
        }
    }
    echo "</ul>";
} else {
    echo "<p>Nenhum registro encontrado para '$nomeBusca'.</p>";
}

$conn->close();
?>
