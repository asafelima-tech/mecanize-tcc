/* =========================================================
   Máscaras de formulário com IMask
   CPF, CNPJ, telefone e CEP — mesmo formato que o back-end já
   aceita (com pontuação). Cada bloco só roda se o campo existir.
   ========================================================= */
(function () {
  if (typeof IMask === 'undefined') return;

  // CPF — 000.000.000-00
  const cpf = document.getElementById('cpf');
  if (cpf) IMask(cpf, { mask: '000.000.000-00' });

  // CNPJ — 00.000.000/0000-00
  const cnpj = document.getElementById('cnpj');
  if (cnpj) IMask(cnpj, { mask: '00.000.000/0000-00' });

  // Telefone — fixo (00) 0000-0000 ou celular (00) 00000-0000
  const telefone = document.getElementById('telefone');
  if (telefone) IMask(telefone, {
    mask: [
      { mask: '(00) 0000-0000' },
      { mask: '(00) 00000-0000' }
    ]
  });

  // CEP — 00000-000
  const cep = document.getElementById('cep');
  if (cep) IMask(cep, { mask: '00000-000' });

  // Placa — máscara dinâmica entre os dois formatos brasileiros, sempre em
  // maiúsculas. Alterna conforme o 5º caractere: letra → Mercosul (ABC1D23),
  // dígito → antiga (ABC-1234).
  const placa = document.getElementById('placa');
  if (placa) {
    const placaMask = IMask(placa, {
      mask: [
        { mask: 'aaa-0000' }, // antiga: ABC-1234
        { mask: 'aaa0a00' }   // Mercosul: ABC1D23
      ],
      prepare: function (str) { return str.toUpperCase(); }
    });

    // Mantém o campo oculto com a placa "crua" (sem traço) sincronizado,
    // quando ele existir (tela de cadastro de veículo).
    const placaNorm = document.getElementById('placa_normalizada');
    function sincronizarPlacaNormalizada() {
      if (placaNorm) {
        placaNorm.value = placa.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 7);
      }
    }
    placaMask.on('accept', sincronizarPlacaNormalizada);
    sincronizarPlacaNormalizada();

    // Exposto para o preenchimento automático via OCR (saveVeiculo → lerPlaca).
    window.placaMask = placaMask;
  }
})();
