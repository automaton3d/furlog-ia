<?php
require_once 'db.php';

echo "<h2>Primeiros registros da tabela pgv_name</h2>";

$sql = "SELECT n_id, n_full, n_surname, n_givn FROM pgv_name LIMIT 20";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    echo "<table border=1 cellpadding=5>";
    echo "<tr><th>ID</th><th>Nome completo</th><th>Sobrenome</th><th>Nome dado</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row["n_id"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["n_full"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["n_surname"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["n_givn"]) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>Nenhum registro encontrado.</p>";
}

$conn->close();
?>
