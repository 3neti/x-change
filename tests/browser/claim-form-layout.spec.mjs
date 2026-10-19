import { createRequire } from 'node:module';
const require = createRequire(`${process.env.WORKFLOW_BROWSER_HOST}/package.json`);
const { test, expect } = require('@playwright/test');

const presentation = {
    schema: 'form-flow.presentation.v1', profile: 'guided-claim-v1',
    brand: {name:'Pay Code',logo:'/vendor/x-change/images/pay-code/pay-code-logo.svg',placement:'top_left',logo_height:32,logo_max_width:112},
    layout: {density:'compact',capture_surface:'fill_available',scrolling:'when_needed'},
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
            await page.addInitScript(()=>localStorage.setItem('appearance','dark'));
            if(screen==='splash') await render(page,'form-flow/core/Splash',{content:'',is_default_splash:true,voucher_code:'AA317',timeout:0});
            else if(screen==='details') await render(page,'form-flow/core/GenericForm',{title:'Disbursement Details',fields:[{name:'mobile',type:'tel',group:'redeemer',label:'Mobile Number'},{name:'account_number',type:'text',group:'endorsement',label:'Account Number'}]});
            else await render(page,'x-change/claim/Preview',{viewport:{profile:'mobile',width:402,height:874},step:{title:screen,screen:{kind:screen,code:'AA317',amount:'₱100.00',title:screen,description:'Synthetic claim preview',fields:[]}}});
            await expect(page.locator('html')).toHaveAttribute('data-x-change-claim-appearance','light');
            expect(await page.locator('html').evaluate(el=>({dark:el.classList.contains('dark'),scheme:el.style.colorScheme,saved:localStorage.getItem('appearance')}))).toEqual({dark:false,scheme:'light',saved:'dark'});
            await page.emulateMedia({colorScheme:mode==='dark'?'light':'dark'});
            await expect(page.locator('html')).not.toHaveClass(/\bdark\b/);
            await page.screenshot({path:`${process.env.WORKFLOW_BROWSER_ARTIFACTS}/appearance-${screen}-${mode}.png`,fullPage:true,animations:'disabled'});
        });
    }
}
