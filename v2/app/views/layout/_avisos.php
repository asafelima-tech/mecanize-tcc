<?php
/**
 * Avisos: janela que abre pelo sininho do topo (não é uma tela). Fica ancorada abaixo do sininho,
 * fecha com Esc, com o X ou clicando fora. Cada aviso leva ao que ele aponta (e fica lido);
 * "Marcar todos como lidos" acontece sem sair da página.
 * Espera: $avisos (Notificacao::listar), $avisosNaoLidos
 */
$quando = function (string $dt): string {
    $t = strtotime($dt);
    $hora = date('H:i', $t);
    if (date('Y-m-d', $t) === date('Y-m-d')) return 'Hoje, ' . $hora;
    if (date('Y-m-d', $t) === date('Y-m-d', strtotime('-1 day'))) return 'Ontem, ' . $hora;
    return date('d/m', $t) . ($t < strtotime('-1 year') ? '/' . date('Y', $t) : '') . ', ' . $hora;
};
?>
<dialog id="dlg-avisos" class="dlg dlg--avisos" aria-labelledby="dlg-avisos-titulo">
  <div class="avisos__topo">
    <div>
      <h3 id="dlg-avisos-titulo">Avisos</h3>
      <p class="avisos__resumo" data-avisos-resumo><?= $avisosNaoLidos ? $avisosNaoLidos . ($avisosNaoLidos === 1 ? ' não lido' : ' não lidos') : 'Tudo lido' ?></p>
    </div>
    <div class="avisos__acoes">
      <?php if ($avisosNaoLidos): ?>
        <form method="post" action="./notificacoes-ler-todas" data-avisos-ler-todas>
          <?= csrf_field() ?><input type="hidden" name="voltar" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
          <button class="btn btn--fantasma btn--sm"><?= icone('check') ?> Marcar todos como lidos</button>
        </form>
      <?php endif; ?>
      <button type="button" class="avisos__fechar" data-fechar-avisos aria-label="Fechar avisos"><?= icone('close', 16) ?></button>
    </div>
  </div>

  <?php if (!$avisos): ?>
    <div class="avisos__vazio">
      <span class="avisos__vazio-icone"><?= icone('bell', 22) ?></span>
      <strong>Nenhum aviso por enquanto</strong>
      <span>Quando uma OS parar por falta de peça, o administrador é avisado aqui.</span>
    </div>
  <?php else: ?>
    <ul class="avisos__lista">
      <?php foreach ($avisos as $n): $novo = $n['lida_em'] === null; ?>
        <li>
          <a class="aviso<?= $novo ? ' aviso--novo' : '' ?>" href="./notificacao?id=<?= (int) $n['id'] ?>">
            <span class="aviso__icone"><?= icone($n['tipo'] === 'PECA_FALTANDO' ? 'part' : 'bell', 16) ?></span>
            <span class="aviso__texto">
              <strong><?= e($n['titulo']) ?></strong>
              <?php if ($n['mensagem']): ?><span><?= e($n['mensagem']) ?></span><?php endif; ?>
              <small><?= e($quando($n['criada_em'])) ?></small>
            </span>
            <?php if ($novo): ?><span class="ponto-novo" aria-label="Não lido"></span><?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</dialog>
