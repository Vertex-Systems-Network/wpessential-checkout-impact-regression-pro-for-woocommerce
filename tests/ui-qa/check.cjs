const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const axe = require('axe-core');
const base = 'http://127.0.0.1:8080';
const issues = [];
async function probe(page, label, fn) {
  try {
    await fn();
    console.log('PASS: ' + label);
  } catch (e) {
    issues.push(label + ': ' + e.message);
    console.error('FAIL: ' + label + ': ' + e.message);
  }
}
(async () => {
  const browser = await chromium.launch({headless: true});
  try {
    for (const rtl of [false, true]) {
      const context = await browser.newContext({viewport:{width:1440,height:900},locale:rtl?'ar':'en-US'});
      const page = await context.newPage();
      const name = rtl ? 'Arabic RTL' : 'English LTR';
      await page.goto(base + '/wp-login.php');
      await page.locator('#user_login').fill(rtl?'qa-rtl':'gallery-admin');
      await page.locator('#user_pass').fill(process.env.GALLERY_PASSWORD);
      await page.locator('#wp-submit').click();
      await page.waitForURL('**/wp-admin/**');
      await page.goto(base + '/wp-admin/admin.php?page=wpessential-checkout-impact');
      await page.locator('.wpecir-app').waitFor();
      await probe(page,name+' actual document locale/direction',async()=>{
        const locale=await page.locator('html').evaluate(el=>({lang:el.lang,dir:getComputedStyle(el).direction}));
        assert.equal(locale.dir,rtl?'rtl':'ltr');
        assert(rtl?locale.lang.startsWith('ar'):locale.lang.startsWith('en'),JSON.stringify(locale));
      });
      const tabs=page.getByRole('tab');
      await probe(page,name+' keyboard tab selection and focus',async()=>{
        const selected=page.locator('[role=tab][aria-selected=true]');
        await selected.focus();
        await page.keyboard.press('Home');
        assert.equal(await tabs.first().getAttribute('aria-selected'),'true');
        assert(await tabs.first().evaluate(el=>document.activeElement===el));
        await page.keyboard.press('End');
        assert.equal(await tabs.last().getAttribute('aria-selected'),'true');
        assert(await tabs.last().evaluate(el=>document.activeElement===el));
        await page.keyboard.press('Home');
        // Horizontal keyboard ordering should follow the visual reading direction.
        await page.keyboard.press(rtl?'ArrowLeft':'ArrowRight');
        assert.equal(await tabs.nth(1).getAttribute('aria-selected'),'true');
        assert(await tabs.nth(1).evaluate(el=>document.activeElement===el));
      });
      for(const width of [1440, 1024, 768, 390, 320]){
        await page.setViewportSize({width,height:900});
        for(const tab of ['alerts','reports']){
          await page.locator('#wpecir-tab-'+tab).click();
          if(tab==='alerts'){
            await page.waitForFunction(()=>document.querySelector('#wpecir-pro-recipients')?.value.includes('ops@example.test'));
          } else {
            await page.locator('.wpecir-report summary').first().waitFor();
            await page.locator('.wpecir-report summary').first().click();
            await page.locator('.wpecir-report__body').first().waitFor({state:'visible'});
          }
          await probe(page,name+' '+width+'px '+tab+' layout',async()=>{
            const metrics=await page.locator('.wpecir-app').evaluate(el=>{
              const rect=el.getBoundingClientRect();
              const visibleControls=[...el.querySelectorAll('input:not([type=hidden]),select,button:not([role=tab])')].filter(x=>x.getClientRects().length&&getComputedStyle(x).visibility!=='hidden');
              const clipped=visibleControls.filter(x=>{const r=x.getBoundingClientRect();return r.left < -2 || r.right > innerWidth+2;}).map(x=>x.id||x.textContent.trim().slice(0,40));
              return {left:rect.left,right:rect.right,width:innerWidth,scroll:el.scrollWidth,client:el.clientWidth,clipped,documentOverflow:document.documentElement.scrollWidth-innerWidth};
            });
            console.log(JSON.stringify(metrics));
            assert(metrics.left>=-2 && metrics.right<=width+2,'app exceeds viewport');
            assert(metrics.scroll<=metrics.client+2,'app overflows horizontally (nested scroll regions permitted)');
            assert.deepEqual(metrics.clipped,[],'controls outside viewport');
          });
          if(width===1440 || width===390){
            await probe(page,name+' '+width+'px '+tab+' axe WCAG A/AA',async()=>{
              await page.evaluate(axe.source);
              const results=await page.evaluate(async()=>window.axe.run(document.querySelector('.wpecir-app'),{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21a','wcag21aa','wcag22aa']}}));
              const findings=results.violations.map(v=>({id:v.id,impact:v.impact,targets:v.nodes.map(n=>n.target),help:v.help}));
              console.log('AXE '+JSON.stringify(findings));
              assert.equal(findings.length,0,findings.map(x=>x.id).join(', '));
            });
          }
        }
      }
      await context.close();
    }
  }finally{await browser.close();}
  if(issues.length){console.error('QA findings:\n'+issues.join('\n'));process.exitCode=1;}else{console.log('UI QA OK');}
})().catch(e=>{console.error(e);process.exitCode=1;});
