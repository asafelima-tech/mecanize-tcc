/* =========================================================
   Equivalente em JavaScript ao htmlspecialchars() do PHP.

   As telas montam pedaços de HTML na mão com innerHTML e template
   literal — os painéis de "Clientes VIP", "Novos no mês" e "Novos
   usuários", por exemplo. O dado vem do banco, então um cliente
   cadastrado com o nome

       <img src=x onerror="...">

   deixava de ser texto e virava uma tag de verdade dentro da página,
   rodando script na sessão de quem abrisse o painel (normalmente o
   administrador). Nas telas geradas pelo PHP isso nunca aconteceu
   porque lá tudo passa por htmlspecialchars(); faltava o mesmo
   cuidado do lado do JavaScript.

   Trocar os 5 caracteres abaixo faz o navegador exibir o nome como
   texto, exatamente como foi digitado, sem interpretá-lo como HTML.
   ========================================================= */
(function () {
  var TROCAS = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };

  // null/undefined viram string vazia: as telas já usam o resultado
  // direto em "campo || 'Não informado'".
  window.escaparHtml = function (valor) {
    if (valor === null || valor === undefined) return '';

    return String(valor).replace(/[&<>"']/g, function (caractere) {
      return TROCAS[caractere];
    });
  };
})();
