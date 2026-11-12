// Executa um roteiro de verificações (arquivo .js que devolve JSON.stringify(log)) numa página HTML local, em Chrome com emulação de celular.
// uso: node page_test.mjs <pagina.html> <roteiro.js> [largura=390] [altura=844]
import { spawn } from 'node:child_process';
import { mkdtempSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const [, , pagina, roteiro, w = '390', h = '844'] = process.argv;
const dir = mkdtempSync(join(tmpdir(), 'pt-'));
const porta = 9300 + Math.floor(Math.random() * 600);
const chrome = spawn((process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'), ['--headless=new', '--disable-gpu', '--no-first-run', `--remote-debugging-port=${porta}`, `--user-data-dir=${join(dir, 'p')}`, 'about:blank'], { stdio: 'ignore' });
const dorme = (ms) => new Promise((r) => setTimeout(r, ms));
async function wsUrl() { for (let i = 0; i < 60; i++) { try { const l = await (await fetch(`http://127.0.0.1:${porta}/json/list`)).json(); const p = l.find((x) => x.type === 'page'); if (p) return p.webSocketDebuggerUrl; } catch {} await dorme(250); } throw new Error('Chrome não respondeu'); }

try {
  const sock = new WebSocket(await wsUrl());
  await new Promise((r) => (sock.onopen = r));
  let id = 0; const pend = new Map(); const ev = [];
  sock.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && pend.has(d.id)) { pend.get(d.id)(d); pend.delete(d.id); } else ev.push(d); };
  const cmd = (method, params = {}) => new Promise((res) => { const i = ++id; pend.set(i, res); sock.send(JSON.stringify({ id: i, method, params })); });
  await cmd('Page.enable'); await cmd('Runtime.enable');
  await cmd('Emulation.setDeviceMetricsOverride', { width: +w, height: +h, deviceScaleFactor: 1, mobile: true });
  await cmd('Page.navigate', { url: /^https?:/.test(pagina) ? pagina : pathToFileURL(pagina).href });
  for (let i = 0; i < 60 && !ev.some((e) => e.method === 'Page.loadEventFired'); i++) await dorme(200);
  await dorme(1000);
  const erros = ev.filter((e) => e.method === 'Runtime.exceptionThrown').map((e) => e.params.exceptionDetails.exception?.description || e.params.exceptionDetails.text);
  const r = await cmd('Runtime.evaluate', { expression: readFileSync(roteiro, 'utf8'), awaitPromise: true, returnByValue: true });
  if (r.result.exceptionDetails) { console.log('EXCEÇÃO', JSON.stringify(r.result.exceptionDetails).slice(0, 700)); process.exitCode = 1; }
  else {
    const linhas = JSON.parse(r.result.result.value); linhas.forEach((l) => console.log('  ' + l));
    const falhas = linhas.filter((l) => l.startsWith('[FALHA]')).length;
    if (erros.length) console.log('  [aviso] erros de JS na página:', erros.slice(0, 3).join(' || '));
    console.log(`\n== ${linhas.length - falhas}/${linhas.length} verificações OK${falhas ? ' — ' + falhas + ' FALHA(S)' : ''}`);
    if (falhas) process.exitCode = 1;
  }
  sock.close();
} catch (e) { console.error('ERRO', e.message); process.exitCode = 1; } finally { chrome.kill(); }
