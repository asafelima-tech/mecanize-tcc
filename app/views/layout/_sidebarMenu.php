<?php
/**
 * Menu lateral do sistema, em grupos que abrem e fecham.
 *
 * É só a APRESENTAÇÃO do que já existia: as telas, as rotas e quem pode ver o
 * quê continuam sendo decididos por Permissao e routes.php. Esconder um link
 * aqui não é controle de acesso — quem barra de verdade é a matriz de rotas.
 * O menu só espelha o que ela já permite:
 *   - 'so' => 'ADMIN'  : itens do administrador (preço de custo, financeiro, usuários...);
 *   - 'so' => 'CAIXA'  : o caixa é da equipe inteira (admin, atendente e técnico).
 *
 * Um único menu para a equipe: técnico e atendente têm as mesmas permissões
 * (ver Permissao). O que muda entre os dois é só o nome e a tela de entrada —
 * o técnico cai no "Meu painel", o atendente no Início.
 *
 * Cada item lista também as telas "filhas" dele (cadastro, edição, detalhe), para
 * o item continuar marcado — e o grupo continuar aberto — quando a pessoa está
 * dentro delas, e não só na lista.
 *
 * Espera: $rotaAtual, $ehTecnico (definidos em header.php).
 */
$tipoUsuario = $_SESSION['user_tipo'] ?? '';
$ehAdmin     = $tipoUsuario === 'ADMIN';
$operaCaixa  = Permissao::permite($tipoUsuario ?: null, Permissao::EQUIPE);

// Mensagens de clientes ainda não lidas (a central de conversas).
$conversasNaoLidas = 0;
try {
    require_once __DIR__ . '/../../models/Conversa.php';
    $conversasNaoLidas = (new Conversa())->totalNaoLidas();
} catch (\Throwable $e) {
    error_log('[menu conversas] ' . $e->getMessage());
}

$svg = static fn(string $miolo, string $largura = '2') => '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $largura . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $miolo . '</svg>';

$ICONE_PAINEL   = '<rect x="3" y="2" width="14" height="18" rx="2" /><path d="M7 2v2a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V2" /><path d="M6 12l3 3 5-6" />';
$ICONE_INICIO   = '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><polyline points="9 22 9 12 15 12 15 22" />';
$ICONE_CAIXA    = '<rect x="2" y="7" width="20" height="14" rx="2" /><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2" /><circle cx="12" cy="14" r="2" />';

// ── Fora de grupo: o que a pessoa usa o dia inteiro ──
$topo = [
    ['href' => './painelTecnico', 'rotulo' => $ehTecnico ? 'Meu painel' : 'Painel de trabalho', 'icone' => $ICONE_PAINEL, 'rotas' => ['viewOSTecnico']],
    ['href' => './home',          'rotulo' => 'Início', 'icone' => $ICONE_INICIO],
];

// ── Grupos, por finalidade ──
$grupos = [
    [
        'id' => 'oficina', 'rotulo' => 'Oficina',
        'icone' => '<path d="M14.7 6.3a4 4 0 0 0 5 5l-9.4 9.4a2.8 2.8 0 0 1-4-4z" /><path d="M14.7 6.3l3-3a5.5 5.5 0 0 1 3 3l-3 3" />',
        'itens' => [
            ['href' => './listOcorrencias',   'rotulo' => 'Atendimento',       'rotas' => ['saveOcorrencia', 'viewOcorrencia']],
            ['href' => './filaOperacional',   'rotulo' => 'Fila'],
            ['href' => './listOrcamentos',    'rotulo' => 'Orçamentos',        'rotas' => ['saveOrcamento', 'novoOrcamento', 'viewOrcamento', 'editOrcamento']],
            ['href' => './listOrdemServico',  'rotulo' => 'Ordens de Serviço', 'rotas' => ['saveOrdemServico', 'editOrdemServico', 'viewOrdemServico', 'kanbanOrdemServico', 'pagamentoOrdemServico']],
            ['href' => './listAgendamentos',  'rotulo' => 'Agenda',            'rotas' => ['saveAgendamento', 'editAgendamento']],
            ['href' => './painel',            'rotulo' => 'Painel (TV)',       'novaAba' => true],
        ],
    ],
    [
        'id' => 'clientes', 'rotulo' => 'Clientes',
        'icone' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />',
        'itens' => [
            ['href' => './listClientes', 'rotulo' => 'Clientes',  'rotas' => ['saveCliente', 'editCliente', 'viewCliente', 'listClientesDesativados', 'vipList', 'vipPerfil', 'novosMesList']],
            ['href' => './listVeiculos', 'rotulo' => 'Veículos',  'rotas' => ['saveVeiculo', 'editVeiculo', 'viewVeiculo', 'listVeiculosNovosMes', 'veiculoPerfil']],
            ['href' => './listConversas', 'rotulo' => 'Conversas', 'rotas' => ['viewConversa'], 'badge' => $conversasNaoLidas],
        ],
    ],
    [
        'id' => 'estoque', 'rotulo' => 'Estoque',
        'icone' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" /><polyline points="3.27 6.96 12 12.01 20.73 6.96" /><line x1="12" y1="22.08" x2="12" y2="12" />',
        'itens' => [
            // O cadastro de produtos mostra preço de custo e valor de inventário: é do administrador.
            ['href' => './listProduto',              'rotulo' => 'Produtos',             'so' => 'ADMIN', 'rotas' => ['saveProduto', 'editProduto', 'viewProduto', 'listCategoria', 'saveCategoria', 'editCategoria']],
            ['href' => './listMovimentacaoEstoque',  'rotulo' => 'Movimentações',        'rotas' => ['saveMovimentacaoEstoque']],
            ['href' => './consultaEstoque',          'rotulo' => 'Consulta de estoque'],
            ['href' => './listSolicitacoes',         'rotulo' => 'Solicitação de peças', 'rotas' => ['saveSolicitacao', 'viewSolicitacao']],
            ['href' => './painelCompras',            'rotulo' => 'Compras',              'so' => 'ADMIN', 'rotas' => ['listPedidosCompra', 'criarPedidoCompra', 'viewPedidoCompra']],
            ['href' => './listFornecedores',         'rotulo' => 'Fornecedores',         'so' => 'ADMIN', 'rotas' => ['saveFornecedor', 'editFornecedor']],
        ],
    ],
    [
        'id' => 'financeiro', 'rotulo' => 'Financeiro',
        'icone' => '<line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />',
        'itens' => [
            // Sozinho (técnico e atendente só têm o caixa aqui), vira item solto: ver mais abaixo.
            ['href' => './listCaixa',          'rotulo' => 'Caixa',           'so' => 'CAIXA', 'icone' => $ICONE_CAIXA, 'rotas' => ['abrirCaixa', 'fecharCaixa', 'caixaMovimentacoes']],
            ['href' => './painelFinanceiro',   'rotulo' => 'Financeiro',      'so' => 'ADMIN', 'rotas' => ['saveDespesa', 'viewDespesa']],
            ['href' => './listContasReceber',  'rotulo' => 'A receber',       'so' => 'ADMIN'],
            ['href' => './listContasPagar',    'rotulo' => 'A pagar',         'so' => 'ADMIN'],
            ['href' => './listCartoes',        'rotulo' => 'Cartões',         'so' => 'ADMIN', 'rotas' => ['saveCartao', 'editCartao', 'viewCartao', 'viewFatura']],
            ['href' => './comissoesFuncionarios', 'rotulo' => 'Comissões',    'so' => 'ADMIN'],
            ['href' => './relatorioCaixa',     'rotulo' => 'Relatório de Caixa', 'so' => 'ADMIN'],
        ],
    ],
    [
        'id' => 'administracao', 'rotulo' => 'Administração',
        'icone' => '<line x1="4" y1="21" x2="4" y2="14" /><line x1="4" y1="10" x2="4" y2="3" /><line x1="12" y1="21" x2="12" y2="12" /><line x1="12" y1="8" x2="12" y2="3" /><line x1="20" y1="21" x2="20" y2="16" /><line x1="20" y1="12" x2="20" y2="3" /><line x1="1" y1="14" x2="7" y2="14" /><line x1="9" y1="8" x2="15" y2="8" /><line x1="17" y1="16" x2="23" y2="16" />',
        'itens' => [
            ['href' => './listRel',        'rotulo' => 'Relatórios',  'so' => 'ADMIN'],
            ['href' => './listDocumentos', 'rotulo' => 'Documentos',  'so' => 'ADMIN', 'rotas' => ['saveDocumento']],
            ['href' => './listUsuarios',   'rotulo' => 'Usuários',    'so' => 'ADMIN', 'rotas' => ['saveUsuario', 'editUsuario', 'viewUsuario', 'listUsuariosNovos30d']],
            ['href' => './integracoes',    'rotulo' => 'Integrações', 'so' => 'ADMIN'],
        ],
    ],
];

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

/** O item pode ser visto por quem está logado? */
$visivel = static fn(array $i) => match ($i['so'] ?? null) {
    'ADMIN' => $ehAdmin,
    'CAIXA' => $operaCaixa,
    default => true,
};

/** O item é a tela atual (ou uma tela filha dela)? */
$ehAtual = static function (array $i) use ($rotaAtual): bool {
    $propria = ltrim(parse_url($i['href'], PHP_URL_PATH) ?: '', './');

    return $rotaAtual === $propria || in_array($rotaAtual, $i['rotas'] ?? [], true);
};

$selo = static fn(int $n) => $n > 0 ? '<span class="sidebar__badge">' . ($n > 99 ? '99+' : $n) . '</span>' : '';

/** Item solto (fora de grupo, ou dentro dele sem ícone). */
$linkItem = static function (array $i, bool $comIcone, string $iconePadrao = '') use ($h, $ehAtual, $svg, $selo): string {
    $atual = $ehAtual($i);
    $alvo  = !empty($i['novaAba']) ? ' target="_blank" rel="noopener"' : '';
    $miolo = $i['icone'] ?? $iconePadrao;

    return '<li class="' . ($atual ? 'is-active' : '') . '">'
        . '<a href="' . $h($i['href']) . '"' . $alvo . ' data-tip="' . $h($i['rotulo']) . '"' . ($atual ? ' aria-current="page"' : '') . '>'
        . ($comIcone && $miolo !== '' ? $svg($miolo) : '')
        . '<span class="label">' . $h($i['rotulo']) . '</span>' . $selo((int) ($i['badge'] ?? 0))
        . '</a></li>';
};
?>
      <ul class="sidebar__nav">
        <?php foreach ($topo as $item): ?>
          <?= $linkItem($item, true) ?>
        <?php endforeach; ?>

        <?php foreach ($grupos as $grupo): ?>
          <?php
          $itens = array_values(array_filter($grupo['itens'], $visivel));

          if (!$itens) {
              continue; // nada neste grupo para este perfil
          }

          // Um grupo com um item só não precisa de grupo: aparece solto, como antes.
          if (count($itens) === 1) {
              echo $linkItem($itens[0], true, $grupo['icone']);
              continue;
          }

          $contemAtual = (bool) array_filter($itens, $ehAtual);
          $naoLidas    = array_sum(array_map(static fn($i) => (int) ($i['badge'] ?? 0), $itens));
          $idLista     = 'sidebar-grupo-' . $grupo['id'];
          ?>
          <li class="sidebar__group<?= $contemAtual ? ' is-open is-active-group' : '' ?>" data-grupo="<?= $h($grupo['id']) ?>">
            <button type="button" class="sidebar__group-toggle" aria-expanded="<?= $contemAtual ? 'true' : 'false' ?>" aria-controls="<?= $h($idLista) ?>" data-tip="<?= $h($grupo['rotulo']) ?>">
              <?= $svg($grupo['icone']) ?>
              <span class="label"><?= $h($grupo['rotulo']) ?></span>
              <?= $selo($naoLidas) ?>
              <svg class="sidebar__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9" /></svg>
            </button>
            <div class="sidebar__sub-wrap" id="<?= $h($idLista) ?>">
              <ul class="sidebar__sub">
                <?php foreach ($itens as $item): ?>
                  <?= $linkItem($item, false) ?>
                <?php endforeach; ?>
              </ul>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <script>
        // Grupos do menu: abrir/fechar, lembrar o que a pessoa deixou aberto e dicas na barra recolhida.
        // Fica aqui (logo depois do menu) para o estado ser aplicado ANTES da primeira pintura: sem isso
        // os grupos abertos "pulariam" abrindo a cada página.
        (function () {
          var nav = document.querySelector('.sidebar__nav');
          var grupos = nav ? nav.querySelectorAll('.sidebar__group') : [];
          if (!grupos.length) return;

          var CHAVE = 'sidebar-grupos';
          var ler = function () { try { return JSON.parse(localStorage.getItem(CHAVE)) || {}; } catch (e) { return {}; } };
          var gravar = function (m) { try { localStorage.setItem(CHAVE, JSON.stringify(m)); } catch (e) {} };
          var definir = function (li, abrir) {
            li.classList.toggle('is-open', abrir);
            li.querySelector('.sidebar__group-toggle').setAttribute('aria-expanded', abrir ? 'true' : 'false');
          };
          var recolhida = function () { return document.body.classList.contains('sidebar-collapsed') && window.innerWidth > 768; };

          // Restaura o que ficou aberto. O grupo da página atual já vem aberto do servidor e nunca fecha sozinho.
          nav.style.setProperty('--sub-anim', '0s');
          var salvo = ler();
          grupos.forEach(function (li) {
            if (!li.classList.contains('is-active-group') && salvo[li.getAttribute('data-grupo')] === true) definir(li, true);
          });
          requestAnimationFrame(function () { requestAnimationFrame(function () { nav.style.removeProperty('--sub-anim'); }); });

          nav.addEventListener('click', function (e) {
            var botao = e.target.closest('.sidebar__group-toggle');
            if (!botao) return;
            var li = botao.closest('.sidebar__group');
            var id = li.getAttribute('data-grupo');
            var m = ler();

            // Na barra estreita não cabe a lista: abre a barra e já mostra o grupo clicado.
            if (recolhida()) {
              document.body.classList.remove('sidebar-collapsed');
              try { localStorage.setItem('sidebar-collapsed', 'false'); } catch (err) {}
              definir(li, true); m[id] = true; gravar(m);
              return;
            }

            var abrir = !li.classList.contains('is-open');
            definir(li, abrir); m[id] = abrir; gravar(m);
          });

          // Dica com o nome do item, só na barra recolhida (ali só se vê o ícone).
          var dica = null;
          var esconder = function () { if (dica) { dica.remove(); dica = null; } };
          nav.addEventListener('mouseover', function (e) {
            if (!recolhida()) return;
            var alvo = e.target.closest('[data-tip]');
            if (!alvo) return;
            esconder();
            dica = document.createElement('div');
            dica.className = 'sidebar-tooltip';
            dica.textContent = alvo.getAttribute('data-tip');
            document.body.appendChild(dica);
            var r = alvo.getBoundingClientRect();
            dica.style.left = (r.right + 12) + 'px';
            dica.style.top = (r.top + r.height / 2) + 'px';
          });
          nav.addEventListener('mouseout', esconder);
          nav.addEventListener('click', esconder);
          nav.addEventListener('scroll', esconder);
        })();
      </script>
