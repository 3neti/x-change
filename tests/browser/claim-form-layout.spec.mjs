import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire(`${process.env.WORKFLOW_BROWSER_HOST}/package.json`);
const { test, expect } = require('@playwright/test');

const presentation = {
    schema: 'form-flow.presentation.v1', profile: 'guided-claim-v1',
    brand: {name:'Pay Code',logo:'/vendor/x-change/images/pay-code/pay-code-logo.svg',placement:'top_left',logo_height:32,logo_max_width:112},
    layout: {artwork_aspect_ratio:'1200/630',density:'compact',capture_surface:'fill_available',scrolling:'when_needed'},
    actions: {placement:'viewport_bottom',primary_position:'right',secondary_position:'left',reserve_content_space:true,primary_label:null,secondary_label:null},
    progress: {show:true,current:1,total:2},diagnostics:{show_versions:false},
};
async function render(page, component, props) {
    const base=process.env.WORKFLOW_BROWSER_URL;
    if(!base || !new URL(base).hostname.endsWith('.test')) throw Error('Local host required');
    await page.route('**/*', async route => {
        const request=route.request();
        if(new URL(request.url()).origin!==base || request.method()!=='GET') return route.abort();
        if(new URL(request.url()).pathname!=='/login') return route.continue();
        const response=await route.fetch(), html=await response.text();
        const pattern=/(<script[^>]*data-page="app"[^>]*>)([\s\S]*?)(<\/script>)/;
        const match=html.match(pattern);
        if(!match) throw Error('Inertia page script missing');
        const original=JSON.parse(match[2]);
        const fixture={...original,component,props:{...original.props,flow_id:'synthetic-layout',step_index:0,preview_mode:true,presentation,ui_variant:'compact',action_placement:'viewport_bottom',...props}};
        await route.fulfill({response,body:html.replace(pattern,(_,a,ignored,z)=>a+JSON.stringify(fixture).replaceAll('<','\\u003c')+z)});
    });
    await page.goto(`${base}/login`);
}
for(const width of [360,390,1280]) {
    for(const code of ['AA317','ABCD1234','ABCD1234EF56']) {
        test(`splash ${code.length} characters stays on one line at ${width}px`,async({page})=>{
            await page.setViewportSize({width,height:720});
            await render(page,'form-flow/core/Splash',{content:'',is_default_splash:true,voucher_code:code,timeout:0});
            const display=page.getByTestId('form-flow-splash-pay-code');
            await expect(display).toHaveText(`|| ${code} ||`);
            const geometry=await display.evaluate(el=>{const s=el.querySelector('span'),r=s.getBoundingClientRect(),c=el.getBoundingClientRect(),range=document.createRange();range.selectNodeContents(s);return {lines:range.getClientRects().length,left:r.left>=c.left-1,right:r.right<=c.right+1,wrap:getComputedStyle(s).whiteSpace};});
            expect(geometry).toEqual({lines:1,left:true,right:true,wrap:'nowrap'});
            await page.screenshot({path:`${process.env.WORKFLOW_BROWSER_ARTIFACTS}/splash-${code.length}-${width}.png`,fullPage:true,animations:'disabled'});
        });
    }
    test(`grouped form controls retain padding at ${width}px`,async({page})=>{
        await page.setViewportSize({width,height:720});
        await render(page,'form-flow/core/GenericForm',{title:'Disbursement Details',fields:[
            {name:'mobile',type:'tel',label:'Mobile Number',required:true,group:'redeemer'},
            {name:'bank_code',type:'bank_account',label:'Bank/Wallet',required:true,group:'endorsement',default:'GXCHPHM2XXX',institution_options:[{key:'GXCHPHM2XXX',value:'GXCHPHM2XXX',name:'GCash',short_name:'GCash',category:'emi',account_label:'Mobile number',identifier_scheme:'mobile',aliases:[],commonly_used:true}]},
            {name:'account_number',type:'text',label:'Account Number',required:true,group:'endorsement',help_text:'Enter the complete receiving account number. Keep every digit, including any leading zero.'},
        ]});
        const groups=page.getByTestId('form-flow-field-group');
        await expect(groups).toHaveCount(2);
        for(const group of await groups.all()) {
            const input=group.locator('input').last();
            await input.focus();
            const bounds=await group.evaluate(el=>{
                const outer=el.getBoundingClientRect(),content=el.querySelector('[data-testid="form-flow-field-group-content"]'),inner=content.getBoundingClientRect(),style=getComputedStyle(content);
                const controls=[...el.querySelectorAll('input, button[role="combobox"]')].filter(e=>e.getBoundingClientRect().width>0).map(e=>e.getBoundingClientRect());
                return {paddingLeft:style.paddingLeft,paddingRight:style.paddingRight,paddingBottom:style.paddingBottom,contained:controls.every(r=>r.left>=outer.left+15 && r.right<=outer.right-15 && r.bottom<=outer.bottom-11),symmetric:Math.abs((inner.left-outer.left)-(outer.right-inner.right))<2};
            });
            expect(bounds).toEqual({paddingLeft:'16px',paddingRight:'16px',paddingBottom:'12px',contained:true,symmetric:true});
        }
        expect(await page.getByRole('textbox',{name:'Account Number *',exact:true}).evaluate(el=>el.validity.valueMissing)).toBe(true);
        expect(await page.locator('html').evaluate(el=>el.scrollWidth<=el.clientWidth)).toBe(true);
        await page.screenshot({path:`${process.env.WORKFLOW_BROWSER_ARTIFACTS}/fields-${width}.png`,fullPage:true,animations:'disabled'});
    });
}

for (const width of [360, 390, 1280]) {
    for (const account of ['09173011987', '123456789012345678901234']) {
        test(`destination summary balances ${account.length}-digit account at ${width}px`, async ({page}) => {
            await page.setViewportSize({width,height:720});
            await render(page,'form-flow/core/GenericForm',{
                title:'Disbursement Details', description:'Redeeming voucher AA317 from Synthetic Issuer',
                fields:[
                    {name:'amount',type:'hidden',default:100},
                    {name:'mobile',type:'tel',label:'Mobile Number',group:'redeemer',default:'09173011987'},
                    {name:'bank_code',type:'bank_account',label:'Bank/Wallet',group:'endorsement',default:'PAPHPHM1XXX',institution_options:[{key:'PAPHPHM1XXX',value:'PAPHPHM1XXX',name:'Maya Wallet',short_name:'Maya Wallet',category:'wallet',account_label:'Mobile number',identifier_scheme:'ph_mobile',aliases:[],commonly_used:true}]},
                    {name:'account_number',type:'text',label:'Account Number',group:'endorsement',default:account},
                ],
            });
            const summary=page.getByTestId('form-flow-destination-summary');
            await expect(summary).toBeVisible();
            await expect(summary.locator('dt')).toHaveText(['Amount','Payment method','Bank / wallet','Account number']);
            await expect(summary.locator('dd')).toHaveText(['₱100.00','InstaPay','Maya Wallet',account]);
            expect(await summary.evaluate(el=>{
                const outer=el.getBoundingClientRect();
                return [...el.querySelectorAll('dd')].every(dd=>{const r=dd.getBoundingClientRect();return dd.scrollWidth<=dd.clientWidth+1 && r.left>=outer.left+15 && r.right<=outer.right-15;});
            })).toBe(true);
            expect(await page.locator('html').evaluate(el=>el.scrollWidth<=el.clientWidth)).toBe(true);
            expect(await summary.locator('dl').evaluate(el=>getComputedStyle(el).rowGap)).toBe('16px');
            await page.evaluate(()=>window.scrollTo(0,document.documentElement.scrollHeight));
            const summaryBox=await summary.boundingBox(), actionBox=await page.getByRole('button',{name:'Continue',exact:true}).boundingBox();
            expect(summaryBox.y+summaryBox.height).toBeLessThanOrEqual(actionBox.y);
            await summary.screenshot({path:`${process.env.WORKFLOW_BROWSER_ARTIFACTS}/destination-${account.length}-${width}.png`,animations:'disabled'});
        });
    }
}

for (const mode of ['light','dark']) {
    for (const screen of ['claim_entry','splash','details','confirmation','success']) {
        test(`public ${screen} stays light with device appearance ${mode}`,async({page})=>{
            await page.setViewportSize({width:402,height:874});
            await page.emulateMedia({colorScheme:mode});
            await page.addInitScript(()=>{localStorage.setItem('appearance','dark');localStorage.setItem('x-change:experience-theme:v1','amber');});
            if(screen==='splash') await render(page,'form-flow/core/Splash',{content:'',is_default_splash:true,voucher_code:'AA317',timeout:0});
            else if(screen==='details') await render(page,'form-flow/core/GenericForm',{title:'Disbursement Details',fields:[{name:'mobile',type:'tel',group:'redeemer',label:'Mobile Number'},{name:'account_number',type:'text',group:'endorsement',label:'Account Number'}]});
            else await render(page,'x-change/claim/Preview',{viewport:{profile:'mobile',width:402,height:874},step:{title:screen,screen:{kind:screen,code:'AA317',amount:'₱100.00',title:screen,description:'Synthetic claim preview',fields:[]}}});
            await expect(page.locator('html')).toHaveAttribute('data-x-change-claim-appearance','light');
            await expect(page.locator('html')).toHaveAttribute('data-x-change-theme','default');
            const canvas = screen === 'details' || screen === 'splash'
                ? page.getByTestId('form-flow-presentation')
                : page.locator('.x-claim-canvas').first();
            expect(await canvas.evaluate(el=>getComputedStyle(el).backgroundColor)).toBe('rgb(255, 255, 255)');
            if (screen==='confirmation') {
                await expect(page.getByTestId('claim-review-icon')).toBeVisible();
                await expect(page.locator('svg.lucide-circle-check')).toHaveCount(0);
            }
            if (screen==='success') await expect(page.getByTestId('claim-pending-icon')).toBeVisible();

            expect(await page.locator('html').evaluate(el=>({dark:el.classList.contains('dark'),scheme:el.style.colorScheme,saved:localStorage.getItem('appearance')}))).toEqual({dark:false,scheme:'light',saved:'dark'});
            await page.emulateMedia({colorScheme:mode==='dark'?'light':'dark'});
            await expect(page.locator('html')).not.toHaveClass(/\bdark\b/);
            await page.screenshot({path:`${process.env.WORKFLOW_BROWSER_ARTIFACTS}/appearance-${screen}-${mode}.png`,fullPage:true,animations:'disabled'});
        });
    }
}

for (const screen of ['claim_entry','splash','details','confirmation','success']) {
    test(`YAML presentation contract customizes ${screen}`, async ({page}) => {
        const profile = {...presentation, appearance:{mode:'system',light:{canvas:'#f0f9ff',surface:'#f0f9ff',foreground:'#102030'},dark:{canvas:'#112233',surface:'#112233',foreground:'#ffffff'}},icons:{size:40,review:{name:'clipboard-check',color:'#123456'},pending:{name:'clock',color:'#a16207'}}};
        await page.emulateMedia({colorScheme:'dark'});
        if(screen==='splash') await render(page,'form-flow/core/Splash',{presentation:profile,content:'',is_default_splash:true,voucher_code:'AA317',timeout:0});
        else if(screen==='details') await render(page,'form-flow/core/GenericForm',{presentation:profile,title:'Disbursement Details',fields:[{name:'mobile',type:'tel',label:'Mobile Number'}]});
        else await render(page,'x-change/claim/Preview',{presentation:null,claim_ui:profile,viewport:{profile:'mobile',width:402,height:874},step:{title:screen,screen:{kind:screen,code:'AA317',amount:'₱100.00',title:screen,description:'Synthetic claim preview',fields:[]}}});
        await expect(page.locator('html')).toHaveAttribute('data-x-change-claim-appearance','dark');
        const canvas = ['splash','details'].includes(screen) ? page.getByTestId('form-flow-presentation') : page.locator('.x-claim-canvas').first();
        await expect(canvas).toHaveCSS('background-color','rgb(17, 34, 51)');
        if(screen==='confirmation') {
            const icon=page.getByTestId('claim-review-icon');
            await expect(icon).toHaveAttribute('data-claim-icon-name','clipboard-check');
            await expect(icon).toHaveCSS('width','40px');
            await expect(icon).toHaveCSS('color','rgb(18, 52, 86)');
        }
        await page.emulateMedia({colorScheme:'light'});
        await expect(page.locator('html')).toHaveAttribute('data-x-change-claim-appearance','light');
        await expect(canvas).toHaveCSS('background-color','rgb(240, 249, 255)');
    });
}

for (const width of [360, 402, 1280]) {
    test(`custom rider splash keeps the complete artwork at ${width}px`, async ({page}) => {
        await page.setViewportSize({width,height:874});
        const content = readFileSync(new URL('./fixtures/planetary-rose-splash.html', import.meta.url), 'utf8')
            .replace('https://github.com/lbhurtado/failure-of-simultaneity/blob/main/planetary-rose.PNG?raw=true', '/vendor/x-stamp/assets/artwork/planetary-rose-v1.png');
        await render(page, 'form-flow/core/Splash', {content, voucher_code:'AA317', is_default_splash:false,timeout:0});
        const image = page.getByRole('img', {name:'i carry your heart with me'});
        await expect(image).toBeVisible();
        await expect.poll(() => image.evaluate(el => el.complete && el.naturalWidth > 0)).toBe(true);
        const geometry = await image.evaluate(el => {
            const image = el.getBoundingClientRect();
            const frame = el.parentElement.getBoundingClientRect();
            return {image:{x:image.x,y:image.y,width:image.width,height:image.height},frame:{x:frame.x,width:frame.width},natural:{width:el.naturalWidth,height:el.naturalHeight},pageWidth:document.documentElement.scrollWidth,viewport:innerWidth};
        });
        const artwork = page.getByTestId('form-flow-html-content');
        const card = await page.getByTestId('form-flow-splash-artwork-card').boundingBox();
        expect(card.width / card.height).toBeCloseTo(1200 / 630, 2);
        const frame = await artwork.boundingBox();
        expect(frame.width / frame.height).toBeCloseTo(1200 / 630, 2);
        expect(geometry.image.width).toBeLessThanOrEqual(frame.width + 1);
        expect(geometry.image.height).toBeLessThanOrEqual(frame.height + 1);
        const textBoxes = await image.evaluate(el => [...el.parentElement.querySelectorAll('h2,p')].map(e => {
            const r = e.getBoundingClientRect(); return {left:r.left,right:r.right,top:r.top,bottom:r.bottom};
        }));
        for (const text of textBoxes) {
            expect(text.left).toBeGreaterThanOrEqual(geometry.image.x - 1);
            expect(text.right).toBeLessThanOrEqual(geometry.image.x + geometry.image.width + 2);
            expect(text.top).toBeGreaterThanOrEqual(geometry.image.y - 1);
            expect(text.bottom).toBeLessThanOrEqual(geometry.image.y + geometry.image.height + 1);
        }
        await page.screenshot({path:`${process.env.WORKFLOW_BROWSER_ARTIFACTS}/custom-splash-${width}.png`,fullPage:true,animations:'disabled'});
        expect(geometry.image.x).toBeGreaterThanOrEqual(0);
        expect(geometry.image.x + geometry.image.width).toBeLessThanOrEqual(width);
        expect(geometry.pageWidth).toBeLessThanOrEqual(width);
        expect(geometry.image.width / geometry.image.height).toBeCloseTo(geometry.natural.width / geometry.natural.height, 2);
    });
}

test('compiled Claim-tab splash uses the same fitted artwork and keeps sanitization', async ({page}) => {
    await page.setViewportSize({width:402,height:874});
    const content = readFileSync(new URL('./fixtures/planetary-rose-splash.html', import.meta.url), 'utf8')
        .replace('https://github.com/lbhurtado/failure-of-simultaneity/blob/main/planetary-rose.PNG?raw=true', '/vendor/x-stamp/assets/artwork/planetary-rose-v1.png')
        .replace('<h2 ', '<h2 onclick="window.splashUnsafe=true" ')
        + '<script>window.splashUnsafe=true</script>';
    await render(page,'x-change/claim/Preview',{viewport:{profile:'mobile',width:402,height:874},step:{title:'Splash',screen:{kind:'splash',component:'form-flow/core/Splash',props:{presentation,content,voucher_code:'AA317',is_default_splash:false,timeout:0,preview_mode:true}}}});
    const artwork = page.getByTestId('form-flow-html-content');
    await expect(artwork).toBeVisible();
    await expect(artwork.locator('img')).toBeVisible();
    expect(await artwork.locator('h2').getAttribute('onclick')).toBeNull();
    expect(await artwork.locator('script').count()).toBe(0);
    expect(await page.evaluate(() => window.splashUnsafe)).toBeUndefined();
    await page.setViewportSize({width:800,height:900});
    await expect.poll(async () => artwork.locator('img').evaluate(el=>Math.round(el.getBoundingClientRect().height))).toBe(353);
    expect(await page.locator('html').evaluate(el=>el.scrollWidth<=innerWidth)).toBe(true);
});

test('text-only rider introductions keep a natural height', async ({page}) => {
    await page.setViewportSize({width:402,height:874});
    await render(page,'form-flow/core/Splash',{content:'<div style="max-width:1200px"><h2>Welcome</h2><p>Your claim begins here.</p></div>',is_default_splash:false,timeout:0});
    const content=page.getByTestId('form-flow-html-content');
    await expect(content).toBeVisible();
    expect(await content.evaluate(el=>el.style.aspectRatio)).toBe('');
    expect(await content.locator('h2').evaluate(el=>getComputedStyle(el.parentElement.parentElement).transform)).toBe('none');
});

test('older rider splash previews contain the full image in a stamp-ratio frame', async ({page}) => {
    await page.setViewportSize({width:402,height:874});
    await render(page,'x-change/claim/Preview',{viewport:{profile:'mobile',width:402,height:874},step:{title:'Rider splash',screen:{kind:'rider_splash',artwork_url:'/vendor/x-stamp/assets/artwork/planetary-rose-v1.png',title:'A message for you',description:'The issuer’s introduction appears before claim details.'}}});
    const frame=page.getByTestId('claim-rider-splash-artwork');
    await expect(frame).toBeVisible();
    await expect(frame.locator('img')).toHaveCSS('object-fit','contain');
    const box=await frame.boundingBox();
    expect(box.width/box.height).toBeCloseTo(1200/630,2);
    await expect(page.getByRole('button',{name:'Continue',exact:true})).toBeVisible();
});
