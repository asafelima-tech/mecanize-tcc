<?php /** Lado escuro das telas de acesso (Design System · 11 · Login). Ilustração decorativa da marca. */ ?>
<section class="login-vitrine" aria-label="Mecanize">
  <img class="login-vitrine__marca-dagua" src="./img/logo-branco.png<?= $logoV ?? '' ?>" data-recorte="simbolo" alt="" aria-hidden="true">
  <img class="login-vitrine__logo" src="./img/logo-branco.png<?= $logoV ?? '' ?>" data-recorte="logo" alt="Mecanize">

  <!-- Prévia do sistema: os mesmos cartões do Início e da Agenda (números do painel e um agendamento). -->
  <div class="login-vitrine__cena vit" aria-hidden="true">
    <div class="vit-cartao vit-cartao--numero vit-cartao--fat">
      <div class="vit-numero__topo"><span>Faturamento mensal</span><i class="vit-chip">+45%</i></div>
      <strong class="vit-numero__valor">R$ 6.429,90</strong>
      <span class="vit-numero__pe">16 OS entregues no mês</span>
    </div>
    <div class="vit-cartao vit-cartao--numero vit-cartao--escuro vit-cartao--andamento">
      <div class="vit-numero__topo"><span>Em andamento</span><i class="vit-chip">3 prontas</i></div>
      <strong class="vit-numero__valor">5</strong>
      <span class="vit-numero__pe">Serviços em execução</span>
    </div>
    <div class="vit-cartao vit-cartao--agenda">
      <div class="vit-agenda__hora"><b>09:00</b><span>até 10:00</span></div>
      <div class="vit-agenda__corpo">
        <div class="vit-agenda__titulo"><b>Troca de óleo e filtros</b><i class="vit-badge"><i></i>Agendado</i></div>
        <span class="vit-agenda__meta">Fernanda Costa Lima · <b>ILD6X26</b> · Gabriel</span>
      </div>
      <span class="vit-botao">Confirmar</span>
    </div>
  </div>

  <div class="login-vitrine__texto">
    <strong>Da agenda ao caixa, a oficina em um só lugar.</strong>
    <span>Orçamentos, ordens de serviço, estoque e financeiro.</span>
  </div>
</section>
