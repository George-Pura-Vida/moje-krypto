export const NOTICE_KEY='moje-krypto-cookie-notice-v1';
export const NOTICE_TTL=180*24*60*60*1000;
export function noticeAcknowledged(storage,now=Date.now()){
 try{const saved=JSON.parse(storage.getItem(NOTICE_KEY));return saved?.version===1&&saved?.necessary===true&&Number.isFinite(saved.at)&&saved.at<=now&&now-saved.at<NOTICE_TTL;}catch{return false;}
}
export function acknowledgeNotice(storage,now=Date.now()){
 try{storage.setItem(NOTICE_KEY,JSON.stringify({version:1,necessary:true,at:now}));return true;}catch{return false;}
}
export function setupPrivacy(doc,storage,clock=Date.now){
 if(doc.getElementById('cookie-notice'))return;
 const banner=doc.createElement('section');banner.id='cookie-notice';banner.className='cookie-notice';banner.setAttribute('aria-label','Informace o cookies');
 banner.innerHTML='<div><strong>Pouze nezbytné cookies a místní úložiště</strong><p>Používáme je pro bezpečné přihlášení a vámi vyžádané funkce. Aplikace nyní nepoužívá analytické ani marketingové cookies. <a href="/legal.php?view=cookies">Podrobnosti</a></p></div><div class="cookie-actions"><button type="button" data-cookie-ack>Rozumím · pouze nezbytné</button><button type="button" data-cookie-settings>Nastavení cookies</button></div>';
 const dialog=doc.createElement('dialog');dialog.id='cookie-settings';dialog.className='cookie-settings';dialog.setAttribute('aria-labelledby','cookie-settings-title');
 dialog.innerHTML='<h2 id="cookie-settings-title">Nastavení cookies</h2><p>Tato verze aplikace používá pouze nezbytné technologie. Souhlas s reklamou nebo analytikou nevyžadujeme a tyto technologie nespouštíme.</p><p><strong>Nezbytné: vždy aktivní</strong> — přihlašovací relace a ochrana formulářů. Uživatelská data se uchovávají podle funkcí této aplikace.</p><p>Analytické a marketingové cookies: <strong>nepoužívají se</strong>.</p><p>Uzavření lišty si zapamatujeme na 180 dní. Nastavení lze znovu otevřít v patičce. <a href="/legal.php?view=cookies">Úplné informace</a></p><p data-cookie-status role="status"></p><div class="cookie-actions"><button type="button" data-cookie-ack>Rozumím · pouze nezbytné</button><button type="button" data-cookie-close>Zavřít nastavení</button></div>';
 banner.hidden=noticeAcknowledged(storage,clock());doc.body.append(banner,dialog);
 const open=()=>{if(!dialog.open)dialog.showModal();};
 doc.addEventListener('click',event=>{
  const target=event.target.closest?.('[data-cookie-settings],[data-cookie-ack],[data-cookie-close]');if(!target)return;
  if(target.hasAttribute('data-cookie-settings'))open();
  else if(target.hasAttribute('data-cookie-close'))dialog.close();
  else{const saved=acknowledgeNotice(storage,clock());banner.hidden=true;if(dialog.open)dialog.close();if(!saved){banner.querySelector('p').textContent='Nastavení úložiště prohlížeče neumožňuje zapamatovat uzavření lišty. Při další návštěvě se může zobrazit znovu.';}}
 });
}
if(typeof document!=='undefined'){
 let storage;try{storage=window.localStorage;}catch{storage={getItem(){return null;},setItem(){throw Error('Storage unavailable');}};}
 setupPrivacy(document,storage);
}
