<?php
/**
 * Painel Admin leve – Furtadês IA
 * Inspeciona banco GEDCOM (PhpGedView), extração de nomes, intenção e contexto.
 * Protegido pelo mesmo sistema de login do site.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$sessionLifetime = 60 * 60 * 24 * 30;
session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.gc_maxlifetime', $sessionLifetime);
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/consultas.php';
require_once __DIR__ . '/intencao.php';
require_once __DIR__ . '/livros.php';

$usuarioAtual = usuarioLogado();
if (!$usuarioAtual) {
    header('Location: index.php');
    exit;
}

if (function_exists('registrarAtividade')) {
    registrarAtividade();
}

/** Helper: pergunta de parentesco estruturado (espelha a lógica do index) */
function ehPerguntaParentescoEstruturada(string $msg): bool
{
    $n = function_exists('normalizarTexto') ? normalizarTexto($msg) : strtolower($msg);
    $padroes = [
        '/\bfilhos?\b/',
        '/\bpais?\b/',
        '/\bmae\b/',
        '/\bpai\b/',
        '/\birmaos?\b/',
        '/\birma\b/',
        '/\bnetos?\b/',
        '/\bnetas?\b/',
        '/\bdescendentes?\b/',
        '/\bascendentes?\b/',
        '/\bparentesco\b/',
        '/\brelacao\b/',
        '/\bconjuge\b/',
        '/\besposa\b/',
        '/\besposo\b/',
        '/\btio\b/',
        '/\btia\b/',
        '/\bavo\b/',
        '/\bava\b/',
    ];
    foreach ($padroes as $p) {
        if (preg_match($p, $n)) {
            return true;
        }
    }
    return false;
}

$aba = $_GET['aba'] ?? 'busca';
$q = trim($_GET['q'] ?? $_POST['q'] ?? '');
$pid = trim($_GET['pid'] ?? '');

$service = new SearchServiceDB($conn);
$resultadosBusca = null;
$individuo = null;
$debugIntencao = null;
$contextoPreview = null;
$trechosLivros = null;
$erro = null;

try {
    // --- Aba Busca / Detalhe ---
    if ($aba === 'busca' || $aba === 'detalhe') {
        if ($pid !== '') {
            $individuo = $service->getIndividual($pid);
            if (!$individuo) {
                $erro = "Indivíduo não encontrado: " . htmlspecialchars($pid);
            }
        } elseif ($q !== '') {
            $resultadosBusca = $service->search($q, 'indi', 20);
        }
    }

    // --- Aba Teste (intenção + nomes + contexto) ---
    if ($aba === 'teste' && $q !== '') {
        $intencao = detectarIntencao($q);
        $nomes = extrairNomesParaBusca($q);
        $parentesco = ehPerguntaParentescoEstruturada($q);

        $arvoreGeral = [];
        $idsJa = [];
        $achouNoBanco = false;

        foreach ($nomes as $nomeBusca) {
            $res = $service->search($nomeBusca, 'indi', 8);
            if (!$res || $res['count'] === 0) {
                continue;
            }
            foreach (array_slice($res['results'], 0, $parentesco ? 5 : 2) as $hit) {
                $id = $hit['id'];
                if (isset($idsJa[$id])) {
                    continue;
                }
                if ($parentesco && isset($hit['score']) && (float)$hit['score'] < 0.45) {
                    continue;
                }
                $idsJa[$id] = true;
                $indi = $service->getIndividual($id);
                if (!$indi) {
                    continue;
                }

                $bloco = "Indivíduo: {$indi['nome']} [{$indi['id']}]\n";
                foreach ($indi['families'] as $fam) {
                    $linha = "  Família | Pai: " . ($fam['husb_name'] ?? '?') . " | Mãe: " . ($fam['wife_name'] ?? '?');
                    $filhosList = [];
                    if (!empty($fam['children'])) {
                        foreach ($fam['children'] as $c) {
                            $filhosList[] = ($c['nome'] ?? $c['id']) . ' [' . ($c['id'] ?? '') . ']';
                        }
                    }
                    if ($filhosList) {
                        $linha .= "\n  Filhos (" . count($filhosList) . "): " . implode('; ', $filhosList);
                    }
                    $bloco .= $linha . "\n";
                }
                $arvoreGeral[] = $bloco;
                $achouNoBanco = true;
            }
        }

        $contexto = '';
        if ($achouNoBanco) {
            if ($parentesco) {
                $contexto .= "=== FONTE PRINCIPAL (use esta para responder parentesco/filhos/netos/pais) ===\n";
                $contexto .= "Dados genealógicos da família:\n" . implode("\n", $arvoreGeral) . "\n";
                $contexto .= "=== FIM DA FONTE PRINCIPAL ===\n";
                $contexto .= "Instrução: para listar filhos, pais ou parentesco, use EXCLUSIVAMENTE os dados acima. Não invente nomes a partir de livros.\n\n";
            } else {
                $contexto .= "Dados genealógicos da família:\n" . implode("\n", $arvoreGeral) . "\n";
            }
        }

        $usarLivros = !($parentesco && $achouNoBanco);
        $livros = new LivrosFamiliares();
        if ($usarLivros) {
            $trechos = $livros->buscarTrechos($q, 4);
            if ($trechos) {
                $contexto .= $trechos;
            }
            $trechosLivros = $trechos;
        }

        $super = buscarSuperlativos($conn, $q);
        if (!empty($super['dados'])) {
            $contexto = "=== DADOS DE SUPERLATIVOS ===\n" . print_r($super, true) . "\n" . $contexto;
        }

        $debugIntencao = [
            'intencao'   => $intencao,
            'parentesco' => $parentesco,
            'nomes'      => $nomes,
            'achou_banco'=> $achouNoBanco,
            'usar_livros'=> $usarLivros,
            'hits'       => count($idsJa),
        ];
        $contextoPreview = $contexto;
    }

    // --- Aba Stats rápidas ---
    $stats = null;
    if ($aba === 'stats') {
        $stats = [];
        $r = $conn->query("SELECT COUNT(*) AS c FROM pgv_individuals");
        $stats['individuos'] = $r ? (int)$r->fetch_assoc()['c'] : 0;
        $r = $conn->query("SELECT COUNT(*) AS c FROM pgv_families");
        $stats['familias'] = $r ? (int)$r->fetch_assoc()['c'] : 0;
        $r = $conn->query("SELECT COUNT(*) AS c FROM pgv_name WHERE n_type = 'NAME'");
        $stats['nomes'] = $r ? (int)$r->fetch_assoc()['c'] : 0;
        $r = $conn->query("SELECT COUNT(*) AS c FROM pgv_dates WHERE d_fact = 'BIRT'");
        $stats['nascimentos'] = $r ? (int)$r->fetch_assoc()['c'] : 0;
        $r = $conn->query("SELECT COUNT(*) AS c FROM pgv_dates WHERE d_fact = 'DEAT'");
        $stats['obitos'] = $r ? (int)$r->fetch_assoc()['c'] : 0;

        $livrosObj = new LivrosFamiliares();
        $stats['livros'] = $livrosObj->listarLivros();
    }
} catch (Throwable $e) {
    $erro = $e->getMessage();
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin – Furtadês IA</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: #f0f2f5;
            color: #222;
            line-height: 1.45;
        }
        header {
            background: #1a1a2e;
            color: #fff;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        header h1 { margin: 0; font-size: 1.25rem; font-weight: 600; }
        header a { color: #8ecae6; text-decoration: none; margin-left: 12px; font-size: 0.9rem; }
        header a:hover { text-decoration: underline; }
        nav.tabs {
            background: #16213e;
            padding: 0 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
        }
        nav.tabs a {
            color: #bbb;
            text-decoration: none;
            padding: 10px 16px;
            font-size: 0.9rem;
            border-bottom: 3px solid transparent;
        }
        nav.tabs a.active {
            color: #fff;
            border-bottom-color: #4cc9f0;
            font-weight: 600;
        }
        nav.tabs a:hover { color: #fff; }
        main { max-width: 960px; margin: 20px auto; padding: 0 16px 40px; }
        .card {
            background: #fff;
            border-radius: 10px;
            padding: 18px 20px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        .card h2 { margin: 0 0 12px; font-size: 1.1rem; color: #1a1a2e; }
        form.inline { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        input[type="text"] {
            flex: 1;
            min-width: 180px;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
        }
        button, .btn {
            background: #4361ee;
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-block;
        }
        button:hover, .btn:hover { background: #3a56d4; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.92rem;
        }
        th, td {
            text-align: left;
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
        }
        th { background: #f8f9fa; font-weight: 600; }
        tr:hover td { background: #f8fafc; }
        .score {
            font-variant-numeric: tabular-nums;
            font-weight: 600;
        }
        .score-high { color: #2d6a4f; }
        .score-mid { color: #b08900; }
        .score-low { color: #9b2226; }
        pre.ctx {
            background: #1e1e2e;
            color: #cdd6f4;
            padding: 14px;
            border-radius: 8px;
            overflow-x: auto;
            font-size: 0.82rem;
            white-space: pre-wrap;
            word-break: break-word;
            max-height: 480px;
            overflow-y: auto;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-ok { background: #d8f3dc; color: #1b4332; }
        .badge-warn { background: #fff3cd; color: #856404; }
        .badge-off { background: #e9ecef; color: #495057; }
        .meta { color: #666; font-size: 0.85rem; margin-bottom: 8px; }
        .erro {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .fam-block {
            border-left: 3px solid #4361ee;
            padding: 8px 12px;
            margin: 10px 0;
            background: #f8f9ff;
            border-radius: 0 6px 6px 0;
        }
        .grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }
        .stat {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 14px;
            text-align: center;
        }
        .stat .n { font-size: 1.6rem; font-weight: 700; color: #4361ee; }
        .stat .l { font-size: 0.8rem; color: #666; margin-top: 4px; }
        ul.kids { margin: 4px 0 0; padding-left: 18px; }
        @media (max-width: 600px) {
            header { flex-direction: column; align-items: flex-start; }
            main { margin-top: 12px; }
        }
    </style>
</head>
<body>
<header>
    <h1>Admin · Furtadês IA</h1>
    <div>
        <span style="opacity:.8;font-size:.9rem"><?= h($usuarioAtual) ?></span>
        <a href="index.php">← Chat</a>
        <a href="perguntas.php">Log de perguntas</a>
        <a href="index.php?sair=1">Sair</a>
    </div>
</header>

<nav class="tabs">
    <a href="?aba=busca" class="<?= $aba === 'busca' || $aba === 'detalhe' ? 'active' : '' ?>">Busca GEDCOM</a>
    <a href="?aba=teste" class="<?= $aba === 'teste' ? 'active' : '' ?>">Testar pergunta</a>
    <a href="?aba=stats" class="<?= $aba === 'stats' ? 'active' : '' ?>">Estatísticas</a>
</nav>

<main>
<?php if ($erro): ?>
    <div class="erro"><?= h($erro) ?></div>
<?php endif; ?>

<?php if ($aba === 'busca' || $aba === 'detalhe'): ?>
    <div class="card">
        <h2>Buscar indivíduo no banco (pgv_*)</h2>
        <form class="inline" method="get" action="">
            <input type="hidden" name="aba" value="busca">
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Nome (ex: mariana, pio furtado, zeca…)" autofocus>
            <button type="submit">Buscar</button>
        </form>
        <p class="meta">Usa o mesmo <code>SearchServiceDB</code> do chat. Clique no ID para ver famílias e filhos.</p>
    </div>

    <?php if ($resultadosBusca !== null): ?>
    <div class="card">
        <h2>Resultados (<?= (int)$resultadosBusca['count'] ?>)</h2>
        <?php if (empty($resultadosBusca['results'])): ?>
            <p>Nenhum indivíduo encontrado para “<?= h($q) ?>”.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Score</th>
                    <th>ID</th>
                    <th>Nome</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($resultadosBusca['results'] as $r):
                $sc = (float)($r['score'] ?? 0);
                $cls = $sc >= 0.9 ? 'score-high' : ($sc >= 0.5 ? 'score-mid' : 'score-low');
            ?>
                <tr>
                    <td class="score <?= $cls ?>"><?= number_format($sc, 3) ?></td>
                    <td><code><?= h($r['id']) ?></code></td>
                    <td><?= h($r['nome'] ?? $r['name'] ?? '') ?></td>
                    <td><a class="btn" style="padding:4px 10px;font-size:.8rem" href="?aba=detalhe&amp;pid=<?= urlencode($r['id']) ?>">Detalhes</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($individuo): ?>
    <div class="card">
        <h2><?= h($individuo['nome']) ?></h2>
        <p class="meta">ID: <code><?= h($individuo['id']) ?></code></p>

        <?php if (empty($individuo['families'])): ?>
            <p>Nenhuma família vinculada neste registro.</p>
        <?php else: ?>
            <?php foreach ($individuo['families'] as $i => $fam): ?>
            <div class="fam-block">
                <strong>Família <?= h($fam['f_id'] ?? ('#' . ($i + 1))) ?></strong><br>
                Pai: <?= h($fam['husb_name'] ?? $fam['f_husb'] ?? '?') ?>
                <?php if (!empty($fam['f_husb'])): ?>
                    (<a href="?aba=detalhe&amp;pid=<?= urlencode($fam['f_husb']) ?>"><?= h($fam['f_husb']) ?></a>)
                <?php endif; ?>
                <br>
                Mãe: <?= h($fam['wife_name'] ?? $fam['f_wife'] ?? '?') ?>
                <?php if (!empty($fam['f_wife'])): ?>
                    (<a href="?aba=detalhe&amp;pid=<?= urlencode($fam['f_wife']) ?>"><?= h($fam['f_wife']) ?></a>)
                <?php endif; ?>

                <?php
                $kids = $fam['children'] ?? [];
                if (!empty($fam['f_chil']) && empty($kids)) {
                    echo '<p class="meta">f_chil bruto: <code>' . h($fam['f_chil']) . '</code></p>';
                }
                ?>
                <?php if ($kids): ?>
                    <div style="margin-top:6px"><strong>Filhos (<?= count($kids) ?>):</strong>
                    <ul class="kids">
                    <?php foreach ($kids as $c): ?>
                        <li>
                            <a href="?aba=detalhe&amp;pid=<?= urlencode($c['id']) ?>"><?= h($c['nome'] ?? $c['id']) ?></a>
                            <code style="opacity:.7"><?= h($c['id']) ?></code>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                    </div>
                <?php else: ?>
                    <p class="meta" style="margin-top:6px">Sem filhos resolvidos neste registro.</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <p style="margin-top:14px">
            <a class="btn btn-secondary" href="?aba=busca&amp;q=<?= urlencode($individuo['nome'] ?? '') ?>">← Voltar à busca</a>
        </p>
    </div>
    <?php endif; ?>

<?php elseif ($aba === 'teste'): ?>
    <div class="card">
        <h2>Simular pergunta do usuário</h2>
        <form class="inline" method="get" action="">
            <input type="hidden" name="aba" value="teste">
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Ex: quem são os filhos de Mariana?" autofocus>
            <button type="submit">Analisar</button>
        </form>
        <p class="meta">Mostra intenção, nomes extraídos, hits no banco e o contexto que seria enviado à Groq (sem chamar a API).</p>
    </div>

    <?php if ($debugIntencao !== null): ?>
    <div class="card">
        <h2>Diagnóstico</h2>
        <p>
            Intenção:
            <span class="badge <?= $debugIntencao['intencao'] === 'genealogia' ? 'badge-ok' : 'badge-off' ?>">
                <?= h($debugIntencao['intencao']) ?>
            </span>
            &nbsp; Parentesco estruturado:
            <span class="badge <?= $debugIntencao['parentesco'] ? 'badge-ok' : 'badge-off' ?>">
                <?= $debugIntencao['parentesco'] ? 'sim' : 'não' ?>
            </span>
            &nbsp; Achou no banco:
            <span class="badge <?= $debugIntencao['achou_banco'] ? 'badge-ok' : 'badge-warn' ?>">
                <?= $debugIntencao['achou_banco'] ? 'sim' : 'não' ?>
            </span>
            &nbsp; Inclui livros:
            <span class="badge <?= $debugIntencao['usar_livros'] ? 'badge-warn' : 'badge-ok' ?>">
                <?= $debugIntencao['usar_livros'] ? 'sim' : 'não (banco prioritário)' ?>
            </span>
        </p>
        <p><strong>Nomes extraídos:</strong>
            <?php if (empty($debugIntencao['nomes'])): ?>
                <em>(nenhum)</em>
            <?php else: ?>
                <?= h(implode(', ', $debugIntencao['nomes'])) ?>
            <?php endif; ?>
        </p>
        <p class="meta">Hits de indivíduos incluídos no contexto: <?= (int)$debugIntencao['hits'] ?></p>
    </div>

    <div class="card">
        <h2>Contexto que iria para a IA</h2>
        <?php if (trim((string)$contextoPreview) === ''): ?>
            <p><em>(vazio – a IA responderia só com o system prompt)</em></p>
        <?php else: ?>
            <pre class="ctx"><?= h($contextoPreview) ?></pre>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php elseif ($aba === 'stats'): ?>
    <div class="card">
        <h2>Estatísticas do banco</h2>
        <?php if ($stats): ?>
        <div class="grid-stats">
            <div class="stat"><div class="n"><?= $stats['individuos'] ?></div><div class="l">Indivíduos</div></div>
            <div class="stat"><div class="n"><?= $stats['familias'] ?></div><div class="l">Famílias</div></div>
            <div class="stat"><div class="n"><?= $stats['nomes'] ?></div><div class="l">Nomes (NAME)</div></div>
            <div class="stat"><div class="n"><?= $stats['nascimentos'] ?></div><div class="l">Nascimentos</div></div>
            <div class="stat"><div class="n"><?= $stats['obitos'] ?></div><div class="l">Óbitos</div></div>
        </div>
        <p style="margin-top:16px"><strong>Livros carregados:</strong>
            <?= $stats['livros'] ? h(implode(', ', $stats['livros'])) : '<em>nenhum</em>' ?>
        </p>
        <?php endif; ?>
    </div>
<?php endif; ?>
</main>
</body>
</html>

