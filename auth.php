<?php
/**
 * Sistema de autenticação com persistência de 30 dias
 */

// Lista de usuários autorizados
$usuariosAutorizados = [
    'denci'     => '1953', 'neto'      => '1954', 'joao'      => '1955',
    'pio'       => '1956', 'katia'     => '1957', 'cota'      => '1959',
    'leli'      => '1960', 'dido'      => '1962', 'marita'    => '1963',
    'carminha'  => '1965', 'afonso'    => '1967',
];

// Chave secreta para assinar o token (TROQUE por uma string aleatória sua!)
define('AUTH_SECRET_KEY', 'mude-esta-chave-para-algo-aleatorio-e-secreto-2026!@#');
define('AUTH_COOKIE_NAME', 'furtades_auth');
define('AUTH_COOKIE_LIFETIME', 60 * 60 * 24 * 30); // 30 dias

/**
 * Verifica credenciais
 */
function autenticarUsuario(string $username, string $senha, array $usuarios): bool {
    $username = strtolower(trim($username));
    return isset($usuarios[$username]) && $usuarios[$username] === $senha;
}

/**
 * Retorna o usuário logado (Sessão ou Cookie Persistente)
 */
function usuarioLogado(): ?string {
    // 1. Tenta pegar da sessão primeiro
    if (!empty($_SESSION['usuario_logado'])) {
        return $_SESSION['usuario_logado'];
    }
    
    // 2. Se não houver sessão, tenta restaurar pelo cookie persistente
    if (!empty($_COOKIE[AUTH_COOKIE_NAME])) {
        $usuario = validarTokenCookie($_COOKIE[AUTH_COOKIE_NAME]);
        if ($usuario) {
            // Restaura a sessão automaticamente
            $_SESSION['usuario_logado'] = $usuario;
            $_SESSION['login_time'] = time();
            return $usuario;
        }
    }
    
    return null;
}

/**
 * Faz login e cria cookie persistente se solicitado
 */
function fazerLogin(string $username, bool $lembrar = false): void {
    $username = strtolower(trim($username));
    $_SESSION['usuario_logado'] = $username;
    $_SESSION['login_time'] = time();
    
    if ($lembrar) {
        $token = gerarToken($username);
        setcookie(AUTH_COOKIE_NAME, $token, [
            'expires'  => time() + AUTH_COOKIE_LIFETIME,
            'path'     => '/',
            'domain'   => '', // Deixe vazio para usar o domínio atual
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/**
 * Faz logout (limpa sessão E cookie)
 */
function fazerLogout(): void {
    session_unset();
    session_destroy();
    
    if (isset($_COOKIE[AUTH_COOKIE_NAME])) {
        setcookie(AUTH_COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[AUTH_COOKIE_NAME]);
    }
}

/**
 * Gera token seguro
 */
function gerarToken(string $username): string {
    $payload = $username . '|' . (time() + AUTH_COOKIE_LIFETIME);
    $signature = hash_hmac('sha256', $payload, AUTH_SECRET_KEY);
    return base64_encode($payload . '|' . $signature);
}

/**
 * Valida token do cookie
 */
function validarTokenCookie(string $token): ?string {
    $decoded = base64_decode($token, true);
    if ($decoded === false) return null;
    
    $partes = explode('|', $decoded);
    if (count($partes) !== 3) return null;
    
    [$username, $expiraEm, $signature] = $partes;
    
    global $usuariosAutorizados;
    if (!isset($usuariosAutorizados[$username])) return null;
    if ((int)$expiraEm < time()) return null; // Expirou
    
    $payload = $username . '|' . $expiraEm;
    $signatureEsperada = hash_hmac('sha256', $payload, AUTH_SECRET_KEY);
    
    if (!hash_equals($signatureEsperada, $signature)) return null; // Assinatura inválida
    
    return $username;
}

// ===== RASTREAMENTO DE ONLINE (Opcional, se já tiver no seu código) =====
if (!function_exists('registrarAtividade')) {
    define('ONLINE_FILE', __DIR__ . '/online_users.json');
    define('ONLINE_TIMEOUT', 15 * 60);

    function registrarAtividade(): void {
        $usuario = usuarioLogado();
        if (!$usuario) return;
        
        $dados = file_exists(ONLINE_FILE) ? json_decode(file_get_contents(ONLINE_FILE), true) ?: [] : [];
        $dados[$usuario] = ['ultimo_acesso' => time(), 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'];
        
        $agora = time();
        foreach ($dados as $user => $info) {
            if ($agora - $info['ultimo_acesso'] > ONLINE_TIMEOUT) unset($dados[$user]);
        }
        file_put_contents(ONLINE_FILE, json_encode($dados, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
