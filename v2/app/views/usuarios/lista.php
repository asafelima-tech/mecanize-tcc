<?php
/**
 * Usuários (Design System, tela 23): números da equipe, abas ativos/inativos, a lista com perfil,
 * comissão e data de cadastro, e o que cada perfil pode fazer.
 * Espera: $usuarios, $f, $p, $vagas [ativos, limite], $resumo
 */
$titulo = 'Usuários';
[$ativos, $limite] = $vagas;
$iniciais = function (string $nome): string {
    $p = preg_split('/\s+/', trim($nome));
    return mb_strtoupper(mb_substr($p[0], 0, 1) . (count($p) > 1 ? mb_substr(end($p), 0, 1) : ''));
};
$corPerfil = ['ADMIN' => 'success', 'ATENDENTE' => 'info', 'TECNICO' => 'neutral'];
$inativos = $resumo['total'] - $resumo['ativos'];
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title">Usuários</h1>
    <p class="page-header__subtitle">Quem acessa o sistema e o que cada perfil pode fazer · <?= $ativos ?> de <?= $limite ?> usuários ativos do plano.</p>
  </div>
  <div class="page-header__actions">
    <?php if ($ativos < $limite): ?>
      <a class="btn btn--primary" href="./usuario-form"><?= icone('plus') ?> Novo usuário</a>
    <?php else: ?>
      <span class="etiqueta">Limite do plano atingido</span>
    <?php endif; ?>
  </div>
</div>

<div class="stat-grid stat-grid--icone">
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Total de usuários</span><?= icone('users') ?></div>
    <span class="stat-card__value"><?= (int) $resumo['total'] ?></span>
    <span class="stat-card__pe">na oficina</span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Ativos</span><?= icone('check') ?></div>
    <span class="stat-card__value"><?= (int) $resumo['ativos'] ?></span>
    <span class="stat-card__pe"><?= $inativos ?> inativo<?= $inativos === 1 ? '' : 's' ?></span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Administradores</span><?= icone('lock') ?></div>
    <span class="stat-card__value"><?= (int) $resumo['admins'] ?></span>
    <span class="stat-card__pe">acesso total</span>
  </div>
  <div class="stat-card">
    <div class="stat-card__topo"><span class="stat-card__label">Novos (30 dias)</span><?= icone('user') ?></div>
    <span class="stat-card__value"><?= (int) $resumo['novos'] ?></span>
    <span class="stat-card__pe"><?= $resumo['ultimo_novo'] ? e($resumo['ultimo_novo']) : 'ninguém entrou no período' ?></span>
  </div>
</div>

<div class="lista-lateral card--espaco">
  <div>
    <nav class="abas abas--contagem" aria-label="Situação">
      <a href="<?= e(url_com(['status' => null, 'pagina' => null])) ?>"<?= $f['status'] === 'ativos' ? ' class="ativa" aria-current="page"' : '' ?>>Usuários ativos<span class="abas__n"><?= (int) $resumo['ativos'] ?></span></a>
      <a href="<?= e(url_com(['status' => 'inativos', 'pagina' => null])) ?>"<?= $f['status'] === 'inativos' ? ' class="ativa" aria-current="page"' : '' ?>>Usuários inativos<span class="abas__n"><?= $inativos ?></span></a>
    </nav>

    <form class="filtros filtros--solto" method="get" action="./usuarios">
      <?php if ($f['status'] !== 'ativos'): ?><input type="hidden" name="status" value="<?= e($f['status']) ?>"><?php endif; ?>
      <div class="filtros__busca filtros__busca--largo"><?= icone('search') ?><input type="search" name="busca" value="<?= e($f['busca']) ?>" placeholder="Buscar por nome ou e-mail" aria-label="Buscar"></div>
      <select name="perfil" aria-label="Perfil">
        <option value="">Todos os perfis</option>
        <?php foreach (Usuario::PERFIS as $k => $r): ?><option value="<?= $k ?>" <?= $f['perfil'] === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
      </select>
    </form>

    <div class="card card--tabela">
      <?php if (!$usuarios): ?><div class="vazio"><?= icone('users', 32, 1.25) ?>Nenhum usuário encontrado.</div><?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Usuário</th><th>Perfil</th><th>Comissão</th><th>Criado em</th><th class="num"><span class="sr-only">Ações</span></th></tr></thead>
            <tbody>
              <?php foreach ($usuarios as $u): $eu = (int) $u['id'] === Contexto::usuarioId(); $bloqueado = $u['bloqueado_ate'] && strtotime($u['bloqueado_ate']) > time(); $link = './usuario-form?id=' . (int) $u['id']; ?>
                <tr data-href="<?= e($link) ?>" class="<?= $u['ativo'] ? '' : 'inativo' ?>">
                  <td><span class="celula-pessoa"><span class="avatar avatar--g<?= $eu ? ' avatar--eu' : '' ?>"><?= e($iniciais($u['nome'])) ?></span>
                    <span><a href="<?= e($link) ?>"><?= e($u['nome']) ?></a><?= $eu ? ' <span class="secundario">(você)</span>' : '' ?><?= $bloqueado ? ' <span class="badge badge--warning">Bloqueado</span>' : '' ?><span class="sub"><?= e($u['email']) ?></span></span></span></td>
                  <td><span class="badge badge--plain badge--perfil-<?= strtolower($u['perfil']) ?>"><?= e(Usuario::PERFIS[$u['perfil']] ?? $u['perfil']) ?></span></td>
                  <td><?= $u['comissao_tipo'] === 'NENHUMA' ? '<span class="secundario">—</span>' : e(rtrim(rtrim((string) $u['comissao_percentual'], '0'), '.')) . '% ' . e(mb_substr(Usuario::COMISSOES[$u['comissao_tipo']], 2)) ?></td>
                  <td class="secundario tabular"><?= data_br($u['criado_em']) ?></td>
                  <td class="num"><span class="acoes-linha">
                    <a class="btn btn--fantasma btn--icone" href="<?= e($link) ?>" aria-label="Editar <?= e($u['nome']) ?>" title="Editar"><?= icone('edit') ?></a>
                    <?php if (!$eu): ?>
                      <details class="menu-mais"><summary class="btn btn--fantasma btn--icone" aria-label="Mais ações"><?= icone('more-h') ?></summary>
                        <div class="menu-mais__lista">
                          <form method="post" action="./usuario-ativo?id=<?= (int) $u['id'] ?>" <?= $u['ativo'] ? 'data-confirmar="Desativar ' . e($u['nome']) . '? A sessão dele será encerrada."' : '' ?>>
                            <?= csrf_field() ?><input type="hidden" name="ativo" value="<?= $u['ativo'] ? 0 : 1 ?>">
                            <button><?= icone($u['ativo'] ? 'lock' : 'check') ?><?= $u['ativo'] ? 'Desativar' : 'Reativar' ?></button>
                          </form>
                        </div>
                      </details>
                    <?php endif; ?>
                  </span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <?php $rotuloItens = $f['status'] === 'inativos' ? 'usuários inativos' : 'usuários'; require V2_RAIZ . '/app/views/layout/_rodape.php'; ?>
    </div>
  </div>

  <aside class="card painel-lateral" aria-label="Perfis de acesso">
    <h2 class="card__titulo">Perfis de acesso</h2>
    <dl class="perfis">
      <div><dt><span class="badge badge--plain badge--perfil-admin">Administrador</span></dt>
        <dd>Dono ou gerente. Acesso a tudo da oficina, inclusive financeiro, estoque, relatórios, configurações e usuários.</dd></div>
      <div><dt><span class="badge badge--plain badge--perfil-atendente">Atendente</span></dt>
        <dd>Balcão. Cadastra clientes e veículos, agenda, abre e conduz orçamentos e OS, recebe pela OS e opera o caixa.</dd></div>
      <div><dt><span class="badge badge--plain badge--perfil-tecnico">Técnico</span></dt>
        <dd>Mecânico. Tem as mesmas permissões do atendente, é o responsável pelas OS e pode receber comissão.</dd></div>
    </dl>
    <p class="nota"><?= icone('info') ?><span>Consultar, todos consultam (o custo das peças só o administrador vê). Excluir cadastros é sempre do administrador.</span></p>
  </aside>
</div>
