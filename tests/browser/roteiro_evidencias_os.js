(async () => {
  const log = []; const ok = (c, m) => log.push((c ? '[OK] ' : '[FALHA] ') + m);
  const dorme = (ms) => new Promise((r) => setTimeout(r, ms));
  const $ = (id) => document.getElementById(id);
  const esperar = async (fn, ms = 5000) => { const t = Date.now(); while (Date.now() - t < ms) { if (fn()) return true; await dorme(60); } return false; };
  async function foto(w, h, tipo = 'image/png') {
    const c = document.createElement('canvas'); c.width = w; c.height = h; const g = c.getContext('2d');
    for (let i = 0; i < 80; i++) { g.fillStyle = 'hsl(' + (i * 4) + ',70%,50%)'; g.fillRect(Math.random() * w, Math.random() * h, w / 5, h / 5); }
    const b = await new Promise((r) => c.toBlob(r, tipo)); return new File([b], 'IMG_2031.png', { type: tipo });
  }
  async function escolher(arq) { const dt = new DataTransfer(); dt.items.add(arq); $('foto-evidencia').files = dt.files; $('foto-evidencia').dispatchEvent(new Event('change')); }

  ok(!!$('foto-evidencia') && !!$('form-evidencia') && !!$('enviar-evidencia'), 'a seção de evidências está na tela');
  ok($('enviar-evidencia').disabled === true, 'o botão Enviar começa desabilitado (sem foto)');
  ok(document.querySelector('input[name=tipo]:checked').value === 'DURANTE', 'tipo padrão: Durante');
  ok($('foto-evidencia').accept === 'image/*' && $('foto-evidencia').getAttribute('capture') === 'environment', 'abre a câmera traseira no celular');
  ok($('form-evidencia').enctype === 'multipart/form-data' && $('form-evidencia').getAttribute('action') === './enviarEvidenciaOS', 'formulário multipart para ./enviarEvidenciaOS (caminho relativo)');

  // foto grande: reduzida ANTES de subir
  const grande = await foto(4000, 3000);
  await escolher(grande);
  ok(await esperar(() => !$('enviar-evidencia').disabled), 'com a foto pronta o botão habilita');
  const f = $('foto-evidencia').files[0];
  ok(f.type === 'image/jpeg' && f.name === 'IMG_2031.jpg' && f.size < grande.size, 'o arquivo do formulário já é a versão reduzida (JPEG, ' + Math.round(grande.size / 1024) + ' KB -> ' + Math.round(f.size / 1024) + ' KB)');
  ok($('previa-evidencia').style.display === 'block' && $('previa-evidencia').src.startsWith('blob:'), 'mostra a prévia da foto');
  ok($('status-evidencia').textContent.includes('Foto pronta') && $('status-evidencia').textContent.includes('KB'), 'diz que a foto está pronta e o tamanho');

  // arquivo que não é imagem
  await escolher(new File(['texto'], 'nota.txt', { type: 'text/plain' }));
  ok(await esperar(() => $('status-evidencia').textContent.includes('Escolha uma foto')), 'arquivo que não é foto: mensagem clara');
  ok($('enviar-evidencia').disabled === true && $('previa-evidencia').style.display === 'none', 'e o envio fica bloqueado, sem prévia');

  // envio: evita duplo toque
  await escolher(await foto(800, 600));
  await esperar(() => !$('enviar-evidencia').disabled);
  let enviou = 0; $('form-evidencia').addEventListener('submit', (e) => { e.preventDefault(); enviou++; });
  $('enviar-evidencia').click();
  ok(enviou === 1 && $('enviar-evidencia').disabled && $('enviar-evidencia').textContent === 'Enviando…', 'ao enviar, o botão trava e mostra "Enviando…" (sem envio duplicado)');

  // miniaturas e legendas
  const itens = [...document.querySelectorAll('.os-tec-evid-item')];
  ok(itens.length === 2 && itens[0].querySelector('a').getAttribute('href').startsWith('./verEvidenciaOS?id='), 'miniaturas apontam para a rota autorizada (nada de caminho de arquivo)');
  ok(itens[0].querySelector('.legenda').textContent.includes('Antes') && itens[1].querySelector('.legenda').textContent.includes('Problema encontrado'), 'legenda com o tipo, quem e quando');

  // celular
  const larg = innerWidth;
  const estouros = [...document.querySelectorAll('body *')].filter((e) => { const r = e.getBoundingClientRect(), cs = getComputedStyle(e); return r.width > 0 && r.right > larg + 1 && cs.position !== 'fixed' && cs.visibility !== 'hidden' && !e.matches('#foto-evidencia'); });
  ok(estouros.length === 0, 'nada estoura a largura de ' + larg + 'px');
  const alvos = [...document.querySelectorAll('.os-tec-evid-tipos span, .os-tec-evid-foto, #enviar-evidencia')].map((e) => e.getBoundingClientRect().height);
  ok(alvos.every((a) => a >= 44), 'alvos de toque da seção têm 44 px ou mais (' + alvos.map(Math.round).join(', ') + ')');
  return JSON.stringify(log);
})()
