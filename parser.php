<?php
header('Content-Type: text/html; charset=utf-8');

// Função para remover acentos de forma mais abrangente
function removerAcentos($string) {
    $acentos = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
        'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ç' => 'C', 'Ñ' => 'N'
    ];
    return strtr($string, $acentos);
}

// Definição das consultas canônicas
$consultasCanonicas = [
    'familia' => 'mostrar_familia',
    'filhos'  => 'mostrar_filhos',
    'pais'    => 'mostrar_pais',
    'irmaos'  => 'mostrar_irmaos',
    'conjuge' => 'mostrar_conjuge',
];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['query'])) {
    $queryString = removerAcentos(trim($_POST['query']));

    $parser = new QueryParser();
    $queryExecutor = new QueryExecutor($consultasCanonicas);

    $parsedQuery = $parser->parse($queryString);

    echo "<pre>Consulta processada:<br>";
    print_r($parsedQuery);
    echo "</pre>";

    $queryExecutor->executarConsulta($parsedQuery);
}

class QueryParser {
    public function parse(string $queryString): ?array {
        // Converter para minúsculas e remover múltiplos espaços
        $queryString = strtolower(preg_replace('/\s+/', ' ', trim($queryString)));

        // Remover palavras irrelevantes
        $queryString = preg_replace('/\b(o|os|as|de|do|da|dos|das|em|para|com|um|uma|e)\b/', '', $queryString);
        $queryString = trim(preg_replace('/\s+/', ' ', $queryString)); // Remover espaços extras

        // Debug
        echo "<pre>String após limpeza: $queryString</pre>";

        if (preg_match('/(mostrar|mostre)\s*a\s*fam[íi]lia\s+(.*)/', $queryString, $matches)) {
            return ['tipo' => 'familia', 'nome' => trim($matches[2])];
        } elseif (preg_match('/(mostrar|mostre)\s*os\s*filhos\s*de\s*(.*)/', $queryString, $matches)) {
            return ['tipo' => 'filhos', 'nome' => trim($matches[2])];
        } elseif (preg_match('/(mostrar|mostre)\s*os\s*pais\s*de\s*(.*)/', $queryString, $matches)) {
            return ['tipo' => 'pais', 'nome' => trim($matches[2])];
        } elseif (preg_match('/(mostrar|mostre)\s*os\s*irm[ãa]os\s*de\s*(.*)/', $queryString, $matches)) {
            return ['tipo' => 'irmaos', 'nome' => trim($matches[2])];
        } elseif (preg_match('/(mostrar|mostre)\s*o\s*c[ôo]njuge\s*de\s*(.*)/', $queryString, $matches)) {
            return ['tipo' => 'conjuge', 'nome' => trim($matches[2])];
        } else {
            return null;
        }
    }
}

class QueryExecutor {
    private $consultasCanonicas;

    public function __construct($consultasCanonicas) {
        $this->consultasCanonicas = $consultasCanonicas;
    }

    public function executarConsulta($parsedQuery) {
        if ($parsedQuery === null) {
            echo "Consulta inválida!";
            return;
        }

        $tipoConsulta = $parsedQuery['tipo'];
        $nomePessoa = htmlspecialchars($parsedQuery['nome']); // Evita XSS

        if (isset($this->consultasCanonicas[$tipoConsulta])) {
            $funcao = $this->consultasCanonicas[$tipoConsulta];
            call_user_func([$this, $funcao], $nomePessoa);
        } else {
            echo "Tipo de consulta não encontrado!";
        }
    }

    public function mostrar_familia($nome) {
        echo "Mostrando a família de $nome...";
    }

    public function mostrar_filhos($nome) {
        echo "Mostrando os filhos de $nome...";
    }

    public function mostrar_pais($nome) {
        echo "Mostrando os pais de $nome...";
    }

    public function mostrar_irmaos($nome) {
        echo "Mostrando os irmãos de $nome...";
    }

    public function mostrar_conjuge($nome) {
        echo "Mostrando o cônjuge de $nome...";
    }
}
?>
