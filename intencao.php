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

/**
 * Extrai possíveis nomes de pessoas a partir da pergunta do usuário.
 * Evita buscar a frase inteira no banco (ex: "Qual o nome da segunda filha de Carminha").
 */
function extrairNomesParaBusca(string $mensagem): array
{
    $original = trim($mensagem);
    $lower = function_exists('mb_strtolower') ? mb_strtolower($original, 'UTF-8') : strtolower($original);

    // Nomes compostos / sobrenomes conhecidos do clã (prioridade)
    $conhecidos = [
        'carminha furtado', 'carminha',
        'dyleli furtado', 'dyleli',
        'mariana dos anjos', 'mariana furtado', 'mariana',
        'pio furtado', 'raimundo pio', 'pio',
        'josé maria furtado', 'jose maria furtado', 'j.m. furtado', 'jm furtado', 'tio zeca', 'zeca',
        'furtado',
    ];

    $encontrados = [];
    foreach ($conhecidos as $nome) {
        if (strpos($lower, $nome) !== false) {
            $encontrados[] = $nome;
            // evita pegar só "furtado" se já pegou "carminha furtado"
            if ($nome !== 'furtado' && strpos($nome, ' ') !== false) {
                // ok
            }
        }
    }

    // Remove termos genéricos demais se houver nomes mais específicos
    $especificos = array_filter($encontrados, fn($n) => $n !== 'furtado' && strlen($n) > 4);
    if ($especificos) {
        $encontrados = array_values($especificos);
    }

    // Fallback: pega palavras capitalizadas da frase original (nomes próprios)
    if (empty($encontrados)) {
        if (preg_match_all('/\b([A-ZÁÉÍÓÚÂÊÔÃÕÇ][a-záéíóúâêôãõç]+(?:\s+[A-ZÁÉÍÓÚÂÊÔÃÕÇ][a-záéíóúâêôãõç]+)*)/u', $original, $m)) {
            $stop = ['Qual', 'Quem', 'Como', 'Onde', 'Quando', 'Sobre', 'Filha', 'Filho', 'Filhos', 'Pai', 'Mãe', 'Mae', 'Irmão', 'Irmao', 'Irmã', 'Irma'];
            foreach ($m[1] as $cand) {
                if (!in_array($cand, $stop, true) && function_exists('mb_strlen') ? mb_strlen($cand) : strlen($cand) > 2) {
                    $encontrados[] = $cand;
                }
            }
        }
    }

    // Último fallback: última palavra "substantiva"
    if (empty($encontrados)) {
        $palavras = preg_split('/\s+/', $lower);
        $stop = ['qual','o','a','os','as','nome','da','de','do','dos','das','segunda','segundo','terceira','primeiro','filha','filho','filhos','pai','mae','mãe','com','relação','relacao','parentesco','me','fale','sobre','quem','foi','era'];
        foreach (array_reverse($palavras) as $p) {
            $p = preg_replace('/[^\p{L}]/u', '', $p);
            if (strlen($p) > 3 && !in_array($p, $stop, true)) {
                $encontrados[] = $p;
                break;
            }
        }
    }

    // Preferência: nomes mais longos primeiro
    usort($encontrados, fn($a, $b) => strlen($b) <=> strlen($a));
    return array_values(array_unique($encontrados));
}

