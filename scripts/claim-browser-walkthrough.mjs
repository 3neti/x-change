import fs from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const scenario = process.env.XCHANGE_CLAIM_WALKTHROUGH_SCENARIO ?? 'claim_basic_no_rider';
const baseUrl = process.env.XCHANGE_CLAIM_WALKTHROUGH_BASE_URL ?? 'http://localhost';
const artifactDirectory = process.env.XCHANGE_CLAIM_WALKTHROUGH_ARTIFACT_DIR;
const headed = process.env.XCHANGE_CLAIM_WALKTHROUGH_HEADED === '1';
const slowMo = Number.parseInt(process.env.XCHANGE_CLAIM_WALKTHROUGH_SLOW_MO ?? '100', 10);
const payCode = (process.env.XCHANGE_CLAIM_WALKTHROUGH_PAY_CODE ?? '').trim().toUpperCase();
const mobile = process.env.XCHANGE_CLAIM_WALKTHROUGH_MOBILE ?? '09173011987';
const bankCode = process.env.XCHANGE_CLAIM_WALKTHROUGH_BANK_CODE ?? 'GXCHPHM2XXX';
const accountNumber = process.env.XCHANGE_CLAIM_WALKTHROUGH_ACCOUNT_NUMBER ?? '09173011987';
const submitClaim = process.env.XCHANGE_CLAIM_WALKTHROUGH_SUBMIT_CLAIM === '1';

if (!artifactDirectory) {
  throw new Error('XCHANGE_CLAIM_WALKTHROUGH_ARTIFACT_DIR is required.');
}

let chromium;

try {
  ({ chromium } = await import('playwright'));
} catch (error) {
  throw new Error('Playwright is required for non-dry-run claim walkthroughs. Run npm install in the host app first.');
}

await fs.mkdir(path.join(artifactDirectory, 'screenshots'), { recursive: true });
await fs.mkdir(path.join(artifactDirectory, 'storyboard-frames'), { recursive: true });

const browser = await chromium.launch({ headless: !headed, slowMo });
const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
const actions = [];
const checkpoints = [];
const features = {
  pre_claim_rider_splash: false,
  form_flow_splash: false,
  claim_success: false,
  rider_message: false,
  rider_splash: false,
  rider_redirect_countdown: false,
  rider_redirect: false,
};

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
  await recordAction(`capture:${key}`, 'captured', { screenshot_path: screenshotPath });

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
    return await locator.count() > 0 && await locator.first().isVisible();
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
  const nativeSelect = page.locator('select[name="bank_code"], select#bank_code');

  if (await nativeSelect.count()) {
    await nativeSelect.first().selectOption(bankCode).catch(async () => {
      await nativeSelect.first().selectOption({ label: /GCash/i });
    });

    return;
  }

  const bankText = page.getByText(/Bank\/Wallet|Bank\/EMI|Bank or wallet|GCash/i).first();

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
    claim_success: 'Success',
    rider_message: 'Rider message',
    rider_splash: 'Rider splash',
    rider_redirect_countdown: 'Redirect countdown',
    rider_redirect: 'Rider redirect',
  };
  const featureSummary = Object.entries(featureLabels)
    .map(([key, label]) => `
      <span class="feature ${storyboard.features?.[key] ? 'feature-on' : 'feature-off'}">
        ${escapeHtml(label)}: ${storyboard.features?.[key] ? 'captured' : 'not seen'}
      </span>
    `)
    .join('\n');

  const cards = storyboard.checkpoints
    .map((checkpoint, index) => {
      const relativeImage = path.relative(artifactDirectory, checkpoint.screenshot_path);

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
  await fs.writeFile(artifacts.storyboard_json, `${JSON.stringify(storyboard, null, 2)}\n`);

  const pdfPage = await browser.newPage({ viewport: { width: 1280, height: 720 } });
  await pdfPage.goto(pathToFileURL(artifacts.storyboard_html).href, { waitUntil: 'load' });
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
    throw new Error('A Pay Code is required for non-dry-run browser walkthroughs. Pass --code or --create-fixture.');
  }

  await page.goto(new URL('/x/claim', baseUrl).toString(), { waitUntil: 'networkidle' });
  await capture('claim-entry', 'Claim entry', 'Redeemer starts at /x/claim before entering a Pay Code.');

  await page.locator('#code').fill(payCode);
  await page.waitForTimeout(1700);
  await capture('xray-preview', 'Pay Code x-ray preview', 'The entered Pay Code reveals the claim preview and x-ray information.');

  const legacySplash = page.locator('[aria-label="legacy-splash"], [role="dialog"]').first();

  if (await legacySplash.count()) {
    features.pre_claim_rider_splash = true;
    features.rider_splash = true;
    await capture(
      'pre-claim-rider-splash',
      'Rider splash',
      'A rider splash appears after Pay Code inspection and before claim start.',
      { feature: 'rider_splash', phase: 'pre_claim' },
    );
    await clickVisibleButton([/Continue Now/i, /Continue/i, /Start Claim/i]);
    await page.waitForTimeout(700);
  }

  await clickVisibleButton([/Start Claim/i, /^Claim$/i, /Continue/i]);
  await page.waitForURL(/\/(form-flow|x\/claim)\//, { timeout: 15000 }).catch(() => {});

  if (page.url().includes('/form-flow/')) {
    features.form_flow_splash = true;
    await capture('form-flow-splash', 'Form-flow splash', 'The configured splash is shown before payout details.');
    await clickVisibleButton([/Continue Now/i, /Continue/i, /Next/i]);
    await page.waitForTimeout(800);
  }

  await capture('generic-payout-form', 'Generic payout form', 'Redeemer sees the generic form for mobile, bank or wallet, and account number.');

  await fillBySelectorOrLabel([
    'input[name="mobile"]',
    'input#mobile',
    'input[type="tel"]',
    'label:Mobile Number',
  ], mobile);
  await chooseBankIfPossible();
  await fillBySelectorOrLabel([
    'input[name="account_number"]',
    'input#account_number',
    'label:Account Number',
  ], accountNumber);
  await page.locator('input[name="account_number"], input#account_number').first().scrollIntoViewIfNeeded().catch(() => {});
  await page.waitForTimeout(500);
  await capture('generic-payout-form-filled', 'Generic payout form filled', 'Mobile, wallet, and account number are filled before continuing.');

  const continueButton = page.locator('form button[type="submit"]').last();
  await continueButton.scrollIntoViewIfNeeded().catch(() => {});
  await continueButton.click({ timeout: 10000 });
  await page.getByRole('button', { name: /Confirm Redemption/i }).waitFor({ timeout: 15000 }).catch(() => {});
  await page.waitForTimeout(800);
  await capture('confirmation', 'Claim confirmation', 'Redeemer reviews the collected payout details before final confirmation.');

  if (submitClaim) {
    await clickVisibleButton([/Confirm Redemption/i, /Confirm & Claim/i]);
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(2500);

    features.claim_success = true;
    const riderMessageRegion = page.locator('[data-testid="success-stage-region"]').first();
    features.rider_message = await isVisible(riderMessageRegion);

    await capture(
      'claim-success-rider-message',
      features.rider_message ? 'Claim success with rider message' : 'Claim success',
      features.rider_message
        ? 'Redeemer sees the completed claim state with the issuer rider message in the same success card.'
        : 'Redeemer sees the completed claim state before any rider handoff.',
      { feature: features.rider_message ? 'claim_success+rider_message' : 'claim_success', phase: 'success' },
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

    const redirectCountdown = page.locator('[data-testid="redirect-countdown-region"]').first();

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
    await page.waitForURL((url) => !url.href.includes(`/x/claim/${payCode}/success`), { timeout: 15000 }).catch(() => {});
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(1000);
    features.rider_redirect = !page.url().includes(`/x/claim/${payCode}/success`);
    await capture(
      'rider-url',
      'Rider URL',
      'The browser reaches the configured rider URL when redirect is enabled.',
      { feature: 'rider_redirect', phase: 'redirect' },
    );
  }

  const artifacts = {
    root: artifactDirectory,
    storyboard_json: path.join(artifactDirectory, 'walkthrough-storyboard.json'),
    storyboard_html: path.join(artifactDirectory, 'walkthrough-storyboard.html'),
    storyboard_pdf: path.join(artifactDirectory, 'walkthrough-storyboard.pdf'),
    report_json: path.join(artifactDirectory, 'browser-walkthrough-report.json'),
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
      label: scenario === 'claim_basic_15_full_browser_handoff'
        ? 'Basic PHP 15 browser claim with rider handoff'
        : scenario,
      description: 'Recorded browser walkthrough of the x-change claim experience.',
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

  await fs.writeFile(artifacts.report_json, `${JSON.stringify(report, null, 2)}\n`);
  await fs.writeFile(artifacts.metadata_json, `${JSON.stringify({
    scenario,
    base_url: baseUrl,
    headed,
    slow_mo: slowMo,
    pay_code: payCode,
    submit_claim: submitClaim,
    features,
  }, null, 2)}\n`);
  await fs.writeFile(artifacts.action_log_jsonl, `${actions.map((action) => JSON.stringify(action)).join('\n')}\n`);

  console.log(JSON.stringify(report));
} finally {
  await browser.close();
}
