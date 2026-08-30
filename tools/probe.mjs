import { chromium } from 'playwright';
const BASE='http://46.225.66.194';
const b = await chromium.launch();
const p = await b.newPage({ viewport:{width:1440,height:900} });
await p.goto(`${BASE}/wp-login.php`,{waitUntil:'domcontentloaded'});
await p.fill('#user_login','admin'); await p.fill('#user_pass','lXw3M7HX3QLNVefX61aM');
await Promise.all([p.waitForNavigation(),p.click('#wp-submit')]);
await p.goto(`${BASE}/wp-admin/admin.php?page=media-library`,{waitUntil:'networkidle'});
const out = await p.evaluate(() => {
  const box = document.querySelector('.vgml-shell-content .postbox');
  if (!box) return { err: 'no .postbox inside .vgml-shell-content' };
  const cs = getComputedStyle(box);
  const sheets = [...document.styleSheets].map(s => s.href).filter(Boolean);
  return {
    inShell: !!box.closest('.vgml-shell-content'),
    border: cs.borderTopWidth + ' ' + cs.borderTopStyle + ' ' + cs.borderTopColor,
    background: cs.backgroundColor,
    shadow: cs.boxShadow.slice(0,40),
    ourSheetLoaded: sheets.some(h => h.includes('vergeml-shell.css')),
    sheetOrder: sheets.filter(h=>/common|forms|vergeml-shell/.test(h)).map(h=>h.split('/').pop().split('?')[0]),
  };
});
console.log(JSON.stringify(out,null,2));
await b.close();
