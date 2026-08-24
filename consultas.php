<?php
/**
 * Serviço de busca genealógica usando MariaDB
 * Adaptado do SearchService (JSON) para as tabelas do PhpGedView
 */

class SearchServiceDB
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /** Helper: buscar nome completo pelo ID */
    private function getNomeById(string $id): ?string {
        $sql = "SELECT n_full FROM pgv_name WHERE n_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return $row ? $row['n_full'] : null;
    }

    /** Busca geral (indivíduos e/ou famílias) */
    public function search(string $query, string $type = 'all', int $limit = 20): array
    {
        $results = [];

        if ($type === 'all' || $type === 'indi') {
            $results = array_merge($results, $this->searchIndividuals($query, $limit));
        }

        if ($type === 'all' || $type === 'fam') {
            $results = array_merge($results, $this->searchFamilies($query, $limit));
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return [
            'results' => array_slice($results, 0, $limit),
            'count'   => count($results)
        ];
    }

    /** Busca indivíduos – tokens AND (resolve apelidos entre aspas) + encurtamento */
    private function searchIndividuals(string $query, int $limit): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $qNorm = function_exists('normalizarTexto') ? normalizarTexto($query) : strtolower($query);
        $qNorm = preg_replace('/["\'\`]/u', '', $qNorm);
        $parts = preg_split('/\s+/u', $qNorm, -1, PREG_SPLIT_NO_EMPTY);
        $parts = array_values(array_filter($parts, fn($p) => strlen($p) > 1));

        $resultsById = [];

        // 1) Tokens AND: todos devem aparecer no nome
        if (count($parts) >= 2) {
            $sql = "SELECT i.i_id AS id, n.n_full AS nome FROM pgv_name n
                    JOIN pgv_individuals i ON n.n_id = i.i_id WHERE 1=1";
            $types = '';
            $likes = [];
            foreach ($parts as $tok) {
                $sql .= " AND n.n_full LIKE ?";
                $types .= 's';
                $likes[] = '%' . $tok . '%';
            }
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param($types, ...$likes);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $id = $row['id'];
                $score = max($this->calculateScore($query, $row['nome']), 0.98);
                $nomeNorm = function_exists('normalizarTexto') ? normalizarTexto($row['nome']) : strtolower($row['nome']);
                if (str_contains($nomeNorm, 'furtado') && str_contains($nomeNorm, 'neto')) {
                    $score = 1.0;
                }
                if (!isset($resultsById[$id]) || $score > $resultsById[$id]['score']) {
                    $row['type']  = 'INDI';
                    $row['score'] = $score;
                    $resultsById[$id] = $row;
                }
            }
        }

        // 2) LIKE por variantes encurtadas (fallback)
        $variants = [];
        for ($i = count($parts); $i >= 1; $i--) {
            $slice = implode(' ', array_slice($parts, 0, $i));
            if ($slice !== '') {
                $variants[] = $slice;
            }
        }
        $variants = array_values(array_unique(array_filter(array_merge([$query, $qNorm], $variants))));

        foreach ($variants as $v) {
            $sql = "SELECT i.i_id AS id, n.n_full AS nome
                    FROM pgv_name n
                    JOIN pgv_individuals i ON n.n_id = i.i_id
                    WHERE n.n_full LIKE ?";
            $stmt = $this->conn->prepare($sql);
            $like = '%' . $v . '%';
            $stmt->bind_param('s', $like);
            $stmt->execute();
            $result = $stmt->get_result();

            $vTokens = preg_split('/\s+/u', function_exists('normalizarTexto') ? normalizarTexto($v) : strtolower($v), -1, PREG_SPLIT_NO_EMPTY);
            $tokenBonus = min(1.0, count($vTokens) / max(1, count($parts)));

            while ($row = $result->fetch_assoc()) {
                $id = $row['id'];
                $score = $this->calculateScore($query, $row['nome']) * (0.5 + 0.5 * $tokenBonus);
                $nomeNorm = function_exists('normalizarTexto') ? normalizarTexto($row['nome']) : strtolower($row['nome']);
                $nomeNorm = preg_replace('/["\'\`]/u', '', $nomeNorm);
                $allMatch = true;
                foreach ($vTokens as $tok) {
                    if ($tok !== '' && !str_contains($nomeNorm, $tok)) {
                        $allMatch = false;
                        break;
                    }
                }
                if ($allMatch && count($vTokens) >= 2) {
                    $score = max($score, 0.95);
                }
                if (!isset($resultsById[$id]) || $score > $resultsById[$id]['score']) {
                    $row['type']  = 'INDI';
                    $row['score'] = $score;
                    $resultsById[$id] = $row;
                }
            }
        }

        $results = array_values($resultsById);
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $limit);
    }

    /** Busca famílias */
    private function searchFamilies(string $query, int $limit): array
    {
        $sql = "SELECT f_id AS id, f_husb, f_wife FROM pgv_families WHERE f_id LIKE ?";
        $stmt = $this->conn->prepare($sql);
        $like = "%$query%";
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $result = $stmt->get_result();

        $results = [];
        while ($row = $result->fetch_assoc()) {
            $row['type'] = 'FAM';
            $row['name'] = "Família {$row['id']}";
            $row['score'] = $this->calculateScore($query, $row['name']);
            $results[] = $row;
        }

        return $results;
    }

    /** Detalhes de um indivíduo (inclui famílias e filhos) */
    public function getIndividual(string $id): ?array
    {
        $sql = "SELECT i.i_id AS id, n.n_full AS nome
                FROM pgv_individuals i
                JOIN pgv_name n ON i.i_id = n.n_id
                WHERE i.i_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $indi = $result->fetch_assoc();

        if (!$indi) return null;

        $indi['families'] = [];

        // Famílias onde é pai/mãe
        $sql = "SELECT * FROM pgv_families WHERE f_husb = ? OR f_wife = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $id, $id);
        $stmt->execute();
        $resFam = $stmt->get_result();
        while ($fam = $resFam->fetch_assoc()) {
            $fam['husb_name'] = $this->getNomeById($fam['f_husb']);
            $fam['wife_name'] = $this->getNomeById($fam['f_wife']);
            $fam['children'] = $this->resolveChildren($fam);

            $indi['families'][] = $fam;
        }

        // Famílias onde é filho
        $sql = "SELECT f.* FROM pgv_families f
                JOIN pgv_link l ON f.f_id = l.l_to
                WHERE l.l_from = ? AND l.l_type = 'CHIL'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $resFam = $stmt->get_result();
        while ($fam = $resFam->fetch_assoc()) {
            $fam['husb_name'] = $this->getNomeById($fam['f_husb']);
            $fam['wife_name'] = $this->getNomeById($fam['f_wife']);
            $indi['families'][] = $fam;
        }

        return $indi;
    }

    /** Detalhes de uma família (inclui filhos) */
    public function getFamily(string $id): ?array
    {
        $sql = "SELECT * FROM pgv_families WHERE f_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $fam = $result->fetch_assoc();

        if (!$fam) return null;

        $fam['husb_name'] = $this->getNomeById($fam['f_husb']);
        $fam['wife_name'] = $this->getNomeById($fam['f_wife']);
        $fam['children'] = $this->resolveChildren($fam);

        return $fam;
    }

    /**
     * Resolve filhos de uma família PhpGedView.
     * Prioriza f_chil (ex.: "I3;I4;") e completa com pgv_link CHIL.
     */
    private function resolveChildren(array $fam): array
    {
        $byId = [];

        // 1) Campo f_chil do PhpGedView (fonte confiável neste GEDCOM)
        if (!empty($fam['f_chil'])) {
            $ids = preg_split('/[;,\s]+/', trim($fam['f_chil']), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($ids as $cid) {
                $cid = trim($cid);
                if ($cid === '' || isset($byId[$cid])) continue;
                if (!preg_match('/^I\d+/i', $cid)) continue;
                $byId[$cid] = [
                    'id' => $cid,
                    'nome' => $this->getNomeById($cid) ?? $cid,
                ];
            }
        }

        // 2) Links CHIL apontando para a família
        if (!empty($fam['f_id'])) {
            $sqlKids = "SELECT l.l_from AS child_id FROM pgv_link l WHERE l.l_to = ? AND l.l_type = 'CHIL'";
            $stmtKids = $this->conn->prepare($sqlKids);
            $fid = $fam['f_id'];
            $stmtKids->bind_param("s", $fid);
            $stmtKids->execute();
            $resKids = $stmtKids->get_result();
            while ($kid = $resKids->fetch_assoc()) {
                $cid = $kid['child_id'];
                if ($cid === '' || isset($byId[$cid])) continue;
                if (!preg_match('/^I\d+/i', $cid)) continue;
                $byId[$cid] = [
                    'id' => $cid,
                    'nome' => $this->getNomeById($cid) ?? $cid,
                ];
            }
        }

        return array_values($byId);
    }

    /** Score simples para ordenar resultados */
    private function calculateScore(string $query, string $text): float
    {
        $norm = function_exists('normalizarTexto')
            ? 'normalizarTexto'
            : (function_exists('fur_strtolower') ? 'fur_strtolower' : 'strtolower');

        $q = $norm($query);
        $t = $norm($text);

        if ($t !== '' && $q !== '' && str_contains($t, $q)) {
            if (str_starts_with($t, $q)) {
                return 1.0;
            }
            return 0.92;
        }

        $qWords = preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($qWords)) {
            return 0.0;
        }

        $matches = 0;
        foreach ($qWords as $w) {
            if ($w !== '' && str_contains($t, $w)) {
                $matches++;
            }
        }
        return $matches / count($qWords);
    }
} // <--- FIM DA CLASSE SearchServiceDB

/**
 * Busca informações de superlativos (mais velho, mais novo, maior longevidade)
 * FUNÇÃO GLOBAL (fora da classe)
 */
function buscarSuperlativos(mysqli $conn, string $mensagem): array {
    $msgNorm = function_exists('normalizarTexto') ? normalizarTexto($mensagem) : strtolower($mensagem);


    $tipo = null;
    if (preg_match('/\b(mais velho vivo|vivo mais velho|pessoa viva mais velha|ancião vivo)\b/iu', $msgNorm)) {
        $tipo = 'mais_velho_vivo';
    } elseif (preg_match('/\b(mais velho|mais velha|mais antigo|mais antiga|primeiro a nascer|nasceu primeiro)\b/iu', $msgNorm)) {
        $tipo = 'mais_velho';
    } elseif (preg_match('/\b(mais novo|mais nova|mais jovem|mais recente|último a nascer|nasceu por último)\b/iu', $msgNorm)) {
        $tipo = 'mais_novo';
    } elseif (preg_match('/\b(maior idade|viveu mais|longevidade|mais tempo|mais longevo)\b/iu', $msgNorm)) {
        $tipo = 'maior_longevidade';
    }
    if (!$tipo) return [];

    $resultados = [];

    if ($tipo === 'mais_velho_vivo') {
        // Busca pessoas com nascimento, SEM registro de óbito (DEAT), ordenadas pela mais velha
        // Usamos 1900 como limite inferior para evitar retornar pessoas do século 19 que só faltam o registro de óbito no GEDCOM
        $sql = "SELECT 
                    b.d_gid AS id_individuo,
                    n.n_full AS nome,
                    CONCAT_WS(' ', NULLIF(b.d_day, 0), b.d_month, b.d_year) AS data_nascimento,
                    b.d_year AS ano
                FROM pgv_dates b
                INNER JOIN pgv_name n ON n.n_id = b.d_gid
                WHERE b.d_fact = 'BIRT'
                  AND b.d_year IS NOT NULL
                  AND b.d_year BETWEEN 1900 AND YEAR(CURDATE())
                  AND n.n_type = 'NAME'
                  AND NOT EXISTS (
                      SELECT 1 FROM pgv_dates d 
                      WHERE d.d_gid = b.d_gid AND d.d_fact = 'DEAT'
                  )
                ORDER BY b.d_year ASC
                LIMIT 5";
    } elseif ($tipo === 'mais_velho') {
        $sql = "SELECT 
                    d.d_gid AS id_individuo,
                    n.n_full AS nome,
                    CONCAT_WS(' ', NULLIF(d.d_day, 0), d.d_month, d.d_year) AS data_nascimento,
                    d.d_year AS ano
                FROM pgv_dates d
                INNER JOIN pgv_name n ON n.n_id = d.d_gid
                WHERE d.d_fact = 'BIRT'
                  AND d.d_year IS NOT NULL
                  AND d.d_year BETWEEN 1800 AND YEAR(CURDATE())
                  AND n.n_type = 'NAME'
                ORDER BY d.d_year ASC
                LIMIT 5";
    } elseif ($tipo === 'mais_novo') {
        $sql = "SELECT 
                    d.d_gid AS id_individuo,
                    n.n_full AS nome,
                    CONCAT_WS(' ', NULLIF(d.d_day, 0), d.d_month, d.d_year) AS data_nascimento,
                    d.d_year AS ano
                FROM pgv_dates d
                INNER JOIN pgv_name n ON n.n_id = d.d_gid
                WHERE d.d_fact = 'BIRT'
                  AND d.d_year IS NOT NULL
                  AND d.d_year BETWEEN 1800 AND YEAR(CURDATE())
                  AND n.n_type = 'NAME'
                ORDER BY d.d_year DESC
                LIMIT 5";
    } elseif ($tipo === 'maior_longevidade') {
        $sql = "SELECT 
                    n.n_full AS nome,
                    CONCAT_WS(' ', NULLIF(b.d_day, 0), b.d_month, b.d_year) AS nascimento,
                    CONCAT_WS(' ', NULLIF(de.d_day, 0), de.d_month, de.d_year) AS falecimento,
                    b.d_year AS ano_nasc,
                    de.d_year AS ano_falec,
                    (de.d_year - b.d_year) AS idade_aprox
                FROM pgv_dates b
                INNER JOIN pgv_dates de ON de.d_gid = b.d_gid AND de.d_fact = 'DEAT'
                INNER JOIN pgv_name n ON n.n_id = b.d_gid
                WHERE b.d_fact = 'BIRT'
                  AND b.d_year IS NOT NULL
                  AND de.d_year IS NOT NULL
                  AND (de.d_year - b.d_year) > 0
                  AND n.n_type = 'NAME'
                ORDER BY idade_aprox DESC
                LIMIT 5";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Erro ao preparar query de superlativos: " . $conn->error);
        return [];
    }

    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $resultados[] = $row;
    }

    $stmt->close();

    return [
        'tipo' => $tipo,
        'dados' => $resultados
    ];
}
?>
