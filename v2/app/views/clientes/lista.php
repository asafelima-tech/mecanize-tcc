<?php /** Espera: $clientes, $f, $p */ $titulo = 'Clientes'; ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Clientes</h1>
    <p class="page-header__subtitle">Cadastro de clientes, com veículos e histórico de serviços.</p>
  </div>
  <div class="page-header__actions"><a class="btn btn--primary" href="./cliente-form"><i class="bi bi-plus-lg"></i> Novo cliente</a></div>
</div>

<div class="card card--tabela">
  <form class="filtros" method="get" action="./clientes">
    <div class="filtros__busca"><i class="bi bi-search"></i><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Nome, CPF/CNPJ, telefone ou e-mail" aria-label="Buscar"></div>
    <?php require V2_RAIZ . '/app/views/layout/_status.php'; ?>
  </form>

  <?php if (!$clientes): ?>
    <div class="vazio"><i class="bi bi-people"></i><?= $f['busca'] !== '' ? 'Nenhum cliente encontrado para essa busca.' : 'Nenhum cliente cadastrado ainda.' ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Cliente</th><th>Contato</th><th>Cidade</th><th class="num">Veículos</th><th>Último serviço</th></tr></thead>
        <tbody>
          <?php foreach ($clientes as $c): ?>
            <tr data-href="./cliente?id=<?= (int) $c['id'] ?>" class="<?= $c['ativo'] ? '' : 'inativo' ?>">
              <td><a href="./cliente?id=<?= (int) $c['id'] ?>"><?= e($c['nome']) ?></a><span class="sub"><?= $c['documento'] ? e(documento_fmt($c['documento'])) : ($c['tipo_pessoa'] === 'PJ' ? 'Pessoa jurídica' : 'Pessoa física') ?></span></td>
              <td><?= $c['telefone'] ? e(telefone_fmt($c['telefone'])) : '—' ?><span class="sub"><?= e($c['email'] ?? '') ?></span></td>
              <td><?= e(trim(($c['cidade'] ?? '') . ($c['uf'] ? ' / ' . $c['uf'] : '')) ?: '—') ?></td>
              <td class="num"><?= (int) $c['veiculos'] ?></td>
              <td><?= data_br($c['ultima_os']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php require V2_RAIZ . '/app/views/layout/_paginacao.php'; ?>
</div>
