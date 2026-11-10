<?php /** Espera: $usuarios, $f, $p, $vagas [ativos, limite] */ $titulo = 'Usuários'; [$ativos, $limite] = $vagas; ?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Usuários</h1>
    <p class="page-header__subtitle">Equipe com acesso ao sistema · <?= $ativos ?> de <?= $limite ?> usuários ativos do plano</p>
  </div>
  <div class="page-header__actions">
    <?php if ($ativos < $limite): ?>
      <a class="btn btn--primary" href="./usuario-form"><i class="bi bi-plus-lg"></i> Novo usuário</a>
    <?php else: ?>
      <span class="form-dica">Limite do plano atingido</span>
    <?php endif; ?>
  </div>
</div>

<div class="card card--tabela">
  <form class="filtros" method="get" action="./usuarios">
    <div class="filtros__busca"><i class="bi bi-search"></i><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Nome ou e-mail" aria-label="Buscar"></div>
    <select name="perfil" aria-label="Perfil">
      <option value="">Todos os perfis</option>
      <?php foreach (Usuario::PERFIS as $k => $r): ?><option value="<?= $k ?>" <?= $f['perfil'] === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
    </select>
    <?php require V2_RAIZ . '/app/views/layout/_status.php'; ?>
  </form>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Usuário</th><th>Perfil</th><th>Comissão</th><th>Situação</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($usuarios as $u): $eu = (int) $u['id'] === Contexto::usuarioId(); ?>
          <tr data-href="./usuario-form?id=<?= (int) $u['id'] ?>" class="<?= $u['ativo'] ? '' : 'inativo' ?>">
            <td><a href="./usuario-form?id=<?= (int) $u['id'] ?>"><?= e($u['nome']) ?></a><?= $eu ? ' <span class="badge badge--info">você</span>' : '' ?><span class="sub"><?= e($u['email']) ?></span></td>
            <td><?= e(Usuario::PERFIS[$u['perfil']] ?? $u['perfil']) ?></td>
            <td><?= $u['comissao_tipo'] === 'NENHUMA' ? '—' : e(rtrim(rtrim((string) $u['comissao_percentual'], '0'), '.')) . '% ' . e(mb_substr(Usuario::COMISSOES[$u['comissao_tipo']], 2)) ?></td>
            <td><?php if (!$u['ativo']): ?><span class="badge badge--neutral">Inativo</span><?php elseif ($u['bloqueado_ate'] && strtotime($u['bloqueado_ate']) > time()): ?><span class="badge badge--warning">Bloqueado</span><?php else: ?><span class="badge badge--success">Ativo</span><?php endif; ?></td>
            <td class="num">
              <?php if (!$eu): ?>
                <form method="post" action="./usuario-ativo?id=<?= (int) $u['id'] ?>" style="display:inline" <?= $u['ativo'] ? 'data-confirmar="Desativar ' . e($u['nome']) . '? A sessão dele será encerrada."' : '' ?>>
                  <?= csrf_field() ?><input type="hidden" name="ativo" value="<?= $u['ativo'] ? 0 : 1 ?>">
                  <button class="btn btn--ghost btn--sm"><?= $u['ativo'] ? 'Desativar' : 'Reativar' ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if (!$usuarios): ?><div class="vazio">Nenhum usuário encontrado.</div><?php endif; ?>
  <?php require V2_RAIZ . '/app/views/layout/_paginacao.php'; ?>
</div>
