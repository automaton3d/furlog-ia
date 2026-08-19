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
    $intencao = detectarIntencao($mensagem);
    $contexto = "";

    // 1. Busca no banco genealógico (quando a intenção for genealógica)
    if ($intencao === "genealogia") {
        $service = new SearchServiceDB($conn);
        $result = $service->search($mensagem, "indi");

        if ($result && $result['count'] > 0) {
            $pid = $result['results'][0]["id"];
            $indi = $service->getIndividual($pid);

            $arvore = [];
            foreach ($indi['families'] as $fam) {
                $linha = "Família {$fam['f_id']} | Pai: {$fam['husb_name']} | Mãe: {$fam['wife_name']}";
                if (!empty($fam['children'])) {
                    $linha .= "\n  Filhos (via pgv_link):";
                    foreach ($fam['children'] as $c) {
                        $linha .= "\n    - {$c['id']} | {$c['nome']}";
                    }
                }
                if (!empty($fam['f_chil'])) {
                    $ids = explode(';', trim($fam['f_chil'], ';'));
                    if (!empty($ids)) {
                        $linha .= "\n  Filhos (via f_chil):";
                        foreach ($ids as $cid) {
                            $stmt = $conn->prepare("SELECT n_full FROM pgv_name WHERE n_id = ?");
                            $stmt->bind_param("s", $cid);
                            $stmt->execute();
                            $res = $stmt->get_result();
                            $row = $res->fetch_assoc();
                            $nome = $row ? $row['n_full'] : "(sem nome)";
                            $linha .= "\n    - {$cid} | {$nome}";
                        }
                    }
                }
                $arvore[] = $linha;
            }

            $contexto .= "Dados genealógicos encontrados:\n" . implode("\n", $arvore) . "\n\n";
        } else {
            $contexto .= "Não encontrei registros genealógicos estruturados para essa busca.\n\n";
        }
    }

    // 2. Fatos-chave + trechos dos livros
    $livros = new LivrosFamiliares();
    $fatosPath = __DIR__ . "/knowledge/fatos_chave.md";
    if (file_exists($fatosPath)) {
        $fatos = file_get_contents($fatosPath);
        // Só inclui se a pergunta mencionar nomes da família
        $msgLower = strtolower($mensagem);
        $nomesChave = ["mariana", "pio", "zeca", "furtado", "carminha", "dyleli", "j.m", "tio zeca"];
        foreach ($nomesChave as $n) {
            if (strpos($msgLower, $n) !== false) {
                $contexto .= "Fatos-chave da família (use como base):
" . $fatos . "

";
                break;
            }
        }
    }
    $trechos = $livros->buscarTrechos($mensagem, 4);
    if (!empty($trechos)) {
        $contexto .= $trechos;
    }

    // 3. Chama a Groq com histórico + contexto enriquecido
    $historico = $_SESSION["chat"] ?? [];
    $resposta = chamarGroq($mensagem, $contexto, $historico);

    // Modo debug: acrescente ?debug=1 na URL para ver o contexto enviado
    if (isset($_GET["debug"])) {
        $resposta = "=== DEBUG – Contexto enviado à Groq ===\n\n" 
                  . (empty(trim($contexto)) ? "(vazio)" : $contexto)
                  . "\n\n=== Resposta da IA ===\n\n" . $resposta;
    }

    if (!isset($_SESSION["chat"])) $_SESSION["chat"] = [];
    $_SESSION["chat"][] = ["user" => $mensagem, "ia" => $resposta];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>Furtadês Chat</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: #f2f2f2;
            display: flex;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
        }
        .sidebar {
            width: 200px;
            min-width: 200px;
            background: #dcdcdc;
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }
        .logo h1 { font-size: 1.8em; margin: 0; line-height: 1.2; }
        .m { color:#34A853; } .o2 { color:#FBBC05; } .g { color:#4285F4; }
        .o { color:#EA4335; } .g2 { color:#34A853; } .l { color:#000; }
        .sidebar a {
            margin-top: 20px;
            text-decoration: none;
            color: #4285F4;
            font-weight: bold;
            padding: 10px 0;
        }
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 0;
            overflow: hidden;
        }
        .chat-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 800px;
            min-height: 0;
        }
        .chat-history {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            background: #fff;
            border-radius: 8px;
            -webkit-overflow-scrolling: touch;
        }
        .mensagem { margin: 10px 0; display: flex; }
        .bubble-user {
            background: #e0e0e0;
            padding: 10px 14px;
            border-radius: 16px 16px 4px 16px;
            max-width: 85%;
            margin-left: auto;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .bubble-ia {
            background: #d4f8d4;
            padding: 10px 14px;
            border-radius: 16px 16px 16px 4px;
            max-width: 85%;
            margin-right: auto;
            white-space: pre-line;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .chat-input {
            padding: 10px;
            background: #eee;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-shrink: 0;
        }
        .chat-input input {
            flex: 1;
            padding: 12px 14px;
            border-radius: 24px;
            border: 1px solid #ccc;
            font-size: 16px; /* evita zoom no iOS */
            min-width: 0;
        }
        .chat-input button {
            padding: 12px 16px;
            border: none;
            background: #4285F4;
            color: #fff;
            border-radius: 24px;
            cursor: pointer;
            font-size: 15px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .clear-btn {
            padding: 12px 14px;
            border: none;
            background: #EA4335;
            color: #fff;
            border-radius: 24px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .center-page {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            padding: 20px;
        }
        .search-box { text-align: center; width: 100%; max-width: 400px; }
        .search-box input {
            padding: 14px 18px;
            width: 100%;
            max-width: 100%;
            border-radius: 24px;
            border: 1px solid #ccc;
            font-size: 16px;
            box-sizing: border-box;
        }
        .search-box button {
            margin-top: 12px;
            padding: 12px 28px;
            border: none;
            background: #4285F4;
            color: #fff;
            border-radius: 24px;
            cursor: pointer;
            font-size: 16px;
        }
        .intro { font-size: 1.15em; margin-bottom: 20px; line-height: 1.4; }

        /* ========== MOBILE ========== */
        @media (max-width: 700px) {
            body { flex-direction: column; }
            .sidebar {
                width: 100%;
                min-width: 0;
                padding: 10px 16px;
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
            .logo h1 { font-size: 1.4em; }
            .sidebar a {
                margin-top: 0;
                padding: 8px 12px;
                background: #fff;
                border-radius: 20px;
                font-size: 0.9em;
            }
            .chat-area { width: 100%; }
            .chat-wrapper { max-width: 100%; border-radius: 0; }
            .chat-history {
                border-radius: 0;
                padding: 12px;
            }
            .bubble-user, .bubble-ia { max-width: 90%; font-size: 0.95em; }
            .chat-input {
                padding: 8px 10px;
                padding-bottom: max(8px, env(safe-area-inset-bottom));
            }
            .chat-input input { padding: 11px 14px; }
            .chat-input button, .clear-btn { padding: 11px 14px; }
            .search-box input { width: 100%; }
            .intro { font-size: 1.05em; padding: 0 8px; }
        }

        @media (max-width: 400px) {
            .clear-btn { display: none; } /* esconde "Limpar" em telas muito pequenas */
            .logo h1 { font-size: 1.2em; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h1>
                <span class="m">F</span><span class="o2">u</span><span class="g">r</span>
                <span class="o">t</span><span class="o2">a</span><span class="g2">d</span>
                <span class="l">ê</span><span class="g">s</span>
            </h1>
        </div>
        <a href="?novo=1">➕ Novo Chat</a>
    </div>

    <div class="chat-area">
        <div class="chat-wrapper">
        <?php if (empty($_SESSION["chat"])): ?>
            <div class="center-page">
                <div class="search-box">
                    <div class="intro">Olá! No que podemos nos aprofundar sobre a família Furtado hoje?</div>
                    <form method="post">
                        <input type="text" name="mensagem" placeholder="Digite sua pergunta..." required />
                        <br>
                        <button type="submit">Pesquisar</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="chat-history" id="chat-history">
                <?php foreach ($_SESSION["chat"] as $linha): ?>
                    <div class="mensagem"><div class="bubble-user"><?= htmlspecialchars($linha["user"]) ?></div></div>
                    <div class="mensagem"><div class="bubble-ia"><?= nl2br(htmlspecialchars($linha["ia"])) ?></div></div>
                <?php endforeach; ?>
            </div>
            <div class="chat-input">
                <form method="post" style="display:flex; width:100%;">
                    <input type="text" name="mensagem" placeholder="Digite sua mensagem..." required />
                    <button type="submit">Enviar</button>
                    <a href="?limpar=1" class="clear-btn">Limpar</a>
                </form>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <script>
        const chatHistory = document.getElementById('chat-history');
        if (chatHistory) chatHistory.scrollTop = chatHistory.scrollHeight;
    </script>
</body>
</html>
