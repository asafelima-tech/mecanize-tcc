<?php /** Espera: $notificacoes */ $titulo = 'Avisos'; $naoLidas = count(array_filter($notificacoes, fn($n) => $n['lida_em'] === null)); ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Avisos</h1>
    <p class="page-header__subtitle"><?= $naoLidas ? $naoLidas . ' não lido(s)' : 'Tudo lido' ?></p>
  </div>
  <?php if ($naoLidas): ?>
    <form method="post" action="./notificacoes-ler-todas"><?= csrf_field() ?><button class="btn btn--ghost"><i class="bi bi-check2-all"></i> Marcar todos como lidos</button></form>
  <?php endif; ?>
</div>
<div class="card card--tabela">
  <?php if (!$notificacoes): ?>
    <div class="vazio"><i class="bi bi-bell"></i>Nenhum aviso por enquanto. Quando uma OS parar por falta de peça, o administrador é avisado aqui.</div>
  <?php else: ?>
    <div class="table-wrap"><table class="table"><tbody>
      <?php foreach ($notificacoes as $n): ?>
        <tr data-href="./notificacao?id=<?= (int) $n['id'] ?>" class="<?= $n['lida_em'] ? 'linha-apagada' : '' ?>">
          <td style="width:16px"><?php if (!$n['lida_em']): ?><span class="ponto-novo" aria-label="Não lido"></span><?php endif; ?></td>
          <td><a href="./notificacao?id=<?= (int) $n['id'] ?>"><?= e($n['titulo']) ?></a><?php if ($n['mensagem']): ?><span class="sub"><?= e($n['mensagem']) ?></span><?php endif; ?></td>
          <td class="num form-dica"><?= data_br($n['criada_em'], true) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
