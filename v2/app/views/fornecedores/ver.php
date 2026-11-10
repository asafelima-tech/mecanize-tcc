<?php /** Espera: $fo */ $titulo = $fo['nome']; ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= e($fo['nome']) ?> <?php if (!$fo['ativo']): ?><span class="badge badge--neutral">Inativo</span><?php endif; ?></h1>
    <p class="page-header__subtitle"><a href="./fornecedores">Fornecedores</a></p>
  </div>
  <div class="page-header__actions acoes">
    <?php $rotaAtivo = 'fornecedor-ativo'; $rotaExcluir = 'fornecedor-excluir'; $registro = $fo; $nome = 'este fornecedor'; require V2_RAIZ . '/app/views/layout/_ativo.php'; ?>
    <a class="btn btn--primary btn--sm" href="./fornecedor-form?id=<?= (int) $fo['id'] ?>"><i class="bi bi-pencil"></i> Editar</a>
  </div>
</div>
<div class="card form-card">
  <dl class="dados">
    <div><dt>CPF/CNPJ</dt><dd><?= $fo['documento'] ? e(documento_fmt($fo['documento'])) : '—' ?></dd></div>
    <div><dt>Contato</dt><dd><?= e($fo['contato'] ?: '—') ?></dd></div>
    <div><dt>Telefone</dt><dd><?= $fo['telefone'] ? e(telefone_fmt($fo['telefone'])) : '—' ?></dd></div>
    <div><dt>E-mail</dt><dd><?= e($fo['email'] ?: '—') ?></dd></div>
  </dl>
  <?php if ($fo['observacoes']): ?><p style="margin-top:14px;font-size:13px;white-space:pre-line"><?= e($fo['observacoes']) ?></p><?php endif; ?>
</div>
