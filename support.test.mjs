import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';

const contents=async file=>readFile(new URL(file,import.meta.url),'utf8');
const app="Moje krypto";

test('email-first Fio support and opt-in anonymous QR are displayed',async()=>{
 const html=await contents('./support.html');
 const js=await contents('./support.js');
 const css=await contents('./support.css');
 for(const id of ['donorEmail','donorConsent','donationFeedback','prepareThanks','anonymousChoice','prepareAnonymous','paymentPlaceholder','paymentPreview','paymentQr','paymentVsLine','selectedVs']){
   assert.ok(html.includes('id="'+id+'"'),'Missing '+id);
 }
 assert.ok(html.indexOf('id="prepareThanks"')<html.indexOf('id="prepareAnonymous"'));
 assert.match(html,/Příspěvek s poděkováním e-mailem/);
 assert.match(html,/<a[^>]*href="\/account\.php\?view=privacy"/);
 assert.match(js,/https:\/\/muj-dip\.jirijanousek\.cz\/donation-intent-cross\.php/);
 assert.match(js,/credentials:'omit'/);
 assert.doesNotMatch(js,/FIO_TOKEN|FIO_SECRET|PRIVATE_KEY/);
 assert.match(js,/variableSymbol/);
 assert.match(js,/qrMode='none'/);
 assert.match(js,/qrMode='linked'/);
 assert.match(js,/qrMode='anonymous'/);
 assert.match(css,/donation-recommended/);
 assert.match(css,/payment-placeholder/);
});

test('QR encodes a distinct valid 8 digit VS for payments and each app message',async()=>{
 const src=await contents('./support-payment.js');
 const {paymentDescriptor,amountInHellers,payment}=await import('data:text/javascript,'+encodeURIComponent(src));
 assert.equal(amountInHellers('1,50'),150);
 assert.equal(payment.message,'PODPORA APLIKACE '+app.toLocaleUpperCase('cs-CZ'));
 const coded=paymentDescriptor('100','12345678');
 assert.match(coded,/ACC:CZ8220100000000223366333/);
 assert.match(coded,/AM:100\.00/);
 assert.match(coded,/X-VS:12345678/);
 assert.ok(coded.includes('*MSG:'+payment.message));
 assert.ok(!paymentDescriptor('100').includes('X-VS:'));
 assert.throws(()=>paymentDescriptor('100','not-a-vs'));
});

test('donor page and existing account and navigation link to correct support',async()=>{
 const account=await contents('./account.php');
 assert.ok(account.includes('href="/support.html"'));
 assert.ok(account.includes('site-common-footer'));
 assert.ok(account.includes('/privacy.js?v=2'));
 assert.ok(account.includes('data-cookie-settings'));
 assert.ok(account.includes('shared')||account.includes('společného platebního'));
 const index=await contents('./index.php');
 assert.match(index,/nav-support[^>]*href="\/support\.html"/);
 assert.match(index,/support-cta[^>]*href="\/support\.html"/);
 assert.match(index,/href="\/support\.html"/);
});
