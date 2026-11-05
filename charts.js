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
  const W=900,H=opt.tall?360:250,L=69,R=W-20,T=18,B=H-43;
  const values=lines.flatMap(s=>s.points.map(p=>Number(p[1]))).filter(Number.isFinite);
  if(!values.length){empty(id,'Nejsou dostupné hodnoty.');return;}
  const minX=Math.min(...lines.map(l=>l.points[0][0]));
  const maxX=Math.max(...lines.map(l=>l.points[l.points.length-1][0]));
  let minY=Math.min(...values),maxY=Math.max(...values);
  const margin=(maxY-minY)*0.1||Math.max(1,Math.abs(maxY)*0.1);minY-=margin;maxY+=margin;
  if(opt.zero)minY=Math.min(0,minY);
  const x=t=>L+(t-minX)/(maxX-minX||1)*(R-L);
  const y=v=>B-(v-minY)/(maxY-minY||1)*(B-T);
  let grid='',ticks='';
  for(let i=0;i<5;i++){
   const yy=T+(B-T)*i/4, v=maxY-(maxY-minY)*i/4;
   grid+='<line class="grid-line" x1="'+L+'" y1="'+yy+'" x2="'+R+'" y2="'+yy+'"/><text class="axis-label" x="'+(L-8)+'" y="'+(yy+4)+'" text-anchor="end">'+esc(opt.percent?f(v,0)+' %':short(v))+'</text>';
   const xx=L+(R-L)*i/4, tt=minX+(maxX-minX)*i/4;
   ticks+='<text class="axis-label" x="'+xx+'" y="'+(B+26)+'" text-anchor="middle">'+esc(date(tt))+'</text>';
  }
  let paths='';
  lines.forEach((s,i)=>{
   const arr=s.points.length>1100?Array.from({length:1100},(_,j)=>s.points[Math.floor(j*(s.points.length-1)/1099)]):s.points;
   const d=arr.map((p,k)=>(k?'L':'M')+x(p[0]).toFixed(2)+','+y(p[1]).toFixed(2)).join(' ');
   paths+='<path class="chart-line" stroke="'+esc(s.color||'#ffbe67')+'" d="'+d+'" '+(s.dashed?'stroke-dasharray="6 5"':'')+'></path>';
  });
  const tooltipHeight=32+lines.length*19;
  box.innerHTML='<svg viewBox="0 0 '+W+' '+H+'" preserveAspectRatio="none" role="img" aria-label="'+esc(opt.title||'Čárový graf')+'">'+grid+ticks+paths+'<g class="chart-hover" style="display:none"><line class="hover-marker" x1="0" y1="'+T+'" x2="0" y2="'+B+'"/><rect class="chart-tooltip" x="0" y="0" width="206" height="'+tooltipHeight+'" rx="9"/><text class="hover-label" fill="#f5eddc" font-size="12"></text></g><rect class="chart-hit" x="'+L+'" y="'+T+'" width="'+(R-L)+'" height="'+(B-T)+'" fill="transparent" /></svg>';
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
   const xxLabel=clamp(xx,110,R-110);
   tooltip.style.display='';
   const line=tooltip.querySelector('line');line.setAttribute('x1',xx);line.setAttribute('x2',xx);
   const rect=tooltip.querySelector('rect');rect.setAttribute('x',xxLabel-103);rect.setAttribute('y',T+4);
   const label=tooltip.querySelector('text');label.setAttribute('x',xxLabel-95);label.setAttribute('y',T+5);
   let txt='<tspan x="'+(xxLabel-95)+'" dy="15">'+esc(date(at))+'</tspan>';
   for(const l of lines){const v=nearest(l.points,at)[1];txt+='<tspan x="'+(xxLabel-95)+'" dy="18" fill="'+esc(l.color||'#ffbe67')+'">'+esc(l.name)+': '+esc(opt.percent?f(v,1)+' %':short(v))+'</tspan>';}
   label.innerHTML=txt;
  });
  zone.addEventListener('pointerleave',()=>{tooltip.style.display='none'});
 }
 window.KryptoCharts={fmt:f,money,pct,date,esc,empty,draw,short};
})();