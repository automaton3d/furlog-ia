<?php
/**
 * Recuperação por parágrafos dos livros familiares do clã Furtado
 */

class LivrosFamiliares
{
    private array $paragrafos = []; // [ ['arquivo'=>, 'texto'=>, 'lower'=>], ... ]
    private string $dir;

    public function __construct(string $dir = __DIR__ . '/knowledge')
    {
        $this->dir = $dir;
        $this->carregarEDividir();
    }

    private function carregarEDividir(): void
    {
        $arquivos = glob($this->dir . '/livro*.txt');
        sort($arquivos);

        foreach ($arquivos as $arq) {
            $conteudo = file_get_contents($arq);
            if ($conteudo === false) continue;
            $conteudo = str_replace("\x0C", "\n", $conteudo);
            $nome = basename($arq);

            // Divide em blocos por linhas em branco ou form feeds
            $blocos = preg_split('/\n\s*\n/', $conteudo);
            foreach ($blocos as $bloco) {
                $bloco = trim(preg_replace('/[ \t]+/', ' ', $bloco));
                $bloco = preg_replace('/\n{2,}/', "\n", $bloco);
                // Remove números de página isolados
                $bloco = preg_replace('/^\d+\s*$/m', '', $bloco);
                $bloco = trim($bloco);

                if (strlen($bloco) < 120) continue; // ignora blocos muito curtos
                if ($this->pareceLixo($bloco)) continue;

                $this->paragrafos[] = [
                    'arquivo' => $nome,
                    'texto'   => $bloco,
                    'lower'   => strtolower($bloco),
                ];
            }
        }
    }

    private function pareceLixo(string $t): bool
    {
        $lower = strtolower($t);
        // Sumários e listas de capítulos
        if (substr_count($t, "\n") > 12 && strlen($t) / (substr_count($t, "\n") + 1) < 35) {
            return true;
        }
        $lixo = ['sumário', 'sumario', 'dedicatória', 'agradecimentos', 'participação especial',
                 'outras participações', 'homenagem especial', 'índice'];
        foreach ($lixo as $p) {
            if (strpos($lower, $p) !== false && substr_count($t, "\n") > 6) return true;
        }
        return false;
    }

    private function extrairTermos(string $query): array
    {
        $query = strtolower(trim($query));
        $stop = ['quem','foi','era','é','e','sobre','me','fale','diga','qual','relação','relacao',
                 'de','da','do','das','dos','a','o','os','as','ou','em','para','com','por','que',
                 'se','um','uma','nos','nas','ao','à','como','onde','quando','história','historia',
                 'conte','falar'];
        $termos = preg_split('/[\s,;:.!?"\'\(\)\[\]\/]+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
        $termos = array_filter($termos, fn($t) => strlen($t) > 2 && !in_array($t, $stop, true));
        return array_values(array_unique($termos));
    }

    public function buscarTrechos(string $query, int $maxTrechos = 4, int $janela = 0): string
    {
        $termos = $this->extrairTermos($query);
        if (empty($termos) || empty($this->paragrafos)) {
            return "";
        }

        $candidatos = [];
        foreach ($this->paragrafos as $p) {
            $score = 0.0;
            $hits = 0;
            foreach ($termos as $termo) {
                $c = substr_count($p['lower'], $termo);
                if ($c > 0) {
                    $hits++;
                    $score += $c * (strlen($termo) > 5 ? 4.0 : 2.0);
                }
            }
            if ($hits === 0) continue;

            // Bônus forte se contém várias palavras da query
            $score += $hits * 3.0;

            // Bônus por conteúdo biográfico
            foreach (['nasceu', 'faleceu', 'casou', 'filha', 'filho', 'mãe', 'mae', 'pai',
                      'esposa', 'esposo', 'morreu', 'viveu', 'chamava', 'chamado'] as $b) {
                if (strpos($p['lower'], $b) !== false) $score += 5.0;
            }

            // Bônus por tamanho razoável (prosa)
            $len = strlen($p['texto']);
            if ($len > 250 && $len < 2500) $score += 4.0;
            if ($len > 500) $score += 3.0;

            // Penaliza poesia/música (muitas aspas e reticências)
            if (substr_count($p['texto'], '...') > 3) $score *= 0.4;
            if (substr_count($p['texto'], '"') > 6) $score *= 0.5;

            $candidatos[] = [
                'arquivo' => $p['arquivo'],
                'trecho'  => $p['texto'],
                'score'   => $score,
            ];
        }

        if (empty($candidatos)) return "";

        usort($candidatos, fn($a, $b) => $b['score'] <=> $a['score']);

        $selecionados = [];
        $vistos = [];
        foreach ($candidatos as $c) {
            $hash = md5(substr($c['trecho'], 0, 160));
            if (isset($vistos[$hash])) continue;
            $vistos[$hash] = true;
            $selecionados[] = $c;
            if (count($selecionados) >= $maxTrechos) break;
        }

        $nomes = [
            'livro1.txt' => 'Cenas de minha infância (J.M. Furtado)',
            'livro2.txt' => 'Furtadês (Dyleli Furtado)',
            'livro3.txt' => 'Memorial de Mariana',
            'livro4.txt' => 'O Efeito Pipoca (Carminha Furtado)',
        ];

        $saida = "Trechos relevantes dos livros familiares:\n\n";
        foreach ($selecionados as $i => $s) {
            $titulo = $nomes[$s['arquivo']] ?? $s['arquivo'];
            $saida .= "--- Trecho " . ($i + 1) . " · {$titulo} ---\n";
            $saida .= trim($s['trecho']) . "\n\n";
        }
        return $saida;
    }

    public function resumoGeral(): string
    {
        $path = $this->dir . '/resumo_familia.md';
        return file_exists($path) ? file_get_contents($path) : "Livros do clã Furtado.";
    }

    public function listarLivros(): array
    {
        $arqs = [];
        foreach ($this->paragrafos as $p) $arqs[$p['arquivo']] = true;
        return array_keys($arqs);
    }
}
