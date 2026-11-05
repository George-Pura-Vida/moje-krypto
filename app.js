"use strict";
(function(){
 const C=window.KryptoCharts,M=window.KryptoModels,K=window.KryptoCost,$=id=>document.getElementById(id);
 const color={BTC:'#f7b450',ETH:'#9895ec',market:'#7eb4f2',custom:'#f6b962',inflation:'#ed858b',real:'#74d9a6'};
 const types={received:'📥 Přijato',sent:'📤 Odesláno',buy:'🛒 Nákup',sell:'💵 Prodej'};
 const fresh=()=>({assets:[{symbol:'BTC',quantity:0,costBasis:null},{symbol:'ETH',quantity:0,costBasis:null}],transactions:[]});
 let data=fresh(),prices={BTC:null,ETH:null},marketCache=new Map(),inflight=new Map(),symbol='BTC',period='1Y',page='dashboard';
 let edited=null,pricesAt=null,stalePrices=false,saving=false,pendingSave=false,toastTimer;
 const n=(id,d=0)=>{const v=Number($(id).value);return Number.isFinite(v)?v:d};
 const safe=C.esc,fmt=C.fmt,money=C.money,pct=C.pct;
 function notify(message){const el=$('toast');el.textContent=message;el.classList.add('visible');clearTimeout(toastTimer);toastTimer=setTimeout(()=>el.classList.remove('visible'),3500)}
 function validate(o){
  if(!o||!Array.isArray(o.assets)||!Array.isArray(o.transactions)||o.assets.length>40||o.transactions.length>10000)throw Error('Soubor nemá platnou strukturu.');
  const assets=['BTC','ETH'].map(symbol=>{
   const row=o.assets.find(x=>x.symbol===symbol)||{},q=Number(row.quantity||0);
   const cost=row.costBasis==null||row.costBasis===''?null:Number(row.costBasis);
   if(!Number.isFinite(q)||q<0||q>1e9||(cost!==null&&(!Number.isFinite(cost)||cost<0||cost>1e15)))throw Error('Neplatná hodnota portfolia.');
   return {symbol,quantity:q,costBasis:cost};
  });
  const transactions=o.transactions.map(x=>{
   const q=Number(x.quantity),v=Number(x.czk),date=String(x.date||'');
   if(!/^\d{4}-\d{2}-\d{2}$/.test(date)||!['BTC','ETH'].includes(x.symbol)||!Object.keys(types).includes(x.type)||!Number.isFinite(q)||!Number.isFinite(v)||q<0||v<0||q>1e9||v>1e15)throw Error('Neplatný záznam transakce.');
   return {id:String(x.id||crypto.randomUUID()).slice(0,90),date,symbol:x.symbol,type:x.type,quantity:q,czk:v,note:String(x.note||'').slice(0,160)};
  });
  return {assets,transactions};
 }
 async function init(){
  setupEvents();setupAppearance();runForecast();window.addEventListener('resize',fitNumbers);
  try{const res=await fetch('?api=data',{credentials:'same-origin'});const json=await res.json();if(!res.ok)throw Error(json.error||'Načtení dat selhalo');data=validate(json.data);}
  catch(e){notify('⚠️ '+e.message)}
  renderAll();await refreshPrices();
  refreshChart();
  setInterval(refreshPrices,120000);
 }
 let saveQueue=Promise.resolve();
 function persist(){const snapshot=JSON.stringify(data);const task=saveQueue.catch(()=>{}).then(async()=>{const response=await fetch('?api=data',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name=csrf-token]').content},body:snapshot});const j=await response.json();if(!response.ok||!j.ok)throw Error(j.error||'Ukládání selhalo');});saveQueue=task;return task;}
 async function refreshPrices(){
  const status=$('online-status');status.className='online-status';status.textContent='● Načítání cen';
  try{
   const res=await fetch('?api=prices',{credentials:'same-origin',cache:'no-store'}),json=await res.json();
   if(!res.ok)throw Error(json.error||'Kurz není dostupný');
   for(const key of ['BTC','ETH']){const p=json.data&&json.data[key];if(p&&Number(p.price)>0)prices[key]={price:Number(p.price),change24h:p.change24h==null?null:Number(p.change24h)};}
   pricesAt=json.updated_at;stalePrices=!!json.stale;
   status.textContent=(stalePrices?'● Uložený kurz':'● Online CZK')+' · '+(json.data.source||'trh')+' · '+new Date((pricesAt||Date.now()/1000)*1000).toLocaleTimeString('cs-CZ',{hour:'2-digit',minute:'2-digit'});status.className='online-status '+(stalePrices?'bad':'ok');
  }catch(e){status.textContent='● Data nejsou dostupná';status.className='online-status bad';notify('⚠️ '+e.message);}
  renderDashboard();requestAnimationFrame(fitNumbers);
 }
 function asset(sym){return data.assets.find(x=>x.symbol===sym)||{quantity:0,costBasis:null};}
 function portfolio(){
  const rows=['BTC','ETH'].map(sym=>{
   const item=asset(sym),derived=K.estimatedHoldingCost(data.transactions,sym,item.quantity);
   const manual=item.costBasis;
   return {sym,amount:item.quantity,price:prices[sym]?.price??null,
    cost:manual!==null?manual:derived.cost,
    costSource:manual!==null?'manual':derived.cost!==null?'estimated':'unknown'};
  });
  for(const r of rows)r.value=r.price!==null?r.amount*r.price:null;
  const complete=rows.every(r=>r.amount===0||r.value!==null);
  return {rows,total:complete?rows.reduce((sum,r)=>sum+(r.value||0),0):null};
 }
 function received(type){return type==='received'||type==='buy';}
 function annual(year){return data.transactions.filter(t=>t.date.startsWith(String(year))&&received(t.type)).reduce((sum,t)=>sum+t.czk,0)}
 function ordered(){return [...data.transactions].sort((a,b)=>b.date.localeCompare(a.date)||b.id.localeCompare(a.id))}
 function renderDashboard(){
  for(const sym of ['BTC','ETH']){
   const p=prices[sym],pre=sym.toLowerCase();
   $(pre+'-price').textContent=p?money(p.price):'—';
   const info=$(pre+'-change');info.textContent=p?'24 h: '+pct(p.change24h)+(stalePrices?' · archivní kurz':''):'Kurz zatím není dostupný';
   info.className=p&&p.change24h!==null?(p.change24h>=0?'up':'down'):'';
  }
  const v=portfolio();
  $('portfolio-value').textContent=v.total===null?'—':money(v.total);
  $('portfolio-caption').textContent=pricesAt?'Kurzy k '+new Date(pricesAt*1000).toLocaleString('cs-CZ'):'Čekám na tržní data';
  const owned=v.rows.filter(r=>r.amount>0);
  const costKnown=owned.length&&owned.every(r=>r.cost!==null&&r.value!==null);
  const allCost=owned.reduce((sum,r)=>sum+(r.cost||0),0);
  const roi=costKnown&&allCost>0?(v.total/allCost-1)*100:null;
  const estimated=owned.some(r=>r.costSource==='estimated');
  $('portfolio-roi-label').textContent=estimated?'📊 Odhad zhodnocení':'📊 Moje skutečné zhodnocení';
  $('portfolio-roi').textContent=roi===null?'—':pct(roi);
  $('portfolio-roi').className=roi===null?'':roi>=0?'up':'down';
  $('portfolio-roi-info').textContent=roi===null
    ?'Chybí úplná historie, návaznost zůstatků nebo doložené náklady.'
    :(estimated?'Odhad z hodnot převodů':'Podle zadaných pořizovacích nákladů')
      +' · rozdíl '+money(v.total-allCost)+(estimated?' · není skutečný zisk':'');
  $('info-banner').style.display=(!data.transactions.length&&!owned.length)?'':'none';
  const shown=v.rows.filter(r=>r.value!==null&&r.value>0);
  const total=shown.reduce((s,r)=>s+r.value,0);let from=0,stops=[];
  for(const r of shown){const to=from+r.value/total*360;stops.push(color[r.sym]+' '+from+'deg '+to+'deg');from=to;}
  $('donut').style.background=stops.length?'conic-gradient('+stops.join(',')+')':'#43525a';
  $('donut-amount').textContent=total?money(total):'—';
  $('donut-legend').innerHTML=shown.length?shown.map(r=>'<div class="donut-row"><span><i class="dot" style="background:'+color[r.sym]+'"></i>'+r.sym+'</span><b>'+fmt(r.value/total*100,1)+' %</b></div>').join(''):'<div class="empty">Nejprve přidej portfolio.</div>';
  $('portfolio-summary').innerHTML=v.rows.map(r=>'<div class="summary-row"><span>'+r.sym+' · '+fmt(r.amount,8)+'</span><b>'+(r.value!==null?money(r.value):'—')+'</b></div>'
    +(r.amount>0&&r.cost!==null?'<div class="summary-row"><span>Průměr '+r.sym+' · '+(r.costSource==='estimated'?'odhad dle převodů':'zadané náklady')+'</span><b>'+money(r.cost/r.amount)+'</b></div>':'')).join('');
  if(data.transactions.length){
   const netBtc=data.transactions.filter(t=>t.symbol==='BTC').reduce((sum,t)=>sum+(received(t.type)?t.quantity:-t.quantity),0);
   const discrepancy=asset('BTC').quantity-netBtc;
   $('portfolio-summary').innerHTML+='<div class="summary-row"><span>🔎 Evidovaný čistý tok BTC</span><b>'+fmt(netBtc,8)+' BTC</b></div>'
     +'<div class="summary-row"><span>Rozdíl toku proti zůstatku</span><b>'+fmt(discrepancy,8)+' BTC</b></div>';
  }

  C.annual('annual-bars',data.transactions,$('annual-type').value);
  const currentYear=new Date().getFullYear(),monthCount=new Date().getMonth()+1,years=[...new Set(data.transactions.map(t=>Number(t.date.slice(0,4))))].sort();
  $('annual-totals').innerHTML=years.map(y=>'<div class="summary-row"><span>'+y+(y===currentYear?' · neuzavřený rok, k '+new Date().toLocaleDateString('cs-CZ'):' · uzavřený rok')+'</span><b>'+money(annual(y))+'</b></div>').join('');
  $('donut').classList.toggle('pie-mode',$('allocation-type').value==='pie');
  $('recent-transactions').innerHTML=ordered().slice(0,5).map(t=>'<div class="recent"><div class="recent-left"><span class="coin-icon '+(t.symbol==='ETH'?'eth':'')+'">'+(t.symbol==='BTC'?'₿':'◆')+'</span><div><b>'+types[t.type]+' '+t.symbol+'</b><small>'+safe(t.date)+' · '+fmt(t.quantity,8)+' '+t.symbol+'</small></div></div><div class="recent-right '+(received(t.type)?'up':'down')+'">'+(received(t.type)?'+':'−')+money(t.czk)+'</div></div>').join('')||'<div class="empty">Žádné evidované transakce.</div>';
 }
 function renderTransactions(){
  const year=$('filter-year').value,sym=$('filter-symbol').value;
  const years=[...new Set(data.transactions.map(t=>t.date.slice(0,4)))].sort().reverse();
  $('filter-year').innerHTML='<option value="all">Všechny roky</option>'+years.map(y=>'<option value="'+safe(y)+'">'+safe(y)+'</option>').join('');
  $('filter-year').value=years.includes(year)?year:'all';const yy=$('filter-year').value;
  const rows=ordered().filter(t=>(yy==='all'||t.date.startsWith(yy))&&(sym==='all'||t.symbol===sym));
  $('transactions-body').innerHTML=rows.map(t=>{
   const implied=K.impliedUnitPrice(t);
   return '<tr><td>'+safe(t.date)+'</td><td>'+types[t.type]+'</td><td>'+safe(t.symbol)+'</td><td>'+fmt(t.quantity,8)+'</td><td>'+money(t.czk)+'</td>'
     +'<td class="unit-price" title="Orientační kurz = částka převodu v Kč / přijaté množství; nemusí odpovídat nákupní ceně.">'
     +(implied===null?'—':'≈ '+money(implied)+'</td>'
     +'<td><button class="small-btn" data-edit="'+safe(t.id)+'">Upravit</button><button class="small-btn delete" data-delete="'+safe(t.id)+'">Smazat</button></td></tr>';
  }).join('')||'<tr><td colspan="7" class="empty">Žádné transakce.</td></tr>';
  const summary=K.transferTotals(rows);
  $('transactions-summary').innerHTML=['BTC','ETH'].filter(sym=>summary[sym].count||summary[sym].missing).map(sym=>{
   const r=summary[sym];
   return '<tr class="transaction-total"><td colspan="3">Σ Přijaté převody '+sym+'</td><td>'+fmt(r.quantity,8)+'</td><td>'+money(r.czk)+'</td>'
     +'<td class="unit-price">'+(r.average===null?'—':'≈ '+money(r.average))+'</td><td>'+r.count+' záznamů'+(r.missing?' · '+r.missing+' bez ceny':'')+'</td></tr>';
  }).join('');
  $('transactions-footer').textContent='Počet zobrazených transakcí: '+rows.length
   +'. Vážený průměr = součet částek v Kč / součet přijatého množství. Hodnota při převodu není prokazatelná pořizovací cena; bez poplatků a kurzových rozdílů.';
  $('transactions-body').querySelectorAll('[data-edit]').forEach(b=>b.addEventListener('click',()=>openTransaction(data.transactions.find(t=>t.id===b.dataset.edit))));
  $('transactions-body').querySelectorAll('[data-delete]').forEach(b=>b.addEventListener('click',async()=>{if(!confirm('Opravdu smazat tuto transakci?'))return;data.transactions=data.transactions.filter(t=>t.id!==b.dataset.delete);renderAll();await persist()}));
 }
 function renderSettings(){
  for(const sym of ['BTC','ETH']){const row=asset(sym),id=sym.toLowerCase();$('qty-'+id).value=row.quantity||'';$('cost-'+id).value=row.costBasis??''}
 }
 function fitNumbers(){document.querySelectorAll('.stat strong,.four-metrics b,.donut-core b').forEach(el=>{el.style.removeProperty('font-size');const width=el.clientWidth;if(width>0&&el.scrollWidth>width){const px=parseFloat(getComputedStyle(el).fontSize);el.style.fontSize=Math.max(12,Math.floor(px*width/el.scrollWidth))+'px';}})}
 function renderAll(fill=true){renderDashboard();renderTransactions();if(fill)renderSettings();requestAnimationFrame(fitNumbers);}
 function navigate(to){
  page=to;
  const names={dashboard:'Přehled kryptoměn',markets:'Trhy & historické grafy',transactions:'Moje transakce',strategies:'DCA & Buy the Dip',forecast:'Predikce & scénáře',settings:'Nastavení a data',goals:'Cesta k mému cíli'};
  document.querySelectorAll('.page').forEach(x=>x.classList.toggle('active',x.id==='page-'+to));
  document.querySelectorAll('.nav-link').forEach(x=>x.classList.toggle('active',x.dataset.page===to));
  $('page-title').textContent=names[to];closeMenu();window.scrollTo(0,0);
  if(to==='dashboard'||to==='markets')refreshChart();
  if(to==='forecast')runForecast();
  if(to==='goals'){runGoal();runSatoshi();}
  if(to==='strategies'&&!$('dca-chart').dataset.done)runDca();
 }
 function closeMenu(){$('sidebar').classList.remove('open');$('mobile-shade').classList.remove('open');$('burger').setAttribute('aria-expanded','false')}
 function chooseCoin(key){
  symbol=key;
  document.querySelectorAll('#quick-symbol button,#market-symbol button').forEach(b=>b.classList.toggle('selected',b.dataset.symbol===symbol));
  refreshChart();
 }
 function setupEvents(){
  document.querySelectorAll('[data-page]').forEach(b=>b.addEventListener('click',()=>navigate(b.dataset.page)));
  document.querySelectorAll('[data-go]').forEach(b=>b.addEventListener('click',()=>navigate(b.dataset.go)));
  $('burger').addEventListener('click',()=>{let open=$('sidebar').classList.toggle('open');$('mobile-shade').classList.toggle('open',open);$('burger').setAttribute('aria-expanded',String(open))});
  $('mobile-shade').addEventListener('click',closeMenu);
  const periods=[['1D','1D'],['7D','7D'],['1M','1M'],['3M','3M'],['6M','6M'],['1Y','1R'],['5Y','5 let'],['15Y','15 let']];
  document.querySelectorAll('[data-periods]').forEach(box=>{
   box.innerHTML=periods.map(([p,l])=>'<button type="button" data-period="'+p+'" class="'+(p===period?'selected':'')+'">'+l+'</button>').join('');
   box.addEventListener('click',e=>{const b=e.target.closest('[data-period]');if(!b)return;period=b.dataset.period;document.querySelectorAll('[data-period]').forEach(x=>x.classList.toggle('selected',x.dataset.period===period));refreshChart()});
  });
  document.querySelectorAll('#quick-symbol button,#market-symbol button').forEach(b=>b.addEventListener('click',()=>chooseCoin(b.dataset.symbol)));
  ['show-inflation','show-real','benchmark-return','market-inflation'].forEach(id=>$(id).addEventListener('input',()=>renderMarket()));
  $('filter-year').addEventListener('change',renderTransactions);
  $('filter-symbol').addEventListener('change',renderTransactions);
  $('add-transaction').addEventListener('click',()=>openTransaction());
  $('close-modal').addEventListener('click',()=>$('transaction-dialog').close());
  $('cancel-modal').addEventListener('click',()=>$('transaction-dialog').close());
  $('transaction-form').addEventListener('submit',saveTransaction);
  $('save-portfolio').addEventListener('click',savePortfolio);
  $('run-dca').addEventListener('click',runDca);
  $('calculate-forecast').addEventListener('click',runForecast);
  $('use-history').addEventListener('click',applyHistoricalCagr);
  $('set-112').addEventListener('click',()=>{$('f-mid').value=112;runForecast();notify('112 % p. a. je pouze ilustrativní scénář.')});
  ['f-initial','f-monthly','f-years','f-inflation','f-low','f-mid','f-high'].forEach(id=>$(id).addEventListener('change',runForecast));
  document.querySelectorAll('[data-chart-mode]').forEach(select=>select.addEventListener('change',()=>{document.querySelectorAll('[data-chart-mode]').forEach(x=>x.value=select.value);if(page==='dashboard'||page==='markets')refreshChart();if(page==='strategies')runDca();if(page==='forecast')runForecast();}));
  $('annual-type').addEventListener('change',renderDashboard);$('allocation-type').addEventListener('change',renderDashboard);
  $('export-json').addEventListener('click',exportJson);
  $('export-xlsx').addEventListener('click',exportExcel);
  $('paste-import').addEventListener('click',()=>{$('paste-data').value='';$('paste-dialog').showModal()});
  $('paste-cancel').addEventListener('click',()=>$('paste-dialog').close());
  $('paste-confirm').addEventListener('click',importPaste);
  $('calculate-goal').addEventListener('click',runGoal);
  $('g-coin').addEventListener('change',()=>{$('g-target').value=$('g-coin').value==='BTC'?0.1:1;$('g-price').value='';runGoal()});
  ['g-target','g-monthly','g-months','g-growth','g-price'].forEach(id=>$(id).addEventListener('change',runGoal));
  ['sat-czk','sat-price'].forEach(id=>$(id).addEventListener('input',runSatoshi));
  $('export-csv').addEventListener('click',exportCsv);
  $('import-file').addEventListener('change',importFile);
  $('print').addEventListener('click',printReport);
  $('erase').addEventListener('click',async()=>{if(!confirm('Opravdu smazat všechny soukromé transakce a celé portfolio?'))return;data=fresh();await persist();renderAll();notify('Data byla vymazána.')});
 }
 async function getHistory(coin,range){
  const key=coin+'-'+range;
  if(marketCache.has(key))return marketCache.get(key);
  if(inflight.has(key))return inflight.get(key);
  const promise=(async()=>{
   const response=await fetch('?api=history&coin='+encodeURIComponent(coin)+'&period='+encodeURIComponent(range),{credentials:'same-origin'});
   const j=await response.json();if(!response.ok||!j.data||!Array.isArray(j.data.points))throw Error(j.error||'Historická data zatím nejsou k dispozici.');
   const points=j.data.points.map(p=>[Number(p[0]),Number(p[1])]).filter(p=>p[0]>0&&p[1]>0&&Number.isFinite(p[1])).sort((a,b)=>a[0]-b[0]);
   if(points.length<2)throw Error('Zvolený historický úsek je prázdný.');
   const h={points,partial:!!j.data.coverage_partial,stale:!!j.stale,source:j.data.source||'Tržní zdroj',fxCurrent:!!j.data.fx_current};
   marketCache.set(key,h);return h;
  })();
  inflight.set(key,promise);try{return await promise}finally{inflight.delete(key)}
 }
 async function refreshChart(){
  if(page!=='dashboard'&&page!=='markets')return;
  const el=page==='dashboard'?'home-chart':'market-chart';
  C.empty(el,'⏳ Načítám dostupnou historii v CZK…');
  try{
   const snapshotCoin=symbol,snapshotPeriod=period;
   const history=await getHistory(snapshotCoin,snapshotPeriod);
   if(snapshotCoin!==symbol||snapshotPeriod!==period)return;
   if(page==='dashboard'){
    C.draw('home-chart',[{name:symbol+' CZK',color:color.market,points:history.points}],{title:'Cena '+symbol+' v CZK'});
    const p=history.points,a=p[0],b=p[p.length-1];
    $('home-chart-footer').textContent=C.date(a[0])+' až '+C.date(b[0])+' · '+money(a[1])+' → '+money(b[1])+' · '+pct((b[1]/a[1]-1)*100)+' · '+history.source+(history.fxCurrent?' · ⚠️ Dnešní USD/CZK, nikoli historický korunový výnos.':'')+(history.partial?' · ⚠️ Neúplné historické pokrytí':'')+(history.stale?' · poslední uložená data':'');
   }else renderMarket(history);
  }catch(e){
   C.empty(el,'⚠️ '+e.message);
   $(page==='dashboard'?'home-chart-footer':'market-chart-footer').textContent='Zdroje: CoinGecko / Kraken / Coinbase. Data jsou dočasně nedostupná; žádné náhradní ceny nevymýšlíme.';
  }
 }
 function renderMarket(history){
  if(page!=='markets')return;
  const h=history||marketCache.get(symbol+'-'+period);if(!h){refreshChart();return;}
  const list=h.points,a=list[0],b=list[list.length-1],duration=(b[0]-a[0])/(365.2425*86400);
  const gain=(b[1]/a[1]-1)*100,annual=duration>.08?(Math.pow(b[1]/a[1],1/duration)-1)*100:null;
  $('market-start').textContent=money(a[1]);$('market-end').textContent=money(b[1]);
  $('market-growth').textContent=pct(gain);$('market-cagr').textContent=annual===null?'—':pct(annual);
  $('market-growth').className=gain>=0?'up':'down';
  const inflation=Math.max(-99,Math.min(1000,n('market-inflation',2))),own=Math.max(-99,Math.min(1000,n('benchmark-return',12)));
  const model=list.map(([t,price])=>{
   const years=(t-a[0])/(365.2425*86400);
   return [t,(price/a[1]-1)*100,(Math.pow(1+own/100,years)-1)*100,(Math.pow(1+inflation/100,years)-1)*100,(price/a[1]/Math.pow(1+inflation/100,years)-1)*100];
  });
  const lines=[
   {name:'Trh '+symbol,color:color.market,points:model.map(x=>[x[0],x[1]])},
   {name:'Vlastní scénář',color:color.custom,points:model.map(x=>[x[0],x[2]])}
  ];
  if($('show-inflation').checked)lines.push({name:'Inflace',color:color.inflation,points:model.map(x=>[x[0],x[3]]),dashed:true});
  if($('show-real').checked)lines.push({name:'Trh reálně',color:color.real,points:model.map(x=>[x[0],x[4]])});
  C.draw('market-chart',lines,{percent:true,tall:true,title:'Index kumulativního procentního výnosu'});
  $('market-chart-footer').textContent='Všechny křivky ukazují celkovou změnu v % od '+C.date(a[0])+' do '+C.date(b[0])+'. '+h.points.length+' bodů · Zdroj '+h.source+(h.fxCurrent?' · ⚠️ Výnos v USD; dnešní přepočet CZK, nikoli historický korunový výnos.':'')+(h.partial?' · ⚠️ Požadovaný časový rozsah není úplný':'')+(h.stale?' · archivní cache':'');
 }
 function openTransaction(t){
  edited=t?.id||null;$('modal-title').textContent=t?'Upravit transakci':'Nová transakce';
  const d=new Date(),today=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
  $('tx-date').value=t?.date||today;$('tx-symbol').value=t?.symbol||'BTC';$('tx-type').value=t?.type||'received';
  $('tx-qty').value=t?.quantity??'';$('tx-czk').value=t?.czk??'';$('tx-note').value=t?.note??'';
  $('transaction-dialog').showModal();
 }
 async function saveTransaction(e){
  e.preventDefault();
  const transaction={id:edited||crypto.randomUUID(),date:$('tx-date').value,symbol:$('tx-symbol').value,type:$('tx-type').value,quantity:Number($('tx-qty').value),czk:Number($('tx-czk').value),note:$('tx-note').value.slice(0,160)};
  try{
   const item=validate({assets:data.assets,transactions:[transaction]}).transactions[0];
   data.transactions=edited?data.transactions.map(x=>x.id===edited?item:x):data.transactions.concat(item);
   $('transaction-dialog').close();renderAll();await persist();notify('✅ Transakce uložena.');
  }catch(err){notify('⚠️ '+err.message)}
 }
 async function savePortfolio(){
  try{
   const assets=['BTC','ETH'].map(sym=>{
    const id=sym.toLowerCase(),qty=Number($('qty-'+id).value||0),raw=$('cost-'+id).value,cost=raw.trim()===''?null:Number(raw);
    if(!Number.isFinite(qty)||qty<0||(cost!==null&&(!Number.isFinite(cost)||cost<0)))throw Error('Zadej platné množství a náklady.');
    return {symbol:sym,quantity:qty,costBasis:cost};
   });
   data.assets=assets;renderAll(false);await persist();notify('✅ Portfolio uloženo.');
  }catch(e){notify('⚠️ '+e.message)}
 }
 const tile=(label,value,detail,cls)=>'<div class="result-tile"><small>'+safe(label)+'</small><b class="'+(cls||'')+'">'+safe(value)+'</b><em>'+safe(detail||'')+'</em></div>';
 async function runDca(){
  $('dca-results').innerHTML='<div class="empty">⏳ Počítám ze skutečné historie…</div>';C.empty('dca-chart','Načítám ceny…');
  try{
   const c=$('dca-coin').value,p=$('dca-period').value;
   const h=await getHistory(c,p),out=M.simulateDCA(h.points,{
    monthly:n('dca-monthly',3000),initial:n('dca-initial',0),extra:n('dip-extra',1000),threshold:n('dip-threshold',15)
   });
   $('dca-results').innerHTML=tile('💰 DCA · vloženo',money(out.regularSpent),out.months+' měsíčních nákupů')
     +tile('📈 DCA · aktuální hodnota',money(out.regularValue),pct(out.regularSpent>0?(out.regularValue/out.regularSpent-1)*100:null),out.regularValue>=out.regularSpent?'up':'down')
     +tile('🛒 DCA · průměrná cena',money(out.regularAverage),fmt(out.regularUnits,8)+' '+c)
     +tile('🎯 DCA + dip · hodnota',money(out.dipValue),'Vloženo '+money(out.dipSpent))
     +tile('📉 Průměr s extra nákupy',money(out.dipAverage),out.dips+' extra nákupů')
     +tile('🪙 Konečná cena 1 '+c,money(out.last),'Od '+money(out.first));
   C.draw('dca-chart',[
    {name:'DCA',color:color.market,points:out.chart.map(x=>[x[0],x[1]])},
    {name:'DCA + dip',color:color.real,points:out.chart.map(x=>[x[0],x[2]])},
    {name:'Vloženo DCA',color:color.custom,points:out.chart.map(x=>[x[0],x[3]]),dashed:true}
   ],{title:'Vývoj DCA a nákupů při poklesu v CZK'});
   C.draw('dca-price-chart',[
    {name:'Kurz '+c,color:color.market,points:out.chart.map(x=>[x[0],x[7]])},
    {name:'Průměr DCA',color:color.custom,points:out.chart.map(x=>[x[0],x[5]])},
    {name:'Průměr s dipem',color:color.real,points:out.chart.map(x=>[x[0],x[6]])}
   ],{title:'Průměrné nákupní ceny za jednotku v Kč'});
   $('dca-chart').dataset.done='1';
   $('dca-foot').textContent=C.date(out.from)+' až '+C.date(out.to)+' · Zdroj '+h.source+' · extra kapitál na dip '+money(out.dipSpent-out.regularSpent)+(h.fxCurrent?' · Model s konstantním dnešním USD/CZK':'')+(h.partial?' · ⚠️ historie kratší než požadované období':'')+'. Bez poplatků, daní a skluzu.';
  }catch(e){C.empty('dca-chart','Výpočet není dostupný.');$('dca-results').innerHTML='<div class="empty">⚠️ '+safe(e.message)+'</div>';$('dca-foot').textContent='';}
 }
 async function applyHistoricalCagr(){
  const coin=$('f-history-coin').value;
  const note=$('f-history-note');
  note.textContent='⏳ Načítám historii…';
  try{
   const h=await getHistory(coin,'15Y');
   const first=h.points[0],last=h.points[h.points.length-1],years=(last[0]-first[0])/(365.2425*86400);
   if(years<0.2)throw Error('Není dostatek historických dat pro výpočet.');
   const cagr=(Math.pow(last[1]/first[1],1/years)-1)*100;
   $('f-mid').value=cagr.toFixed(2);
   runForecast();
   note.textContent=' Historie '+C.date(first[0])+'–'+C.date(last[0])+', CAGR '+pct(cagr)+(h.partial?' (neúplné období)':'')+(h.fxCurrent?' · CAGR v USD, nikoli historický výnos CZK':'');
   notify('Historický CAGR je modelová vstupní hodnota, nikoli předpověď budoucnosti.');
  }catch(e){note.textContent=' ⚠️ '+e.message;}
 }
 function runForecast(){
  const res=M.forecast({initial:n('f-initial',100000),monthly:n('f-monthly',3000),years:n('f-years',10),inflation:n('f-inflation',2),rates:[n('f-low',-15),n('f-mid',12),n('f-high',25)]});
  $('forecast-summary').innerHTML=tile('💵 Celkem vloženo',money(res.invested),res.years+' let')
   +tile('🔴 Pesimistický',money(res.final[0]),pct(res.rates[0])+' ročně')
   +tile('🔵 Vlastní scénář',money(res.final[1]),pct(res.rates[1])+' ročně',res.final[1]>=res.invested?'up':'down')
   +tile('🟢 Optimistický',money(res.final[2]),pct(res.rates[2])+' ročně')
   +tile('🛒 Hodnota po inflaci',money(res.real[1]),'Kupní síla vlastního scénáře')
   +tile('📈 Modelový výsledek',money(res.final[1]-res.invested),'Bez daní a poplatků');
  C.draw('forecast-chart',[
   {name:'Pesimistický',color:color.inflation,points:res.entries.map(x=>[x.t,x.values[0]])},
   {name:'Vlastní',color:color.market,points:res.entries.map(x=>[x.t,x.values[1]])},
   {name:'Optimistický',color:color.real,points:res.entries.map(x=>[x.t,x.values[2]])},
   {name:'Vklady',color:color.custom,points:res.entries.map(x=>[x.t,x.invested]),dashed:true}
  ],{title:'Nominální hodnota modelových investičních scénářů'});
  $('forecast-table').innerHTML=res.entries.filter((x,i)=>i===0||i===res.years||i%Math.max(1,Math.ceil(res.years/8))===0).map(x=>'<div class="forecast-row"><span>Rok '+new Date(x.t*1000).getUTCFullYear()+'</span><strong>'+money(x.values[1])+'</strong></div>').join('');
 }
 function download(content,name,mime){
  const b=new Blob([content],{type:mime}),url=URL.createObjectURL(b),a=document.createElement('a');
  a.href=url;a.download=name;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),2000);
 }
 function exportJson(){download(JSON.stringify({...data,version:1,exportedAt:new Date().toISOString()},null,2),'moje-krypto-zaloha.json','application/json');notify('✅ Záloha JSON připravena.')}
 const quoted=s=>'"'+String(s??'').replace(/"/g,'""')+'"';
 function exportCsv(){
  const columns=['date','symbol','type','quantity','czk','note'];
  const rows=ordered().map(t=>columns.map(k=>quoted(['quantity','czk'].includes(k)?String(t[k]).replace('.',','):t[k])).join(';'));
  download('\uFEFF'+columns.join(';')+'\r\n'+rows.join('\r\n'),'moje-krypto-prevody.csv','text/csv;charset=utf-8');
  notify('✅ CSV transakcí připraveno.');
 }

 function exportExcel(){try{download(window.KryptoData.xlsx(data),'moje-krypto.xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');notify('Excel připraven: portfolio a '+data.transactions.length+' transakcí.')}catch(e){notify('⚠️ '+e.message)}}
 async function acceptImport(imported){
  const next=validate(imported);if(!await reviewImport(next))return false;
  if(data.transactions.length)exportJson();const before=data;data=next;
  try{await persist();renderAll();notify('✅ Import uložen: '+data.transactions.length+' transakcí.');return true}catch(e){data=before;renderAll();throw Error('Import nebyl uložen: '+e.message)}
 }
 async function importFile(e){const files=Array.from(e.target.files||[]);e.target.value='';if(!files.length)return;try{let imported=structuredClone(data);for(const file of files){if(file.size>2400000)throw Error('Limit souboru je 2,4 MB.');const ext=file.name.toLowerCase();if(ext.endsWith('.json'))imported=JSON.parse(await file.text());else if(ext.endsWith('.csv'))imported=window.KryptoData.rowsToData(window.KryptoData.csv(await file.text()),imported);else if(ext.endsWith('.xlsx'))imported=await window.KryptoData.readXlsx(await file.arrayBuffer(),imported);else throw Error('Použij .xlsx, .csv nebo .json.')}await acceptImport(imported)}catch(err){notify('⚠️ '+err.message)}}
 async function importPaste(){try{const text=$('paste-data').value.trim();if(text.length>2400000)throw Error('Limit dat je 2,4 MB.');const imported=text.startsWith('{')?JSON.parse(text):window.KryptoData.rowsToData(window.KryptoData.csv(text),data);if(await acceptImport(imported))$('paste-dialog').close()}catch(e){notify('⚠️ '+e.message)}}
 function reviewImport(next){return new Promise(resolve=>{const dialog=$('import-review');$('import-review-summary').textContent='Připraveno '+next.transactions.length+' transakcí. BTC '+fmt(next.assets.find(a=>a.symbol==='BTC').quantity,8)+' · ETH '+fmt(next.assets.find(a=>a.symbol==='ETH').quantity,10)+'. Současná evidence bude nahrazena těmito daty; pokud obsahuje transakce, nejprve se stáhne její JSON záloha.';const finish=ok=>{dialog.close();dialog.removeEventListener('cancel',cancel);resolve(ok)};const cancel=e=>{e.preventDefault();finish(false)};$('import-review-yes').onclick=()=>finish(true);$('import-review-no').onclick=()=>finish(false);dialog.addEventListener('cancel',cancel);dialog.showModal()})}
 function runGoal(){
  const coin=$('g-coin').value,price=n('g-price')||prices[coin]?.price,target=n('g-target'),monthly=n('g-monthly'),months=Math.max(1,Math.min(600,Math.round(n('g-months',24)))),growth=n('g-growth'),current=asset(coin).quantity;
  if(!(price>0&&target>0&&monthly>=0&&growth>-100&&growth<=200)){$('goal-results').innerHTML=tile('Cena zatím není dostupná','Doplň cenu nebo počkej na kurz','Plán bez ceny nelze spočítat.');C.empty('goal-chart','Čekám na platnou cenu a parametry.');return}
  const gap=Math.max(0,target-current),factor=Math.pow(1+growth/100,1/12),start=Date.now()/1000,points=[[start,current]],targetLine=[[start,target]];let quantity=current,discount=0,reach=current>=target?0:null;
  for(let i=1;i<=600;i++){const p=price*Math.pow(factor,i);quantity+=monthly/p;if(i<=months){discount+=1/p;points.push([start+i*30.4375*86400,quantity]);targetLine.push([start+i*30.4375*86400,target])}if(reach===null&&quantity>=target)reach=i;}
  const need=discount>0?gap/discount:0,progress=Math.min(100,current/target*100);$('goal-progress').value=progress;
  $('goal-results').innerHTML=tile('Mám / cíl',fmt(current,8)+' / '+fmt(target,8)+' '+coin,progress.toFixed(1)+' % splněno')+tile('Zbývá dokoupit',fmt(gap,8)+' '+coin,'Při dnešní ceně '+money(gap*price))+tile('Měsíčně pro zvolený termín',money(need),months+' měsíců při modelové změně ceny '+pct(growth))+tile('Dosažení při tvém rozpočtu',reach===0?'Cíl splněn':reach===null?'Více než 50 let':reach+' měsíců',money(monthly)+' měsíčně · změna ceny '+pct(growth));
  C.draw('goal-chart',[{name:'Modelované množství '+coin,color:color.market,points},{name:'Můj cíl '+coin,color:color.custom,points:targetLine,dashed:true}],{title:'Cesta k cíli v '+coin,unit:coin});
  $('goal-foot').textContent=($('g-price').value?'Vlastní zadaná cena: ':'Poslední načtená tržní cena: ')+money(price)+' / '+coin+'. Nákupy na konci měsíce; plán není automatická objednávka. Při vyšší ceně se cíl v mincích dosahuje pomaleji.';
 }
 function runSatoshi(){const price=n('sat-price')||prices.BTC?.price,amount=n('sat-czk');$('sat-result').innerHTML=price>0&&amount>=0?tile('Za '+money(amount),fmt(Math.floor(amount/price*1e8),0)+' satoshi',fmt(amount/price,8)+' BTC při '+money(price)+' / BTC'):tile('Čekám na cenu BTC','—','Zadej vlastní kurz, pokud tržní data nejsou dostupná.')}
 function printReport(){
  const p=portfolio(),stamp=new Date().toLocaleString('cs-CZ');let html='<h1>Moje krypto · soukromý report</h1><p>Vytvořeno '+safe(stamp)+'</p><h2>Portfolio</h2><table><thead><tr><th>Aktivum</th><th>Množství</th><th>Tržní hodnota</th><th>Nákupní náklady</th></tr></thead><tbody>';
  html+=p.rows.map(r=>'<tr><td>'+r.sym+'</td><td>'+fmt(r.amount,10)+'</td><td>'+money(r.value)+'</td><td>'+money(r.cost)+'</td></tr>').join('')+'</tbody></table><p>Hodnota portfolia '+money(p.total)+'. '+safe($('online-status').textContent)+'</p><h2>Transakce</h2><table><thead><tr><th>Datum</th><th>Typ</th><th>Aktivum</th><th>Množství</th><th>Kč při převodu</th><th>Poznámka</th></tr></thead><tbody>';
  html+=ordered().map(t=>'<tr><td>'+safe(t.date)+'</td><td>'+safe(types[t.type])+'</td><td>'+t.symbol+'</td><td>'+fmt(t.quantity,10)+'</td><td>'+money(t.czk)+'</td><td>'+safe(t.note)+'</td></tr>').join('')+'</tbody></table><p>Hodnoty převodů nejsou pořizovací náklady. Neznámé náklady zůstávají nevyplněné. Report není daňovým výpočtem.</p>';
  if($('goal-results').textContent)html+='<h2>Cesta k cíli · model</h2>'+$('goal-results').innerHTML+'<p>'+safe($('goal-foot').textContent)+'</p>';
  $('print-report').innerHTML=html;window.print();
 }

 function setupAppearance(){
  const presets={ocean:{bg:'#0d2450',text:'#eaf0ff',accent:'#ffbd63'},emerald:{bg:'#0c302d',text:'#e9faf3',accent:'#75dfbd'},amethyst:{bg:'#2b1b4b',text:'#f5edff',accent:'#d5adff'},pearl:{bg:'#eef3fa',text:'#163253',accent:'#975406'}};
  const mix=(a,b,t)=>'#'+[1,3,5].map(i=>Math.round(parseInt(a.slice(i,i+2),16)*(1-t)+parseInt(b.slice(i,i+2),16)*t).toString(16).padStart(2,'0')).join('');
  const lum=hex=>{const p=[1,3,5].map(i=>{const c=parseInt(hex.slice(i,i+2),16)/255;return c<=.04045?c/12.92:Math.pow((c+.055)/1.055,2.4)});return .2126*p[0]+.7152*p[1]+.0722*p[2]};
  function apply(name,custom){const p=custom||presets[name]||presets.ocean;if(!/^#[0-9a-f]{6}$/i.test(p.bg)||!/^#[0-9a-f]{6}$/i.test(p.text))return;const light=lum(p.bg)>.45,accent=p.accent||(light?'#975406':'#ffbd63'),styles={'--bg':p.bg,'--sidebar':mix(p.bg,light?'#ffffff':'#000000',.16),'--panel':mix(p.bg,'#ffffff',light?.8:.08),'--surface':mix(p.bg,'#ffffff',light?.5:.13),'--line':mix(p.bg,p.text,.24),'--text':p.text,'--muted':mix(p.text,p.bg,.27),'--gold':accent,'--gold2':accent};for(const [key,value] of Object.entries(styles))document.documentElement.style.setProperty(key,value);document.documentElement.dataset.theme=light?'light':'dark';$('theme-preset').value=name;$('theme-background').value=p.bg;$('theme-text').value=p.text;const contrast=(Math.max(lum(p.bg),lum(p.text))+.05)/(Math.min(lum(p.bg),lum(p.text))+.05);$('theme-contrast').textContent=contrast<4.5?'Pozadí a text mají nízký kontrast. Pro lepší čitelnost zvol světlejší text nebo tmavší pozadí.':'Kontrast textu a pozadí: '+contrast.toFixed(1)+' : 1';try{localStorage.setItem('moje-krypto-appearance',JSON.stringify({name,...p}))}catch{}}
  $('theme-preset').addEventListener('change',()=>{const name=$('theme-preset').value;if(name!=='custom')apply(name)});document.querySelectorAll('[data-theme]').forEach(b=>b.addEventListener('click',()=>apply(b.dataset.theme)));['theme-background','theme-text'].forEach(id=>$(id).addEventListener('input',()=>apply('custom',{bg:$('theme-background').value,text:$('theme-text').value})));$('theme-reset').addEventListener('click',()=>apply('ocean'));let saved;try{saved=JSON.parse(localStorage.getItem('moje-krypto-appearance'))}catch{}apply(saved?.name||'ocean',saved);
 }

 init();
})();
