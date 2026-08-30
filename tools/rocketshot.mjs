import { chromium } from 'playwright';
const BASE='http://46.225.66.194';
const b = await chromium.launch();
const p = await b.newPage({ viewport:{width:1440,height:900} });
await p.goto(`${BASE}/wp-login.php`,{waitUntil:'domcontentloaded'});
await p.fill('#user_login','admin'); await p.fill('#user_pass','lXw3M7HX3QLNVefX61aM');
await Promise.all([p.waitForNavigation(),p.click('#wp-submit')]);
for (const [name,url] of [['rocket','options-general.php?page=wprocket'],['rocket2','admin.php?page=wprocket']]) {
  try {
    await p.goto(`${BASE}/wp-admin/${url}`,{waitUntil:'networkidle'});
    await p.waitForTimeout(2000);
    const bad = await p.evaluate(()=>document.body.innerText.includes('do not have sufficient permissions')||document.body.innerText.includes('Sorry, you are not allowed'));
    const h = await p.evaluate(()=>document.body.scrollHeight);
    if (!bad && h > 300) { await p.screenshot({path:`tools/shots/${name}.png`,fullPage:true}); console.log(name,h+'px',url); }
    else console.log(name,'no',url);
  } catch(e){ console.log(name,'err',e.message.slice(0,50)); }
}
await b.close();
