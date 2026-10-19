import { createRequire } from 'node:module';
import fs from 'node:fs/promises';
import path from 'node:path';

const { chromium } = createRequire(path.resolve(process.cwd(), 'package.json'))('playwright');

const base = process.env.PRESENTATION_QA_URL ?? 'https://x-change-sandbox.test';
if (!new URL(base).hostname.endsWith('.test')) throw new Error('Presentation QA requires a local .test host.');
const output = process.env.PRESENTATION_QA_OUTPUT ?? '/private/tmp/claim-presentation-qa';
await fs.mkdir(output, { recursive: true });
const browser = await chromium.launch();
const context = await browser.newContext({ ignoreHTTPSErrors: true });
const page = await context.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
await context.route('**/*', route => new URL(route.request().url()).origin === new URL(base).origin ? route.continue() : route.abort());
await page.goto(`${base}/x/auto-generate`);
const cookies = await context.cookies();
const csrf = decodeURIComponent(cookies.find(cookie => cookie.name === 'XSRF-TOKEN')?.value ?? '');
const results = [];
try {
  for (const fields of [[], ['otp'], ['kyc'], ['location'], ['selfie'], ['signature'], ['otp', 'kyc', 'location', 'selfie', 'signature']]) {
    const lane = fields.join('-') || 'basic';
    let response = await context.request.post(`${base}/x/auto-generate/claim-previews`, {
      headers: { 'X-XSRF-TOKEN': csrf, Accept: 'application/json', Origin: base },
      data: { cash: { amount: 25, currency: 'PHP' }, inputs: { fields }, feedback: { mobile: null }, rider: { message: null, url: null, splash: null }, dry_run: true },
    });
    if (response.status() === 429) {
      const delay = Math.min(Number(response.headers()['retry-after'] ?? 60), 60);
      console.log(`Waiting ${delay}s for the application preview throttle`);
      await new Promise(resolve => setTimeout(resolve, delay * 1000 + 100));
      response = await context.request.post(`${base}/x/auto-generate/claim-previews`, {headers: {'X-XSRF-TOKEN':csrf, Accept:'application/json', Origin:base},data:{cash:{amount:25,currency:'PHP'},inputs:{fields},feedback:{mobile:null},rider:{message:null,url:null,splash:null},dry_run:true}});
    }
    if (!response.ok()) throw new Error(`${lane}: preview failed (${response.status()}): ${await response.text()}`);
    const manifest = await response.json();
    if (manifest.safety.money_movement || manifest.safety.provider_calls || manifest.safety.claim_submission) throw new Error('Unsafe preview contract');
    const visited = [];
    for (const step of manifest.journey.steps) {
      if (!step.preview_url) continue;
      await page.goto(new URL(step.preview_url, base).href);
      await page.waitForLoadState('networkidle');
      const profile = page.getByTestId('form-flow-presentation');
      if (!await profile.count()) continue;
      await profile.waitFor();
      visited.push(step.key);
      if (await profile.getAttribute('data-form-flow-profile') !== 'guided-claim-v1') throw new Error(`${lane}/${step.key}: wrong profile`);
      for (const [width, height, enlarged] of [[360,720,false],[390,667,false],[390,844,false],[1280,900,false],[390,440,true]]) {
        await page.setViewportSize({ width, height });
        await page.addStyleTag({ content: `html { font-size: ${enlarged ? 20 : 16}px !important; }` });
        const horizontalOverflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
        if (horizontalOverflow) throw new Error(`${lane}/${step.key}: horizontal overflow at ${width}x${height}`);
        const logo = page.getByTestId('form-flow-presentation-logo');
        if (await logo.count()) {
          const box = await logo.boundingBox();
          if (!box || box.height > 48.5) throw new Error('Logo exceeds inherited constraint');
        }
        const filename = `${lane}-${step.key}-${width}x${height}.png`.replace(/[^a-zA-Z0-9.-]/g,'_');
        await page.screenshot({ path: path.join(output, filename), fullPage: true });
        results.push({ lane, step: step.key, width, height, enlarged, screenshot: filename });
      }
    }
    for (const handler of fields) {
      if (!visited.some(key => key.endsWith(`-${handler}`))) throw new Error(`${lane}: ${handler} screen was not exercised`);
    }
  }
  if (errors.length) throw new Error(`Browser errors: ${errors.join('; ')}`);
  if (!results.length) throw new Error('No inherited presentation screens were exercised');
  await fs.writeFile(path.join(output,'results.json'), JSON.stringify({results, errors},null,2));
  await fs.writeFile(path.join(output,'index.html'), `<!doctype html><meta charset="utf-8"><title>Claim presentation QA</title><style>body{font:16px system-ui;margin:24px}section{display:inline-block;vertical-align:top;width:320px;margin:12px}img{width:100%;border:1px solid #ddd}</style><h1>Claim presentation QA</h1><p>${results.length} rendered checks; synthetic, no provider calls or claim submission.</p>${results.map(r=>`<section><h2>${r.lane}</h2><p>${r.step} · ${r.width}×${r.height}${r.enlarged?' · enlarged text':''}</p><a href="${r.screenshot}"><img src="${r.screenshot}"></a></section>`).join('')}`);
  console.log(JSON.stringify({ checks: results.length, report: path.join(output,'index.html') }));
} finally { await browser.close(); }
