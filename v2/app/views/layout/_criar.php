<?php
/**
 * Botão "Criar" do topo: um painel que abre logo abaixo dele com os atalhos para os formulários de criação que já existem.
 * Só os nomes, numa coluna; as áreas (atendimento, cadastros, estoque, financeiro, equipe) são separadas por uma linha fina.
 * Cada opção só aparece se o perfil pode abrir a rota (o nível vem de config/rotas.php, como no menu).
 * Espera: $rotas, $perfil, $somenteLeitura.
 */
$gruposCriar = [
    'Atendimento' => [
        ['os-nova', '', 'work-order', 'Ordem de serviço'],
        ['agenda-form', '', 'calendar', 'Agendamento'],
    ],
    'Cadastros' => [
        ['cliente-form', '', 'user', 'Cliente'],
        ['veiculo-form', '', 'car', 'Veículo'],
        ['produto-form', 'tipo=PECA', 'part', 'Peça'],
        ['produto-form', 'tipo=SERVICO', 'mechanic', 'Serviço'],
        ['fornecedor-form', '', 'transfer', 'Fornecedor'],
    ],
    'Estoque' => [
        ['estoque-entrada', '', 'download', 'Entrada de peças'],
        ['estoque-ajuste', '', 'checklist', 'Ajuste de estoque'],
    ],
    'Financeiro' => [
        ['conta-form', 'tipo=RECEBER', 'trend-up', 'Conta a receber'],
        ['conta-form', 'tipo=PAGAR', 'trend-down', 'Conta a pagar'],
    ],
    'Equipe' => [
        ['usuario-form', '', 'users', 'Usuário'],
    ],
    'Plataforma' => [
        ['plataforma-nova', '', 'dashboard', 'Oficina'],
    ],
];
$visiveis = [];
foreach ($gruposCriar as $grupo => $itens) {
    $itens = array_values(array_filter($itens, fn($i) => isset($rotas[$i[0]]) && Permissao::permite($perfil, $rotas[$i[0]][2])));
    if ($itens) {
        $visiveis[$grupo] = $itens;
    }
}
if (!$visiveis) return;
?>
<div class="criar-menu">
  <button type="button" class="topbar-criar" data-abrir-criar aria-haspopup="true" aria-expanded="false" aria-controls="painel-criar">
    <span class="topbar-criar__mais" aria-hidden="true"><?= icone('plus', 14, 2.25) ?></span>Criar
  </button>

  <div id="painel-criar" class="painel-criar" role="menu" aria-label="Criar" hidden>
    <?php if (!empty($somenteLeitura)): ?>
      <p class="painel-criar__aviso">Licença em modo de consulta: os formulários abrem, mas não salvam.</p>
    <?php endif; ?>
    <?php foreach ($visiveis as $grupo => $itens): ?>
      <div class="painel-criar__grupo" role="group" aria-label="<?= e($grupo) ?>">
        <?php foreach ($itens as [$rota, $query, , $rotulo]): ?>
          <a class="painel-criar__opcao" role="menuitem" href="./<?= e($rota) ?><?= $query !== '' ? '?' . e($query) : '' ?>"><?= e($rotulo) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
