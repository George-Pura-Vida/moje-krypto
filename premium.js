"use strict";
(function(){
 const $=id=>document.getElementById(id);
 const money=x=>new Intl.NumberFormat("cs-CZ",{style:"currency",currency:"CZK",maximumFractionDigits:0}).format(Number.isFinite(Number(x))?Number(x):0);
 const fmt=(x,d=4)=>new Intl.NumberFormat("cs-CZ",{maximumFractionDigits:d}).format(Number.isFinite(Number(x))?Number(x):0);
 const escape=x=>String(x??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
 const parseNumber=x=>{let s=String(x??"").replace(/[\u00a0\s]/g,"").replace(/[^0-9,.+-]/g,"");if(s.includes(",")&&s.includes("."))s=s.lastIndexOf(",")>s.lastIndexOf(".")?s.replace(/\./g,"").replace(",","."):s.replace(/,/g,"");else s=s.replace(",",".");return Number(s)||0;};
 const clamp=(n,min,max)=>Math.min(max,Math.max(min,Number.isFinite(n)?n:min));
 const defaults={homePrice:8000000,years:10,homeInflation:3,btcGrowth:10,monthly:5000,manualPrice:0,weights:[50,10,15,15,10]};
 const fieldIds={homePrice:"pro-home-price",years:"pro-years",homeInflation:"pro-home-inflation",btcGrowth:"pro-btc-growth",monthly:"pro-monthly",manualPrice:"pro-manual-price"};
 const limits={homePrice:[100000,100000000000],years:[1,40],homeInflation:[-20,30],btcGrowth:[-80,100],monthly:[0,10000000],manualPrice:[0,100000000000]};
 const assets=["Bitcoin","Ethereum","Zlato","Akcie","Hotovost"];
 const colors=["#ffbd63","#8f80e5","#f1a84a","#5fc9bb","#829bac"];
 let goals={...defaults,weights:defaults.weights.slice()}, portfolio={assets:[],transactions:[]}, livePrice=0, saveTimer=null, initialized=false, importedRows=[],importHeaders=[],importFileName="";
 let csrf=document.querySelector('meta[name="csrf-token"]')?.content||"";
 function toast(m){let box=$("toast");if(box){box.textContent=m;box.classList.add("visible");setTimeout(()=>box.classList.remove("visible"),5500);}else alert(m);}
 async function api(path,options){const res=await fetch(path,Object.assign({credentials:"same-origin",cache:"no-store"},options||{}));const j=await res.json();if(!res.ok||j.error)throw Error(j.error||"Server je nedostupný.");return j;}
 function changeMenu(){document.querySelectorAll('[data-page="property"],[data-page="diversify"],[data-page="exports"]').forEach(el=>el.addEventListener("click",()=>{let p=el.dataset.page;$("page-title").textContent={property:"Bitcoin a moje nemovitost",diversify:"Diverzifikace portfolia",exports:"Import a profesionální reporty"}[p];render();}));}
 function applyGoals(){Object.entries(fieldIds).forEach(([key,id])=>{const el=$(id);if(el)el.value=goals[key];});renderAllocation();}
 function collectGoals(){Object.entries(fieldIds).forEach(([key,id])=>{let raw=Number($(id)?.value),range=limits[key];goals[key]=clamp(raw,range[0],range[1]);});}
 function scheduleSave(){if(!initialized)return;clearTimeout(saveTimer);saveTimer=setTimeout(saveGoals,650);}
 async function saveGoals(){try{await api("?api=goals",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":csrf},body:JSON.stringify(goals)});}catch(e){toast("⚠️ Nastavení se nepodařilo uložit: "+e.message);}}
 function btcHeld(){return Math.max(0,Number(portfolio.assets?.find(a=>a.symbol==="BTC")?.quantity)||0);}
 function currentPrice(){return goals.manualPrice>0?goals.manualPrice:livePrice;}
 function simulate(){const p=currentPrice(),y=clamp(goals.years,1,40),base=clamp(goals.homePrice,100000,1e11),homeFuture=base*Math.pow(1+goals.homeInflation/100,y);
  if(p<=0)return {valid:false,homeFuture};
  const factor=Math.pow(1+goals.btcGrowth/100,1/12),months=y*12;let price=p,qty=btcHeld();let invested=0;
  for(let i=0;i<months;i++){price*=factor;const buy=goals.monthly;qty+=buy/price;invested+=buy;}
  const btcFuture=qty*price,covered=100*btcFuture/homeFuture;
  return {valid:true,p,qty,price,btcFuture,homeFuture,covered,needed:homeFuture/price,gap:Math.max(0,homeFuture-btcFuture),invested};
 }
 function renderHousing(){const r=simulate();$("pro-result-home").textContent=money(r.homeFuture);$("pro-btc-value").textContent=r.valid?money(r.btcFuture):"—";$("pro-covered").textContent=r.valid?fmt(r.covered,1)+" %":"—";$("pro-needed").textContent=r.valid?fmt(r.needed,6)+" BTC":"—";$("pro-gap").textContent=r.valid?money(r.gap):"—";$("pro-progress-fill").style.width=(r.valid?clamp(r.covered,0,100):0)+"%";
  $("pro-price-source").textContent=goals.manualPrice>0?"🧮 Použitá ručně nastavená cena 1 BTC: "+money(goals.manualPrice)+" · nejedná se o aktuální tržní kurz":livePrice>0?"🟢 Výchozí kurz z CryptoCompare / CoinDesk Data: "+money(livePrice)+" za 1 BTC · může být zpožděný":"⚠️ Tržní kurz není dostupný. Pro výpočet zadejte vlastní modelovou cenu BTC.";
  const max=Math.max(r.homeFuture,r.valid?r.btcFuture:0,1);
  const cards=[["Budoucí cena nemovitosti",r.homeFuture],["Modelová hodnota Vašeho BTC",r.valid?r.btcFuture:0]];
  $("pro-housing-chart").innerHTML=cards.map((v,i)=>'<div class="pro-bar-item"><small>'+v[0]+'</small><strong>'+(i===1&&!r.valid?"—":money(v[1]))+'</strong><div class="pro-bar-track"><i style="width:'+clamp(v[1]/max*100,0,100)+'%"></i></div></div>').join("");
  document.querySelectorAll("[data-pro-rate]").forEach(btn=>btn.classList.toggle("pro-selected",Number(btn.dataset.proRate)===goals.btcGrowth));
 }
 function renderAllocation(){let total=goals.weights.reduce((a,b)=>a+b,0),norm=total?goals.weights.map(x=>100*x/total):[0,0,0,0,0],acc=0;
  const stops=norm.map((v,i)=>{const start=acc;acc+=v;return colors[i]+" "+start+"% "+acc+"%";}).join(",");
  $("pro-pie").style.background=total>0?"conic-gradient("+stops+")":"#43515d";
  $("pro-pie-total").textContent=fmt(total,0)+" %";$("pro-alloc-total").textContent="Součet cílových podílů: "+fmt(total,0)+" %"+(Math.abs(total-100)>.001?" · upravte na 100 %":" ✓");
  $("pro-alloc-total").style.color=Math.abs(total-100)>.001?"#ffaeaa":"#77dfba";
  $("pro-alloc-legend").innerHTML=assets.map((n,i)=>'<span><i style="background:'+colors[i]+'"></i>'+n+' '+fmt(goals.weights[i],0)+' %</span>').join("");
  let maximum=Math.max(...goals.weights),idx=goals.weights.indexOf(maximum);
  $("pro-risk-text").textContent=Math.abs(total-100)>.001?"Pro úplný cílový model musí součet vah odpovídat 100 %.":maximum>=65?"Plán je výrazně koncentrovaný do aktiva "+assets[idx]+" ("+fmt(maximum,0)+" %). Jednotlivá aktiva mohou mít značné propady.":"Rozložení mezi různé druhy aktiv může snížit koncentraci, ale neeliminuje kolísání ani riziko ztráty. Riziko závisí na chování a korelacích aktiv.";
 }
 function allocationInputs(){const host=$("pro-alloc-inputs");host.innerHTML=assets.map((n,i)=>'<div class="pro-alloc-row"><span class="pro-swatch" style="background:'+colors[i]+'"></span><label for="pro-weight-'+i+'">'+n+'</label><input id="pro-weight-'+i+'" type="number" min="0" max="100" step="1" value="'+goals.weights[i]+'" aria-label="Podíl '+n+' v procentech"></div>').join("");
  assets.forEach((_,i)=>$("pro-weight-"+i).addEventListener("input",e=>{goals.weights[i]=clamp(Number(e.target.value),0,100);renderAllocation();scheduleSave();}));renderAllocation();}
 function render(){if($("pro-result-home"))renderHousing();if($("pro-pie"))renderAllocation();}
 function setup(){changeMenu();
  Object.entries(fieldIds).forEach(([key,id])=>$(id).addEventListener("input",()=>{const v=Number($(id).value);goals[key]=clamp(v,limits[key][0],limits[key][1]);render();scheduleSave();}));
  document.querySelectorAll("[data-pro-rate]").forEach(b=>b.addEventListener("click",()=>{goals.btcGrowth=Number(b.dataset.proRate);$("pro-btc-growth").value=goals.btcGrowth;render();scheduleSave();}));
  const preset=(id,weights)=>$(id).addEventListener("click",()=>{goals.weights=weights.slice();allocationInputs();scheduleSave();});
  preset("pro-balanced",[20,20,20,20,20]);preset("pro-conservative",[10,5,30,30,25]);preset("pro-aggressive",[60,15,10,10,5]);
  $("pro-open-settings").onclick=()=>document.querySelector('[data-page="settings"]')?.click();
  $("pro-export-xlsx").onclick=exportExcel;$("pro-export-pdf").onclick=printReport;
  $("pro-import-file").addEventListener("change",selectImport);
  $("pro-import-confirm").addEventListener("click",confirmImport);
  $("pro-import-cancel").onclick=()=>{$("pro-mapping").hidden=true;importedRows=[];};
 }
 async function initialize(){if(!$("page-property"))return;setup();
  const outcomes=await Promise.allSettled([api("?api=goals"),api("?api=data"),api("?api=prices")]);
  if(outcomes[0].status==="fulfilled"){const s=outcomes[0].value.data||{};goals={...defaults,...s,weights:Array.isArray(s.weights)&&s.weights.length===5?s.weights.slice():defaults.weights.slice()};}
  else toast("⚠️ Plán bydlení se nepodařilo načíst.");
  if(outcomes[1].status==="fulfilled"){const d=outcomes[1].value.data||{};portfolio={assets:Array.isArray(d.assets)?d.assets:[],transactions:Array.isArray(d.transactions)?d.transactions:[]};}
  else toast("⚠️ Portfolio se nepodařilo načíst.");
  if(outcomes[2].status==="fulfilled")livePrice=Number(outcomes[2].value.data?.BTC?.price)||0;
  applyGoals();allocationInputs();render();initialized=true;
 }
 const download=(blob,name)=>{let url=URL.createObjectURL(blob),a=document.createElement("a");a.href=url;a.download=name;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1200);};
 const dateToday=()=>new Date().toISOString().slice(0,10);
 function reportRows(){let r=simulate(),qty=btcHeld(),bPrice=currentPrice(),lines=portfolio.transactions.slice(-35).reverse();
  return {r,qty,bPrice,lines};
 }
 function printReport(){let {r,qty,bPrice,lines}=reportRows(),root=$("pro-print-report");if(!root){root=document.createElement("section");root.id="pro-print-report";root.className="pro-print-report";document.body.appendChild(root);}
  let h="<h1>₿ MOJE KRYPTO — investiční report</h1><p>Vytvořeno: "+escape(new Intl.DateTimeFormat("cs-CZ").format(new Date()))+" · Autor aplikace: Jiří Janoušek</p>";
  const cards=[["Držený Bitcoin",fmt(qty,8)+" BTC"],["Modelová výchozí cena 1 BTC",bPrice?money(bPrice):"Nedostupná"],["Budoucí cena nemovitosti",money(r.homeFuture)],["Modelová budoucí hodnota BTC",r.valid?money(r.btcFuture):"Nedostupná"]];
  h+='<div class="report-grid">'+cards.map(x=>'<div>'+escape(x[0])+'<b>'+escape(x[1])+'</b></div>').join("")+'</div>';
  h+="<h2>🏡 Model vlastní nemovitosti</h2><p>Horizont "+fmt(goals.years,0)+" let, růst ceny nemovitosti "+fmt(goals.homeInflation,1)+" % p. a., změna ceny BTC "+fmt(goals.btcGrowth,1)+" % p. a., měsíční nákup "+money(goals.monthly)+".</p>";
  h+='<p><b>Pokrytí cíle: '+(r.valid?fmt(r.covered,1)+" %":"nelze určit bez kurzu BTC")+'</b></p>';
  h+="<h2>🧩 Cílové rozložení portfolia</h2><p>"+assets.map((n,i)=>escape(n)+": "+fmt(goals.weights[i],0)+" %").join(" · ")+"</p>";
  h+='<h2>📋 Poslední transakce</h2><table><thead><tr><th>Datum</th><th>Aktivum</th><th>Typ</th><th>Množství</th><th>Částka v Kč</th></tr></thead><tbody>'+lines.map(t=>'<tr><td>'+escape(t.date)+'</td><td>'+escape(t.symbol)+'</td><td>'+escape(t.type)+'</td><td>'+fmt(t.quantity,8)+'</td><td>'+money(t.czk)+'</td></tr>').join("")+'</tbody></table>';
  h+='<p class="report-note">Report uvádí nejvýše posledních 35 transakcí. Kompletní evidenci obsahuje Excel nebo JSON export. Převody do peněženky nejsou automaticky nové investice ani realizovaný zisk. Model budoucí hodnoty používá konstantní roční změnu a nereflektuje všechny daně, poplatky a rizika. Výnosy nejsou zaručené.</p>';
  root.innerHTML=h;document.body.classList.add("pro-printing");window.print();
 }
 window.addEventListener("afterprint",()=>document.body.classList.remove("pro-printing"));
 function crc32(bytes){let c=~0;for(const v of bytes){c^=v;for(let i=0;i<8;i++)c=(c>>>1)^((c&1)?0xedb88320:0);}return (~c)>>>0;}
 const utf8=s=>new TextEncoder().encode(s);
 function header(length,fill){let a=new Uint8Array(length);fill(new DataView(a.buffer));return a;}
 function zip(files){let local=[],central=[],offset=0,centralLen=0;
  for(const f of files){const name=utf8(f.name),data=typeof f.data==="string"?utf8(f.data):f.data,c=crc32(data);
   const l=header(30,v=>{v.setUint32(0,0x04034b50,true);v.setUint16(4,20,true);v.setUint16(6,0x0800,true);v.setUint32(14,c,true);v.setUint32(18,data.length,true);v.setUint32(22,data.length,true);v.setUint16(26,name.length,true);});
   local.push(l,name,data);
   const d=header(46,v=>{v.setUint32(0,0x02014b50,true);v.setUint16(4,20,true);v.setUint16(6,20,true);v.setUint16(8,0x0800,true);v.setUint32(16,c,true);v.setUint32(20,data.length,true);v.setUint32(24,data.length,true);v.setUint16(28,name.length,true);v.setUint32(42,offset,true);});
   central.push(d,name);offset+=l.length+name.length+data.length;centralLen+=d.length+name.length;}
  const end=header(22,v=>{v.setUint32(0,0x06054b50,true);v.setUint16(8,files.length,true);v.setUint16(10,files.length,true);v.setUint32(12,centralLen,true);v.setUint32(16,offset,true);});
  return new Blob([...local,...central,end],{type:"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"});
 }
 function xml(x){return String(x??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&apos;");}
 function excelColumn(i){let s="";for(i++;i;i=Math.floor((i-1)/26))s=String.fromCharCode(65+(i-1)%26)+s;return s;}
 function sheet(rows){return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'+rows.map((row,i)=>'<row r="'+(i+1)+'">'+row.map((value,j)=>{const c=excelColumn(j)+(i+1);return typeof value==="number"&&Number.isFinite(value)?'<c r="'+c+'"><v>'+value+'</v></c>':'<c r="'+c+'" t="inlineStr"><is><t>'+xml(value)+'</t></is></c>';}).join("")+"</row>").join("")+"</sheetData></worksheet>";}
 function makeWorkbook(tabs){let types=['<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'];
  let files=[{name:"_rels/.rels",data:'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'}];
  let wb='<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
  let rel='<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
  tabs.forEach((t,i)=>{const n=i+1;types.push('<Override PartName="/xl/worksheets/sheet'+n+'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>');files.push({name:"xl/worksheets/sheet"+n+".xml",data:sheet(t.rows)});wb+='<sheet name="'+xml(t.name)+'" sheetId="'+n+'" r:id="rId'+n+'"/>';rel+='<Relationship Id="rId'+n+'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'+n+'.xml"/>';});
  files.push({name:"[Content_Types].xml",data:types.join("")+"</Types>"},{name:"xl/workbook.xml",data:wb+"</sheets></workbook>"},{name:"xl/_rels/workbook.xml.rels",data:rel+"</Relationships>"});return zip(files);
 }
 function exportExcel(){const r=simulate(),btc=btcHeld(),price=currentPrice();
  const tabs=[
   {name:"Transakce",rows:[["Datum","Aktivum","Typ","Množství","Částka CZK","Poznámka"],...portfolio.transactions.map(t=>[t.date,t.symbol,t.type,Number(t.quantity)||0,Number(t.czk)||0,t.note||""])]},
   {name:"Portfolio",rows:[["Aktivum","Množství","Nákupní náklady CZK"],...portfolio.assets.map(a=>[a.symbol,Number(a.quantity)||0,a.costBasis==null?"Neuvedeno":Number(a.costBasis)])]},
   {name:"Bydlení",rows:[["Ukazatel","Hodnota"],["Výchozí cena BTC CZK",price],["Držený BTC",btc],["Horizont let",goals.years],["BTC scénář procent ročně",goals.btcGrowth],["Nové vklady CZK měsíčně",goals.monthly],["Cena domu dnes CZK",goals.homePrice],["Růst ceny domu procent ročně",goals.homeInflation],["Modelová cena domu CZK",r.homeFuture],["Modelová hodnota BTC CZK",r.valid?r.btcFuture:"Bez ceny BTC"],["Pokrytí cíle procent",r.valid?r.covered:"Bez ceny BTC"]]},
   {name:"Diverzifikace",rows:[["Aktivum","Cílový podíl procent"],...assets.map((n,i)=>[n,goals.weights[i])]}]
  ;download(makeWorkbook(tabs),"moje-krypto-report-"+dateToday()+".xlsx");toast("✅ Excel report je připraven ke stažení.");
 }
 async function unzip(arrayBuffer){const view=new DataView(arrayBuffer),len=view.byteLength;let eocd=-1;for(let p=len-22;p>=Math.max(0,len-65557);p--)if(view.getUint32(p,true)===0x06054b50){eocd=p;break;}if(eocd<0)throw Error("Soubor není platný XLSX.");
  const count=view.getUint16(eocd+10,true);if(count>1000)throw Error("Excel má příliš mnoho součástí.");
  let pos=view.getUint32(eocd+16,true),files=new Map();
  for(let i=0;i<count;i++){if(pos+46>len||view.getUint32(pos,true)!==0x02014b50)throw Error("Poškozený XLSX soubor.");
   const method=view.getUint16(pos+10,true),packed=view.getUint32(pos+20,true),rawSize=view.getUint32(pos+24,true),nameLen=view.getUint16(pos+28,true),extra=view.getUint16(pos+30,true),comment=view.getUint16(pos+32,true),loc=view.getUint32(pos+42,true);
   if(rawSize>24*1024*1024)throw Error("Excel obsahuje příliš velký list.");const name=new TextDecoder().decode(new Uint8Array(arrayBuffer,pos+46,nameLen));
   if(/^(xl\/sharedStrings\.xml|xl\/worksheets\/[^/]+\.xml)$/.test(name)){const dataOffset=loc+30+view.getUint16(loc+26,true)+view.getUint16(loc+28,true);if(dataOffset+packed>len)throw Error("Neúplný XLSX soubor.");
    let raw=new Uint8Array(arrayBuffer,dataOffset,packed);
    if(method===8){if(typeof DecompressionStream==="undefined")throw Error("Prohlížeč nepodporuje rozbalení tohoto XLSX. Použijte CSV.");raw=new Uint8Array(await new Response(new Blob([raw]).stream().pipeThrough(new DecompressionStream("deflate-raw"))).arrayBuffer());}
    else if(method!==0)throw Error("Nepodporovaná komprese XLSX.");
    files.set(name,new TextDecoder().decode(raw));
   }
   pos+=46+nameLen+extra+comment;
  }
  return files;
 }
 function parseSheet(xmlstr,strings){const doc=new DOMParser().parseFromString(xmlstr,"text/xml");if(doc.querySelector("parsererror"))throw Error("Chybná data XLSX.");let out=[];
  const rows=Array.from(doc.getElementsByTagName("row")).slice(0,5002);
  for(const row of rows){let cells=[];for(const c of row.getElementsByTagName("c")){const letters=(c.getAttribute("r")||"A1").match(/^[A-Z]+/)?.[0]||"A";let ix=0;for(const ch of letters)ix=ix*26+ch.charCodeAt(0)-64;ix--;if(ix>150)continue;
   const type=c.getAttribute("t"),val=c.getElementsByTagName("v")[0]?.textContent||"";cells[ix]=type==="s"?strings[Number(val)]||"":type==="inlineStr"?c.getElementsByTagName("is")[0]?.textContent||"":val;}
   out.push(Array.from({length:cells.length},(_,i)=>cells[i]??""));
  }return out;
 }
 async function parseXlsx(file){const files=await unzip(await file.arrayBuffer());let strings=[],str=files.get("xl/sharedStrings.xml");if(str){let doc=new DOMParser().parseFromString(str,"text/xml");strings=Array.from(doc.getElementsByTagName("si")).map(x=>x.textContent||"");}
  const sheetName=[...files.keys()].filter(k=>k.startsWith("xl/worksheets/")&&k.endsWith(".xml")).sort()[0];if(!sheetName)throw Error("Soubor neobsahuje čitelný list.");return parseSheet(files.get(sheetName),strings);
 }
 function parseCsv(text){text=text.replace(/^\uFEFF/,"");let first=text.split(/\r?\n/,1)[0],delim=[";",",","\t"].sort((a,b)=>first.split(b).length-first.split(a).length)[0],rows=[],row=[],val="",quote=false;
  for(let i=0;i<text.length;i++){let c=text[i];if(c==='"'){if(quote&&text[i+1]==='"'){val+='"';i++;}else quote=!quote;}else if(!quote&&c===delim){row.push(val);val="";}else if(!quote&&(c==="\n"||c==="\r")){if(c==="\r"&&text[i+1]==="\n")i++;row.push(val);if(row.some(Boolean))rows.push(row);row=[];val="";}else val+=c;}
  row.push(val);if(row.some(Boolean))rows.push(row);return rows.slice(0,5002);
 }
 const norm=s=>String(s??"").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g,"").replace(/[^a-z0-9]/g,"");
 const descriptors=[
  {key:"date",name:"Datum",names:["datum","date","created","time"]},
  {key:"symbol",name:"Kryptoměna",names:["mena","aktivum","coin","symbol","asset"]},
  {key:"type",name:"Typ transakce",names:["typ","type","operace","smer","action"]},
  {key:"quantity",name:"Množství kryptoměny",names:["mnozstvi","quantity","btc","amount","objem"]},
  {key:"czk",name:"Částka Kč",names:["castka","czk","kc","celkem","hodnota","total","fiat"]},
  {key:"note",name:"Poznámka",names:["poznamka","note","popis","description"]}];
 function showMapping(){const head=importHeaders;$("pro-mapping-help").textContent="Soubor: "+importFileName+" · "+importedRows.length+" řádků. Vyberte především datum a množství; hodnotu v Kč doporučujeme doplnit pro správnou evidenci. Výchozí typ při chybějícím sloupci je Přijato (ne nákup).";
  $("pro-mapping-controls").innerHTML=descriptors.map(d=>{let guess=head.findIndex(h=>d.names.some(n=>norm(h).includes(norm(n))));return '<label>'+d.name+'<select id="pro-map-'+d.key+'"><option value="-1">— Vybrat ručně —</option>'+head.map((h,i)=>'<option value="'+i+'" '+(i===guess?"selected":"")+'>'+escape(h||("Sloupec "+(i+1)))+"</option>").join("")+"</select></label>";}).join("");
  $("pro-import-preview").innerHTML="<table><thead><tr>"+head.slice(0,10).map(v=>"<th>"+escape(v)+"</th>").join("")+"</tr></thead><tbody>"+importedRows.slice(0,3).map(r=>"<tr>"+r.slice(0,10).map(v=>"<td>"+escape(v)+"</td>").join("")+"</tr>").join("")+"</tbody></table>";
  $("pro-mapping").hidden=false;$("pro-mapping").scrollIntoView({behavior:"smooth",block:"start"});
 }
 async function selectImport(e){const file=e.target.files?.[0];e.target.value="";if(!file)return;try{if(file.size>8*1024*1024)throw Error("Maximální velikost je 8 MB.");const data=file.name.toLowerCase().endsWith(".xlsx")?await parseXlsx(file):file.name.toLowerCase().endsWith(".csv")?parseCsv(await file.text()):null;if(!data)throw Error("Podporovány jsou .xlsx a .csv, ne staré .xls.");
  if(data.length<2)throw Error("Žádné datové řádky.");importHeaders=data[0].map((h,i)=>String(h||"Sloupec "+(i+1)));importedRows=data.slice(1).filter(r=>r.some(x=>String(x).trim()!==""));importFileName=file.name;showMapping();}
  catch(err){toast("⚠️ Import se nepodařil: "+err.message);}
 }
 function parseDate(input){const s=String(input??"").trim();if(/^\d{4}-\d{2}-\d{2}/.test(s))return s.slice(0,10);const m=s.match(/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})/);if(m)return m[3]+"-"+m[2].padStart(2,"0")+"-"+m[1].padStart(2,"0");if(/^\d{5}(?:\.0+)?$/.test(s))return new Date(Date.UTC(1899,11,30)+Math.round(Number(s))*86400000).toISOString().slice(0,10);return "";}
 function txType(s){const n=norm(s);if(/prodej|sell/.test(n))return "sell";if(/odes|vyber|sent|send|withdraw/.test(n))return "sent";if(/nakup|buy|purchase/.test(n))return "buy";return "received";}
 async function confirmImport(){if(importedRows.length===0)return;let map={};descriptors.forEach(d=>map[d.key]=Number($("pro-map-"+d.key).value));if(map.date<0||map.quantity<0){toast("⚠️ Přiřaďte datum a množství kryptoměny.");return;}
  const key=t=>[t.date,t.symbol,t.type,Number(t.quantity).toFixed(8),Number(t.czk).toFixed(2)].join("|"),known=new Set(portfolio.transactions.map(key)),newRows=[];let skipped=0;
  for(const row of importedRows){const get=k=>map[k]<0?"":row[map[k]],date=parseDate(get("date")),sym=norm(get("symbol"));const symbol=/eth|ethereum/.test(sym)?"ETH":/btc|bitcoin/.test(sym)||sym===""?"BTC":null,quantity=parseNumber(get("quantity")),czk=Math.abs(parseNumber(get("czk")));
   if(!date||!symbol||quantity<=0||quantity>1e9||czk>1e15){skipped++;continue;}
   const tx={id:crypto.randomUUID(),date,symbol,type:txType(get("type")),quantity,czk,note:String(get("note")||"").slice(0,160)};
   if(known.has(key(tx)))continue;known.add(key(tx));newRows.push(tx);
  }
  if(!newRows.length){toast("⚠️ Žádné nové platné transakce. Přeskočeno "+skipped+" řádků.");return;}
  if(portfolio.transactions.length+newRows.length>10000){toast("⚠️ Limit je 10 000 transakcí.");return;}
  if(!confirm("Importovat "+newRows.length+" záznamů? Stav držených kryptoměn to automaticky nezmění; nastavte ho v portfoliu zvlášť."))return;
  try{const combined={assets:portfolio.assets,transactions:portfolio.transactions.concat(newRows)};
   await api("?api=data",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":csrf},body:JSON.stringify(combined)});
   toast("✅ Importováno "+newRows.length+" řádků. Znovu načítám přehled.");location.reload();}
  catch(err){toast("⚠️ Import se nepodařilo uložit: "+err.message);}
 }
 initialize();
})();
