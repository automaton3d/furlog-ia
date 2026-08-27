<?php
/**
 * Visualizador do log de perguntas
 * Requer autenticação
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

require_once 'auth.php';

// Verifica autenticação
$usuarioAtual = usuarioLogado();
if (!$usuarioAtual) {
    header("Location: index.php");
    exit;
}

// Registra atividade
if (function_exists('registrarAtividade')) {
    registrarAtividade();
}

$logFile = __DIR__ . '/perguntas.log';
$linhas = [];
$totalLinhas = 0;
$filtroUsuario = $_GET['usuario'] ?? '';
$filtroBusca = $_GET['busca'] ?? '';

// Limpar log se solicitado
if (isset($_GET['limpar']) && $_GET['limpar'] === '1') {
    file_put_contents($logFile, '');
    header("Location: perguntas.php");
    exit;
}

// Lê o log
if (file_exists($logFile)) {
    $conteudo = file_get_contents($logFile);
    $todasLinhas = explode("\n", trim($conteudo));
    $todasLinhas = array_filter($todasLinhas, fn($l) => trim($l) !== '');
    $todasLinhas = array_reverse($todasLinhas); // Mais recente primeiro
    
    // Aplica filtros
    foreach ($todasLinhas as $linha) {
        // Filtro por usuário
        if ($filtroUsuario && !preg_match('/Usuário:\s*' . preg_quote($filtroUsuario, '/') . '\b/i', $linha)) {
            continue;
        }
        // Filtro por busca
        if ($filtroBusca && stripos($linha, $filtroBusca) === false) {
            continue;
        }
        $linhas[] = $linha;
        $totalLinhas++;
    }
}

// Extrai lista de usuários únicos para o filtro
$usuariosUnicos = [];
if (file_exists($logFile)) {
    $conteudo = file_get_contents($logFile);
    if (preg_match_all('/Usuário:\s*(\w+)/i', $conteudo, $matches)) {
        $usuariosUnicos = array_unique($matches[1]);
        sort($usuariosUnicos);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log de Perguntas - Furtadês</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: #f2f2f2;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #4285F4;
            color: #fff;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            font-size: 1.5em;
        }
        .header a {
            color: #fff;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            transition: background 0.2s;
        }
        .header a:hover {
            background: rgba(255,255,255,0.3);
        }
        .filters {
            padding: 16px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filters form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
            flex: 1;
        }
        .filters input, .filters select {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 0.9em;
        }
        .filters input[type="text"] {
            flex: 1;
            min-width: 200px;
        }
        .filters button {
            padding: 8px 16px;
            background: #4285F4;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
        }
        .filters button:hover {
            background: #3367d6;
        }
        .filters .btn-clear {
            background: #EA4335;
        }
        .filters .btn-clear:hover {
            background: #c5221f;
        }
        .stats {
            padding: 12px 20px;
            background: #e8f0fe;
            border-bottom: 1px solid #d0d0d0;
            font-size: 0.9em;
            color: #333;
        }
        .log-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85em;
        }
        .log-table thead {
            background: #f0f0f0;
            position: sticky;
            top: 0;
        }
        .log-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #ddd;
        }
        .log-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        .log-table tr:hover {
            background: #f8f9fa;
        }
        .log-table .col-data { width: 160px; }
        .log-table .col-usuario { width: 100px; }
        .log-table .col-id { width: 180px; }
        .log-table .col-feedback { width: 80px; text-align: center; }
        .feedback-pendente { color: #888; }
        .feedback-up { color: #34A853; font-weight: 600; }
        .feedback-down { color: #EA4335; font-weight: 600; }
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #888;
        }
        .empty-state svg {
            width: 64px;
            height: 64px;
            stroke: #ccc;
            fill: none;
            stroke-width: 1.5;
            margin-bottom: 16px;
        }
        @media (max-width: 768px) {
            body { padding: 10px; }
            .header { flex-direction: column; gap: 12px; }
            .log-table { font-size: 0.75em; }
            .log-table th, .log-table td { padding: 8px 6px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Log de Perguntas</h1>
            <a href="index.php">← Voltar ao Chat</a>
        </div>
        
        <div class="filters">
            <form method="get">
                <select name="usuario">
                    <option value="">Todos os usuários</option>
                    <?php foreach ($usuariosUnicos as $u): ?>
                        <option value="<?= htmlspecialchars($u) ?>" <?= $filtroUsuario === $u ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucfirst($u)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="busca" placeholder="Buscar no log..." value="<?= htmlspecialchars($filtroBusca) ?>" />
                <button type="submit">Filtrar</button>
                <a href="perguntas.php" style="padding:8px 16px; background:#6c757d; color:#fff; text-decoration:none; border-radius:6px; font-size:0.9em;">Limpar filtros</a>
            </form>
            <a href="?limpar=1" class="btn-clear" onclick="return confirm('Tem certeza que deseja apagar TODO o log de perguntas? Esta ação não pode ser desfeita.');" style="padding:8px 16px; background:#EA4335; color:#fff; text-decoration:none; border-radius:6px; font-size:0.9em;">🗑️ Limpar Log</a>
        </div>
        
        <div class="stats">
            <strong><?= number_format($totalLinhas) ?></strong> registro(s) encontrado(s)
            <?php if ($filtroUsuario || $filtroBusca): ?>
                (filtrado)
            <?php endif; ?>
        </div>
        
        <?php if (empty($linhas)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
                <p>Nenhum registro encontrado.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th class="col-data">Data/Hora</th>
                            <th class="col-usuario">Usuário</th>
                            <th class="col-id">ID</th>
                            <th>Pergunta</th>
                            <th>Resposta (1ª linha)</th>
                            <th class="col-feedback">Feedback</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($linhas as $linha): 
                            // Parse da linha do log
                            $partes = explode(' | ', $linha);
                            $dataHora = $partes[0] ?? '';
                            $usuario = 'anon';
                            $turnId = '';
                            $pergunta = '';
                            $resposta = '';
                            $feedback = 'pendente';
                            
                            foreach ($partes as $parte) {
                                if (preg_match('/Usuário:\s*(\S+)/i', $parte, $m)) $usuario = $m[1];
                                if (preg_match('/ID:\s*(\S+)/i', $parte, $m)) $turnId = $m[1];
                                if (preg_match('/Pergunta:\s*(.+)$/i', $parte, $m)) $pergunta = $m[1];
                                if (preg_match('/Resposta \(1ª linha\):\s*(.+)$/i', $parte, $m)) $resposta = $m[1];
                                if (preg_match('/Feedback:\s*(\S+)/i', $parte, $m)) $feedback = $m[1];
                            }
                            
                            $feedbackClass = 'feedback-pendente';
                            $feedbackIcon = '⏳';
                            if ($feedback === 'up') { $feedbackClass = 'feedback-up'; $feedbackIcon = '👍'; }
                            elseif ($feedback === 'down') { $feedbackClass = 'feedback-down'; $feedbackIcon = '👎'; }
                        ?>
                            <tr>
                                <td class="col-data"><?= htmlspecialchars($dataHora) ?></td>
                                <td class="col-usuario"><?= htmlspecialchars(ucfirst($usuario)) ?></td>
                                <td class="col-id" style="font-family:monospace; font-size:0.85em;"><?= htmlspecialchars($turnId) ?></td>
                                <td><?= htmlspecialchars($pergunta) ?></td>
                                <td><?= htmlspecialchars($resposta) ?></td>
                                <td class="col-feedback <?= $feedbackClass ?>"><?= $feedbackIcon ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
