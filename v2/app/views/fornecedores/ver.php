<?php
/**
 * Ficha de consulta do fornecedor, no formato das outras fichas: identificação no topo, os números da relação
 * (compras pelo custo da nota, notas, última compra e contas a pagar em aberto), os dados de contato
 * e observações, as entradas de mercadoria dele e, ao lado, os atalhos.
 * Ações: editar e registrar entrada; desativar e excluir no menu (as mesmas rotas de antes).
 * Espera: $fo, $resumo, $entradas
 */
$titulo = $fo['nome'];
$id = (int) $fo['id'];
$partes = preg_split('/\s+/', trim($fo['nome']));
$iniciais = mb_strtoupper(mb_substr($partes[0], 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : ''));
$digitos = strlen((string) $fo['documento']);
$vazio = '<span class="secundario">Não informado</span>';
?>
<header class="ver-topo">
  <div class="ver-topo__id">
    <span class="ver-topo__avatar ver-topo__avatar--quadrado" aria-hidden="true"><?= e($iniciais) ?></span>
    <div class="ver-topo__texto">
      <p class="ver-topo__voltar"><a href="./fornecedores"><?= icone('chevron-left', 14, 2) ?> Fornecedores</a></p>
      <h1><?= e($fo['nome']) ?></h1>
      <p class="ver-topo__meta">
        <?php if ($fo['documento']): ?><span class="ver-chip ver-chip--mono"><?= $digitos === 14 ? 'CNPJ' : 'CPF' ?> <?= e(documento_fmt($fo['documento'])) ?></span><?php endif; ?>
        <span class="ver-chip"><?= icone('calendar', 13) ?> Desde <?= data_br($fo['criado_em']) ?></span>
        <?php if (!$fo['ativo']): ?><span class="ver-chip ver-chip--escuro">Inativo</span><?php endif; ?>
      </p>
    </div>
  </div>
  <div class="ver-topo__acoes">
    <?php if ($fo['ativo']): ?><a class="btn btn--ghost btn--sm" href="./estoque-entrada"><?= icone('download') ?> Entrada de mercadoria</a><?php endif; ?>
    <a class="btn btn--primary btn--sm" href="./fornecedor-form?id=<?= $id ?>"><?= icone('edit') ?> Editar</a>
    <details class="menu-mais">
      <summary class="btn btn--ghost btn--sm btn--icone" aria-label="Mais ações"><?= icone('more-h') ?></summary>
      <div class="menu-mais__lista">
        <a href="./financeiro?tipo=PAGAR&amp;busca=<?= e(urlencode($fo['nome'])) ?>"><?= icone('wallet') ?>Contas a pagar</a>
        <form method="post" action="./fornecedor-ativo?id=<?= $id ?>" <?= (int) $fo['ativo'] ? 'data-confirmar="Desativar este fornecedor? Ele deixa de aparecer nas listas e nas escolhas, mas o histórico continua."' : '' ?>>
          <?= csrf_field() ?><input type="hidden" name="ativo" value="<?= (int) $fo['ativo'] ? 0 : 1 ?>">
          <button><?= icone($fo['ativo'] ? 'lock' : 'check') ?><?= (int) $fo['ativo'] ? 'Desativar' : 'Reativar' ?></button>
        </form>
        <form method="post" action="./fornecedor-excluir?id=<?= $id ?>" data-confirmar="Excluir este fornecedor definitivamente? Só é possível se não houver nada ligado a ele.">
          <?= csrf_field() ?><button><?= icone('trash') ?>Excluir</button>
        </form>
      </div>
    </details>
  </div>
</header>

<div class="ver-numeros" aria-label="Relação com o fornecedor">
  <div><span>Total comprado</span><strong><?= dinheiro($resumo['compras']) ?></strong><small>pelo custo das notas</small></div>
  <div><span>Notas de entrada</span><strong><?= $resumo['notas'] ?></strong><small>entradas de mercadoria</small></div>
  <div><span>Última compra</span><strong><?= $resumo['ultima'] ? data_br($resumo['ultima']) : '—' ?></strong><small><?= $resumo['ultima'] ? 'entrada mais recente' : 'nenhuma entrada ainda' ?></small></div>
  <div><span>A pagar</span><strong><?= dinheiro($resumo['pagar_saldo']) ?></strong><small><?= $resumo['pagar_n'] ? $resumo['pagar_n'] . ' conta' . ($resumo['pagar_n'] === 1 ? '' : 's') . ' em aberto' : 'nada em aberto' ?></small></div>
</div>

<div class="ver-grade ver-grade--uma">
  <div class="ver-principal">
    <section class="card ver-dados" aria-label="Dados do fornecedor">
      <div class="ver-grupo">
        <h2 class="ver-grupo__titulo"><?= icone('phone', 15) ?> Contato</h2>
        <dl class="ver-lista">
          <div><dt>Pessoa de contato</dt><dd><?= $fo['contato'] ? e($fo['contato']) : $vazio ?></dd></div>
          <div><dt>Telefone / WhatsApp</dt><dd class="tabular"><?= $fo['telefone'] ? e(telefone_fmt($fo['telefone'])) : $vazio ?></dd></div>
          <div><dt>E-mail</dt><dd><?= $fo['email'] ? '<a href="mailto:' . e($fo['email']) . '" class="ver-link">' . e($fo['email']) . '</a>' : $vazio ?></dd></div>
        </dl>
      </div>
      <div class="ver-grupo">
        <h2 class="ver-grupo__titulo"><?= icone('file', 15) ?> Observações</h2>
        <?php if ($fo['observacoes']): ?><p class="ver-texto"><?= e($fo['observacoes']) ?></p><?php else: ?><p class="secundario ver-vazio">Nenhuma observação.</p><?php endif; ?>
      </div>
    </section>

    <section class="card card--tabela ver-historico" aria-labelledby="ver-entradas">
      <header class="ver-historico__cabeca">
        <div><h2 id="ver-entradas">Entradas de mercadoria</h2><p><?= $entradas ? 'As notas mais recentes deste fornecedor.' : 'As notas deste fornecedor aparecem aqui.' ?></p></div>
        <a class="link-forte" href="./estoque">Ver no estoque</a>
      </header>
      <?php if (!$entradas): ?>
        <div class="vazio"><?= icone('download', 28, 1.25) ?>Nenhuma entrada de mercadoria deste fornecedor.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Nota</th><th>Data</th><th class="num">Itens</th><th>Registrado por</th><th class="num">Total</th></tr></thead>
            <tbody>
              <?php foreach ($entradas as $en): ?>
                <tr>
                  <td><strong><?= $en['documento'] ? e($en['documento']) : '<span class="secundario">Sem nota</span>' ?></strong></td>
                  <td class="tabular"><?= data_br($en['criado_em'], true) ?></td>
                  <td class="num"><?= (int) $en['itens'] ?></td>
                  <td class="secundario"><?= e($en['usuario'] ?: '—') ?></td>
                  <td class="num"><strong><?= dinheiro($en['total']) ?></strong></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>
</div>
