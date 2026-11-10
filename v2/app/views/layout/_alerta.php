<?php
/**
 * Alerta inline — erro de validação, aviso e confirmação de ação.
 *
 * Substitui os blocos de <div style="background:var(--color-danger-soft)...">
 * que estavam copiados em ~25 telas, cada um com um tom levemente diferente.
 * Aqui o estilo mora no CSS (.alerta) e usa a mesma paleta das telas de erro.
 *
 * Erro de validação continua no formulário de propósito: mandar a pessoa pra
 * uma tela cheia por causa de um campo obrigatório faria perder tudo que ela
 * já tinha digitado.
 *
 * Uso:
 *   <?php $alertaTipo = 'erro'; $alertaMensagem = $erro;
 *         require V2_RAIZ . '/app/views/layout/_alerta.php'; ?>
 *
 * Variáveis (todas limpas ao final, pra não vazarem pro próximo require):
 *   $alertaTipo     'erro' | 'aviso' | 'sucesso' | 'info'   (padrão: erro)
 *   $alertaMensagem texto exibido — nada é renderizado se estiver vazio
 *   $alertaTitulo   (opcional) primeira linha em negrito
 */

$alertaTipo     = $alertaTipo     ?? 'erro';
$alertaMensagem = trim((string) ($alertaMensagem ?? ''));
$alertaTitulo   = $alertaTitulo   ?? '';

if ($alertaMensagem !== ''):
    if (!in_array($alertaTipo, ['erro', 'aviso', 'sucesso', 'info'], true)) {
        $alertaTipo = 'erro';
    }

    // Cada tom tem seu próprio ícone: a cor sozinha não comunica pra quem não
    // distingue verde de vermelho.
    $alertaIcone = [
        'erro'    => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        'aviso'   => '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'sucesso' => '<path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'info'    => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
    ][$alertaTipo];
?>
    <div class="alerta alerta--<?= $alertaTipo ?>" role="<?= $alertaTipo === 'erro' ? 'alert' : 'status' ?>">
        <svg class="alerta__icone" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $alertaIcone ?></svg>
        <div>
            <?php if ($alertaTitulo !== ''): ?>
                <strong class="alerta__titulo"><?= e($alertaTitulo) ?></strong>
            <?php endif; ?>
            <?= e($alertaMensagem) ?>
        </div>
    </div>
<?php
endif;

// Sem isso, uma tela com dois alertas herdaria o texto do primeiro no segundo.
unset($alertaTipo, $alertaMensagem, $alertaTitulo, $alertaIcone);
