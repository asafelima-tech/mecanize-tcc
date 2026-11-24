<?php
/**
 * Execução de um serviço pelo técnico (celular primeiro). Uma tela só, de cima para baixo:
 * o carro, o que fazer (serviços, relato, diagnóstico, orientações), as peças previstas, os materiais
 * que ele usou, pedidos e problemas, fotos e o andamento. A ação principal fica presa embaixo,
 * ao alcance do polegar: "Começar serviço" ou "Terminei o serviço".
 * Sem valores. Espera: $os, $itens, $materiais, $pedidos, $catalogo, $fotos, $andamento
 */
$st = $os['status'];
$qtd = fn($n) => rtrim(rtrim(number_format((float) $n, 3, ',', '.'), '0'), ',');
$comecou = (bool) $os['execucao_iniciada_em'];
$trabalhando = in_array($st, ['EM_EXECUCAO', 'AGUARDANDO_PECA'], true);
$podeMateriais = $trabalhando;
$devolvida = $trabalhando && $os['execucao_devolvida_em'] && $os['execucao_devolvida_em'] > ($os['execucao_enviada_em'] ?? '');
$servicos = array_values(array_filter($itens, fn($i) => $i['tipo'] === 'SERVICO'));
$pecas = array_values(array_filter($itens, fn($i) => $i['tipo'] === 'PECA'));
$ativosMat = array_values(array_filter($materiais, fn($m) => $m['status'] !== 'CANCELADO'));
$pendentes = count(array_filter($materiais, fn($m) => $m['status'] === 'PENDENTE'));
$rotMat = ['PENDENTE' => ['Aguardando conferência', 'espera'], 'APROVADO' => ['Aprovado', 'feito'], 'RECUSADO' => ['Recusado', 'alerta'], 'CANCELADO' => ['Retirado', 'apagado']];
$rotSituacao = [
    'APROVADA' => 'Para começar', 'EM_EXECUCAO' => $comecou ? 'Em andamento' : 'Para começar', 'AGUARDANDO_PECA' => 'Aguardando peça',
    'AGUARDANDO_VALIDACAO' => 'Aguardando validação', 'PRONTA' => 'Validado', 'ENTREGUE' => 'Entregue',
];
$tomSituacao = ['APROVADA' => 'novo', 'EM_EXECUCAO' => $comecou ? 'andamento' : 'novo', 'AGUARDANDO_PECA' => 'espera', 'AGUARDANDO_VALIDACAO' => 'espera', 'PRONTA' => 'feito', 'ENTREGUE' => 'feito'];
$acoes = [
    'STATUS' => 'Situação', 'EXECUCAO_INICIADA' => 'Começou o serviço', 'EXECUCAO_ENVIADA' => 'Enviado para validação', 'EXECUCAO_VALIDADA' => 'Validado pelo administrador',
    'EXECUCAO_DEVOLVIDA' => 'Devolvido para corrigir', 'MATERIAL_INFORMADO' => 'Material registrado', 'MATERIAL_CANCELADO' => 'Material retirado',
    'MATERIAIS_VALIDADOS' => 'Materiais conferidos', 'PEDIDO_MATERIAL' => 'Pedido de material', 'ANEXO' => 'Foto enviada',
];
?>
<div class="tec tec--servico">
  <p class="tec-voltar"><a href="./servicos"><?= icone('chevron-left', 16, 2) ?> Meus serviços</a></p>

  <header class="tec-carro">
    <div class="tec-carro__topo">
      <span class="tec-situacao tec-situacao--<?= $devolvida ? 'alerta' : $tomSituacao[$st] ?>"><?= $devolvida ? 'Devolvido para corrigir' : e($rotSituacao[$st]) ?></span>
      <span class="tec-cartao__os"><?= os_numero($os['numero']) ?></span>
    </div>
    <h1 class="tec-carro__modelo"><?= e($os['modelo'] ?: 'Veículo') ?></h1>
    <p class="tec-carro__dados"><span class="tec-placa tec-placa--grande"><?= e($os['placa']) ?></span>
      <span><?= e(implode(' · ', array_filter([$os['cor'], $os['ano'] ? (string) (int) $os['ano'] : null, $os['combustivel'], $os['km_veiculo'] ? number_format((int) $os['km_veiculo'], 0, ',', '.') . ' km' : null]))) ?></span></p>
    <p class="tec-carro__cliente"><span><?= icone('user', 14) ?> <?= e($os['cliente']) ?></span><?php if ($os['previsao_conclusao'] && !in_array($st, ['AGUARDANDO_VALIDACAO', 'PRONTA', 'ENTREGUE'], true)): ?><span><?= icone('clock', 14) ?> Entregar <?= data_br($os['previsao_conclusao'], true) ?></span><?php endif; ?></p>
  </header>

  <?php if ($devolvida): ?>
    <div class="tec-aviso tec-aviso--alerta"><?= icone('warning', 18) ?><div><strong>O administrador devolveu este serviço</strong><?= e((string) $os['execucao_devolucao_motivo']) ?><small><?= data_br($os['execucao_devolvida_em'], true) ?></small></div></div>
  <?php elseif ($st === 'AGUARDANDO_VALIDACAO'): ?>
    <div class="tec-aviso"><?= icone('clock', 18) ?><div><strong>Enviado para validação</strong>Você informou que terminou em <?= data_br($os['execucao_enviada_em'], true) ?>. Agora o administrador confere.</div></div>
  <?php elseif ($st === 'AGUARDANDO_PECA'): ?>
    <div class="tec-aviso"><?= icone('part', 18) ?><div><strong>Aguardando peça</strong>Falta no estoque uma peça prevista. O administrador já foi avisado; quando ela chegar, o serviço volta para você.</div></div>
  <?php elseif (in_array($st, ['PRONTA', 'ENTREGUE'], true)): ?>
    <div class="tec-aviso tec-aviso--ok"><?= icone('success', 18) ?><div><strong>Serviço validado</strong>O administrador aprovou a execução.</div></div>
  <?php endif; ?>

  <nav class="tec-atalhos" aria-label="Partes do serviço">
    <a href="#fazer">O que fazer</a><a href="#materiais">Materiais<?= $ativosMat ? ' (' . count($ativosMat) . ')' : '' ?></a><a href="#pedidos">Pedidos<?= $pedidos ? ' (' . count($pedidos) . ')' : '' ?></a><a href="#fotos">Fotos<?= $fotos ? ' (' . count($fotos) . ')' : '' ?></a>
  </nav>

  <div class="tec-grade">
  <div class="tec-grade__principal">

  <!-- O que fazer -->
  <section class="tec-bloco" id="fazer" aria-labelledby="t-fazer">
    <h2 class="tec-bloco__titulo" id="t-fazer">O que fazer</h2>
    <?php if ($servicos): ?>
      <ul class="tec-itens">
        <?php foreach ($servicos as $i): ?>
          <li class="<?= $i['meu'] ? 'is-meu' : '' ?>"><?= icone('mechanic', 18) ?><span><?= e($i['descricao']) ?><?php if ($qtd($i['quantidade']) !== '1'): ?> <small>× <?= $qtd($i['quantidade']) ?></small><?php endif; ?>
            <?php if ($i['tecnico'] && !$i['meu']): ?><small>com <?= e($i['tecnico']) ?></small><?php elseif ($i['meu']): ?><small>seu</small><?php endif; ?></span></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php foreach ([['message', 'Relato do cliente', $os['defeito_relatado']], ['checklist', 'Diagnóstico', $os['diagnostico']],
                    ['info', 'Orientações do administrador', trim(implode("\n\n", array_filter([$os['observacoes_internas'], $os['observacoes']])))]] as [$ic, $rot, $txt]): ?>
      <?php if ($txt): ?>
        <div class="tec-texto<?= $ic === 'info' ? ' tec-texto--destaque' : '' ?>"><h3><?= icone($ic, 16) ?> <?= $rot ?></h3><p><?= nl2br(e($txt)) ?></p></div>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if (!$servicos && !$os['defeito_relatado'] && !$os['diagnostico']): ?><p class="tec-vazio-linha">Sem descrição. Fale com o administrador.</p><?php endif; ?>

    <?php if ($pecas): ?>
      <h3 class="tec-bloco__sub">Peças previstas</h3>
      <ul class="tec-itens tec-itens--pecas">
        <?php foreach ($pecas as $i): ?>
          <li><?= icone('part', 18) ?><span><?= e($i['descricao']) ?> <small>× <?= $qtd($i['quantidade']) ?></small></span>
            <em class="<?= $i['estoque_status'] === 'PENDENTE' ? 'tec-falta' : '' ?>"><?= $i['estoque_status'] === 'BAIXADO' ? 'Separada' : ($trabalhando || $st === 'APROVADA' ? ($st === 'APROVADA' ? 'Sai ao começar' : 'Em falta') : 'Usada') ?></em></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <!-- Materiais usados -->
  <section class="tec-bloco" id="materiais" aria-labelledby="t-mat">
    <h2 class="tec-bloco__titulo" id="t-mat">Materiais que usei</h2>
    <p class="tec-bloco__dica">Registre o que usou além das peças previstas. O administrador confere antes de sair do estoque.</p>
    <?php if ($materiais): ?>
      <ul class="tec-materiais">
        <?php foreach ($materiais as $m): [$rm, $tm] = $rotMat[$m['status']]; ?>
          <li class="tec-material tec-material--<?= $tm ?>">
            <div><strong><?= e($m['nome']) ?></strong><span class="tec-material__qtd"><?= $qtd($m['quantidade']) ?> un.</span>
              <small><?= e($rm) ?><?= $m['observacao'] ? ' · ' . e($m['observacao']) : '' ?><?= $m['status'] === 'RECUSADO' && $m['motivo_decisao'] ? ' · ' . e($m['motivo_decisao']) : '' ?></small></div>
            <?php if ($m['status'] === 'PENDENTE' && $podeMateriais && (int) $m['tecnico_id'] === (int) Contexto::usuarioId()): ?>
              <form method="post" action="./servico-material?id=<?= (int) $os['id'] ?>">
                <?= csrf_field() ?><input type="hidden" name="operacao" value="cancelar"><input type="hidden" name="material_id" value="<?= (int) $m['id'] ?>">
                <button class="tec-botao-icone" aria-label="Retirar <?= e($m['nome']) ?>" title="Retirar"><?= icone('trash', 18) ?></button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($podeMateriais && $comecou): ?>
      <details class="tec-form" <?= !$materiais ? 'open' : '' ?>>
        <summary class="tec-botao tec-botao--secundario"><?= icone('plus', 18, 2) ?> Registrar material</summary>
        <form method="post" action="./servico-material?id=<?= (int) $os['id'] ?>" class="tec-form__corpo">
          <?= csrf_field() ?><input type="hidden" name="operacao" value="registrar">
          <?php $pecaId = 'mat'; $pecaObrigatoria = true; require __DIR__ . '/_peca.php'; ?>
          <div class="tec-campo">
            <label class="tec-campo__rotulo" for="mat-qtd">Quantidade</label>
            <div class="tec-qtd" data-tec-qtd>
              <button type="button" data-passo="-1" aria-label="Menos um"><?= icone('minus', 20, 2) ?></button>
              <input id="mat-qtd" name="quantidade" value="1" inputmode="decimal" required>
              <button type="button" data-passo="1" aria-label="Mais um"><?= icone('plus', 20, 2) ?></button>
            </div>
          </div>
          <div class="tec-campo"><label class="tec-campo__rotulo" for="mat-obs">Observação (opcional)</label><input id="mat-obs" name="observacao" maxlength="255" placeholder="Ex.: trocado o par dianteiro"></div>
          <button class="tec-botao"><?= icone('check', 18, 2) ?> Registrar</button>
        </form>
      </details>
    <?php elseif ($podeMateriais || $st === 'APROVADA'): ?>
      <p class="tec-vazio-linha">Comece o serviço para registrar materiais.</p>
    <?php elseif (!$materiais): ?>
      <p class="tec-vazio-linha">Nenhum material registrado.</p>
    <?php endif; ?>
  </section>

  <!-- Pedidos e problemas -->
  <section class="tec-bloco" id="pedidos" aria-labelledby="t-ped">
    <h2 class="tec-bloco__titulo" id="t-ped">Pedidos e problemas</h2>
    <?php if ($pedidos): ?>
      <ul class="tec-materiais">
        <?php foreach ($pedidos as $p): [$rp, $cp] = Solicitacao::SITUACOES[$p['status']]; $nome = $p['produto'] ?? $p['item_descricao']; ?>
          <li class="tec-material tec-material--<?= ['RECEBIDA' => 'espera', 'APROVADA' => 'andamento', 'ATENDIDA' => 'feito', 'RECUSADA' => 'alerta'][$p['status']] ?>">
            <div><strong><?= e(Solicitacao::TIPOS[$p['tipo']][0]) ?><?= $nome ? ': ' . e($nome) : '' ?></strong><?php if ($p['quantidade']): ?><span class="tec-material__qtd"><?= $qtd($p['quantidade']) ?> un.</span><?php endif; ?>
              <small><?= e($rp) ?> · <?= data_br($p['criado_em'], true) ?><?= $p['resposta'] ? ' · “' . e($p['resposta']) . '”' : '' ?></small></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if (in_array($st, ['APROVADA', 'EM_EXECUCAO', 'AGUARDANDO_PECA', 'AGUARDANDO_VALIDACAO'], true)): ?>
      <details class="tec-form">
        <summary class="tec-botao tec-botao--secundario"><?= icone('message', 18) ?> Pedir ao administrador</summary>
        <form method="post" action="./pedido-criar" class="tec-form__corpo">
          <?= csrf_field() ?><input type="hidden" name="ordem_servico_id" value="<?= (int) $os['id'] ?>"><input type="hidden" name="voltar_os" value="<?= (int) $os['id'] ?>">
          <fieldset class="tec-tipos">
            <legend class="tec-campo__rotulo">O que aconteceu?</legend>
            <?php foreach (Solicitacao::TIPOS as $k => [$rot]): ?>
              <label><input type="radio" name="tipo" value="<?= $k ?>" required><span><?= e($rot) ?></span></label>
            <?php endforeach; ?>
          </fieldset>
          <?php $pecaId = 'ped'; $pecaRotulo = 'Peça do catálogo (se tiver)'; require __DIR__ . '/_peca.php'; ?>
          <div class="tec-campo"><label class="tec-campo__rotulo" for="ped-item">Ou escreva o nome da peça</label><input id="ped-item" name="item_descricao" maxlength="150" placeholder="Ex.: correia do alternador 6PK"></div>
          <div class="tec-campo"><label class="tec-campo__rotulo" for="ped-qtd">Quantidade (opcional)</label><input id="ped-qtd" name="quantidade" inputmode="decimal" placeholder="1"></div>
          <div class="tec-campo"><label class="tec-campo__rotulo" for="ped-just">Explique</label><textarea id="ped-just" name="justificativa" rows="3" maxlength="500" required placeholder="Ex.: a pastilha da prateleira acabou; o disco também está gasto"></textarea></div>
          <button class="tec-botao"><?= icone('send', 18) ?> Enviar ao administrador</button>
        </form>
      </details>
    <?php elseif (!$pedidos): ?>
      <p class="tec-vazio-linha">Nenhum pedido neste serviço.</p>
    <?php endif; ?>
  </section>
  </div>

  <aside class="tec-grade__lado" aria-label="Ação, fotos e andamento">
  <!-- Ação principal, presa embaixo -->
  <?php if ($st === 'APROVADA' || ($trabalhando && !$comecou)): ?>
    <div class="tec-acao">
      <form method="post" action="./servico-acao?id=<?= (int) $os['id'] ?>">
        <?= csrf_field() ?><input type="hidden" name="acao" value="comecar">
        <p class="tec-acao__dica">Próximo passo<?= $pecas && $st === 'APROVADA' ? ' · as peças previstas saem do estoque' : '' ?></p>
        <button class="tec-botao tec-botao--grande"><?= icone('arrow-right', 20, 2) ?> Começar serviço</button>
      </form>
    </div>
  <?php elseif ($st === 'EM_EXECUCAO'): ?>
    <div class="tec-acao">
      <p class="tec-acao__dica">Quando terminar · o administrador confere e valida</p>
      <button type="button" class="tec-botao tec-botao--grande" data-abrir="dlg-terminar"><?= icone('success', 20, 2) ?> Terminei o serviço</button>
    </div>
    <dialog id="dlg-terminar" class="dlg tec-folha">
      <form method="post" action="./servico-acao?id=<?= (int) $os['id'] ?>">
        <?= csrf_field() ?><input type="hidden" name="acao" value="terminar">
        <h3>Terminou o serviço?</h3>
        <p>O serviço vai para o administrador conferir. Depois de enviar, você não muda mais os materiais.</p>
        <ul class="tec-resumo">
          <li><?= icone('part', 16) ?> <?= $pendentes ? $pendentes . ($pendentes === 1 ? ' material registrado' : ' materiais registrados') : 'Nenhum material registrado além do previsto' ?></li>
          <li><?= icone('image', 16) ?> <?= count($fotos) ?> <?= count($fotos) === 1 ? 'foto' : 'fotos' ?></li>
        </ul>
        <div class="tec-campo"><label class="tec-campo__rotulo" for="relato">O que foi feito (opcional)</label>
          <textarea id="relato" name="relato" rows="4" maxlength="2000" placeholder="Ex.: troquei as pastilhas e regulei o freio de mão. O disco traseiro está no limite."></textarea></div>
        <div class="tec-folha__acoes"><button type="button" class="tec-botao tec-botao--secundario" data-fechar>Voltar</button><button class="tec-botao"><?= icone('send', 18) ?> Enviar para validação</button></div>
      </form>
    </dialog>
  <?php elseif ($st === 'AGUARDANDO_PECA'): ?>
    <div class="tec-acao"><p class="tec-acao__info"><?= icone('part', 18) ?> Aguardando a peça chegar</p></div>
  <?php endif; ?>

  <!-- Fotos -->
  <section class="tec-bloco" id="fotos" aria-labelledby="t-fotos">
    <h2 class="tec-bloco__titulo" id="t-fotos">Fotos</h2>
    <?php if ($fotos): ?>
      <div class="tec-fotos">
        <?php foreach ($fotos as $a): ?>
          <a href="./anexo?id=<?= (int) $a['id'] ?>" target="_blank" rel="noopener"><img src="./anexo?id=<?= (int) $a['id'] ?>" alt="<?= e(Anexo::CATEGORIAS_OS[$a['categoria']] ?? 'Foto') ?>" loading="lazy"><span><?= e(Anexo::CATEGORIAS_OS[$a['categoria']] ?? '') ?></span></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (in_array($st, ['EM_EXECUCAO', 'AGUARDANDO_PECA', 'APROVADA'], true) && count($fotos) < Anexo::MAX_POR_DONO): ?>
      <form method="post" action="./os-anexar?os=<?= (int) $os['id'] ?>" enctype="multipart/form-data" class="tec-foto-form">
        <?= csrf_field() ?>
        <div class="tec-segmento" role="radiogroup" aria-label="Tipo da foto">
          <?php foreach (['FOTO_ANTES' => 'Antes', 'FOTO_DURANTE' => 'Durante', 'FOTO_DEPOIS' => 'Depois', 'PROBLEMA' => 'Problema'] as $k => $r): ?>
            <label><input type="radio" name="categoria" value="<?= $k ?>" <?= $k === 'FOTO_DURANTE' ? 'checked' : '' ?>><span><?= $r ?></span></label>
          <?php endforeach; ?>
        </div>
        <label class="tec-botao tec-botao--secundario tec-foto-form__escolher"><?= icone('image', 18) ?> Tirar ou escolher foto
          <input type="file" name="arquivos[]" accept="image/jpeg,image/png,image/webp" multiple required data-enviar-ao-escolher></label>
        <input type="hidden" name="descricao" value="">
      </form>
    <?php elseif (!$fotos): ?>
      <p class="tec-vazio-linha">Nenhuma foto.</p>
    <?php endif; ?>
  </section>

  <?php if ($andamento): ?>
    <section class="tec-bloco" aria-labelledby="t-and">
      <h2 class="tec-bloco__titulo" id="t-and">Andamento</h2>
      <ol class="tec-linha">
        <?php foreach ($andamento as $h): $d = $h['dados'] ? json_decode($h['dados'], true) : []; ?>
          <li><strong><?= e($acoes[$h['acao']] ?? $h['acao']) ?><?= $h['acao'] === 'STATUS' && $h['estado_novo'] ? ': ' . e(os_status($h['estado_novo'])[0]) : '' ?><?= in_array($h['acao'], ['MATERIAL_INFORMADO', 'MATERIAL_CANCELADO'], true) ? ': ' . e($d['produto'] ?? '') . ' (' . $qtd($d['quantidade'] ?? 0) . ')' : '' ?></strong>
            <?php if ($h['motivo'] && in_array($h['acao'], ['EXECUCAO_DEVOLVIDA', 'EXECUCAO_ENVIADA', 'STATUS'], true)): ?><span>“<?= e($h['motivo']) ?>”</span><?php endif; ?>
            <small><?= data_br($h['criado_em'], true) ?><?= $h['autor'] ? ' · ' . e($h['autor']) : '' ?></small></li>
        <?php endforeach; ?>
      </ol>
    </section>
  <?php endif; ?>
  </aside>
  </div>
</div>

<?php require __DIR__ . '/_peca_script.php'; ?>
<script>
  (function () {
    // Abrir/fechar a folha de "terminei" (os mesmos data-abrir/data-fechar das outras telas).
    document.querySelectorAll('[data-abrir]').forEach(function (b) {
      b.addEventListener('click', function () { var d = document.getElementById(b.dataset.abrir); if (d && d.showModal) d.showModal(); });
    });
    document.querySelectorAll('dialog [data-fechar]').forEach(function (b) { b.addEventListener('click', function () { b.closest('dialog').close(); }); });
    // Foto: envia assim que escolhe (um toque a menos), mostrando que está enviando.
    document.querySelectorAll('[data-enviar-ao-escolher]').forEach(function (i) {
      i.addEventListener('change', function () { if (!i.files.length) return; i.closest('label').classList.add('is-enviando'); i.form.submit(); });
    });
    // Atalhos presos no topo: a divisória só aparece quando a faixa está presa (há conteúdo passando por baixo).
    var atalhos = document.querySelector('.tec-atalhos');
    if (atalhos) {
      var conferir = function () {
        var topo = parseFloat(getComputedStyle(atalhos).top) || 0;
        atalhos.classList.toggle('is-presa', atalhos.getBoundingClientRect().top <= topo + 0.5 && window.scrollY > 0);
      };
      window.addEventListener('scroll', conferir, { passive: true });
      window.addEventListener('resize', conferir);
      conferir();
    }
    // Voltando de uma ação, rola até a parte certa (?aba=materiais | pedidos | fotos).
    var aba = new URLSearchParams(location.search).get('aba');
    var alvo = aba && document.getElementById(aba);
    if (alvo) alvo.scrollIntoView({ block: 'start' });
  })();
</script>
