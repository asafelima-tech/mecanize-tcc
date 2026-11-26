<?php

/** Funções curtas usadas nas views. */

/** Escapa texto para HTML. Use em TODO valor impresso. */
function e($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Campo oculto do CSRF para os formulários POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(Csrf::token()) . '">';
}

/**
 * Endereço de um arquivo estático (CSS, JS, imagem).
 * Enquanto a v2 roda ao lado da v1, os arquivos são os da v1 (public/assets): ASSETS_URL no .env
 * permite apontar para outro lugar depois da troca, sem editar view nenhuma.
 */
function asset(string $caminho): string
{
    $base = rtrim((string) env('ASSETS_URL', '../../public/assets'), '/');

    return $base . '/' . ltrim($caminho, '/');
}

/** JSON seguro para colar dentro de <script> (um "</script>" num nome não fecha o bloco). */
function json_para_script($valor): string
{
    return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * Valor em reais no padrão único do sistema: "R$ 1.234,56" e, se negativo, "-R$ 1.234,56" (sinal colado, como nas
 * listas de movimento). O espaço entre "R$" e o número não separa, para o valor nunca quebrar em duas linhas.
 */
function dinheiro($valor): string
{
    $n = round((float) $valor, 2);

    return ($n < 0 ? '-' : '') . "R$\u{00A0}" . number_format(abs($n), 2, ',', '.');
}

/** "1 conta", "3 contas": a quantidade com a palavra no singular ou no plural. */
function plural(int $n, string $um, string $varios): string
{
    return $n . ' ' . ($n === 1 ? $um : $varios);
}

function data_br(?string $data, bool $comHora = false): string
{
    if (!$data) {
        return '-';
    }

    return date($comHora ? 'd/m/Y H:i' : 'd/m/Y', strtotime($data));
}

function so_digitos(?string $v): string
{
    return preg_replace('/\D+/', '', (string) $v);
}

/** CPF/CNPJ com pontuação (para exibir). */
function documento_fmt(?string $v): string
{
    $d = so_digitos($v);
    if (strlen($d) === 11) {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d);
    }
    if (strlen($d) === 14) {
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $d);
    }

    return (string) $v;
}

function telefone_fmt(?string $v): string
{
    $d = so_digitos($v);
    if (strlen($d) === 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $d);
    }
    if (strlen($d) === 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $d);
    }

    return (string) $v;
}

function os_numero($n): string
{
    return 'OS-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
}

/** Rótulo e cor de cada situação de OS — um lugar só para todas as telas. */
function os_status(string $status): array
{
    return [
        'ORCAMENTO' => ['Orçamento', 'neutral'], 'APROVADA' => ['Aprovada', 'neutral'], 'EM_EXECUCAO' => ['Em execução', 'info'],
        'AGUARDANDO_PECA' => ['Aguardando peça', 'warning'], 'AGUARDANDO_VALIDACAO' => ['Aguardando validação', 'info'], 'PRONTA' => ['Pronta', 'success'], 'ENTREGUE' => ['Entregue', 'success'],
        'RECUSADA' => ['Recusada', 'muted'], 'CANCELADA' => ['Cancelada', 'muted'],
    ][$status] ?? [$status, 'neutral'];
}

/** Monta a URL da tela atual trocando alguns parâmetros (filtros, página). */
function url_com(array $troca): string
{
    $q = array_diff_key($_GET, ['url' => 1]);
    foreach ($troca as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    $rota = trim((string) ($_GET['url'] ?? ''), '/');

    return './' . $rota . ($q ? '?' . http_build_query($q) : '');
}

/**
 * Ícone do Design System (SVG em linha, cor herdada do texto). Tamanhos do DS: 16 tabela, 20 menu,
 * 24 botão grande, 32 estado vazio. Sozinho num botão, o botão precisa de aria-label.
 */
function icone(string $nome, int $tamanho = 16, float $traco = 1.5, string $classe = ''): string
{
    $d = Icones::TRACOS[$nome] ?? Icones::TRACOS['info'];

    return '<svg class="icone' . ($classe !== '' ? ' ' . e($classe) : '') . '" width="' . $tamanho . '" height="' . $tamanho
        . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $traco
        . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . $d . '"/></svg>';
}

/**
 * Caminho SVG de uma curva suave que passa por cada ponto [[x, y], ...] (coordenadas de 0 a 100, x crescente).
 * Monotônica (Fritsch-Carlson): ondas suaves sem subir ou descer além dos valores reais entre dois pontos.
 */
function curva_suave(array $pts): string
{
    $pts = array_values($pts);
    $n = count($pts);
    if (!$n) return '';
    $f = fn (float $v): string => (string) round($v, 3);
    $d = 'M ' . $f($pts[0][0]) . ' ' . $f($pts[0][1]);
    if ($n === 1) return $d;

    $inc = [];
    for ($i = 0; $i < $n - 1; $i++) {
        $dx = $pts[$i + 1][0] - $pts[$i][0];
        $inc[$i] = $dx == 0 ? 0.0 : ($pts[$i + 1][1] - $pts[$i][1]) / $dx;
    }
    $tan = [$inc[0]];
    for ($i = 1; $i < $n - 1; $i++) $tan[$i] = $inc[$i - 1] * $inc[$i] <= 0 ? 0.0 : ($inc[$i - 1] + $inc[$i]) / 2;
    $tan[$n - 1] = $inc[$n - 2];
    for ($i = 0; $i < $n - 1; $i++) {
        if ($inc[$i] == 0) { $tan[$i] = 0.0; $tan[$i + 1] = 0.0; continue; }
        $a = $tan[$i] / $inc[$i]; $b = $tan[$i + 1] / $inc[$i]; $s = $a * $a + $b * $b;
        if ($s > 9) { $t = 3 / sqrt($s); $tan[$i] = $t * $a * $inc[$i]; $tan[$i + 1] = $t * $b * $inc[$i]; }
    }
    for ($i = 0; $i < $n - 1; $i++) {
        $dx = ($pts[$i + 1][0] - $pts[$i][0]) / 3;
        $d .= ' C ' . $f($pts[$i][0] + $dx) . ' ' . $f($pts[$i][1] + $tan[$i] * $dx)
            . ', ' . $f($pts[$i + 1][0] - $dx) . ' ' . $f($pts[$i + 1][1] - $tan[$i + 1] * $dx)
            . ', ' . $f($pts[$i + 1][0]) . ' ' . $f($pts[$i + 1][1]);
    }

    return $d;
}

/**
 * Caminho SVG de uma linha reta passando por cada ponto [[x, y], ...] (coordenadas de 0 a 100). Para séries diárias de
 * valor, onde suavizar inventaria receita nos dias sem movimento (a curva suave serve para médias móveis).
 */
function linha_reta(array $pts): string
{
    $d = '';
    foreach (array_values($pts) as $i => $p) {
        $d .= ($i === 0 ? 'M ' : ' L ') . round($p[0], 3) . ' ' . round($p[1], 3);
    }

    return $d;
}

/** Padrão da placa pelo formato: 'mercosul' (ABC1D23), 'antiga' (ABC1234) ou null se não reconhecer. */
function placa_tipo(?string $placa): ?string
{
    $p = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $placa));
    if (preg_match('/^[A-Z]{3}\d[A-Z]\d{2}$/', $p)) return 'mercosul';
    if (preg_match('/^[A-Z]{3}\d{4}$/', $p)) return 'antiga';

    return null;
}

/**
 * Placa desenhada no padrão dela, para reconhecer sem precisar ler: Mercosul com a faixa de cima no azul do
 * sistema; modelo antigo cinza, sem faixa e com o hífen (ABC-1234), como a placa de verdade.
 * $pequena: versão de tabela e lista. Com $href, a placa é um link.
 */
function placa_caixa(?string $placa, bool $pequena = true, ?string $href = null): string
{
    $tipo = placa_tipo($placa);
    $p = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $placa));
    $texto = $tipo === 'antiga' ? substr($p, 0, 3) . '-' . substr($p, 3) : ($tipo ? $p : strtoupper(trim((string) $placa)));
    $classe = 'placa-caixa' . ($pequena ? ' placa-caixa--p' : '') . ($tipo ? ' placa-caixa--' . $tipo : '');
    $titulo = $tipo === 'mercosul' ? 'Placa Mercosul' : ($tipo === 'antiga' ? 'Placa modelo antigo' : 'Placa');
    $atr = ' class="' . $classe . '" title="' . $titulo . '"';

    return $href !== null
        ? '<a' . $atr . ' href="' . e($href) . '">' . e($texto) . '</a>'
        : '<span' . $atr . '>' . e($texto) . '</span>';
}
