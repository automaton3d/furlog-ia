<?php
function detectarIntencao($mensagem) {
    $mensagem = strtolower(trim($mensagem));

    // Padrões fortes de genealogia
    $padroes = [
        '/^quem (foi|era|é|e)/',
        '/^me fale sobre/',
        '/^fale (sobre|de)/',
        '/^conte (sobre|a história|a historia)/',
        '/descendentes? de/',
        '/ascendentes? de/',
        '/qual a (relação|relacao|parentesco)/',
        '/filhos? de/',
        '/pais de/',
        '/mãe de|mae de/',
        '/pai de/',
        '/irmão|irmao|irmã|irma/',
        '/tio |tia /',
        '/avô|avo |avó|ava /',
        '/família|familia/',
        '/árvore|arvore/',
        '/genealogia/',
        '/parentesco/',
        '/memorial/',
        '/história de|historia de/',
    ];

    foreach ($padroes as $p) {
        if (preg_match($p, $mensagem)) {
            return "genealogia";
        }
    }

    // Nomes muito comuns do clã → trata como genealogia
    $nomes = ['furtado', 'mariana', 'pio', 'zeca', 'carminha', 'dyleli', 'j.m.', 'jm furtado', 'tio zeca'];
    foreach ($nomes as $n) {
        if (strpos($mensagem, $n) !== false) {
            return "genealogia";
        }
    }

    return "geral";
}
