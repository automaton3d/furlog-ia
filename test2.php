<?php
require_once 'db.php';

echo "<h2>Estrutura da tabela pgv_name</h2>";

$sql = "DESCRIBE pgv_name";
$result = $conn->query($sql);

if ($result) {
    echo "<table border=1 cellpadding=5>";
    echo "<tr><th>Campo</th><th>Tipo</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($row["Field"]) . "</td><td>" . htmlspecialchars($row["Type"]) . "</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>Erro ao descrever tabela: " . $conn->error . "</p>";
}

$conn->close();
?>
