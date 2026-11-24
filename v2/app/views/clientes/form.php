<?php
/**
 * Cadastro e edição de cliente, no padrão de janela (modal) das telas de criar e editar.
 * Topo com título e fechar, seções à esquerda, linhas "rótulo à esquerda, campo à direita" e o rodapé
 * com as ações. Aberta pelo js/modal.js sobre a tela de onde veio; acessada direto, ocupa a página.
 * Os campos, nomes, validações e o destino do formulário são os mesmos de sempre.
 * Espera: $c (dados), $id (null = novo), $erro (opcional)
 */
$titulo = $id ? 'Editar cliente' : 'Novo cliente';
$v = fn($k) => e($c[$k] ?? '');
$pj = ($c['tipo_pessoa'] ?? 'PF') === 'PJ';
$nome = trim((string) ($c['nome'] ?? ''));
$voltar = $id ? './cliente?id=' . (int) $id : './clientes';
$secoes = [['identificacao', 'user', 'Identificação'], ['contato', 'phone', 'Contato'], ['endereco', 'location', 'Endereço'], ['observacoes', 'file', 'Observações']];
?>
<form class="mf" method="post" action="./cliente-salvar<?= $id ? '?id=' . (int) $id : '' ?>" aria-labelledby="mf-titulo">
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
      <?php if ($id && !empty($c['criado_em'])): ?>
        <dl class="mf__nav-rodape">
          <div><dt>Cliente desde</dt><dd><?= data_br($c['criado_em']) ?></dd></div>
          <div><dt>Situação</dt><dd><?= !isset($c['ativo']) || $c['ativo'] ? 'Ativo' : 'Inativo' ?></dd></div>
        </dl>
      <?php endif; ?>
    </nav>

    <div class="mf__conteudo">
      <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

      <section class="mf__secao" id="mf-identificacao" aria-labelledby="mf-h-identificacao">
        <h3 id="mf-h-identificacao">Identificação</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><span id="rotulo-tipo">Tipo</span></div>
          <div class="mf-linha__campo">
            <div class="segmento" role="radiogroup" aria-labelledby="rotulo-tipo">
              <label><input type="radio" name="tipo_pessoa" value="PF" <?= $pj ? '' : 'checked' ?>><span>Pessoa física</span></label>
              <label><input type="radio" name="tipo_pessoa" value="PJ" <?= $pj ? 'checked' : '' ?>><span>Pessoa jurídica</span></label>
            </div>
          </div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="nome"><?= $pj ? 'Razão social' : 'Nome completo' ?></label><small>Como aparece no orçamento e na OS.</small></div>
          <div class="mf-linha__campo"><input id="nome" name="nome" value="<?= $v('nome') ?>" required maxlength="150" autocomplete="off" autofocus></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="documento" id="rotulo-doc"><?= $pj ? 'CNPJ' : 'CPF' ?></label><small>Opcional.</small></div>
          <div class="mf-linha__campo"><input id="documento" name="documento" value="<?= e(documento_fmt($c['documento'] ?? '')) ?>" data-mascara="documento" inputmode="numeric"></div>
        </div>
      </section>

      <section class="mf__secao" id="mf-contato" aria-labelledby="mf-h-contato">
        <h3 id="mf-h-contato">Contato</h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="telefone">Telefone ou WhatsApp</label><small>Para avisos de orçamento e entrega.</small></div>
          <div class="mf-linha__campo"><input id="telefone" name="telefone" value="<?= e(telefone_fmt($c['telefone'] ?? '')) ?>" data-mascara="telefone" inputmode="tel" placeholder="(00) 00000-0000"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="email">E-mail</label></div>
          <div class="mf-linha__campo"><input id="email" name="email" type="email" value="<?= $v('email') ?>" maxlength="150" placeholder="nome@exemplo.com"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="aceita_contato">Aceita receber contato</label><small>O cliente autorizou mensagens por WhatsApp e e-mail.</small></div>
          <div class="mf-linha__campo mf-linha__campo--fim">
            <input class="mf-chave" type="checkbox" role="switch" id="aceita_contato" name="aceita_contato" value="1" data-nativo <?= !empty($c['aceita_contato']) ? 'checked' : '' ?>>
          </div>
        </div>
      </section>

      <section class="mf__secao" id="mf-endereco" aria-labelledby="mf-h-endereco">
        <h3 id="mf-h-endereco">Endereço<small>Opcional</small></h3>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="cep">CEP</label></div>
          <div class="mf-linha__campo"><input id="cep" name="cep" value="<?= $v('cep') ?>" data-mascara="cep" inputmode="numeric" placeholder="00000-000"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="endereco">Endereço</label></div>
          <div class="mf-linha__campo"><input id="endereco" name="endereco" value="<?= $v('endereco') ?>" maxlength="255" placeholder="Rua e número"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="bairro">Bairro</label></div>
          <div class="mf-linha__campo"><input id="bairro" name="bairro" value="<?= $v('bairro') ?>" maxlength="100"></div>
        </div>
        <div class="mf-linha">
          <div class="mf-linha__rotulo"><label for="cidade">Cidade e UF</label></div>
          <div class="mf-linha__campo mf-linha__campo--par">
            <input id="cidade" name="cidade" value="<?= $v('cidade') ?>" maxlength="100" placeholder="Cidade">
            <input id="uf" name="uf" value="<?= $v('uf') ?>" maxlength="2" placeholder="UF" aria-label="UF" style="text-transform:uppercase">
          </div>
        </div>
      </section>

      <section class="mf__secao" id="mf-observacoes" aria-labelledby="mf-h-observacoes">
        <h3 id="mf-h-observacoes">Observações</h3>
        <div class="mf-linha mf-linha--bloco">
          <div class="mf-linha__rotulo"><label for="observacoes">Anotações da equipe</label><small>Preferências, combinados e o que precisa ser lembrado no atendimento.</small></div>
          <div class="mf-linha__campo"><textarea id="observacoes" name="observacoes" rows="4"><?= $v('observacoes') ?></textarea></div>
        </div>
      </section>
    </div>
  </div>

  <footer class="mf__rodape">
    <span class="mf__nota">O nome é obrigatório.</span>
    <a class="btn btn--ghost" href="<?= $voltar ?>" data-mf-fechar>Cancelar</a>
    <button class="btn btn--primary" type="submit"><?= $id ? 'Salvar alterações' : 'Cadastrar cliente' ?></button>
  </footer>

  <script>
    (function () {
      // Tipo de pessoa: troca o rótulo do nome e do documento.
      var f = document.currentScript.closest('form');
      function tipo() {
        var pj = (f.querySelector('input[name=tipo_pessoa]:checked') || {}).value === 'PJ';
        f.querySelector('#rotulo-doc').textContent = pj ? 'CNPJ' : 'CPF';
        f.querySelector('label[for=nome]').textContent = pj ? 'Razão social' : 'Nome completo';
      }
      f.querySelectorAll('input[name=tipo_pessoa]').forEach(function (r) { r.addEventListener('change', tipo); });
    })();
  </script>
</form>
