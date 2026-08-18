<?php
require_once 'db.php';
require_once 'consultas.php';

$service = new SearchServiceDB($conn);

// Busca pelo nome
$result = $service->search("Mariana dos Anjos Lima Furtado", "indi");

if ($result['count'] > 0) {
    foreach ($result['results'] as $r) {
        echo "- ID: {$r['id']} | Nome: {$r['nome']}\n";

        $indi = $service->getIndividual($r['id']);

        if (!empty($indi['families'])) {
            foreach ($indi['families'] as $fam) {
                echo "Família {$fam['f_id']} | Pai: {$fam['husb_name']} | Mãe: {$fam['wife_name']}\n";

                // filhos via pgv_link
                if (!empty($fam['children'])) {
                    echo "  Filhos (via pgv_link):\n";
                    foreach ($fam['children'] as $c) {
                        echo "    - {$c['id']} | {$c['nome']}\n";
                    }
                }

                // filhos via campo f_chil
                if (!empty($fam['f_chil'])) {
                    $ids = explode(';', trim($fam['f_chil'], ';'));
                    if (!empty($ids)) {
                        echo "  Filhos (via f_chil):\n";
                        foreach ($ids as $cid) {
                            $stmt = $conn->prepare("SELECT n_full FROM pgv_name WHERE n_id = ?");
                            $stmt->bind_param("s", $cid);
                            $stmt->execute();
                            $res = $stmt->get_result();
                            $row = $res->fetch_assoc();
                            $nome = $row ? $row['n_full'] : "(sem nome)";
                            echo "    - {$cid} | {$nome}\n";
                        }
                    }
                }
            }
        } else {
            echo "Nenhuma família encontrada para {$indi['nome']}.\n";
        }
    }
} else {
    echo "Nenhum registro encontrado para Mariana dos Anjos Lima Furtado.\n";
}
?>
