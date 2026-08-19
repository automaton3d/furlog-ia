<?php
/**
 * Recuperação de trechos dos livros familiares do clã Furtado
 *
 * Livros:
 *  1. Cenas de minha infância – J.M. Furtado (Tio Zeca)
 *  2. Furtadês – Dyleli Furtado (colab. Carminha)
 *  3. Memorial de Mariana – (filha de Mariana e Pio)
 *  4. O Efeito Pipoca – Quando a dor nos ensina – Carminha Furtado
 */

class LivrosFamiliares
{
    private array $textos = [];
    private string $dir;

    public function __construct(string $dir = __DIR__ . '/knowledge')
    {
        $this->dir = $dir;
        $this->carregarTextos();
    }

    private function carregarTextos(): void
    {
        $arquivos = glob($this->dir . '/livro*.txt');
        sort($arquivos);
        foreach ($arquivos as $arq) {
            $conteudo = file_get_contents($arq);
            if ($conteudo !== false) {
                $conteudo = str_replace("\x0C", "\n", $conteudo);
                $this->textos[basename($arq)] = $conteudo;
            }
        }
    }

    private function lower(string $s): string
    {
        return strtolower($s); // suficiente para busca (português sem acento crítico na maioria dos nomes)
    }

    private function extrairTermos(string $query): array
    {
        $query = $this->lower(trim($query));
        $stop = [
            'quem', 'foi', 'era', 'sobre', 'me', 'fale', 'diga', 'qual', 'relação', 'relacao',
            'de', 'da', 'do', 'das', 'dos', 'a', 'o', 'os', 'as', 'e', 'ou', 'em',
            'para', 'com', 'por', 'que', 'se', 'um', 'uma', 'nos', 'nas', 'ao', 'à',
            'como', 'onde', 'quando', 'história', 'historia', 'conte', 'falar', 'sobre'
        ];
        $termos = preg_split('/[\s,;:.!?"\'\(\)\[\]\/]+/u', $query, -1, PREG_SPLIT_NO_EMPTY);
        $termos = array_filter($termos, function ($t) use ($stop) {
            return strlen($t) > 2 && !in_array($t, $stop, true);
        });
        return array_values(array_unique($termos));
    }

    public function buscarTrechos(string $query, int $maxTrechos = 5, int $janela = 550): string
    {
        $termos = $this->extrairTermos($query);
        if (empty($termos)) {
            return "";
        }

        $candidatos = [];

        foreach ($this->textos as $arquivo => $texto) {
            $textoLower = $this->lower($texto);
            foreach ($termos as $termo) {
                $pos = 0;
                $ocorrencias = 0;
                while (($pos = strpos($textoLower, $termo, $pos)) !== false && $ocorrencias < 8) {
                    $inicio = max(0, $pos - $janela);
                    $trecho = substr($texto, $inicio, $janela * 2);
                    $trecho = $this->limparTrecho($trecho);
                    $score = $this->calcularScore($trecho, $termos);
                    $candidatos[] = [
                        'arquivo' => $arquivo,
                        'trecho'  => $trecho,
                        'score'   => $score
                    ];
                    $pos += strlen($termo);
                    $ocorrencias++;
                }
            }
        }

        if (empty($candidatos)) {
            return "";
        }

        usort($candidatos, fn($a, $b) => $b['score'] <=> $a['score']);

        $selecionados = [];
        $vistos = [];
        foreach ($candidatos as $c) {
            $hash = md5(substr(preg_replace('/\s+/', ' ', $c['trecho']), 0, 150));
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

    private function limparTrecho(string $t): string
    {
        $t = preg_replace('/[ \t]+/', ' ', $t);
        $t = preg_replace('/\n{3,}/', "\n\n", $t);
        $t = preg_replace('/^\s*\d+\s*$/m', '', $t);
        return trim($t);
    }

    private function calcularScore(string $trecho, array $termos): float
    {
        $lower = $this->lower($trecho);
        $score = 0.0;
        foreach ($termos as $termo) {
            $count = substr_count($lower, $termo);
            $peso = strlen($termo) > 5 ? 2.5 : 1.2;
            $score += $count * $peso;
        }
        return $score;
    }

    public function resumoGeral(): string
    {
        $path = $this->dir . '/resumo_familia.md';
        if (file_exists($path)) {
            return file_get_contents($path);
        }
        return "Quatro livros familiares do clã Furtado de Icoaraci/Belém.";
    }

    public function listarLivros(): array
    {
        return array_keys($this->textos);
    }
}
