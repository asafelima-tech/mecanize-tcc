// Testa public/assets/js/placa-ocr.js num Chrome real (emulação de celular), SEM servidor nem banco: fetch é simulado.
import { spawn } from 'node:child_process';
import { writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL, fileURLToPath } from 'node:url';

const raiz = fileURLToPath(new URL('../../public/assets/js/placa-ocr.js', import.meta.url));
const fotoUpload = fileURLToPath(new URL('../../public/assets/js/foto-upload.js', import.meta.url));
const globalCss = fileURLToPath(new URL('../../public/assets/css/global.css', import.meta.url));
const html = `<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="${pathToFileURL(globalCss).href}">
<body style="padding:12px;max-width:390px">
<input type="file" id="foto" accept="image/*"><div id="painel"></div><input id="placa">
<script src="${pathToFileURL(fotoUpload).href}"></script><script src="${pathToFileURL(raiz).href}"></script>`;
const dir = mkdtempSync(join(tmpdir(), 'ocr-'));
const arquivo = join(dir, 'harness.html');
writeFileSync(arquivo, html);

const porta = 9700 + Math.floor(Math.random() * 200);
const chrome = spawn((process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'), ['--headless=new', '--disable-gpu', '--no-first-run', `--remote-debugging-port=${porta}`, `--user-data-dir=${join(dir, 'perfil')}`, '--allow-file-access-from-files', 'about:blank'], { stdio: 'ignore' });
const dorme = (ms) => new Promise((r) => setTimeout(r, ms));

async function ws() {
  for (let i = 0; i < 60; i++) { try { const l = await (await fetch(`http://127.0.0.1:${porta}/json/list`)).json(); const p = l.find((x) => x.type === 'page'); if (p) return p.webSocketDebuggerUrl; } catch {} await dorme(250); }
  throw new Error('Chrome não respondeu');
}

const testes = String.raw`
(async () => {
  const log = [];
  const ok = (c, m) => log.push((c ? '[OK] ' : '[FALHA] ') + m);
  const dorme = (ms) => new Promise((r) => setTimeout(r, ms));
  const painel = () => document.getElementById('painel');
  const texto = () => painel().innerText;
  const botoes = () => [...painel().querySelectorAll('button')].map((b) => b.textContent);
  const clicar = (t) => [...painel().querySelectorAll('button')].find((b) => b.textContent.startsWith(t)).click();
  const esperar = async (fn, ms = 4000) => { const t = Date.now(); while (Date.now() - t < ms) { if (fn()) return true; await dorme(50); } return false; };

  // foto de teste real (canvas -> png) e arquivos
  async function foto(w, h, tipo = 'image/png') {
    const c = document.createElement('canvas'); c.width = w; c.height = h; const g = c.getContext('2d');
    for (let i = 0; i < 60; i++) { g.fillStyle = 'hsl(' + (i * 6) + ',70%,50%)'; g.fillRect(Math.random() * w, Math.random() * h, w / 4, h / 4); }
    const b = await new Promise((r) => c.toBlob(r, tipo)); return new File([b], 'placa.png', { type: tipo });
  }
  let chamadas = 0, abortos = 0, resposta = null, ultimoCorpo = null;
  window.fetch = (url, op) => new Promise((resolve, reject) => {
    chamadas++; ultimoCorpo = op && op.body;
    if (op && op.signal) op.signal.addEventListener('abort', () => { abortos++; reject(new DOMException('Aborted', 'AbortError')); });
    if (resposta === 'REDE') { reject(new TypeError('Failed to fetch')); return; }
    if (resposta === 'PENDENTE') return; // nunca responde (para testar cancelar)
    setTimeout(() => resolve({ text: () => Promise.resolve(typeof resposta === 'string' ? resposta : JSON.stringify(resposta)) }), 20);
  });

  let confirmou = null, digitar = 0;
  const leitor = PlacaOcr.iniciar({ inputFoto: '#foto', painel: '#painel', rota: './lerPlaca',
    aoConfirmar: (p, o) => { confirmou = [p, o]; document.getElementById('placa').value = PlacaOcr.formatar(p); }, aoDigitar: () => { digitar++; } });
  const escolher = async (arq) => { const dt = new DataTransfer(); dt.items.add(arq); const i = document.getElementById('foto'); i.files = dt.files; i.dispatchEvent(new Event('change')); };

  // 0) funções puras
  ok(PlacaOcr.normalizar(' abc-1d23 ') === 'ABC1D23' && PlacaOcr.formato('ABC1D23') === 'MERCOSUL' && PlacaOcr.formato('ABC1234') === 'ANTIGO' && PlacaOcr.formato('ABC12D3') === null, 'normalizar/formato');
  ok(PlacaOcr.formatar('ABC1234') === 'ABC-1234' && PlacaOcr.formatar('ABC1D23') === 'ABC1D23', 'formatar: antiga com hífen, Mercosul sem');

  // 1) leitura boa: sugestão, NÃO preenche sozinho
  resposta = { ok: true, sucesso: true, placa: 'ABC1D23', formato: 'MERCOSUL', confianca: 0.97, baixa_confianca: false, candidatos: [{ placa: 'ABC1023', confianca: 0.5, formato: 'ANTIGO' }] };
  await escolher(await foto(800, 600));
  ok(await esperar(() => texto().includes('ABC1D23')), 'mostra a placa lida');
  ok(chamadas === 1 && texto().includes('confiança 97%') && texto().includes('Placa Mercosul'), 'mostra confiança e formato');
  ok(document.getElementById('placa').value === '' && confirmou === null, 'NÃO preencheu o campo sozinha (aguarda confirmação)');
  ok(botoes().includes('Usar esta placa') && botoes().includes('Corrigir') && botoes().includes('Descartar'), 'oferece usar / corrigir / descartar');
  ok(botoes().some((b) => b.startsWith('ABC-1023')), 'oferece a alternativa lida');
  clicar('Usar esta placa');
  ok(confirmou && confirmou[0] === 'ABC1D23' && confirmou[1] === 'FOTO' && document.getElementById('placa').value === 'ABC1D23', 'só depois do clique o campo é preenchido');
  ok(texto().includes('confirmada por você'), 'a tela registra que a pessoa confirmou');

  // 2) leitura incerta: alerta, ainda sem preencher
  confirmou = null; resposta = { ok: true, placa: 'ABC1D23', formato: 'MERCOSUL', confianca: 0.61, baixa_confianca: true, candidatos: [] };
  await escolher(await foto(400, 300));
  ok(await esperar(() => texto().includes('compare letra por letra')), 'leitura incerta pede conferência letra por letra');
  ok(confirmou === null, 'incerta também não preenche sozinha');
  clicar('Descartar'); ok(texto().includes('descartada') && confirmou === null, 'descartar não preenche nada');

  // 3) formato impossível: sem "Usar esta placa"; corrigir valida
  resposta = { ok: true, placa: 'ABC12D3', formato: null, confianca: 0.99, baixa_confianca: true, erro: 'O texto lido ("ABC12D3") não tem formato de placa brasileira. Confira com cuidado.', candidatos: [] };
  await escolher(await foto(400, 300));
  ok(await esperar(() => texto().includes('formato de placa brasileira')), 'avisa que o texto não é uma placa brasileira');
  ok(!botoes().includes('Usar esta placa') && botoes().includes('Corrigir'), 'sem botão de usar quando o formato é impossível');
  clicar('Corrigir');
  const campo = painel().querySelector('input'); campo.value = 'XX99'; clicar('Confirmar');
  ok(texto().includes('Placa inválida') && confirmou === null, 'correção inválida é recusada');
  campo.value = 'abc-1234'; clicar('Confirmar');
  ok(confirmou && confirmou[0] === 'ABC1234' && confirmou[1] === 'CORRIGIDA' && document.getElementById('placa').value === 'ABC-1234', 'correção válida é confirmada (origem CORRIGIDA)');

  // 4) serviço não configurado: sem nova tentativa, só digitar
  confirmou = null; resposta = { ok: false, sucesso: false, codigo: 'NAO_CONFIGURADO', erro: 'A leitura automática de placa não está configurada neste sistema. Digite a placa.' };
  await escolher(await foto(300, 200));
  ok(await esperar(() => texto().includes('não está configurada')), 'mostra o erro compreensível');
  ok(!botoes().includes('Tentar de novo') && botoes().includes('Digitar a placa'), 'não oferece repetir quando é configuração; oferece digitar');
  clicar('Digitar a placa'); ok(digitar === 1, 'digitar devolve o controle ao formulário');

  // 5) rede caiu: oferece tentar de novo
  resposta = 'REDE'; const antes = chamadas;
  await escolher(await foto(300, 200));
  ok(await esperar(() => texto().includes('Sem conexão')), 'queda de rede vira mensagem clara');
  ok(botoes().includes('Tentar de novo'), 'oferece tentar de novo');
  resposta = { ok: true, placa: 'DEF2G34', formato: 'MERCOSUL', confianca: 0.93, baixa_confianca: false, candidatos: [] };
  clicar('Tentar de novo'); ok(await esperar(() => texto().includes('DEF2G34')) && chamadas === antes + 2, 'tentar de novo reenvia e mostra o resultado');

  // 6) resposta que não é JSON
  resposta = '<html>erro php</html>';
  await escolher(await foto(300, 200));
  ok(await esperar(() => texto().includes('resposta inesperada')), 'resposta não-JSON não quebra a tela');

  // 7) cancelar durante a leitura
  resposta = 'PENDENTE';
  await escolher(await foto(300, 200));
  ok(await esperar(() => botoes().includes('Cancelar')), 'durante a leitura há botão Cancelar');
  clicar('Cancelar'); ok(await esperar(() => texto().includes('cancelada')), 'cancelar aborta o envio');

  // 8) duas fotos seguidas: só a última vale
  resposta = { ok: true, placa: 'GHI3J45', formato: 'MERCOSUL', confianca: 0.95, baixa_confianca: false, candidatos: [] };
  const c0 = chamadas, a0 = abortos;
  leitor.processar(await foto(300, 200)); leitor.processar(await foto(300, 200));
  ok(await esperar(() => texto().includes('GHI3J45')), 'a segunda foto é a que vale');
  await dorme(300); ok(chamadas - c0 === 2 && abortos - a0 === 1, 'a leitura da primeira foto foi CANCELADA ao escolher a segunda (' + (chamadas - c0) + ' envios, ' + (abortos - a0) + ' abortado)');

  // 9) redução da foto no aparelho
  const grande = await foto(4000, 3000);
  const reduzida = await PlacaOcr.reduzir(grande);
  const bmp = await createImageBitmap(reduzida);
  ok(reduzida.type === 'image/jpeg' && Math.max(bmp.width, bmp.height) <= 1280 && reduzida.size < grande.size, 'foto 4000x3000 vira JPEG ' + bmp.width + 'x' + bmp.height + ' (' + Math.round(grande.size / 1024) + ' KB -> ' + Math.round(reduzida.size / 1024) + ' KB)');
  const pequena = await foto(300, 200); const mesma = await PlacaOcr.reduzir(pequena);
  ok(mesma === pequena, 'foto pequena segue como está (sem recompressão desnecessária)');
  let erro = null; try { await PlacaOcr.reduzir(new File(['x'], 'a.txt', { type: 'text/plain' })); } catch (e) { erro = e.message; }
  ok(erro && erro.includes('Escolha uma foto'), 'arquivo que não é imagem é recusado com mensagem');
  resposta = { ok: true, placa: 'JKL4M56', formato: 'MERCOSUL', confianca: 0.9, baixa_confianca: false, candidatos: [] };
  await escolher(await foto(3000, 2000)); await esperar(() => texto().includes('JKL4M56'));
  ok(ultimoCorpo instanceof FormData && ultimoCorpo.get('imagem') instanceof Blob && ultimoCorpo.get('imagem').type === 'image/jpeg', 'o envio leva a foto reduzida (image/jpeg) no campo "imagem"');

  // 10) mobile: nada estoura a largura
  const estouro = [...document.querySelectorAll('body *')].filter((e) => e.getBoundingClientRect().right > innerWidth + 1).length;
  ok(estouro === 0, 'painel cabe na largura do celular (' + innerWidth + 'px)');
  return JSON.stringify(log);
})()`;

try {
  const sock = new WebSocket(await ws());
  await new Promise((r) => (sock.onopen = r));
  let id = 0; const pend = new Map(); const ev = [];
  sock.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && pend.has(d.id)) { pend.get(d.id)(d); pend.delete(d.id); } else ev.push(d); };
  const cmd = (method, params = {}) => new Promise((res) => { const i = ++id; pend.set(i, res); sock.send(JSON.stringify({ id: i, method, params })); });
  await cmd('Page.enable');
  await cmd('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
  await cmd('Page.navigate', { url: pathToFileURL(arquivo).href });
  for (let i = 0; i < 40 && !ev.some((e) => e.method === 'Page.loadEventFired'); i++) await dorme(150);
  const r = await cmd('Runtime.evaluate', { expression: testes, awaitPromise: true, returnByValue: true });
  if (r.result.exceptionDetails) { console.log('EXCEÇÃO', JSON.stringify(r.result.exceptionDetails).slice(0, 600)); process.exitCode = 1; }
  else {
    const linhas = JSON.parse(r.result.result.value); linhas.forEach((l) => console.log('  ' + l));
    const falhas = linhas.filter((l) => l.startsWith('[FALHA]')).length;
    console.log(`\n== ${linhas.length - falhas}/${linhas.length} verificações OK${falhas ? ' — ' + falhas + ' FALHA(S)' : ''}`);
    if (falhas) process.exitCode = 1;
  }
  sock.close();
} catch (e) { console.error('ERRO', e.message); process.exitCode = 1; } finally { chrome.kill(); }
