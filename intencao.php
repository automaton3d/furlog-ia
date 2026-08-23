<?php
/**
 * Detecção de intenção e extração de nomes – versão robusta
 */

function normalizarTexto(string $t): string
{
    $t = mb_strtolower(trim($t), 'UTF-8');
    $map = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n',
    ];
    return strtr($t, $map);
}

function detectarIntencao($mensagem)
{
    $mensagem = normalizarTexto($mensagem);

    // Padrões fortes de genealogia
    $padroes = [
        '/^quem (foi|era|e)/',
        '/^me fale sobre/',
        '/^fale (sobre|de)/',
        '/^conte (sobre|a historia)/',
        '/descendentes? de/',
        '/ascendentes? de/',
        '/qual a (relacao|parentesco)/',
        '/filhos? de/',
        '/pais de/',
        '/mae de/',
        '/pai de/',
        '/irmao|irma/',
        '/tio |tia /',
        '/avo |ava /',
        '/familia/',
        '/arvore/',
        '/genealogia/',
        '/parentesco/',
        '/memorial/',
        '/historia de/',
        '/nasceu|faleceu|casou|casamento/',
        '/neto|neta|sobrinho|sobrinha/',
    ];

    foreach ($padroes as $p) {
        if (preg_match($p, $mensagem)) {
            return "genealogia";
        }
    }

    // Nomes muito comuns do clã → trata como genealogia
    $nomes = [
        'furtado', 'mariana', 'pio', 'zeca', 'carminha', 'dyleli',
        'j.m.', 'jm furtado', 'tio zeca', 'xandico', 'filica',
        'clementina', 'quele', 'quelé', 'icoaraci', 'colares',
    ];
    foreach ($nomes as $n) {
        if (strpos($mensagem, normalizarTexto($n)) !== false) {
            return "genealogia";
        }
    }

    return "geral";
}

/**
 * Extrai possíveis nomes de pessoas a partir da pergunta do usuário.
 * Versão robusta: lista expandida, normalização de acentos,
 * fallbacks para minúsculas e capitalizadas.
 */
function extrairNomesParaBusca(string $mensagem): array
{
    $original = trim($mensagem);
    $normalized = normalizarTexto($original);

    // Nomes compostos / variações conhecidas do clã (ordem: mais específicos primeiro)
    $conhecidos = [
        'carminha furtado', 'carminha',
        'dyleli furtado', 'dyleli',
        'mariana dos anjos', 'mariana furtado', 'mariana dos anjos lima', 'mariana',
        'pio furtado', 'raimundo pio furtado', 'raimundo pio', 'pio',
        'jose maria furtado', 'josé maria furtado', 'j.m. furtado', 'jm furtado',
        'tio zeca', 'zeca', 'jose maria', 'josé maria',
        'alexandre furtado', 'xandico', 'filica',
        'clementina', 'quele', 'quelé', 'joao lima', 'joão lima',
        'furtado',
    ];

    $encontrados = [];
    foreach ($conhecidos as $nome) {
        $nomeNorm = normalizarTexto($nome);
        if ($nomeNorm !== '' && strpos($normalized, $nomeNorm) !== false) {
            $encontrados[] = $nome;
        }
    }

    // Remove genéricos demais se houver nomes mais específicos
    $especificos = array_filter(
        $encontrados,
        fn($n) => normalizarTexto($n) !== 'furtado' && mb_strlen($n) > 4
    );
    if ($especificos) {
        $encontrados = array_values($especificos);
    }

    // Fallback 1: palavras capitalizadas (nomes próprios)
    if (empty($encontrados)) {
        if (preg_match_all(
            '/\b([A-ZÁÉÍÓÚÂÊÔÃÕÇ][a-záéíóúâêôãõç]+(?:\s+[A-ZÁÉÍÓÚÂÊÔÃÕÇ][a-záéíóúâêôãõç]+)*)/u',
            $original,
            $m
        )) {
            $stop = [
                'Qual', 'Quem', 'Como', 'Onde', 'Quando', 'Sobre', 'Filha', 'Filho',
                'Filhos', 'Pai', 'Mãe', 'Mae', 'Irmão', 'Irmao', 'Irmã', 'Irma',
                'Conte', 'Fale', 'Me', 'Olá', 'Ola', 'Por',
            ];
            foreach ($m[1] as $cand) {
                $len = function_exists('mb_strlen') ? mb_strlen($cand) : strlen($cand);
                if (!in_array($cand, $stop, true) && $len > 2) {
                    $encontrados[] = $cand;
                }
            }
        }
    }

    // Fallback 2: tokens longos em minúsculas (perguntas sem maiúsculas)
    if (empty($encontrados)) {
        $stop = [
            'qual', 'o', 'a', 'os', 'as', 'nome', 'da', 'de', 'do', 'dos', 'das',
            'segunda', 'segundo', 'terceira', 'primeiro', 'primeira',
            'filha', 'filho', 'filhos', 'pai', 'mae', 'mãe', 'com',
            'relacao', 'relação', 'parentesco', 'me', 'fale', 'sobre',
            'quem', 'foi', 'era', 'e', 'eh', 'como', 'onde', 'quando',
            'historia', 'história', 'conte', 'diga', 'saber', 'deseja',
            'familia', 'família', 'pessoa', 'pessoas',
        ];
        $palavras = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        $candidatos = [];
        foreach ($palavras as $p) {
            $p = preg_replace('/[^\p{L}]/u', '', $p);
            if (mb_strlen($p) > 3 && !in_array($p, $stop, true)) {
                $candidatos[] = $p;
            }
        }
        // Preferir tokens no final da frase (geralmente o nome)
        $encontrados = array_slice(array_reverse($candidatos), 0, 3);
    }

    // Preferência: nomes mais longos primeiro
    usort($encontrados, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    return array_values(array_unique($encontrados));
}
