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

    /** Busca indivíduos */
    private function searchIndividuals(string $query, int $limit): array
    {
        $sql = "SELECT i.i_id AS id, n.n_full AS nome
                FROM pgv_name n
                JOIN pgv_individuals i ON n.n_id = i.i_id
                WHERE n.n_full LIKE ?";
        $stmt = $this->conn->prepare($sql);
        $like = "%$query%";
        $stmt->bind_param("s", $like);
        $stmt->execute();
        $result = $stmt->get_result();

        $results = [];
        while ($row = $result->fetch_assoc()) {
            $row['type'] = 'INDI';
            $row['score'] = $this->calculateScore($query, $row['nome']);
            $results[] = $row;
        }

        return $results;
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
            $fam['children'] = [];

            $sqlKids = "SELECT l.l_from AS child_id FROM pgv_link l WHERE l.l_to = ? AND l.l_type = 'CHIL'";
            $stmtKids = $this->conn->prepare($sqlKids);
            $stmtKids->bind_param("s", $fam['f_id']);
            $stmtKids->execute();
            $resKids = $stmtKids->get_result();
            while ($kid = $resKids->fetch_assoc()) {
                $fam['children'][] = [
                    'id' => $kid['child_id'],
                    'nome' => $this->getNomeById($kid['child_id'])
                ];
            }

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
        $fam['children'] = [];

        $sqlKids = "SELECT l.l_from AS child_id FROM pgv_link l WHERE l.l_to = ? AND l.l_type = 'CHIL'";
        $stmt = $this->conn->prepare($sqlKids);
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $resKids = $stmt->get_result();
        while ($kid = $resKids->fetch_assoc()) {
            $fam['children'][] = [
                'id' => $kid['child_id'],
                'nome' => $this->getNomeById($kid['child_id'])
            ];
        }

        return $fam;
    }

    /** Score simples para ordenar resultados */
    private function calculateScore(string $query, string $text): float
    {
        $query = strtolower($query);
        $text  = strtolower($text);

        if ($text !== '' && str_contains($text, $query)) {
            return 1.0;
        }

        $qWords = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        $matches = 0;
        foreach ($qWords as $w) {
            if ($w !== '' && str_contains($text, $w)) {
                $matches++;
            }
        }
        return count($qWords) > 0 ? $matches / count($qWords) : 0.0;
    }
}
?>
