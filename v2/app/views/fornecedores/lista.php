<?php /** Espera: $fornecedores, $f, $p */ $titulo = 'Fornecedores'; ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Fornecedores</h1>
    <p class="page-header__subtitle">De quem a oficina compra peças e serviços.</p>
  </div>
  <div class="page-header__actions"><a class="btn btn--primary" href="./fornecedor-form"><i class="bi bi-plus-lg"></i> Novo fornecedor</a></div>
</div>

<div class="card card--tabela">
  <form class="filtros" method="get" action="./fornecedores">
    <div class="filtros__busca"><i class="bi bi-search"></i><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Nome, contato ou CNPJ" aria-label="Buscar"></div>
    <?php require V2_RAIZ . '/app/views/layout/_status.php'; ?>
  </form>
  <?php if (!$fornecedores): ?>
    <div class="vazio"><i class="bi bi-truck"></i>Nenhum fornecedor <?= $f['busca'] !== '' ? 'encontrado' : 'cadastrado ainda' ?>.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Fornecedor</th><th>Contato</th><th>Telefone</th><th>E-mail</th></tr></thead>
        <tbody>
          <?php foreach ($fornecedores as $fo): ?>
            <tr data-href="./fornecedor?id=<?= (int) $fo['id'] ?>" class="<?= $fo['ativo'] ? '' : 'inativo' ?>">
              <td><a href="./fornecedor?id=<?= (int) $fo['id'] ?>"><?= e($fo['nome']) ?></a><span class="sub"><?= $fo['documento'] ? e(documento_fmt($fo['documento'])) : '' ?></span></td>
              <td><?= e($fo['contato'] ?? '—') ?></td>
              <td><?= $fo['telefone'] ? e(telefone_fmt($fo['telefone'])) : '—' ?></td>
              <td><?= e($fo['email'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php require V2_RAIZ . '/app/views/layout/_paginacao.php'; ?>
</div>
