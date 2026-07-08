/* =========================================================
   Auto-capitalização da 1ª letra de cada palavra enquanto
   digita — estilo "adicionar contato" do celular.
   Aplica-se a qualquer input com a classe .js-capitalize-words.
   ========================================================= */
(function () {
  function capitalizarPalavras(valor) {
    // Maiúscula no início e logo após um espaço; o resto fica como digitado.
    return valor.replace(/(^|\s)(\p{L})/gu, function (_, separador, letra) {
      return separador + letra.toLocaleUpperCase('pt-BR');
    });
  }

  document.querySelectorAll('.js-capitalize-words').forEach(function (campo) {
    campo.addEventListener('input', function () {
      var inicio = campo.selectionStart;
      var fim = campo.selectionEnd;
      var novo = capitalizarPalavras(campo.value);
      if (novo !== campo.value) {
        campo.value = novo;
        // troca é 1:1 (não muda o tamanho), então o cursor não se desloca
        campo.setSelectionRange(inicio, fim);
      }
    });
  });
})();
