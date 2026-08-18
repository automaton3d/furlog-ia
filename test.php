<?php
require_once 'db.php'; // usa a conexão definida em db.php

echo "<h2>Teste de conexão com GEDCOM</h2>";

// Verifica se a conexão está ativa
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
} else {
    echo "<p>Conexão estabelecida com sucesso!</p>";
}

// Faz uma consulta simples


$sql = "SHOW TABLES";

//$sql = "SELECT i_id, n_full FROM pgv_name LIMIT 10";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li>ID: " . htmlspecialchars($row["i_id"]) . 
             " — Nome: " . htmlspecialchars($row["n_full"]) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Nenhum registro encontrado na tabela pgv_name.</p>";
}

$conn->close();
?>
