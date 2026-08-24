<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'db.php';
require_once 'consultas.php';
require_once 'intencao.php';
require_once 'ia.php';
require_once 'livros.php';

if (isset($_GET["novo"])) unset($_SESSION["chat"]);
if (isset($_GET["limpar"])) $_SESSION["chat"] = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST["mensagem"])) {
    $mensagem = trim($_POST["mensagem"]);
    $turn_id = uniqid('turn_', true);

    $intencao = detectarIntencao($mensagem);
    $perguntaParentesco = function_exists('ehPerguntaParentescoEstruturada')
        ? ehPerguntaParentescoEstruturada($mensagem)
        : false;
    $contexto = "";
    $achouNoBanco = false;

    // 1. Busca no banco genealógico
    if ($intencao === "genealogia" || $perguntaParentesco) {
        $service = new SearchServiceDB($conn);
        $nomesParaBuscar = extrairNomesParaBusca($mensagem);

        $arvoreGeral = [];
        $idsJaIncluidos = [];
        $totalFilhosEncontrados = 0;
        $totalNetosEncontrados = 0;
        $msgNorm = function_exists('normalizarTexto') ? normalizarTexto($mensagem) : strtolower($mensagem);
        $perguntaFilhos = $perguntaParentesco && preg_match('/\bfilhos?\b/u', $msgNorm);
        $perguntaNetos  = $perguntaParentesco && preg_match('/\b(netos?|netas?|bisnetos?|descendentes?)\b/u', $msgNorm);

        foreach ($nomesParaBuscar as $nomeBusca) {
            $result = $service->search($nomeBusca, "indi", 8);
            if (!$result || $result['count'] === 0) continue;

            $limiteHits = $perguntaParentesco ? 5 : 2;
            foreach (array_slice($result['results'], 0, $limiteHits) as $hit) {
                $pid = $hit["id"];
                if (isset($idsJaIncluidos[$pid])) continue;
                if ($perguntaParentesco && isset($hit['score']) && (float)$hit['score'] < 0.45) continue;
                $idsJaIncluidos[$pid] = true;

                $indi = $service->getIndividual($pid);
                if (!$indi) continue;

                $bloco = "Indivíduo: {$indi['nome']}\n";
                $filhosDesteIndi = 0;
                $filhosIds = [];

                foreach ($indi['families'] as $fam) {
                    $linha = "  Família | Pai: " . ($fam['husb_name'] ?? '?') . " | Mãe: " . ($fam['wife_name'] ?? '?');
                    $filhosList = [];
                    if (!empty($fam['children'])) {
                        foreach ($fam['children'] as $c) {
                            $cid = $c['id'] ?? null;
                            $cnome = $c['nome'] ?? $cid;
                            $filhosList[] = $cnome;
                            if ($cid) $filhosIds[$cid] = $cnome;
                        }
                    }
                    if (!empty($fam['f_chil'])) {
                        $ids = preg_split('/[;,\s]+/', trim($fam['f_chil']), -1, PREG_SPLIT_NO_EMPTY);
                        foreach ($ids as $cid) {
                            $cid = trim($cid);
                            if ($cid === '' || !preg_match('/^I\d+/i', $cid)) continue;
                            $stmt = $conn->prepare("SELECT n_full FROM pgv_name WHERE n_id = ?");
                            $stmt->bind_param("s", $cid);
                            $stmt->execute();
                            $res = $stmt->get_result();
                            $row = $res->fetch_assoc();
                            $cnome = $row ? $row['n_full'] : $cid;
                            $filhosList[] = $cnome;
                            $filhosIds[$cid] = $cnome;
                        }
                    }
                    if ($filhosList) {
                        $filhosList = array_values(array_unique($filhosList));
                        $filhosDesteIndi += count($filhosList);
                        $linha .= "\n  Filhos (" . count($filhosList) . "): " . implode("; ", $filhosList);
                    }
                    $bloco .= $linha . "\n";
                }

                if ($perguntaNetos && !empty($filhosIds)) {
                    $netosLista = [];
                    foreach ($filhosIds as $filhoId => $filhoNome) {
                        $filhoIndi = $service->getIndividual($filhoId);
                        if (!$filhoIndi) continue;
                        $netosDesteFilho = [];
                        foreach ($filhoIndi['families'] as $famF) {
                            if (!empty($famF['children'])) {
                                foreach ($famF['children'] as $c) $netosDesteFilho[] = $c['nome'] ?? $c['id'];
                            }
                            if (!empty($famF['f_chil'])) {
                                $ids = preg_split('/[;,\s]+/', trim($famF['f_chil']), -1, PREG_SPLIT_NO_EMPTY);
                                foreach ($ids as $cid) {
                                    $cid = trim($cid);
                                    if ($cid === '' || !preg_match('/^I\d+/i', $cid)) continue;
                                    $stmt = $conn->prepare("SELECT n_full FROM pgv_name WHERE n_id = ?");
                                    $stmt->bind_param("s", $cid);
                                    $stmt->execute();
                                    $res = $stmt->get_result();
                                    $row = $res->fetch_assoc();
                                    $netosDesteFilho[] = $row ? $row['n_full'] : $cid;
                                }
                            }
                        }
                        $netosDesteFilho = array_values(array_unique(array_filter($netosDesteFilho)));
                        if ($netosDesteFilho) {
                            $totalNetosEncontrados += count($netosDesteFilho);
                            $netosLista[] = "  Netos via {$filhoNome} (" . count($netosDesteFilho) . "): " . implode("; ", $netosDesteFilho);
                        } else {
                            $netosLista[] = "  Netos via {$filhoNome}: (nenhum cadastrado)";
                        }
                    }
                    if ($netosLista) {
                        $bloco .= "  --- Netos de {$indi['nome']} ---\n" . implode("\n", $netosLista) . "\n";
                    }
                }

                if ($perguntaFilhos && $filhosDesteIndi === 0) {
                    $arvoreGeral[] = $bloco . "  (sem filhos cadastrados neste registro)\n";
                    continue;
                }
                if ($perguntaNetos && $totalNetosEncontrados === 0 && $filhosDesteIndi === 0) {
                    $arvoreGeral[] = $bloco . "  (sem netos cadastrados neste registro)\n";
                    continue;
                }

                $totalFilhosEncontrados += $filhosDesteIndi;
                $achouNoBanco = true;
                $arvoreGeral[] = $bloco;
            }
        }

        if ($perguntaFilhos && $totalFilhosEncontrados === 0) $achouNoBanco = false;
        if ($perguntaNetos && $totalNetosEncontrados === 0) $achouNoBanco = false;

        if ($achouNoBanco) {
            if ($perguntaParentesco) {
                $contexto .= "=== FONTE PRINCIPAL (use esta para responder parentesco/filhos/netos/pais) ===\n";
                $contexto .= "Dados genealógicos da família:\n" . implode("\n", $arvoreGeral) . "\n";
                $contexto .= "=== FIM DA FONTE PRINCIPAL ===\n";
                if ($perguntaNetos) {
                    $contexto .= "Instrução: liste os NETOS (filhos dos filhos). Não confunda netos com filhos.\n\n";
                } else {
                    $contexto .= "Instrução: para listar filhos, pais ou parentesco, use EXCLUSIVAMENTE os dados acima. Não invente nomes a partir de livros.\n\n";
                }
            } else {
                $contexto .= "Dados genealógicos da família:\n" . implode("\n", $arvoreGeral) . "\n";
            }
        } elseif (!empty($arvoreGeral)) {
            $contexto .= "Dados genealógicos parciais:\n" . implode("\n", $arvoreGeral) . "\n";
        }

        if (!$achouNoBanco && !empty($nomesParaBuscar)) {
            $logLine = date('c') . " | parentesco=" . ($perguntaParentesco ? '1' : '0')
                . " | filhos_bd=" . $totalFilhosEncontrados
                . " | netos_bd=" . $totalNetosEncontrados
                . " | nomes=" . implode(',', $nomesParaBuscar)
                . " | msg=" . substr($mensagem, 0, 120) . "\n";
            if (@file_put_contents(__DIR__ . '/search_log.txt', $logLine, FILE_APPEND | LOCK_EX) === false) {
                @file_put_contents('/tmp/furtades_search_log.txt', $logLine, FILE_APPEND | LOCK_EX);
            }
        }
    }

    // 2. Fatos-chave + trechos dos livros + SUPERLATIVOS
    $livros = new LivrosFamiliares();
    $usarLivrosEFatos = !($perguntaParentesco && $achouNoBanco);

    // NOVO: Busca superlativos diretamente do banco (mais velho, mais jovem, etc.)
    $superlativos = buscarSuperlativos($conn, $mensagem);
    if (!empty($superlativos['dados'])) {
        $contexto .= "=== DADOS DE SUPERLATIVOS (fonte: banco de dados genealógico) ===\n";
     

        if ($superlativos['tipo'] === 'mais_velho_vivo') {
            $contexto .= "Pessoas vivas mais velhas (nascimento registrado, sem registro de óbito):\n";
            foreach ($superlativos['dados'] as $pessoa) {
                $contexto .= "- {$pessoa['nome']}: nasceu em {$pessoa['data_nascimento']} (ano: {$pessoa['ano']})\n";
            }
        }    
        else if ($superlativos['tipo'] === 'mais_velho') {
            $contexto .= "Pessoas mais velhas (menor ano de nascimento):\n";
            foreach ($superlativos['dados'] as $pessoa) {
                $contexto .= "- {$pessoa['nome']}: nasceu em {$pessoa['data_nascimento']} (ano: {$pessoa['ano']})\n";
            }
        } elseif ($superlativos['tipo'] === 'mais_novo') {
            $contexto .= "Pessoas mais novas/jovens (maior ano de nascimento):\n";
            foreach ($superlativos['dados'] as $pessoa) {
                $contexto .= "- {$pessoa['nome']}: nasceu em {$pessoa['data_nascimento']} (ano: {$pessoa['ano']})\n";
            }
        } elseif ($superlativos['tipo'] === 'maior_longevidade') {
            $contexto .= "Pessoas com maior longevidade:\n";
            foreach ($superlativos['dados'] as $pessoa) {
                $contexto .= "- {$pessoa['nome']}: nasceu em {$pessoa['nascimento']}, faleceu em {$pessoa['falecimento']} (viveu aproximadamente {$pessoa['idade_aprox']} anos)\n";
            }
        }
        $contexto .= "=== FIM DOS DADOS DE SUPERLATIVOS ===\n\n";
    }

    if ($usarLivrosEFatos) {
        $fatosPath = __DIR__ . "/knowledge/fatos_chave.md";
        if (file_exists($fatosPath)) {
            $fatos = file_get_contents($fatosPath);
            $incluirFatos = ($intencao === "genealogia" || $perguntaParentesco);
            if (!$incluirFatos) {
                $msgNorm = function_exists('normalizarTexto') ? normalizarTexto($mensagem) : strtolower($mensagem);
                $nomesChave = ["mariana", "pio", "zeca", "furtado", "carminha", "dyleli", "j.m", "tio zeca", "xandico", "filica", "joao lima", "joão lima"];
                foreach ($nomesChave as $n) {
                    $nNorm = function_exists('normalizarTexto') ? normalizarTexto($n) : strtolower($n);
                    if (strpos($msgNorm, $nNorm) !== false) {
                        $incluirFatos = true;
                        break;
                    }
                }
            }
            if ($incluirFatos) {
                $contexto .= "Fatos-chave da família (prioridade alta):\n" . $fatos . "\n\n";
            }
        }
        $trechos = $livros->buscarTrechos($mensagem, 4);
        if (!empty($trechos)) {
            $contexto .= $trechos;
        }
    }

    // 3. Chama a Groq
    $historico = $_SESSION["chat"] ?? [];
    $resposta = chamarGroq($mensagem, $contexto, $historico);

    if (isset($_GET["debug"])) {
        $resposta = "=== DEBUG – Contexto enviado à Groq ===\n\n" 
                  . (empty(trim($contexto)) ? "(vazio)" : $contexto)
                  . "\n\n=== Resposta da IA ===\n\n" . $resposta;
    }

    // --- LOG: ID único e primeira linha da resposta ---
    $linhasResposta = explode("\n", trim($resposta));
    $primeira_linha = trim($linhasResposta[0] ?? 'Sem resposta');
    if (strlen($primeira_linha) > 120) {
        $primeira_linha = substr($primeira_linha, 0, 117) . '...';
    }

    $logPergunta = str_replace(["\r", "\n", "|"], " ", $mensagem);
    $logResposta = str_replace(["\r", "\n", "|"], " ", $primeira_linha);
    
    $logLine = date('Y-m-d H:i:s') . " | ID: $turn_id | Pergunta: $logPergunta | Resposta (1ª linha): $logResposta | Feedback: pendente\n";
    if (@file_put_contents(__DIR__ . '/perguntas_log.txt', $logLine, FILE_APPEND | LOCK_EX) === false) {
        @file_put_contents('/tmp/furtades_perguntas_log.txt', $logLine, FILE_APPEND | LOCK_EX);
    }

    if (!isset($_SESSION["chat"])) $_SESSION["chat"] = [];
    $_SESSION["chat"][] = [
        "user" => $mensagem, 
        "ia" => $resposta,
        "turn_id" => $turn_id,
        "feedback" => null
    ];
}

// Prepara dados para a view (lista de PDFs)
$pdfDir = __DIR__ . '/pdf';
$pdfs = [];
if (is_dir($pdfDir)) {
    foreach (scandir($pdfDir) as $arq) {
        if (preg_match('/\.pdf$/i', $arq)) {
            $pdfs[] = $arq;
        }
    }
    sort($pdfs, SORT_NATURAL | SORT_FLAG_CASE);
}

// Inclui a view
require_once 'view.php';
?>
