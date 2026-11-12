// Fotografa uma página HTML local com emulação REAL de celular (Chrome DevTools Protocol) e mede estouro horizontal.
// uso: node cdp.mjs <arquivo.html> <saida.png> [largura=390] [altura=844] [mobile=1]
import { spawn } from 'node:child_process';
import { writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const [, , arquivo, saida, w = '390', h = '844', mobile = '1'] = process.argv;
const porta = 9300 + Math.floor(Math.random() * 400);
const perfil = mkdtempSync(join(tmpdir(), 'cdp-'));
const chrome = spawn((process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'), [
  '--headless=new', '--disable-gpu', '--no-first-run', `--remote-debugging-port=${porta}`, `--user-data-dir=${perfil}`, '--hide-scrollbars', 'about:blank',
], { stdio: 'ignore' });

const dorme = (ms) => new Promise((r) => setTimeout(r, ms));
async function alvo() {
  for (let i = 0; i < 60; i++) {
    try {
      const lista = await (await fetch(`http://127.0.0.1:${porta}/json/list`)).json();
      const pag = lista.find((x) => x.type === 'page');
      if (pag) return pag.webSocketDebuggerUrl;
    } catch {}
    await dorme(250);
  }
  throw new Error('Chrome não respondeu');
}

try {
  const ws = new WebSocket(await alvo());
  await new Promise((r) => (ws.onopen = r));
  let id = 0; const pend = new Map(); const eventos = [];
  ws.onmessage = (m) => { const d = JSON.parse(m.data); if (d.id && pend.has(d.id)) { pend.get(d.id)(d); pend.delete(d.id); } else eventos.push(d); };
  const cmd = (method, params = {}) => new Promise((res) => { const i = ++id; pend.set(i, res); ws.send(JSON.stringify({ id: i, method, params })); });

  await cmd('Page.enable');
  await cmd('Emulation.setDeviceMetricsOverride', { width: +w, height: +h, deviceScaleFactor: 1, mobile: mobile === '1' });
  if (mobile === '1') await cmd('Emulation.setTouchEmulationEnabled', { enabled: true });
  await cmd('Page.navigate', { url: /^https?:/.test(arquivo) ? arquivo : pathToFileURL(arquivo).href });
  for (let i = 0; i < 40 && !eventos.some((e) => e.method === 'Page.loadEventFired'); i++) await dorme(250);
  await dorme(900);

  const medida = await cmd('Runtime.evaluate', { returnByValue: true, expression: `(() => {
    const W = window.innerWidth, out = [];
    document.querySelectorAll('body *').forEach((e) => {
      const r = e.getBoundingClientRect(), cs = getComputedStyle(e);
      if (r.width > 0 && r.right > W + 1 && cs.position !== 'fixed' && cs.visibility !== 'hidden' && cs.display !== 'none') {
        // ignora o que está dentro de um contêiner que rola na horizontal de propósito
        let p = e.parentElement, rola = false;
        while (p && p !== document.body) { const o = getComputedStyle(p).overflowX; if ((o === 'auto' || o === 'scroll' || o === 'hidden') && p.getBoundingClientRect().right <= W + 1) { rola = true; break; } p = p.parentElement; }
        if (!rola) out.push((e.tagName.toLowerCase() + '.' + String(e.className && e.className.baseVal === undefined ? e.className : '').trim().replace(/\\s+/g, '.')).slice(0, 80) + ' right=' + Math.round(r.right) + ' w=' + Math.round(r.width));
      }
    });
    const pequenos = [];
    document.querySelectorAll('a, button, input:not([type=hidden]), select, textarea, [role=button]').forEach((e) => {
      const r = e.getBoundingClientRect(), cs = getComputedStyle(e);
      if (r.width > 0 && cs.visibility !== 'hidden' && (r.height < 32 || r.width < 32) && r.top < 4000) pequenos.push((e.tagName.toLowerCase() + '.' + String(e.className).trim().replace(/\\s+/g, '.')).slice(0, 60) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height) + ' "' + (e.innerText || e.value || e.name || '').trim().slice(0, 24) + '"');
    });
    return { janela: W, documento: document.documentElement.scrollWidth, altura: document.documentElement.scrollHeight, estouros: out.slice(0, 12), total: out.length, alvosPequenos: pequenos.length, exemplosPequenos: pequenos.slice(0, 8) };
  })()` });
const m = medida.result.result.value;

  const lay = await cmd('Page.getLayoutMetrics');
  const alt = Math.min(Math.ceil(lay.result.cssContentSize?.height ?? lay.result.contentSize.height), 6000);
  const shot = await cmd('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: 0, width: +w, height: Math.max(alt, +h), scale: 1 } });
  writeFileSync(saida, Buffer.from(shot.result.data, 'base64'));
  console.log(JSON.stringify(m));
  ws.close();
} catch (e) {
  console.error('ERRO', e.message);
  process.exitCode = 1;
} finally {
  chrome.kill();
}
