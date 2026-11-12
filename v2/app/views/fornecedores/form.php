<?php
/**
 * Cadastro e edição de fornecedor, no formato de ficha das outras telas de cadastro: um cartão com
 * três grupos (identificação em destaque, contato e informações complementares) e, ao lado, o resumo
 * que acompanha o que é digitado, com as ações de salvar.
 * Os campos, nomes, validações e o destino do formulário são os mesmos de sempre.
 * Espera: $fo, $id, $erro
 */
$titulo = $id ? 'Editar fornecedor' : 'Novo fornecedor';
$val = fn($k) => e($fo[$k] ?? '');
$nome = trim((string) ($fo['nome'] ?? ''));
$partes = $nome !== '' ? preg_split('/\s+/', $nome) : [];
$iniciais = $partes ? mb_strtoupper(mb_substr($partes[0], 0, 1) . (count($partes) > 1 ? mb_substr(end($partes), 0, 1) : '')) : '';
$doc = documento_fmt($fo['documento'] ?? '');
$digitos = strlen(so_digitos($fo['documento'] ?? ''));
$tipoDoc = $digitos === 14 ? 'Pessoa jurídica' : ($digitos === 11 ? 'Pessoa física' : 'Fornecedor');
$tel = telefone_fmt($fo['telefone'] ?? '');
$voltar = $id ? './fornecedor?id=' . (int) $id : './fornecedores';
?>
<div class="page-header">
  <div>
    <h1 class="page-header__title"><?= $titulo ?></h1>
    <p class="page-header__subtitle"><a href="./fornecedores">Fornecedores</a><?= $id ? ' · ' . $val('nome') : ' · de quem a oficina compra peças e materiais' ?></p>
  </div>
</div>

<form class="form-ficha" method="post" action="./fornecedor-salvar<?= $id ? '?id=' . (int) $id : '' ?>">
  <?= csrf_field() ?>

  <div class="form-ficha__corpo">
    <?php if (!empty($erro)) { $alertaTipo = 'erro'; $alertaMensagem = $erro; require V2_RAIZ . '/app/views/layout/_alerta.php'; } ?>

    <div class="card form-ficha__secao grupos">
      <!-- 1. Identificação (em destaque) -->
      <section class="grupo" aria-labelledby="g-identificacao">
        <header class="grupo__cabeca">
          <span class="grupo__n">1</span>
          <h2 id="g-identificacao">Identificação do fornecedor</h2>
        </header>
        <div class="identidade">
          <span class="identidade__avatar identidade__avatar--quadrado" aria-hidden="true" data-previa-iniciais><?= $iniciais !== '' ? e($iniciais) : icone('transfer', 24) ?></span>
          <div class="form-ficha__grade identidade__campos">
            <div class="form-field form-ficha__c8"><label for="nome">Nome / razão social *</label>
              <input id="nome" name="nome" value="<?= $val('nome') ?>" required maxlength="150" autofocus></div>
            <div class="form-field form-ficha__c4"><label for="documento">CPF/CNPJ</label>
              <input id="documento" name="documento" value="<?= e($doc) ?>" data-mascara="documento" inputmode="numeric" placeholder="Opcional"></div>
            <p class="form-dica form-ficha__c12 identidade__dica">Use o nome pelo qual a oficina conhece o fornecedor. O CPF ou CNPJ evita cadastrar o mesmo fornecedor duas vezes.</p>
          </div>
        </div>
      </section>

      <!-- 2. Contato -->
      <section class="grupo" aria-labelledby="g-contato">
        <header class="grupo__cabeca">
          <span class="grupo__n">2</span>
          <h2 id="g-contato">Contato</h2>
        </header>
        <div class="form-ficha__grade">
          <div class="form-field form-ficha__c4"><label for="contato">Pessoa de contato</label>
            <span class="campo-icone"><?= icone('user') ?><input id="contato" name="contato" value="<?= $val('contato') ?>" maxlength="100" placeholder="Vendedor ou atendente"></span></div>
          <div class="form-field form-ficha__c4"><label for="telefone">Telefone / WhatsApp</label>
            <span class="campo-icone"><?= icone('phone') ?><input id="telefone" name="telefone" value="<?= e($tel) ?>" data-mascara="telefone" inputmode="tel"></span></div>
          <div class="form-field form-ficha__c4"><label for="email">E-mail</label>
            <span class="campo-icone"><?= icone('mail') ?><input id="email" name="email" type="email" value="<?= $val('email') ?>" maxlength="150"></span></div>
        </div>
      </section>

      <!-- 3. Complementares -->
      <section class="grupo" aria-labelledby="g-complementar">
        <header class="grupo__cabeca">
          <span class="grupo__n">3</span>
          <h2 id="g-complementar">Informações complementares</h2>
        </header>
        <div class="form-field"><label for="observacoes">Observações</label>
          <textarea id="observacoes" name="observacoes" rows="4" placeholder="Prazos de entrega, condições de pagamento, pedido mínimo…"><?= $val('observacoes') ?></textarea></div>
      </section>
    </div>
  </div>

  <aside class="form-ficha__lado" aria-label="Resumo do fornecedor">
    <div class="card form-ficha__resumo">
      <div class="form-ficha__perfil">
        <span class="form-ficha__avatar form-ficha__avatar--quadrado" data-previa-iniciais><?= $iniciais !== '' ? e($iniciais) : icone('transfer', 22) ?></span>
        <div class="form-ficha__perfil-texto">
          <strong data-previa-nome><?= $nome !== '' ? e($nome) : 'Novo fornecedor' ?></strong>
          <span data-previa-tipo><?= $tipoDoc ?></span>
        </div>
      </div>
      <dl class="form-ficha__dados form-ficha__dados--lista">
        <div><dt><?= icone('file', 14) ?> CPF/CNPJ</dt><dd data-previa="documento"><?= $doc !== '' ? e($doc) : '—' ?></dd></div>
        <div><dt><?= icone('user', 14) ?> Contato</dt><dd data-previa="contato"><?= $val('contato') ?: '—' ?></dd></div>
        <div><dt><?= icone('phone', 14) ?> Telefone</dt><dd data-previa="telefone"><?= $tel !== '' ? e($tel) : '—' ?></dd></div>
        <div><dt><?= icone('mail', 14) ?> E-mail</dt><dd data-previa="email"><?= $val('email') ?: '—' ?></dd></div>
        <?php if ($id && !empty($fo['criado_em'])): ?>
          <div><dt><?= icone('calendar', 14) ?> Desde</dt><dd><?= data_br($fo['criado_em']) ?></dd></div>
          <div><dt><?= icone('info', 14) ?> Situação</dt><dd><?= !isset($fo['ativo']) || $fo['ativo'] ? 'Ativo' : 'Inativo' ?></dd></div>
        <?php endif; ?>
      </dl>
      <div class="form-ficha__acoes">
        <button class="btn btn--primary"><?= icone('check') ?> Salvar fornecedor</button>
        <a class="btn btn--ghost" href="<?= $voltar ?>">Cancelar</a>
      </div>
      <p class="form-ficha__nota">Campos com * são obrigatórios.</p>
    </div>
  </aside>
</form>

<script>
  (function () {
    // O resumo ao lado acompanha o que é digitado: iniciais, nome, tipo pelo documento e o contato.
    var nome = document.getElementById('nome'), doc = document.getElementById('documento');
    var pNome = document.querySelector('[data-previa-nome]'), pTipo = document.querySelector('[data-previa-tipo]');
    var avatares = document.querySelectorAll('[data-previa-iniciais]');
    var icones = Array.prototype.map.call(avatares, function (a) { return a.querySelector('svg') ? a.innerHTML : ''; });
    var vazio = { 24: <?= json_encode(icone('transfer', 24)) ?>, 22: <?= json_encode(icone('transfer', 22)) ?> };
    function previa() {
      var n = nome.value.trim(), p = n ? n.split(/\s+/) : [];
      pNome.textContent = n || 'Novo fornecedor';
      var ini = p.length ? (p[0].charAt(0) + (p.length > 1 ? p[p.length - 1].charAt(0) : '')).toUpperCase() : '';
      avatares.forEach(function (a, i) {
        if (ini) a.textContent = ini; else a.innerHTML = icones[i] || (i === 0 ? vazio[24] : vazio[22]);
      });
      var d = doc.value.replace(/\D/g, '');
      pTipo.textContent = d.length === 14 ? 'Pessoa jurídica' : (d.length === 11 ? 'Pessoa física' : 'Fornecedor');
    }
    nome.addEventListener('input', previa);
    doc.addEventListener('input', function () { setTimeout(previa, 0); });
    ['documento', 'contato', 'telefone', 'email'].forEach(function (c) {
      var campo = document.getElementById(c), alvo = document.querySelector('[data-previa="' + c + '"]');
      // Depois da máscara do campo, para o resumo mostrar o valor já formatado.
      var sinc = function () { setTimeout(function () { alvo.textContent = campo.value.trim() || '—'; }, 0); };
      campo.addEventListener('input', sinc);
      campo.addEventListener('change', sinc);
    });
  })();
</script>
