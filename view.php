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
            width: 215px;
            min-width: 215px;
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
            margin-top: 10px;
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
        .mensagem { margin: 10px 0; display: flex; flex-direction: column; }
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
            font-size: 16px;
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

        /* --- BOTÕES DE AÇÃO (estilo ghost/monocromático) --- */
        .feedback-actions {
            margin-top: 6px;
            display: flex;
            gap: 4px;
            align-items: center;
            flex-wrap: wrap;
            opacity: 0.55;
            transition: opacity 0.2s;
        }
        .mensagem:hover .feedback-actions { opacity: 1; }
        .feedback-btn {
            background: transparent;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            padding: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #555;
            transition: all 0.15s ease;
        }
        .feedback-btn svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
            display: block;
        }
        .feedback-btn:hover { background-color: #f0f0f0; color: #111; }
        .feedback-btn.active { color: #0f5132; background-color: #e6f4ea; }
        .feedback-btn.active-down { color: #842029; background-color: #f8d7da; }
        .feedback-btn.btn-copy.copiado { color: #0f5132; background-color: #e6f4ea; }
        .feedback-status {
            font-size: 0.8rem;
            color: #0f5132;
            font-style: italic;
            margin-left: 6px;
        }

        /* --- MODAL DE LIVROS --- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .modal-overlay.ativo { display: flex; }
        .modal-content {
            background: #fff;
            border-radius: 12px;
            width: 100%;
            max-width: 520px;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: #4285F4;
            color: #fff;
        }
        .modal-header h2 { margin: 0; font-size: 1.2em; }
        .modal-close {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 1.8em;
            cursor: pointer;
            line-height: 1;
            padding: 0 4px;
        }
        .modal-close:hover { opacity: 0.7; }
        .modal-body { padding: 16px 20px; overflow-y: auto; }
        .sem-livros { color: #666; text-align: center; padding: 20px 0; }
        .lista-livros { list-style: none; padding: 0; margin: 0; }
        .lista-livros li { border-bottom: 1px solid #eee; }
        .lista-livros li:last-child { border-bottom: none; }
        .lista-livros a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 8px;
            text-decoration: none;
            color: #333;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .lista-livros a:hover { background: #f0f6ff; }
        .icone-pdf { font-size: 1.5em; flex-shrink: 0; }
        .nome-livro { flex: 1; font-weight: 500; }
        .acao-livro { color: #4285F4; font-size: 0.85em; font-weight: 600; flex-shrink: 0; }

/* --- WIDGET DE LOGIN (canto superior direito) --- */
.auth-widget {
    position: fixed;
    top: 12px;
    right: 16px;
    z-index: 500;
}
.auth-btn {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 6px 14px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.9em;
    color: #333;
    transition: all 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.auth-btn:hover {
    background: #f5f5f5;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}
.auth-btn svg {
    width: 16px;
    height: 16px;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.auth-user {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 6px 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9em;
    color: #333;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.auth-user .avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #4285F4;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.85em;
    text-transform: uppercase;
}
.auth-user .logout-link {
    color: #888;
    text-decoration: none;
    margin-left: 4px;
    padding: 2px 6px;
    border-radius: 10px;
    transition: all 0.2s;
}
.auth-user .logout-link:hover {
    background: #fee;
    color: #EA4335;
}

/* --- MODAL DE LOGIN --- */
.login-modal {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    padding: 20px;
}
.login-modal.ativo { display: flex; }
.login-box {
    background: #fff;
    border-radius: 12px;
    width: 100%;
    max-width: 360px;
    padding: 28px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}
.login-box h2 {
    margin: 0 0 20px 0;
    font-size: 1.3em;
    color: #333;
    text-align: center;
}
.login-box .form-group {
    margin-bottom: 14px;
}
.login-box label {
    display: block;
    font-size: 0.85em;
    color: #555;
    margin-bottom: 4px;
    font-weight: 500;
}
.login-box input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 1em;
    box-sizing: border-box;
}
.login-box input:focus {
    outline: none;
    border-color: #4285F4;
    box-shadow: 0 0 0 2px rgba(66,133,244,0.15);
}
.login-box button[type="submit"] {
    width: 100%;
    padding: 11px;
    background: #4285F4;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 1em;
    font-weight: 600;
    cursor: pointer;
    margin-top: 8px;
    transition: background 0.2s;
}
.login-box button[type="submit"]:hover {
    background: #3367d6;
}
.login-error {
    background: #f8d7da;
    color: #842029;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.85em;
    margin-bottom: 12px;
    text-align: center;
}
.login-close {
    position: absolute;
    top: 10px;
    right: 14px;
    background: transparent;
    border: none;
    font-size: 1.5em;
    cursor: pointer;
    color: #888;
}
.login-close:hover { color: #333; }

/* --- USUÁRIO NA SIDEBAR (estilo Qwen) --- */
.sidebar-user {
    margin-top: auto;
    padding-top: 16px;
    border-top: 1px solid #c8c8c8;
    display: flex;
    align-items: center;
    gap: 10px;
}
.sidebar-user .avatar-big {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #4285F4;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1em;
    text-transform: uppercase;
    flex-shrink: 0;
}
.sidebar-user .user-info {
    flex: 1;
    min-width: 0;
}
.sidebar-user .user-name {
    font-weight: 600;
    color: #333;
    font-size: 0.95em;
    text-transform: capitalize;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* --- USUÁRIOS ONLINE NA SIDEBAR --- */
.sidebar-online {
    margin-top: 16px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e0e0e0;
}
.online-title {
    font-size: 0.8em;
    font-weight: 600;
    color: #34A853;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.online-list {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.online-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 6px;
    border-radius: 6px;
    font-size: 0.85em;
}
.online-item.online-eu {
    background: #e8f0fe;
    font-weight: 600;
}
.online-avatar {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #4285F4;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75em;
    font-weight: 600;
    text-transform: uppercase;
    flex-shrink: 0;
}
.online-name {
    color: #333;
    text-transform: capitalize;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
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
            .chat-history { border-radius: 0; padding: 12px; }
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
            .clear-btn { display: none; }
            .logo h1 { font-size: 1.2em; }
        }
    </style>
</head>
<body>
    <?php $usuarioAtual = usuarioLogado(); ?>
    
    <!-- Widget de Login/Logout (canto superior direito) -->
    <div class="auth-widget">
        <?php if ($usuarioAtual): ?>
            <div class="auth-user">
                <span class="avatar"><?= htmlspecialchars(substr($usuarioAtual, 0, 1)) ?></span>
                <span><?= htmlspecialchars(ucfirst($usuarioAtual)) ?></span>
                <a href="?sair=1" class="logout-link" title="Sair">
                    <svg viewBox="0 0 24 24" style="width:14px;height:14px;vertical-align:middle">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </a>
            </div>
        <?php else: ?>
            <button type="button" class="auth-btn" onclick="abrirLogin()">
                <svg viewBox="0 0 24 24">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                    <polyline points="10 17 15 12 10 7"/>
                    <line x1="15" y1="12" x2="3" y2="12"/>
                </svg>
                Entrar
            </button>
        <?php endif; ?>
    </div>

    <!-- Modal de Login -->
    <div id="login-modal" class="login-modal" onclick="fecharLogin(event)">
        <div class="login-box" onclick="event.stopPropagation()" style="position:relative">
            <button type="button" class="login-close" onclick="fecharLogin()">&times;</button>
            <h2>🔐 Acesso ao Furtadês</h2>
            <?php if (!empty($erroLogin)): ?>
                <div class="login-error"><?= htmlspecialchars($erroLogin) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="login_username">Usuário</label>
                    <input type="text" id="login_username" name="login_username" required autocomplete="username" autofocus />
                </div>
                <div class="form-group">
                    <label for="login_password">Senha</label>
                    <input type="password" id="login_password" name="login_password" required autocomplete="current-password" />
                </div>
                <button type="submit">Entrar</button>
            </form>
        </div>
    </div>


    <div class="sidebar">
        <div class="logo">
            <h1>
                <span class="m">F</span><span class="o2">u</span><span class="g">r</span>
                <span class="o">t</span><span class="o2">a</span><span class="g2">d</span>
                <span class="l">ê</span><span class="g">s</span>
            </h1>
        </div>
        <a href="?novo=1">➕ Nova Conversa</a>
        <a href="#" onclick="abrirModalLivros(); return false;">📚 Livros</a>
        
        <?php 
        $onlineAgora = function_exists('usuariosOnline') ? usuariosOnline() : [];
        if (!empty($onlineAgora)): 
        ?>
        <div class="sidebar-online">
            <div class="online-title">🟢 Online agora (<?= count($onlineAgora) ?>)</div>
            <div class="online-list">
                <?php foreach ($onlineAgora as $user): 
                    $eEu = ($user['username'] === $usuarioAtual);
                ?>
                    <div class="online-item <?= $eEu ? 'online-eu' : '' ?>">
                        <span class="online-avatar"><?= htmlspecialchars(substr($user['username'], 0, 1)) ?></span>
                        <span class="online-name"><?= htmlspecialchars(ucfirst($user['username'])) ?><?= $eEu ? ' (você)' : '' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
<?php if ($usuarioAtual): ?>
<div class="sidebar-user">
    <div class="avatar-big"><?= htmlspecialchars(substr($usuarioAtual, 0, 1)) ?></div>
    <div class="user-info">
        <div class="user-name"><?= htmlspecialchars(ucfirst($usuarioAtual)) ?></div>
    </div>
</div>
<?php endif; ?>    </div>

    <!-- Modal de Livros -->
    <div id="modal-livros" class="modal-overlay" onclick="fecharModalLivros(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="modal-header">
                <h2>📚 Livros do Clã Furtado</h2>
                <button type="button" class="modal-close" onclick="fecharModalLivros()">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (empty($pdfs)): ?>
                    <p class="sem-livros">Nenhum livro disponível no momento.</p>
                <?php else: ?>
                    <ul class="lista-livros">
                        <?php foreach ($pdfs as $pdf): 
                            $nomeLimpo = preg_replace('/\.pdf$/i', '', $pdf);
                            $nomeLimpo = str_replace(['_', '-'], ' ', $nomeLimpo);
                            $nomeLimpo = ucwords($nomeLimpo);
                        ?>
                            <li>
                                <a href="pdf/<?= htmlspecialchars($pdf) ?>" target="_blank" rel="noopener">
                                    <span class="icone-pdf">📄</span>
                                    <span class="nome-livro"><?= htmlspecialchars($nomeLimpo) ?></span>
                                    <span class="acao-livro">Abrir ↗</span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="chat-area">
        <div class="chat-wrapper">
        <?php if (empty($_SESSION["chat"])): ?>
            <div class="center-page">
                <div class="search-box">
                    <div class="intro">Olá! O que deseja saber sobre a família Furtado?</div>
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
                    <div class="mensagem">
                        <div class="bubble-user"><?= htmlspecialchars($linha["user"]) ?></div>
                    </div>
                    <div class="mensagem">
                        <div class="bubble-ia"><?= nl2br(htmlspecialchars($linha["ia"])) ?></div>
                        
                        <!-- Botões de Ação -->
                        <div class="feedback-actions" data-turn-id="<?= htmlspecialchars($linha["turn_id"]) ?>">
                            <button type="button" class="feedback-btn btn-copy" 
                                    onclick="copiarResposta(this)" 
                                    title="Copiar resposta">
                                <svg viewBox="0 0 24 24" class="icon-default">
                                    <rect x="9" y="9" width="13" height="13" rx="2"/>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                </svg>
                                <svg viewBox="0 0 24 24" class="icon-check" style="display:none">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </button>

                            <button type="button" class="feedback-btn <?= ($linha["feedback"] === 'up') ? 'active' : '' ?>" 
                                    onclick="sendFeedback('<?= htmlspecialchars($linha["turn_id"]) ?>', 'up')" title="Resposta útil">
                                <svg viewBox="0 0 24 24">
                                    <path d="M7 10v12"/>
                                    <path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H7a2 2 0 0 1-2-2V11a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L15 2a3.13 3.13 0 0 1 3 3.88Z"/>
                                </svg>
                            </button>

                            <button type="button" class="feedback-btn <?= ($linha["feedback"] === 'down') ? 'active-down' : '' ?>" 
                                    onclick="sendFeedback('<?= htmlspecialchars($linha["turn_id"]) ?>', 'down')" title="Resposta não útil">
                                <svg viewBox="0 0 24 24">
                                    <path d="M17 14V2"/>
                                    <path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H17a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L9 22a3.13 3.13 0 0 1-3-3.88Z"/>
                                </svg>
                            </button>

                            <span class="feedback-status"><?= $linha["feedback"] ? 'Obrigado pelo feedback!' : '' ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="chat-input">
                <form method="post" style="display:flex; width:100%;">
                <input type="text" name="mensagem" placeholder="Digite sua mensagem..." required />
                <button type="submit">Enviar</button>
                </form>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <script>
        const chatHistory = document.getElementById('chat-history');
        if (chatHistory) chatHistory.scrollTop = chatHistory.scrollHeight;

        // ===== COPIAR RESPOSTA =====
        function copiarResposta(botao) {
            const mensagem = botao.closest('.mensagem');
            const bubbleIA = mensagem ? mensagem.querySelector('.bubble-ia') : null;
            
            if (!bubbleIA) {
                console.error('Não encontrou o bubble-ia');
                mostrarFeedbackCopia(botao, false);
                return;
            }
            
            const textoLimpo = bubbleIA.innerText || bubbleIA.textContent;
            
            if (!textoLimpo || textoLimpo.trim() === '') {
                mostrarFeedbackCopia(botao, false);
                return;
            }
            
            if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
                navigator.clipboard.writeText(textoLimpo)
                    .then(() => mostrarFeedbackCopia(botao, true))
                    .catch(err => {
                        console.error('Erro Clipboard API:', err);
                        copiarFallback(textoLimpo, botao);
                    });
            } else {
                copiarFallback(textoLimpo, botao);
            }
        }

        function copiarFallback(texto, botao) {
            const temp = document.createElement('textarea');
            temp.value = texto;
            temp.style.position = 'fixed';
            temp.style.left = '-9999px';
            temp.style.top = '-9999px';
            temp.style.opacity = '0';
            document.body.appendChild(temp);
            temp.focus();
            temp.select();
            temp.setSelectionRange(0, texto.length);
            
            let sucesso = false;
            try {
                sucesso = document.execCommand('copy');
            } catch (err) {
                console.error('Erro execCommand:', err);
                sucesso = false;
            }
            
            document.body.removeChild(temp);
            mostrarFeedbackCopia(botao, sucesso);
        }

        function mostrarFeedbackCopia(botao, sucesso) {
            const iconDefault = botao.querySelector('.icon-default');
            const iconCheck = botao.querySelector('.icon-check');
            
            if (sucesso) {
                botao.classList.add('copiado');
                if (iconDefault) iconDefault.style.display = 'none';
                if (iconCheck) iconCheck.style.display = 'block';
                botao.setAttribute('title', 'Copiado!');
            } else {
                botao.setAttribute('title', 'Erro ao copiar');
            }
            
            setTimeout(() => {
                botao.classList.remove('copiado');
                if (iconDefault) iconDefault.style.display = 'block';
                if (iconCheck) iconCheck.style.display = 'none';
                botao.setAttribute('title', 'Copiar resposta');
            }, 2000);
        }

        // ===== FEEDBACK 👍 / 👎 =====
        function sendFeedback(turnId, feedback) {
            const formData = new FormData();
            formData.append('turn_id', turnId);
            formData.append('feedback', feedback);

            fetch('feedback.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const container = document.querySelector(`.feedback-actions[data-turn-id="${turnId}"]`);
                    if (!container) return;
                    
                    container.querySelectorAll('.feedback-btn').forEach(btn => {
                        btn.classList.remove('active', 'active-down');
                    });
                    
                    const btn = container.querySelector(`.feedback-btn[onclick*="'${feedback}'"]`);
                    if (btn) {
                        btn.classList.add(feedback === 'up' ? 'active' : 'active-down');
                    }
                    
                    const status = container.querySelector('.feedback-status');
                    if (status) status.textContent = 'Obrigado pelo feedback!';
                }
            })
            .catch(err => console.error('Erro ao enviar feedback:', err));
        }

        // ===== MODAL DE LIVROS =====
        function abrirModalLivros() {
            document.getElementById('modal-livros').classList.add('ativo');
        }
        function fecharModalLivros(event) {
            if (!event || event.target.id === 'modal-livros') {
                document.getElementById('modal-livros').classList.remove('ativo');
            }
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') fecharModalLivros();
        });
// ===== MODAL DE LOGIN =====
function abrirLogin() {
    document.getElementById('login-modal').classList.add('ativo');
    setTimeout(() => {
        const input = document.getElementById('login_username');
        if (input) input.focus();
    }, 100);
}
function fecharLogin(event) {
    if (!event || event.target.id === 'login-modal') {
        document.getElementById('login-modal').classList.remove('ativo');
    }
}
// Fecha o modal de login com ESC (sem conflitar com o modal de livros)
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const loginModal = document.getElementById('login-modal');
        if (loginModal && loginModal.classList.contains('ativo')) {
            loginModal.classList.remove('ativo');
        } else {
            fecharModalLivros();
        }
    }
});
    </script>
</body>
</html>
