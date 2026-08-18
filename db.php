<?php
$conn = new mysqli("localhost", "alexandre", "Bidida_62$$$", "gedcom");
if ($conn->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}
?>
