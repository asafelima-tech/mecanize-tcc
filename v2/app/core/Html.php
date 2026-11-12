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

function dinheiro($valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

function data_br(?string $data, bool $comHora = false): string
{
    if (!$data) {
        return '—';
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
        'AGUARDANDO_PECA' => ['Aguardando peça', 'warning'], 'PRONTA' => ['Pronta', 'success'], 'ENTREGUE' => ['Entregue', 'success'],
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
