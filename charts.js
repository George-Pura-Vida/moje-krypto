"use strict";
(function(){
 const nf=new Intl.NumberFormat('cs-CZ',{maximumFractionDigits:2});
 const f=(n,d=2)=>Number.isFinite(n)?new Intl.NumberFormat('cs-CZ',{maximumFractionDigits:d}).format(n):'—';
 const money=n=>Number.isFinite(n)?f(n,2)+' Kč':'—';
 const pct=n=>Number.isFinite(n)?(n>=0?'+':'')+f(n,2)+' %':'—';
 const short=n=>Math.abs(n)>=1e9?f(n/1e9,2)+' mld.':Math.abs(n)>=1e6?f(n/1e6,2)+' mil.':Math.abs(n)>=1000?f(n/1000,1)+' tis.':f(n,2);
 const date=t=>new Date(t*1000).toLocaleDateString('cs-CZ',{day:'numeric',month:'short',year:'numeric'});
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const clamp=(v,a,b)=>Math.min(b,Math.max(a,v));
 function empty(id,message){const box=document.getElementById(id);if(box)box.innerHTML='<div class="empty">'+esc(message)+'</div>';}
 function draw(id,series,opt={}){
  const box=document.getElementById(id);if(!box)return;
  const lines=series.filter(s=>Array.isArray(s.points)&&s.points.length>=2);
  if(!lines.length){empty(id,'Pro toto období nejsou dostupná data.');return;}
  const W=Math.max(340,Math.min(1100,box.clientWidth||900)),H=opt.tall?360:250,L=76,R=W-18,T=24,B=H-43;
  const values=lines.flatMap(s=>s.points.map(p=>Number(p[1]))).filter(Number.isFinite);
  if(!values.length){empty(id,'Nejsou dostupné hodnoty.');return;}
  const minX=Math.min(...lines.map(l=>l.points[0][0]));
  const maxX=Math.max(...lines.map(l=>l.points[l.points.length-1][0]));
  const tickDate=t=>maxX-minX<3*86400?new Date(t*1000).toLocaleTimeString('cs-CZ',{hour:'2-digit',minute:'2-digit'}):date(t);
  const valueLabel=v=>opt.percent?f(v,1)+' %':opt.unit?f(v,8)+' '+opt.unit:short(v)+' Kč';
  const mode=opt.type||document.querySelector('[data-chart-mode]')?.value||'line';
  let minY=Math.min(...values),maxY=Math.max(...values);
  const margin=(maxY-minY)*0.1||Math.max(1,Math.abs(maxY)*0.1);minY-=margin;maxY+=margin;
  if(opt.zero||mode==='bar')minY=Math.min(0,minY);
  const x=t=>L+(t-minX)/(maxX-minX||1)*(R-L);
  const y=v=>B-(v-minY)/(maxY-minY||1)*(B-T);
  let grid='',ticks='';
  for(let i=0;i<5;i++){
   const yy=T+(B-T)*i/4, v=maxY-(maxY-minY)*i/4;
   grid+='<line class="grid-line" x1="'+L+'" y1="'+yy+'" x2="'+R+'" y2="'+yy+'"/><text class="axis-label" x="'+(L-8)+'" y="'+(yy+4)+'" text-anchor="end">'+esc(opt.unit?f(v,4):opt.percent?f(v,0)+' %':short(v))+'</text>';
   const xx=L+(R-L)*i/4, tt=minX+(maxX-minX)*i/4;
   if(W>600||i%2===0)ticks+='<text class="axis-label" x="'+xx+'" y="'+(B+26)+'" text-anchor="'+(i===0?'start':i===4?'end':'middle')+'">'+esc(tickDate(tt))+'</text>';
  }
  let paths='';
  lines.forEach((s,i)=>{
   const arr=s.points.length>(mode==='bar'?48:1100)?Array.from({length:mode==='bar'?48:1100},(_,j)=>s.points[Math.floor(j*(s.points.length-1)/(mode==='bar'?47:1099))]):s.points;
   const d=arr.map((p,k)=>(k?'L':'M')+x(p[0]).toFixed(2)+','+y(p[1]).toFixed(2)).join(' ');
   if(mode==='area'&&!s.dashed)paths+='<path fill="url(#'+id+'-fill)" d="'+d+' L'+x(arr[arr.length-1][0]).toFixed(2)+','+B+' L'+x(arr[0][0]).toFixed(2)+','+B+' Z"/>';
   if(mode==='bar'&&!s.dashed){const width=Math.max(1,(R-L)/arr.length*.7/lines.length),base=y(Math.max(minY,Math.min(maxY,0)));for(const p of arr){const yy=y(p[1]);paths+='<rect x="'+(x(p[0])+i*width-width*lines.length/2)+'" y="'+Math.min(base,yy)+'" width="'+width+'" height="'+Math.max(1,Math.abs(base-yy))+'" rx="2" fill="'+esc(s.color||'#7eb4f2')+'" opacity=".85"/>';}}
   else paths+='<path class="chart-line" stroke="'+esc(s.color||'#ffbe67')+'" d="'+d+'" '+(s.dashed?'stroke-dasharray="6 5"':'')+'></path>';
   const last=arr[arr.length-1];paths+='<circle cx="'+x(last[0])+'" cy="'+y(last[1])+'" r="3.5" fill="'+esc(s.color||'#ffbe67')+'"/>';
  });
  const tooltipHeight=32+lines.length*19;
  box.innerHTML='<svg viewBox="0 0 '+W+' '+H+'" preserveAspectRatio="none" role="img" aria-label="'+esc(opt.title||'Čárový graf')+'"><defs><linearGradient id="'+id+'-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="'+esc(lines[0].color||'#7eb4f2')+'" stop-opacity=".28"/><stop offset="100%" stop-color="'+esc(lines[0].color||'#7eb4f2')+'" stop-opacity="0"/></linearGradient></defs>'+grid+ticks+paths+'<g class="chart-hover" style="display:none"><line class="hover-marker" x1="0" y1="'+T+'" x2="0" y2="'+B+'"/><rect class="chart-tooltip" x="0" y="0" width="250" height="'+tooltipHeight+'" rx="9"/><text class="hover-label" fill="#f5eddc" font-size="11"></text></g><rect class="chart-hit" x="'+L+'" y="'+T+'" width="'+(R-L)+'" height="'+(B-T)+'" fill="transparent" /></svg>';
  const svg=box.querySelector('svg'),zone=svg.querySelector('.chart-hit'),tooltip=svg.querySelector('.chart-hover');
  function nearest(arr,time){
   let left=0,right=arr.length-1;
   while(left<right){const mid=Math.floor((left+right)/2);if(arr[mid][0]<time)left=mid+1;else right=mid;}
   return arr[Math.min(arr.length-1,Math.max(0,left))];
  }
  zone.addEventListener('pointermove',event=>{
   const bounds=svg.getBoundingClientRect();
   const xx=clamp((event.clientX-bounds.left)/bounds.width*W,L,R);
   const at=minX+(xx-L)/(R-L)*(maxX-minX);
   const xxLabel=clamp(xx,128,W-128);
   tooltip.style.display='block';
   const line=tooltip.querySelector('line');line.setAttribute('x1',xx);line.setAttribute('x2',xx);
   const rect=tooltip.querySelector('rect');rect.setAttribute('x',xxLabel-125);rect.setAttribute('y',T+4);
   const label=tooltip.querySelector('text');label.setAttribute('x',xxLabel-116);label.setAttribute('y',T+5);
   let txt='<tspan x="'+(xxLabel-116)+'" dy="15">'+esc(date(at))+'</tspan>';
   for(const l of lines){const v=nearest(l.points,at)[1];txt+='<tspan x="'+(xxLabel-116)+'" dy="18" fill="'+esc(l.color||'#ffbe67')+'">'+esc(l.name)+': '+esc(valueLabel(v))+'</tspan>';}
   label.innerHTML=txt;
  });
  zone.addEventListener('pointerleave',()=>{tooltip.style.display='none'});
 }

 function annual(id,transactions,mode='grouped'){
  const box=document.getElementById(id);if(!box)return;const years=[...new Set(transactions.map(t=>t.date.slice(0,4)))].sort();if(!years.length){empty(id,'Zatím nejsou importované převody.');return}const now=new Date().getFullYear(),rows=years.map(year=>{const tx=transactions.filter(t=>t.date.startsWith(year)&&['received','buy'].includes(t.type));const btc=tx.filter(t=>t.symbol==='BTC').reduce((n,t)=>n+t.czk,0),eth=tx.filter(t=>t.symbol==='ETH').reduce((n,t)=>n+t.czk,0);return{year,btc,eth,total:btc+eth}}),W=Math.max(340,box.clientWidth||600),H=280,colors=['#ffbd63','#a99cf5','#65d8b6','#6eb9ff'];let svg='';
  if(mode==='pie'){const total=rows.reduce((n,r)=>n+r.total,0);let angle=-Math.PI/2;const cx=W<500?88:125,cy=130,r=W<500?70:95;rows.forEach((row,i)=>{const next=angle+(total?row.total/total:0)*Math.PI*2,x1=cx+r*Math.cos(angle),y1=cy+r*Math.sin(angle),x2=cx+r*Math.cos(next),y2=cy+r*Math.sin(next),color=colors[i%colors.length];svg+='<path d="M'+cx+','+cy+' L'+x1+','+y1+' A'+r+','+r+' 0 '+(next-angle>Math.PI?1:0)+' 1 '+x2+','+y2+' Z" fill="'+color+'" stroke="var(--panel)" stroke-width="2"><title>'+row.year+': '+money(row.total)+'</title></path>';const lx=cx+r+22,ly=70+i*57;svg+='<rect x="'+lx+'" y="'+(ly-9)+'" width="9" height="9" rx="2" fill="'+color+'"/><text class="annual-label" x="'+(lx+16)+'" y="'+ly+'">'+row.year+(Number(row.year)===now?' · probíhá':'')+'</text><text class="annual-value" x="'+lx+'" y="'+(ly+21)+'">'+esc(money(row.total))+'</text>';angle=next;});}
  else{const L=52,R=W-15,top=36,bottom=219,max=Math.max(1,...rows.map(r=>mode==='stacked'?r.total:Math.max(r.btc,r.eth))),slot=(R-L)/rows.length,bar=Math.min(42,slot*.27);for(let i=0;i<4;i++){const yy=top+(bottom-top)*i/3;svg+='<line x1="'+L+'" y1="'+yy+'" x2="'+R+'" y2="'+yy+'" class="grid-line"/><text class="annual-axis" x="'+(L-8)+'" y="'+(yy+4)+'" text-anchor="end">'+esc(short(max*(1-i/3)))+'</text>';}rows.forEach((row,i)=>{const cx=L+slot*(i+.5),h1=row.btc/max*(bottom-top),h2=row.eth/max*(bottom-top),base=bottom;svg+='<g><title>'+row.year+' · BTC '+money(row.btc)+' · ETH '+money(row.eth)+'</title>';if(mode==='stacked'){svg+='<rect x="'+(cx-bar*.7)+'" y="'+(base-h1)+'" width="'+bar*1.4+'" height="'+h1+'" rx="3" fill="url(#annual-gold)"/><rect x="'+(cx-bar*.7)+'" y="'+(base-h1-h2)+'" width="'+bar*1.4+'" height="'+h2+'" rx="3" fill="#a99cf5"/>';}else{svg+='<rect x="'+(cx-bar-3)+'" y="'+(base-h1)+'" width="'+bar+'" height="'+h1+'" rx="4" fill="url(#annual-gold)"/><rect x="'+(cx+3)+'" y="'+(base-h2)+'" width="'+bar+'" height="'+h2+'" rx="4" fill="#a99cf5"/>';}const labelY=Math.max(18,base-(mode==='stacked'?h1+h2:Math.max(h1,h2))-12);svg+='<text class="annual-value" x="'+cx+'" y="'+labelY+'" text-anchor="middle">'+esc(money(row.total))+'</text><text class="annual-label" x="'+cx+'" y="'+(bottom+25)+'" text-anchor="middle">'+row.year+'</text>'+(Number(row.year)===now?'<text class="annual-axis" x="'+cx+'" y="'+(bottom+43)+'" text-anchor="middle">neuzavřený rok</text>':'')+'</g>';});}
  box.innerHTML='<svg viewBox="0 0 '+W+' '+H+'" role="img" aria-label="Příchozí převody po letech v Kč"><defs><linearGradient id="annual-gold" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffda9b"/><stop offset="1" stop-color="#e89b3f"/></linearGradient></defs>'+svg+'</svg>'+(mode==='pie'?'':'<div class="annual-legend"><span><i class="dot btc"></i>BTC</span><span><i class="dot eth"></i>ETH</span></div>');
 }
 window.KryptoCharts={fmt:f,money,pct,date,esc,empty,draw,short,annual};
})();
