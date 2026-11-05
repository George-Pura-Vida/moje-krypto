import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
const root=new URL('.',import.meta.url);
const read=(path)=>readFile(new URL(path,root),'utf8');

test('Necessary-cookie banner is remembered for 180 days without marketing consent',async()=>{
 const js=await read('privacy.js');
 const {noticeAcknowledged,acknowledgeNotice,NOTICE_TTL,NOTICE_KEY}=await import('data:text/javascript,'+encodeURIComponent(js));
 assert.equal(NOTICE_TTL,180*24*60*60*1000);
 const state=new Map();
 const storage={getItem:k=>state.get(k)??null,setItem:(k,v)=>state.set(k,v)};
 const now=1750000000000;
 assert.equal(noticeAcknowledged(storage,now),false);
 assert.equal(acknowledgeNotice(storage,now),true);
 assert.equal(noticeAcknowledged(storage,now+NOTICE_TTL-1),true);
 assert.equal(noticeAcknowledged(storage,now+NOTICE_TTL),false);
 const record=JSON.parse(state.get(NOTICE_KEY));
 assert.equal(record.necessary,true);
 assert.equal(Object.hasOwn(record,'marketing'),false);
 assert.equal(Object.hasOwn(record,'analytics'),false);
 assert.match(js,/data-cookie-settings/);
 assert.match(js,/data-cookie-ack/);
});

test('Common footer, legal navigation and privacy script render on each page',async()=>{
 const pages=["account.php","index.php","support.html","legal.php"];
 for(const page of pages){
  const content=await read(page);
  assert.ok(content.includes('privacy.js?v=2'),'Missing cookie notice on '+page);
  assert.ok(content.includes('site-common-footer'),'Missing shared footer on '+page);
  for(const path of ['terms','legal','privacy','cookies']){
   assert.ok(content.includes('/legal.php?view='+path),'Missing '+path+' on '+page);
  }
  assert.ok(content.includes('data-cookie-settings'),'Missing settings on '+page);
  assert.ok(content.includes('jirijanousek.cz'),'Missing personal site on '+page);
 }
 const policy=await read('legal.php');
 assert.ok(policy.includes('nezbytné')||policy.includes('nezbytn'));
 assert.ok(policy.includes('180 dní'));
 assert.ok(policy.includes('marketing'));
});
