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

<?php
/*
 * Avisos no celular (até 900 px): folha modal que sobe de baixo, com os avisos em "Novos" e "Anteriores".
 * No computador o sininho abre a janela de cima (ancorada, sem véu), como sempre foi; o script do layout escolhe.
 */
$jeito = function (string $tipo): array {
    return match (true) {
        $tipo === 'PECA_FALTANDO', str_starts_with($tipo, 'PEDIDO') => ['part', 'peca'],
        $tipo === 'VALIDAR_OS', $tipo === 'EXECUCAO_VALIDADA' => ['checklist', 'ok'],
        $tipo === 'EXECUCAO_DEVOLVIDA', $tipo === 'PEDIDO_RECUSADA' => ['warning', 'alerta'],
        default => ['bell', 'neutro'],
    };
};
$grupos = ['Novos' => array_values(array_filter($avisos, fn($n) => $n['lida_em'] === null)),
           'Anteriores' => array_values(array_filter($avisos, fn($n) => $n['lida_em'] !== null))];
?>
<dialog id="dlg-avisos-celular" class="dlg dlg--avisos avisos2" aria-labelledby="dlg-avisos-celular-titulo">
  <div class="avisos2__alca" aria-hidden="true"></div>
  <header class="avisos2__topo">
    <div>
      <h3 id="dlg-avisos-celular-titulo">Avisos</h3>
      <p class="avisos2__resumo" data-avisos-resumo-celular><?= $avisosNaoLidos ? $avisosNaoLidos . ($avisosNaoLidos === 1 ? ' não lido' : ' não lidos') : 'Tudo em dia' ?></p>
    </div>
    <button type="button" class="avisos2__fechar" data-fechar-avisos-celular aria-label="Fechar avisos"><?= icone('close', 18, 2) ?></button>
  </header>
  <?php if ($avisosNaoLidos): ?>
    <form method="post" action="./notificacoes-ler-todas" class="avisos2__ler" data-avisos-ler-todas-celular>
      <?= csrf_field() ?><input type="hidden" name="voltar" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
      <button><?= icone('check', 15, 2) ?> Marcar todos como lidos</button>
    </form>
  <?php endif; ?>

  <div class="avisos2__corpo">
    <?php if (!$avisos): ?>
      <div class="avisos2__vazio">
        <span class="avisos2__vazio-icone"><?= icone('bell', 26, 1.5) ?></span>
        <strong>Nenhum aviso por enquanto</strong>
        <span>Validações, pedidos de material e peças em falta aparecem aqui.</span>
      </div>
    <?php else: ?>
      <?php foreach ($grupos as $rotulo => $lista): if (!$lista) continue; ?>
        <section class="avisos2__grupo" data-avisos-grupo="<?= $rotulo === 'Novos' ? 'novos' : 'lidos' ?>">
          <h4><?= $rotulo ?></h4>
          <ul>
            <?php foreach ($lista as $n): $novo = $n['lida_em'] === null; [$ic, $tom] = $jeito((string) $n['tipo']); ?>
              <li>
                <a class="aviso2<?= $novo ? ' aviso2--novo' : '' ?>" href="./notificacao?id=<?= (int) $n['id'] ?>">
                  <span class="aviso2__icone aviso2__icone--<?= $tom ?>"><?= icone($ic, 18) ?></span>
                  <span class="aviso2__texto">
                    <span class="aviso2__linha"><strong><?= e($n['titulo']) ?></strong><small><?= e($quando($n['criada_em'])) ?></small></span>
                    <?php if ($n['mensagem']): ?><span class="aviso2__msg"><?= e($n['mensagem']) ?></span><?php endif; ?>
                  </span>
                  <?php if ($novo): ?><span class="aviso2__ponto" aria-label="Não lido"></span><?php endif; ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</dialog>
