import fs from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const scenario =
    process.env.XCHANGE_CLAIM_WALKTHROUGH_SCENARIO ?? 'claim_basic_no_rider';
const baseUrl =
    process.env.XCHANGE_CLAIM_WALKTHROUGH_BASE_URL ?? 'http://localhost';
const artifactDirectory = process.env.XCHANGE_CLAIM_WALKTHROUGH_ARTIFACT_DIR;
const headed = process.env.XCHANGE_CLAIM_WALKTHROUGH_HEADED === '1';
const slowMo = Number.parseInt(
    process.env.XCHANGE_CLAIM_WALKTHROUGH_SLOW_MO ?? '100',
    10,
);
const payCode = (process.env.XCHANGE_CLAIM_WALKTHROUGH_PAY_CODE ?? '')
    .trim()
    .toUpperCase();
const mobile = process.env.XCHANGE_CLAIM_WALKTHROUGH_MOBILE ?? '09173011987';
const bankCode =
    process.env.XCHANGE_CLAIM_WALKTHROUGH_BANK_CODE ?? 'GXCHPHM2XXX';
const accountNumber =
    process.env.XCHANGE_CLAIM_WALKTHROUGH_ACCOUNT_NUMBER ?? '09173011987';
const submitClaim = process.env.XCHANGE_CLAIM_WALKTHROUGH_SUBMIT_CLAIM === '1';
const ogPreview = parseJson(
    process.env.XCHANGE_CLAIM_WALKTHROUGH_OG_PREVIEW,
    {},
);

if (!artifactDirectory) {
    throw new Error('XCHANGE_CLAIM_WALKTHROUGH_ARTIFACT_DIR is required.');
}

let chromium;

try {
    ({ chromium } = await import('playwright'));
} catch (error) {
    throw new Error(
        'Playwright is required for non-dry-run claim walkthroughs. Run npm install in the host app first.',
    );
}

await fs.mkdir(path.join(artifactDirectory, 'screenshots'), {
    recursive: true,
});
await fs.mkdir(path.join(artifactDirectory, 'storyboard-frames'), {
    recursive: true,
});

const browser = await chromium.launch({ headless: !headed, slowMo });
const mobileViewport = { width: 390, height: 844 };
const socialCardViewport = { width: 1280, height: 760 };
const page = await browser.newPage({ viewport: mobileViewport });
const actions = [];
const checkpoints = [];
const features = {
    og_preview: false,
    pre_claim_rider_splash: false,
    form_flow_splash: false,
    claim_success: false,
    rider_message: false,
    rider_splash: false,
    rider_redirect_countdown: false,
    rider_redirect: false,
};

function parseJson(value, fallback) {
    if (!value) {
        return fallback;
    }

    try {
        return JSON.parse(value);
    } catch {
        return fallback;
    }
}

function routeFromUrl(url) {
    try {
        const parsed = new URL(url);
        return `${parsed.pathname}${parsed.search}`;
    } catch {
        return url;
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function hydratePreviewValue(value) {
    const claimUrl = new URL(`/x/claim/${payCode}`, baseUrl).toString();

    return String(value ?? '')
        .replaceAll('{code}', payCode)
        .replaceAll('{claim_url}', claimUrl);
}

function hydratedOgPreviewPayload() {
    const ogMeta =
        ogPreview.og_meta && typeof ogPreview.og_meta === 'object'
            ? Object.fromEntries(
                  Object.entries(ogPreview.og_meta).map(([key, value]) => [
                      key,
                      hydratePreviewValue(value),
                  ]),
              )
            : {};

    return {
        ...ogPreview,
        title: hydratePreviewValue(ogPreview.title),
        description: hydratePreviewValue(ogPreview.description),
        reference: hydratePreviewValue(ogPreview.reference),
        html: ogPreview.html ? hydratePreviewValue(ogPreview.html) : null,
        og_meta: ogMeta,
    };
}

function ogPreviewDocument(preview) {
    const title =
        preview.og_meta?.title || preview.title || `Pay Code ${payCode}`;
    const description =
        preview.og_meta?.description ||
        preview.description ||
        'Tap to claim this Pay Code.';
    const headline = preview.og_meta?.headline || payCode;
    const subtitle = preview.og_meta?.subtitle || 'PHP 15.00';
    const typeBadge = preview.og_meta?.typeBadge || 'cash';
    const payeeBadge = preview.og_meta?.payeeBadge || 'Pay Code';
    const renderedHtml =
        preview.render_mode === 'html' && preview.html
            ? `<div class="splash">${preview.html}</div>`
            : '';
    const cardClass = renderedHtml ? 'card card-with-visual' : 'card';

    return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src https: data:; style-src 'unsafe-inline'; font-src data:; base-uri 'none'; form-action 'none'; script-src 'none';">
  <title>${escapeHtml(title)}</title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 28px; background: #f8fafc; color: #111827; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    main { width: min(100%, 1200px); }
    .label { margin: 0 0 12px; color: #0369a1; font-size: 13px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .card { aspect-ratio: 1200 / 630; overflow: hidden; border: 1px solid #d9e2ec; border-radius: 12px; background: #dcfce7; box-shadow: 0 20px 60px rgba(15, 23, 42, .12); }
    .inner { position: relative; width: 100%; height: 100%; display: grid; place-items: center; padding: 48px; background: rgba(255, 255, 255, .82); }
    .card-with-visual .inner { grid-template-columns: minmax(0, 1.45fr) minmax(300px, .55fr); gap: 0; padding: 0; background: #020617; }
    .visual-pane { display: none; min-width: 0; width: 100%; height: 100%; padding: 28px; place-items: center; background: #020617; overflow: hidden; }
    .card-with-visual .visual-pane { display: grid; }
    .summary-pane { position: relative; z-index: 1; }
    .card-with-visual .summary-pane { width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; padding: 42px; background: #ffffff; border-left: 1px solid #e2e8f0; }
    .app { position: absolute; top: 34px; left: 42px; color: #94a3b8; font-size: 18px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
    .card-with-visual .app { position: static; margin-bottom: 24px; color: #64748b; font-size: 13px; }
    h1 { margin: 0; font-size: clamp(58px, 12vw, 132px); line-height: .95; font-weight: 900; text-align: center; }
    .card-with-visual h1 { font-size: clamp(44px, 5vw, 68px); text-align: left; letter-spacing: .02em; }
    .amount { margin-top: 18px; color: #334155; font-size: clamp(28px, 4vw, 52px); font-weight: 700; text-align: center; }
    .card-with-visual .amount { margin-top: 12px; font-size: 32px; text-align: left; }
    .badges { display: flex; justify-content: center; gap: 12px; margin-top: 22px; }
    .card-with-visual .badges { justify-content: flex-start; flex-wrap: wrap; margin-top: 24px; }
    .badge { border-radius: 999px; padding: 10px 18px; background: #f1f5f9; color: #475569; font-size: 16px; font-weight: 800; text-transform: uppercase; }
    .card-with-visual .badge { font-size: 12px; padding: 8px 12px; }
    .badge.dark { background: #334155; color: #fff; }
    .copy { margin-top: 18px; text-align: center; }
    .card-with-visual .copy { margin-top: 28px; text-align: left; }
    .copy strong { display: block; color: #111827; font-size: 18px; }
    .card-with-visual .copy strong { font-size: 20px; line-height: 1.25; }
    .copy span { display: block; margin-top: 4px; color: #475569; font-size: 15px; }
    .splash { width: 100%; height: 100%; display: grid; place-items: center; overflow: hidden; pointer-events: none; color: #fff; }
    .splash > * { max-width: 100% !important; max-height: 100% !important; }
    .splash img { max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; }
    .splash .relative { position: relative; }
    .splash .absolute { position: absolute; }
    .splash .inset-0 { inset: 0; }
    .splash .pointer-events-none { pointer-events: none; }
    .splash .flex { display: flex; }
    .splash .items-center { align-items: center; }
    .splash .justify-end { justify-content: flex-end; }
    .splash .overflow-hidden { overflow: hidden; }
    .splash .bg-black { background: #000; }
    .splash .text-center { text-align: center; }
    .splash .text-right { text-align: right; }
    .splash .mx-auto { margin-left: auto; margin-right: auto; }
    .splash .text-white { color: #fff; max-width: 58%; overflow-wrap: anywhere; }
    .splash h1, .splash h2, .splash h3, .splash p { overflow-wrap: anywhere; }
    .splash .font-serif { font-family: ui-serif, Georgia, Cambria, "Times New Roman", Times, serif; }
    .splash .font-normal { font-weight: 400; }
    .splash .italic { font-style: italic; }
    .splash .tracking-wide { letter-spacing: .025em; }
    .splash .tracking-widest { letter-spacing: .1em; }
    .splash .mb-3 { margin-bottom: .75rem; }
    .splash .mb-8 { margin-bottom: 2rem; }
    .splash .rounded-lg { border-radius: .5rem; }
    .splash .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -4px rgba(0,0,0,.1); }
    .splash .text-xs { font-size: .75rem; line-height: 1rem; }
    .splash .text-lg { font-size: 1.125rem; line-height: 1.75rem; }
    .splash .text-2xl { font-size: 1.5rem; line-height: 2rem; }
    .splash .text-4xl { font-size: 2.25rem; line-height: 2.5rem; }
    @media (min-width: 640px) {
      .splash .sm\\:text-sm { font-size: .875rem; line-height: 1.25rem; }
      .splash .sm\\:text-2xl { font-size: 1.5rem; line-height: 2rem; }
      .splash .sm\\:text-4xl { font-size: 2.25rem; line-height: 2.5rem; }
    }
    .card-with-visual .splash h2 { font-size: 1.75rem !important; line-height: 2.15rem !important; }
    .card-with-visual .splash p { font-size: 1rem !important; line-height: 1.5rem !important; }
  </style>
</head>
<body>
  <main>
    <p class="label">${escapeHtml(preview.label || 'Social / OG preview')} · ${escapeHtml(preview.reference || 'rider.og_source')}</p>
    <section class="${cardClass}">
      <div class="inner">
        <div class="visual-pane">
          ${renderedHtml}
        </div>
        <div class="summary-pane">
          <div class="app">x-change</div>
          <h1>${escapeHtml(headline)}</h1>
          <div class="amount">${escapeHtml(subtitle)}</div>
          <div class="badges">
            <span class="badge">${escapeHtml(typeBadge)}</span>
            <span class="badge dark">${escapeHtml(payeeBadge)}</span>
          </div>
          <p class="copy">
            <strong>${escapeHtml(title)}</strong>
            <span>${escapeHtml(description)}</span>
          </p>
        </div>
      </div>
    </section>
  </main>
</body>
</html>`;
}

async function recordAction(event, status = 'passed', details = {}) {
    actions.push({
        sequence: actions.length + 1,
        event,
        status,
        url: page.url(),
        ...details,
    });
}

async function capture(key, title, expected, details = {}) {
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(450);

    const screenshotPath = path.join(
        artifactDirectory,
        'screenshots',
        `${String(checkpoints.length + 1).padStart(2, '0')}-${key}.png`,
    );

    await page.screenshot({ path: screenshotPath, fullPage: true });

    const checkpoint = {
        key,
        title,
        route: routeFromUrl(page.url()),
        actor: 'redeemer',
        expected,
        status: 'captured',
        screenshot_path: screenshotPath,
        ...details,
    };

    checkpoints.push(checkpoint);
    await recordAction(`capture:${key}`, 'captured', {
        screenshot_path: screenshotPath,
    });

    return checkpoint;
}

async function clickVisibleButton(names) {
    for (const name of names) {
        const button = page.getByRole('button', { name });
        if (await button.count()) {
            try {
                await button.first().click({ timeout: 5000 });
                return true;
            } catch {
                continue;
            }
        }
    }

    return false;
}

async function isVisible(locator) {
    try {
        return (
            (await locator.count()) > 0 && (await locator.first().isVisible())
        );
    } catch {
        return false;
    }
}

async function fillBySelectorOrLabel(selectors, value) {
    for (const selector of selectors) {
        const locator = selector.startsWith('label:')
            ? page.getByLabel(selector.slice(6), { exact: false })
            : page.locator(selector);

        if (await locator.count()) {
            await locator.first().fill(value);
            return true;
        }
    }

    return false;
}

async function chooseBankIfPossible() {
    const nativeSelect = page.locator(
        'select[name="bank_code"], select#bank_code',
    );

    if (await nativeSelect.count()) {
        await nativeSelect
            .first()
            .selectOption(bankCode)
            .catch(async () => {
                await nativeSelect.first().selectOption({ label: /GCash/i });
            });

        return;
    }

    const bankText = page
        .getByText(/Bank\/Wallet|Bank\/EMI|Bank or wallet|GCash/i)
        .first();

    if (await bankText.count()) {
        await bankText.click().catch(() => {});
        const gcash = page.getByText(/GCash/i).first();

        if (await gcash.count()) {
            await gcash.click().catch(() => {});
        }
    }

    await page.keyboard.press('Escape').catch(() => {});
    await page.mouse.click(12, 12).catch(() => {});
    await page.waitForTimeout(250);
}

async function renderStoryboard(storyboard, artifacts) {
    const viewOptions = artifactViewOptions(artifacts);
    const featureLabels = {
        og_preview: 'OG preview',
        claim_success: 'Success',
        rider_message: 'Rider message',
        rider_splash: 'Rider splash',
        rider_redirect_countdown: 'Redirect countdown',
        rider_redirect: 'Rider redirect',
    };
    const featureSummary = Object.entries(featureLabels)
        .map(
            ([key, label]) => `
      <span class="feature ${storyboard.features?.[key] ? 'feature-on' : 'feature-off'}">
        ${escapeHtml(label)}: ${storyboard.features?.[key] ? 'captured' : 'not seen'}
      </span>
    `,
        )
        .join('\n');

    const cards = storyboard.checkpoints
        .map((checkpoint, index) => {
            const relativeImage = path.relative(
                artifactDirectory,
                checkpoint.screenshot_path,
            );

            return `
        <section class="checkpoint">
          <div class="copy">
            <div class="eyebrow">Frame ${index + 1}</div>
            <h2>${escapeHtml(checkpoint.title)}</h2>
            <p>${escapeHtml(checkpoint.expected)}</p>
            <dl>
              <div>
                <dt>Actor</dt>
                <dd>${escapeHtml(checkpoint.actor)}</dd>
              </div>
              <div>
                <dt>Route</dt>
                <dd>${escapeHtml(checkpoint.route)}</dd>
              </div>
            </dl>
          </div>
          <figure>
            <img src="${escapeHtml(relativeImage)}" alt="${escapeHtml(checkpoint.title)}">
          </figure>
        </section>
      `;
        })
        .join('\n');

    const html = `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${escapeHtml(storyboard.scenario.label)}</title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #111827; background: #f8fafc; }
    main { max-width: 1180px; margin: 0 auto; padding: 34px 24px; }
    header { min-height: 180px; margin-bottom: 24px; }
    h1 { margin: 0 0 8px; max-width: 840px; font-size: 34px; line-height: 1.1; }
    h2 { margin: 8px 0 12px; font-size: 28px; line-height: 1.08; }
    p { max-width: 620px; color: #334155; line-height: 1.55; }
    .run { color: #64748b; font-size: 13px; }
    .view-options { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
    .view-options a { display: inline-flex; align-items: center; min-height: 34px; border: 1px solid #d9e2ec; border-radius: 6px; padding: 7px 10px; color: #111827; background: #fff; font-size: 13px; font-weight: 700; text-decoration: none; }
    .checkpoint { display: grid; grid-template-columns: minmax(230px, .78fr) minmax(0, 1.22fr); gap: 26px; page-break-inside: avoid; break-inside: avoid; border: 1px solid #d9e2ec; background: white; border-radius: 8px; padding: 22px; margin-bottom: 22px; }
    .features { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 22px; }
    .feature { display: inline-flex; align-items: center; border-radius: 999px; border: 1px solid #d9e2ec; padding: 6px 10px; font-size: 12px; font-weight: 700; }
    .feature-on { border-color: #bbf7d0; color: #166534; background: #f0fdf4; }
    .feature-off { color: #64748b; background: #f8fafc; }
    .copy { align-self: center; min-width: 0; }
    .eyebrow { color: #b91c1c; font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; }
    dl { margin: 24px 0 0; display: grid; gap: 12px; }
    dt { margin-bottom: 3px; color: #64748b; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; }
    dd { margin: 0; color: #111827; font-size: 13px; overflow-wrap: anywhere; }
    figure { margin: 0; min-width: 0; min-height: 680px; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid #d9e2ec; border-radius: 8px; background: #f8fafc; padding: 24px; box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .85); }
    img { display: block; width: auto; height: auto; max-width: 100%; max-height: 620px; object-fit: contain; border: 1px solid #d9e2ec; border-radius: 8px; background: #fff; box-shadow: 0 8px 30px rgba(15, 23, 42, .08); }
    @media print {
      @page { size: A4 landscape; margin: 12mm 10mm 13mm; }
      body { background: white; }
      main { max-width: none; padding: 0; }
      header { min-height: auto; page-break-after: always; break-after: page; }
      .view-options { display: none; }
      .checkpoint { width: 100%; height: 166mm; max-height: 166mm; margin: 0; padding: 8mm; grid-template-columns: 73mm minmax(0, 1fr); gap: 8mm; page-break-after: always; break-after: page; overflow: hidden; }
      .checkpoint:last-child { page-break-after: auto; break-after: auto; }
      .copy { align-self: start; max-height: 148mm; overflow: hidden; }
      h2 { font-size: 22px; }
      p { font-size: 12px; line-height: 1.45; }
      dl { gap: 8px; margin-top: 16px; }
      dd { font-size: 10px; }
      figure { width: 100%; height: 150mm; min-height: 0; max-height: 150mm; padding: 6mm; align-items: center; justify-content: center; }
      img { width: auto; height: auto; max-width: 100%; max-height: 138mm; object-fit: contain; }
      .features { max-width: 210mm; }
    }
  </style>
</head>
<body>
  <main>
    <header>
      <div class="run">Run ${escapeHtml(storyboard.run_id)} · Pay Code ${escapeHtml(storyboard.pay_code)}</div>
      <h1>${escapeHtml(storyboard.scenario.label)}</h1>
      <p>${escapeHtml(storyboard.scenario.description)}</p>
      <div class="features">${featureSummary}</div>
      <nav class="view-options" aria-label="Artifact view options">
        <a href="${escapeHtml(viewOptions.default.url)}">Default PDF</a>
        <a href="${escapeHtml(viewOptions.html.url)}">HTML storyboard</a>
        <a href="${escapeHtml(viewOptions.folder.url)}">Open artifact folder</a>
      </nav>
    </header>
    ${cards}
  </main>
</body>
</html>`;

    await fs.writeFile(artifacts.storyboard_html, html);
    await fs.writeFile(
        artifacts.storyboard_json,
        `${JSON.stringify(storyboard, null, 2)}\n`,
    );

    const pdfPage = await browser.newPage({
        viewport: { width: 1280, height: 720 },
    });
    await pdfPage.goto(pathToFileURL(artifacts.storyboard_html).href, {
        waitUntil: 'load',
    });
    await pdfPage.emulateMedia({ media: 'print' });
    await pdfPage.pdf({
        path: artifacts.storyboard_pdf,
        format: 'A4',
        landscape: true,
        printBackground: true,
        displayHeaderFooter: true,
        headerTemplate: '<span></span>',
        footerTemplate:
            '<div style="width:100%;font:8px Arial;color:#68746e;padding:0 10mm;display:flex;justify-content:space-between"><span>x-change claim walkthrough</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>',
        margin: {
            top: '12mm',
            right: '10mm',
            bottom: '13mm',
            left: '10mm',
        },
    });
    await pdfPage.close();
}

function artifactViewOptions(artifacts) {
    return {
        default: {
            label: 'Default PDF',
            kind: 'pdf',
            path: artifacts.storyboard_pdf,
            url: pathToFileURL(artifacts.storyboard_pdf).href,
            open_command: `open ${JSON.stringify(artifacts.storyboard_pdf)}`,
        },
        html: {
            label: 'HTML storyboard',
            kind: 'html',
            path: artifacts.storyboard_html,
            url: pathToFileURL(artifacts.storyboard_html).href,
            open_command: `open ${JSON.stringify(artifacts.storyboard_html)}`,
        },
        folder: {
            label: 'Artifact folder',
            kind: 'folder',
            path: artifacts.root,
            url: pathToFileURL(artifacts.root).href,
            open_command: `open ${JSON.stringify(artifacts.root)}`,
        },
        current_app: {
            label: 'Current app paths',
            kind: 'paths',
            root: artifacts.root,
            pdf: artifacts.storyboard_pdf,
            html: artifacts.storyboard_html,
        },
    };
}

try {
    if (!payCode) {
        throw new Error(
            'A Pay Code is required for non-dry-run browser walkthroughs. Pass --code or --create-fixture.',
        );
    }

    if (ogPreview && Object.keys(ogPreview).length > 0) {
        const preview = hydratedOgPreviewPayload();

        await page.setViewportSize(socialCardViewport);
        await page.setContent(ogPreviewDocument(preview), {
            waitUntil: 'networkidle',
        });
        features.og_preview = true;
        await capture(
            'og-social-preview',
            'Social / OG preview',
            'Issuer sees the deterministic Open Graph preview before the redeemer journey begins.',
            {
                actor: 'issuer',
                route: `og-meta://pay-code/${payCode}`,
                feature: 'og_preview',
                phase: 'pre_claim',
                og_preview: preview,
            },
        );
        await page.setViewportSize(mobileViewport);
    }

    await page.goto(new URL('/x/claim', baseUrl).toString(), {
        waitUntil: 'networkidle',
    });
    await capture(
        'claim-entry',
        'Claim entry',
        'Redeemer starts at /x/claim before entering a Pay Code.',
    );

    await page.locator('#code').fill(payCode);
    await page.waitForTimeout(1700);
    await capture(
        'xray-preview',
        'Pay Code x-ray preview',
        'The entered Pay Code reveals the claim preview and x-ray information.',
    );

    const legacySplash = page
        .locator('[aria-label="legacy-splash"], [role="dialog"]')
        .first();

    if (await legacySplash.count()) {
        features.pre_claim_rider_splash = true;
        features.rider_splash = true;
        await capture(
            'pre-claim-rider-splash',
            'Rider splash',
            'A rider splash appears after Pay Code inspection and before claim start.',
            { feature: 'rider_splash', phase: 'pre_claim' },
        );
        await clickVisibleButton([
            /Continue Now/i,
            /Continue/i,
            /Start Claim/i,
        ]);
        await page.waitForTimeout(700);
    }

    await clickVisibleButton([/Start Claim/i, /^Claim$/i, /Continue/i]);
    await page
        .waitForURL(/\/(form-flow|x\/claim)\//, { timeout: 15000 })
        .catch(() => {});

    if (page.url().includes('/form-flow/')) {
        features.form_flow_splash = true;
        await capture(
            'form-flow-splash',
            'Form-flow splash',
            'The configured splash is shown before payout details.',
        );
        await clickVisibleButton([/Continue Now/i, /Continue/i, /Next/i]);
        await page.waitForTimeout(800);
    }

    await capture(
        'generic-payout-form',
        'Generic payout form',
        'Redeemer sees the generic form for mobile, bank or wallet, and account number.',
    );

    await fillBySelectorOrLabel(
        [
            'input[name="mobile"]',
            'input#mobile',
            'input[type="tel"]',
            'label:Mobile Number',
        ],
        mobile,
    );
    await chooseBankIfPossible();
    await fillBySelectorOrLabel(
        [
            'input[name="account_number"]',
            'input#account_number',
            'label:Account Number',
        ],
        accountNumber,
    );
    await page
        .locator('input[name="account_number"], input#account_number')
        .first()
        .scrollIntoViewIfNeeded()
        .catch(() => {});
    await page.waitForTimeout(500);
    await capture(
        'generic-payout-form-filled',
        'Generic payout form filled',
        'Mobile, wallet, and account number are filled before continuing.',
    );

    const continueButton = page.locator('form button[type="submit"]').last();
    await continueButton.scrollIntoViewIfNeeded().catch(() => {});
    await continueButton.click({ timeout: 10000 });
    await page
        .getByRole('button', { name: /Confirm Redemption/i })
        .waitFor({ timeout: 15000 })
        .catch(() => {});
    await page.waitForTimeout(800);
    await capture(
        'confirmation',
        'Claim confirmation',
        'Redeemer reviews the collected payout details before final confirmation.',
    );

    if (submitClaim) {
        await clickVisibleButton([/Confirm Redemption/i, /Confirm & Claim/i]);
        await page.waitForLoadState('networkidle').catch(() => {});
        await page.waitForTimeout(2500);

        features.claim_success = true;
        const riderMessageRegion = page
            .locator('[data-testid="success-stage-region"]')
            .first();
        features.rider_message = await isVisible(riderMessageRegion);

        await capture(
            'claim-success-rider-message',
            features.rider_message
                ? 'Claim success with rider message'
                : 'Claim success',
            features.rider_message
                ? 'Redeemer sees the completed claim state with the issuer rider message in the same success card.'
                : 'Redeemer sees the completed claim state before any rider handoff.',
            {
                feature: features.rider_message
                    ? 'claim_success+rider_message'
                    : 'claim_success',
                phase: 'success',
            },
        );

        const postClaimSplash = page.locator('[role="dialog"]').first();

        if (await isVisible(postClaimSplash)) {
            features.rider_splash = true;
            await capture(
                'post-claim-rider-splash',
                'Post-claim rider splash',
                'A post-claim rider splash is visible before redirect or dismissal.',
                { feature: 'rider_splash', phase: 'post_claim' },
            );
            await clickVisibleButton([/Continue Now/i, /Continue/i]);
            await page.waitForTimeout(700);
        }

        const redirectCountdown = page
            .locator('[data-testid="redirect-countdown-region"]')
            .first();

        if (await isVisible(redirectCountdown)) {
            features.rider_redirect_countdown = true;
            await page.waitForTimeout(1100);
            await capture(
                'rider-redirect-countdown',
                'Rider redirect handoff',
                'The success page shows an intentional rider redirect countdown and Continue Now action.',
                { feature: 'rider_redirect', phase: 'redirect' },
            );
        }

        await clickVisibleButton([/Continue Now/i]);
        await page
            .waitForURL(
                (url) => !url.href.includes(`/x/claim/${payCode}/success`),
                { timeout: 15000 },
            )
            .catch(() => {});
        await page.waitForLoadState('networkidle').catch(() => {});
        await page.waitForTimeout(1000);
        features.rider_redirect = !page
            .url()
            .includes(`/x/claim/${payCode}/success`);
        await capture(
            'rider-url',
            'Rider URL',
            'The browser reaches the configured rider URL when redirect is enabled.',
            { feature: 'rider_redirect', phase: 'redirect' },
        );
    }

    const artifacts = {
        root: artifactDirectory,
        storyboard_json: path.join(
            artifactDirectory,
            'walkthrough-storyboard.json',
        ),
        storyboard_html: path.join(
            artifactDirectory,
            'walkthrough-storyboard.html',
        ),
        storyboard_pdf: path.join(
            artifactDirectory,
            'walkthrough-storyboard.pdf',
        ),
        report_json: path.join(
            artifactDirectory,
            'browser-walkthrough-report.json',
        ),
        metadata_json: path.join(artifactDirectory, 'recording-metadata.json'),
        action_log_jsonl: path.join(artifactDirectory, 'action-log.jsonl'),
    };

    const storyboard = {
        schema_version: 'x-change.claim-browser-walkthrough.storyboard.v1',
        generated_at: new Date().toISOString(),
        run_id: path.basename(artifactDirectory),
        pay_code: payCode,
        scenario: {
            key: scenario,
            label:
                scenario === 'claim_basic_15_full_browser_handoff'
                    ? 'Basic PHP 15 browser claim with rider handoff'
                    : scenario,
            description:
                'Recorded browser walkthrough of the x-change claim experience.',
        },
        form_values: {
            mobile,
            bank_code: bankCode,
            account_number: accountNumber,
            submit_claim: submitClaim,
        },
        features,
        checkpoints,
        actions,
    };

    await renderStoryboard(storyboard, artifacts);
    artifacts.view_options = artifactViewOptions(artifacts);
    const report = {
        schema_version: 'x-change.claim-walkthrough.report.v1',
        run_id: path.basename(artifactDirectory),
        scenario,
        pay_code: payCode,
        passed: true,
        dry_run: false,
        submitted_claim: submitClaim,
        checkpoint_count: checkpoints.length,
        final_url: page.url(),
        features,
        artifacts,
    };

    await fs.writeFile(
        artifacts.report_json,
        `${JSON.stringify(report, null, 2)}\n`,
    );
    await fs.writeFile(
        artifacts.metadata_json,
        `${JSON.stringify(
            {
                scenario,
                base_url: baseUrl,
                headed,
                slow_mo: slowMo,
                pay_code: payCode,
                submit_claim: submitClaim,
                features,
            },
            null,
            2,
        )}\n`,
    );
    await fs.writeFile(
        artifacts.action_log_jsonl,
        `${actions.map((action) => JSON.stringify(action)).join('\n')}\n`,
    );

    console.log(JSON.stringify(report));
} finally {
    await browser.close();
}
