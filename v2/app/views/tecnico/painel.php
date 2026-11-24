<?php
/**
 * Painel do técnico (celular primeiro): a lista do que ele precisa executar, em três abas
 * (a fazer, em validação, concluídos). Cada cartão abre a execução do serviço.
 * Sem valores nem indicadores: veículo, o que fazer, orientação, situação e prazo.
 * Espera: $aba, $servicos, $contagens, $pedidosAbertos
 */
$primeiro = explode(' ', trim((string) (Contexto::usuario()['nome'] ?? '')))[0];
$hoje = date('Y-m-d');
$situacao = function (array $s): array {
    $devolvida = $s['execucao_devolvida_em'] && $s['execucao_devolvida_em'] > ($s['execucao_enviada_em'] ?? '');
    return match (true) {
        $devolvida && in_array($s['status'], ['EM_EXECUCAO', 'AGUARDANDO_PECA'], true) => ['Devolvido para corrigir', 'alerta'],
        $s['status'] === 'APROVADA' || ($s['status'] === 'EM_EXECUCAO' && !$s['execucao_iniciada_em']) => ['Para começar', 'novo'],
        $s['status'] === 'EM_EXECUCAO' => ['Em andamento', 'andamento'],
        $s['status'] === 'AGUARDANDO_PECA' => ['Aguardando peça', 'espera'],
        $s['status'] === 'AGUARDANDO_VALIDACAO' => ['Aguardando validação', 'espera'],
        $s['status'] === 'PRONTA' => ['Validado', 'feito'],
        default => ['Entregue', 'feito'],
    };
};
$vazios = [
    'fazer' => ['Nenhum serviço para fazer agora.', 'Quando o administrador passar um serviço para você, ele aparece aqui.'],
    'validacao' => ['Nada esperando validação.', 'Os serviços que você terminar ficam aqui até o administrador conferir.'],
    'feitos' => ['Nenhum serviço concluído nos últimos 30 dias.', ''],
];
?>
<div class="tec">
  <header class="tec-cabeca">
    <p class="tec-cabeca__oi">Olá, <?= e($primeiro) ?></p>
    <h1 class="tec-cabeca__titulo">Meus serviços</h1>
  </header>

  <?php if ($pedidosAbertos): ?>
    <a class="tec-faixa" href="./pedidos"><?= icone('box', 18) ?><span><?= $pedidosAbertos ?> <?= $pedidosAbertos === 1 ? 'pedido de material em aberto' : 'pedidos de material em aberto' ?></span><?= icone('chevron-right', 16, 2) ?></a>
  <?php endif; ?>

  <nav class="tec-abas" aria-label="Situação dos serviços">
    <?php foreach (ServicoTecnico::ABAS as $k => [$rotulo]): ?>
      <a href="./servicos<?= $k === 'fazer' ? '' : '?aba=' . $k ?>"<?= $aba === $k ? ' class="ativa" aria-current="page"' : '' ?>><?= e($rotulo) ?><span><?= (int) $contagens[$k] ?></span></a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$servicos): ?>
    <div class="tec-vazio"><?= icone($aba === 'feitos' ? 'success' : 'mechanic', 32, 1.25) ?><strong><?= e($vazios[$aba][0]) ?></strong><?php if ($vazios[$aba][1]): ?><span><?= e($vazios[$aba][1]) ?></span><?php endif; ?></div>
  <?php else: ?>
    <ul class="tec-lista">
      <?php foreach ($servicos as $s): [$rotSit, $tom] = $situacao($s);
        $prazo = $s['previsao_conclusao'];
        $atrasado = $prazo && !in_array($s['status'], ['AGUARDANDO_VALIDACAO', 'PRONTA', 'ENTREGUE'], true) && strtotime($prazo) < time();
        $oQue = $s['servicos'] ?: $s['defeito_relatado']; ?>
        <li>
          <a class="tec-cartao tec-cartao--<?= $tom ?>" href="./servico?id=<?= (int) $s['id'] ?>">
            <span class="tec-cartao__topo">
              <span class="tec-situacao tec-situacao--<?= $tom ?>"><?= e($rotSit) ?></span>
              <span class="tec-cartao__os"><?= os_numero($s['numero']) ?></span>
            </span>
            <span class="tec-cartao__carro"><?= e($s['modelo'] ?: 'Veículo') ?></span>
            <span class="tec-cartao__placa"><span class="tec-placa"><?= e($s['placa']) ?></span><?= $s['cor'] ? ' ' . e($s['cor']) : '' ?><?= $s['ano'] ? ' · ' . (int) $s['ano'] : '' ?></span>
            <?php if ($oQue): ?><span class="tec-cartao__fazer"><?= e(mb_strimwidth($oQue, 0, 140, '…')) ?></span><?php endif; ?>
            <?php if ($s['status'] !== 'PRONTA' && $s['status'] !== 'ENTREGUE' && $s['execucao_devolvida_em'] && $s['execucao_devolvida_em'] > ($s['execucao_enviada_em'] ?? '')): ?>
              <span class="tec-cartao__nota tec-cartao__nota--alerta"><?= icone('warning', 15) ?><?= e(mb_strimwidth((string) $s['execucao_devolucao_motivo'], 0, 120, '…')) ?></span>
            <?php elseif ($s['observacoes_internas'] && $aba === 'fazer'): ?>
              <span class="tec-cartao__nota"><?= icone('message', 15) ?><?= e(mb_strimwidth($s['observacoes_internas'], 0, 120, '…')) ?></span>
            <?php endif; ?>
            <span class="tec-cartao__pe">
              <?php if ($s['status'] === 'AGUARDANDO_VALIDACAO'): ?>
                <span>Enviado em <?= data_br($s['execucao_enviada_em'], true) ?></span>
              <?php elseif (in_array($s['status'], ['PRONTA', 'ENTREGUE'], true)): ?>
                <span>Concluído em <?= data_br($s['concluido_em']) ?></span>
              <?php elseif ($prazo): ?>
                <span class="<?= $atrasado ? 'tec-atrasado' : '' ?>"><?= icone('clock', 14) ?> <?= $atrasado ? 'Atrasado · era para ' : 'Entregar ' ?><?= substr($prazo, 0, 10) === $hoje ? 'hoje às ' . date('H:i', strtotime($prazo)) : data_br($prazo, true) ?></span>
              <?php else: ?>
                <span>Sem prazo definido</span>
              <?php endif; ?>
              <span class="tec-cartao__cliente"><?= e($s['cliente']) ?></span>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
