<?php
/**
 * Papel timbrado das telas impressas — existe só no papel.
 *
 * Fica escondido por style="display:none" e volta a aparecer pela regra
 * .doc-print do print.css (que é !important e por isso vence o inline).
 * Assim nenhuma tela muda de aparência e nada precisa ser tocado view a view.
 *
 * O título sai do mapa de rotas abaixo e, quando a tela tem um <h1> ou
 * título de modal, é substituído pelo texto real por JS — o mapa é a rede
 * de segurança para rota nova que ninguém lembrou de cadastrar aqui.
 */

$tituloDocumento = [
    'home'                    => 'Painel Inicial',
    'listClientes'            => 'Relação de Clientes',
    'viewCliente'             => 'Ficha do Cliente',
    'listClientesDesativados' => 'Clientes Desativados',
    'listVeiculos'            => 'Relação de Veículos',
    'viewVeiculo'             => 'Ficha do Veículo',
    'listProduto'             => 'Relação de Produtos',
    'viewProduto'             => 'Ficha do Produto',
    'listCategoria'           => 'Categorias de Produtos',
    'listOrcamentos'          => 'Relação de Orçamentos',
    'viewOrcamento'           => 'Orçamento',
    'editOrcamento'           => 'Orçamento',
    'listOrdemServico'        => 'Relação de Ordens de Serviço',
    'viewOrdemServico'        => 'Ordem de Serviço',
    'editOrdemServico'        => 'Ordem de Serviço',
    'kanbanOrdemServico'      => 'Ordens de Serviço por Situação',
    'listAgendamentos'        => 'Agenda de Serviços',
    'listUsuarios'            => 'Relação de Usuários',
    'viewUsuario'             => 'Ficha do Usuário',
    'perfil'                  => 'Perfil do Usuário',
    'listMovimentacaoEstoque' => 'Movimentações de Estoque',
    'listCaixa'               => 'Histórico de Caixa',
    'caixaMovimentacoes'      => 'Livro-Caixa',
    'relatorioCaixa'          => 'Relatório de Caixa',
    'fecharCaixa'             => 'Fechamento de Caixa',
    'listRel'                 => 'Relatório Gerencial',
    'listDocumentos'          => 'Documentos',
][$rotaAtual ?? ''] ?? 'Documento';

$contatoEmpresa = array_filter([
    EMPRESA_ENDERECO,
    EMPRESA_TELEFONE,
    EMPRESA_EMAIL,
    EMPRESA_CNPJ !== '' ? 'CNPJ ' . EMPRESA_CNPJ : '',
]);
?>

<div class="doc-print doc-print__topo" style="display:none;" aria-hidden="true">
    <div class="doc-print__marca">
        <!-- Mesma logomarca dos PDFs; a versão escura, que é a que aparece no papel. -->
        <img class="doc-print__logo" src="./assets/img/logo-mecanize.png" alt="<?= htmlspecialchars(EMPRESA_NOME) ?>">
        <div>
            <?php if (EMPRESA_NOME !== 'Mecanize'): ?>
                <!-- Oficina que personalizou o nome continua identificada por
                     escrito: a logomarca do sistema não fala pelo nome dela. -->
                <div class="doc-print__empresa"><?= htmlspecialchars(EMPRESA_NOME) ?></div>
            <?php endif; ?>
            <?php if ($contatoEmpresa): ?>
                <div class="doc-print__contato"><?= htmlspecialchars(implode(' · ', $contatoEmpresa)) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="doc-print__ref">
        <div class="doc-print__titulo" id="docPrintTitulo"><?= htmlspecialchars($tituloDocumento) ?></div>
        <div class="doc-print__meta">
            Emitido em <?= date('d/m/Y \à\s H:i') ?><br>
            Responsável: <?= htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário') ?>
            (<?= htmlspecialchars($_SESSION['user_tipo'] ?? '') ?>)
        </div>
    </div>
</div>

<script>
    // Título real da tela no lugar do rótulo genérico da rota. Roda antes da
    // caixa de impressão abrir (beforeprint) para pegar telas que montam o
    // conteúdo por JS depois do load.
    (function () {
        function tituloDaTela() {
            var el = document.querySelector('.page-header__title') ||
                     document.querySelector('.modal__title') ||
                     document.querySelector('h1');
            return el ? el.textContent.replace(/\s+/g, ' ').trim() : '';
        }

        function aplicar() {
            var alvo = document.getElementById('docPrintTitulo');
            var titulo = tituloDaTela();
            if (alvo && titulo) alvo.textContent = titulo;
        }

        window.addEventListener('beforeprint', aplicar);
        document.addEventListener('DOMContentLoaded', aplicar);
    })();
</script>
