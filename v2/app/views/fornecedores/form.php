<?php
/**
 * Cadastro e edição de fornecedor, no padrão de janela (modal) das telas de criar e editar.
 * Topo com título e fechar, seções à esquerda, linhas "rótulo à esquerda, campo à direita" e o rodapé
 * com as ações. Aberta pelo js/modal.js sobre a tela de onde veio; acessada direto, ocupa a página.
 * Os campos, nomes, validações e o destino do formulário são os mesmos de sempre.
 * Espera: $fo, $id, $erro
 */
$titulo = $id ? 'Editar fornecedor' : 'Novo fornecedor';
$val = fn($k) => e($fo[$k] ?? '');
$nome = trim((string) ($fo['nome'] ?? ''));
$voltar = $id ? './fornecedor?id=' . (int) $id : './fornecedores';
$secoes = [['identificacao', 'transfer', 'Identificação'], ['contato', 'phone', 'Contato'], ['observacoes', 'file', 'Observações']];
?>
<form class="mf" method="post" action="./fornecedor-salvar<?= $id ? '?id=' . (int) $id : '' ?>" aria-labelledby="mf-titulo">
  <?= csrf_field() ?>

  <header class="mf__topo">
    <h2 class="mf__titulo" id="mf-titulo"><?= $titulo ?><?php if ($id && $nome !== ''): ?><span><?= e($nome) ?></span><?php endif; ?></h2>
    <a class="mf__fechar" href="<?= $voltar ?>" data-mf-fechar aria-label="Fechar" title="Fechar"><?= icone('close', 18, 1.75) ?></a>
  </header>

  <div class="mf__corpo">
    <nav class="mf__nav" aria-label="Seções do cadastro">
      <?php foreach ($secoes as $k => [$ancora, $ic, $rotulo]): ?>
        <a href="#mf-<?= $ancora ?>"<?= $k === 0 ? ' class="is-ativa" aria-current="true"' : '' ?>><?= icone($ic, 18) ?><span><?= $rotulo ?></span></a>
      <?php endforeach; ?>
      <?php if ($id && !empty($fo['criado_em'])): ?>
        <dl class="mf__nav-rodape">
          <div><dt>Fornecedor desde</dt><dd><?= data_br($fo['criado_em']) ?></dd></div>
          <div><dt>Situação</dt><dd><?= !isset($fo['ativo']) || $fo['ativo'] ? 'Ativo' : 'Inativo' ?></dd></div>
        </dl>
      <?php endif; ?>
    </nav>

    <div class="mf__conteudo">
      <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

      <section class="mf__secao" id="mf-identificacao" aria-labelledby="mf-h-identificacao">
        <h3 id="mf-h-identificacao">Identificação</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="nome">Nome ou razão social</label><small>Como a oficina conhece o fornecedor.</small></div>
          <div class="mf-linha__campo"><input id="nome" name="nome" value="<?= $val('nome') ?>" required maxlength="150" autocomplete="off" autofocus></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="documento">CPF ou CNPJ</label><small>Opcional. Evita cadastrar o mesmo fornecedor duas vezes.</small></div>
          <div class="mf-linha__campo"><input id="documento" name="documento" value="<?= e(documento_fmt($fo['documento'] ?? '')) ?>" data-mascara="documento" inputmode="numeric"></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-contato" aria-labelledby="mf-h-contato">
        <h3 id="mf-h-contato">Contato</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="contato">Pessoa de contato</label><small>Vendedor ou atendente.</small></div>
          <div class="mf-linha__campo"><input id="contato" name="contato" value="<?= $val('contato') ?>" maxlength="100"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="telefone">Telefone ou WhatsApp</label></div>
          <div class="mf-linha__campo"><input id="telefone" name="telefone" value="<?= e(telefone_fmt($fo['telefone'] ?? '')) ?>" data-mascara="telefone" inputmode="tel" placeholder="(00) 00000-0000"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="email">E-mail</label></div>
          <div class="mf-linha__campo"><input id="email" name="email" type="email" value="<?= $val('email') ?>" maxlength="150" placeholder="nome@exemplo.com"></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-observacoes" aria-labelledby="mf-h-observacoes">
        <h3 id="mf-h-observacoes">Observações</h3>
        <div class="mf-linha mf-linha--bloco">
          <div class="mf-linha__rotulo"><label for="observacoes">Anotações da equipe</label><small>Prazos de entrega, condições de pagamento e pedido mínimo.</small></div>
          <div class="mf-linha__campo"><textarea id="observacoes" name="observacoes" rows="4"><?= $val('observacoes') ?></textarea></div>
        </div>
      </section>
    </div>
  </div>

  <footer class="mf__rodape">
    <span class="mf__nota">O nome é obrigatório.</span>
    <a class="btn btn--ghost" href="<?= $voltar ?>" data-mf-fechar>Cancelar</a>
    <button class="btn btn--primary" type="submit"><?= $id ? 'Salvar alterações' : 'Cadastrar fornecedor' ?></button>
  </footer>
</form>
