<?php
/**
 * Endpoint para listar usuários online
 * Retorna JSON com usuários ativos nos últimos 15 minutos
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Verifica se há usuário logado (opcional: remova esta verificação se quiser público)
$usuarioAtual = usuarioLogado();
if (!$usuarioAtual) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Não autorizado. Faça login primeiro.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Atualiza atividade do usuário atual
registrarAtividade();

// Lista usuários online
$online = usuariosOnline();

// Formata resposta
$resposta = [
    'status' => 'success',
    'total_online' => count($online),
    'usuarios' => array_map(function($user) {
        $minutos = floor($user['tempo_atras'] / 60);
        $segundos = $user['tempo_atras'] % 60;
        
        return [
            'username' => $user['username'],
            'ultimo_acesso' => date('Y-m-d H:i:s', $user['ultimo_acesso']),
            'ativo_ha' => $minutos > 0 ? "{$minutos}min {$segundos}s" : "{$segundos}s",
            'ip' => $user['ip'],
            'e_usuario_atual' => $user['username'] === $_SESSION['usuario_logado'],
        ];
    }, $online),
    'timestamp' => date('Y-m-d H:i:s'),
];

echo json_encode($resposta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
