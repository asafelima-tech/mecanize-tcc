<?php
/**
 * Alerta inline — erro de validação, aviso e confirmação de ação.
 *
 * Substitui os blocos de <div style="background:var(--color-danger-soft)...">
 * que estavam copiados em ~25 telas, cada um com um tom levemente diferente.
 * Aqui o estilo mora no CSS (.alerta), no padrão de mensagens do Design System.
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

    // Sem cor (Design System): cada tom se distingue por preenchimento, contorno e ícone próprio.
    $alertaIcone = ['erro' => 'error', 'aviso' => 'warning', 'sucesso' => 'success', 'info' => 'info'][$alertaTipo];
?>
    <div class="alerta alerta--<?= $alertaTipo ?>" role="<?= $alertaTipo === 'erro' ? 'alert' : 'status' ?>">
        <?= icone($alertaIcone, 16, 1.5, 'alerta__icone') ?>
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
