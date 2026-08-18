<?php
require_once 'parser2.php';

$logo = "img/logo.gif";

// Estabelece conexão com o banco
function getConnection() {
    $host = 'localhost';
    $db = 'furlgfex_gedcom';
    $user = 'furlgfex_root';
    $pass = 'Grude_62$$$';

    return new mysqli($host, $user, $pass, $db);
}

// Gera recursivamente a árvore genealógica
function arvore($pid, $con) 
{
    $result = array();

    // Nome do indivíduo (raiz)
    $stmt = $con->prepare("SELECT n_full FROM pgv_name WHERE n_id = ?");
    if (!$stmt) {
        die("Erro no prepare (arvore - root name): " . $con->error);
    }
    $stmt->bind_param("s", $pid);
    $stmt->execute();
    $stmt->bind_result($n_full);
    if ($stmt->fetch()) {
        $raiz = decode($n_full);
    }
    $stmt->close();

    // Famílias onde ele é cônjuge
    $query = "SELECT f_husb, f_wife, f_chil FROM pgv_families WHERE f_husb = ? OR f_wife = ?";
    $stmt = $con->prepare($query);
    if (!$stmt) {
        die("Erro no prepare (arvore - families): " . $con->error);
    }
    $stmt->bind_param("ss", $pid, $pid);
    $stmt->execute();
    $stmt->store_result(); // ← necessário para não travar conexão
    $stmt->bind_result($f_husb, $f_wife, $f_chil);

    while ($stmt->fetch()) {
        $f_conj = ($pid === $f_husb) ? $f_wife : $f_husb;
        $conjuge = "";

        $stmt2 = $con->prepare("SELECT n_full FROM pgv_name WHERE n_id = ?");
        if (!$stmt2) {
            die("Erro no prepare (arvore - conjuge): " . $con->error);
        }
        $stmt2->bind_param("s", $f_conj);
        $stmt2->execute();
        $stmt2->bind_result($n_full_conj);
        if ($stmt2->fetch()) {
            $conjuge = decode($n_full_conj);
        }
        $stmt2->close();

        $result[] = "+<a href=\"membro?pid=$f_conj\" id=\"arvore\">$conjuge</a>\r\n";

        if (!empty($f_chil)) {
            $children = explode(";", $f_chil);
            foreach ($children as $child) {
                if (empty($child)) continue;

                $stmt3 = $con->prepare("SELECT n_full FROM pgv_name WHERE n_id = ?");
                if (!$stmt3) {
                    die("Erro no prepare (arvore - filho): " . $con->error);
                }
                $stmt3->bind_param("s", $child);
                $stmt3->execute();
                $stmt3->bind_result($n_full_child);
                if ($stmt3->fetch()) {
                    $filho = decode($n_full_child);
                    
                    
    //                $result[] = "\t<a href=\"membro?pid=$child\" id=\"arvore\">$filho</a>\r\n";
                    $result[] = "\t<a href=\"membro?pid=$f_husb\" id=\"arvore\">$filho</a>\r\n";
                    $lines = arvore($child, $con);
                    foreach ($lines as $line) {
                        $result[] = "\t$line";
                    }
                }
                $stmt3->close();
            }
        }
    }
    $stmt->close();
    return $result;
}

// ===== Página principal =====

$pid = $_GET['pid'] ?? '';

header("Content-Type: text/html; charset=UTF-8");
header("Cache-Control: no-cache");

echo "<!DOCTYPE html>\n<html>";
echo "<head>";
echo "<meta charset=\"UTF-8\">";
echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"busca.css\">";
echo "</head>";
echo "<body style=\"padding:5px;\">";

printMenu();

echo "<div id=\"submenu_div\">";
echo "<ul>";
echo "<li id=\"menu_tab\"><a href=\"membro?pid=$pid\">Dados</a></li>";
echo "<li id=\"menu_tab\">Árvore</li>";
echo "</ul>";
echo "</div>";
echo "<hr>";

echo "<div id=\"left-panel\">";
echo "<img src=\"$logo\" width=\"200px\">";
echo "</div>";

echo "<div id=\"right-panel\">";

$con = getConnection();
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

$raiz = "";
$stmt = $con->prepare("SELECT pgv_name.n_full FROM pgv_name, pgv_individuals WHERE n_id=i_id AND i_id = ?");
if (!$stmt) {
    die("Erro no prepare (principal): " . $con->error);
}
$stmt->bind_param("s", $pid);
$stmt->execute();
$stmt->store_result(); // ← ESSENCIAL para evitar travamento na chamada seguinte
$stmt->bind_result($n_full);
if ($stmt->fetch()) {
    $raiz = decode($n_full);
    echo "<h2>Árvore genealógica de $raiz</h2>";
}
$stmt->close();

echo "<pre>";
echo "$raiz\n";
$arvore = arvore($pid, $con);
foreach ($arvore as $line) {
    echo $line;
}
echo "</pre>";
echo "</div>";

echo "<hr style=\"clear:both\">";
echo "</body></html>";

$con->close();

// ===== Menu de navegação =====
function printMenu() 
{
    echo "<div id=\"menu_div\">";
    echo "<ul>";
    echo "<li id=\"menu_tab\"><a class=\"menu\" href=\"s1\"><b>Pesquisar</b></a></li>";
    echo "<li id=\"menu_tab\"><a class=\"menu\" href=\"pdf/\"><b>Livros</b></a></li>";
    echo "<li id=\"menu_tab\"><a class=\"menu\" href=\"http://clan-furtado.com/arvore/login.php\"><b>Admin</b></a></li>";
    echo "<li id=\"menu_tab\"><a class=\"menu\" href=\"pdf/clan-furtado.html\"><b>Clã</b></a></li>";
    echo "<li id=\"menu_tab\"><a class=\"menu\" href=\"pdf/ajuda.html\"><b>?</b></a></li>";
    echo "</ul>";
    echo "</div>";
}
?>
