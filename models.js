"use strict";
(function(){
 const ts=year=>Date.UTC(year,0,1)/1000;
 const clamp=(v,a,b)=>Math.min(b,Math.max(a,v));
 function FV(initial,monthly,annualPercent,months){
   const r=Math.pow(1+annualPercent/100,1/12)-1;
   if(!Number.isFinite(r))return NaN;
   if(Math.abs(r)<1e-12)return initial+monthly*months;
   return initial*Math.pow(1+r,months)+monthly*(Math.pow(1+r,months)-1)/r;
 }
 function forecast({initial,monthly,years,inflation,rates}){
   years=clamp(Math.round(years),1,50);initial=clamp(initial,0,1e12);monthly=clamp(monthly,0,1e10);
   inflation=clamp(inflation,-99,1000);
   rates=rates.map(r=>clamp(r,-99,1000));
   const origin=new Date().getUTCFullYear();
   const entries=Array.from({length:years+1},(_,y)=>({t:ts(origin+y),values:rates.map(r=>FV(initial,monthly,r,y*12)),invested:initial+monthly*12*y}));
   const final=entries[entries.length-1];const deflator=Math.pow(1+inflation/100,years);
   return {entries,invested:final.invested,final:final.values,real:final.values.map(v=>v/deflator),rates,years,inflation};
 }
 function simulateDCA(prices,{monthly,initial,extra,threshold}){
   if(!prices||prices.length<2)throw Error('Chybí historické ceny pro výpočet.');
   monthly=clamp(monthly,0,1e9);initial=clamp(initial,0,1e9);
   extra=clamp(extra,0,1e9);threshold=clamp(threshold,1,99)/100;
   const first=prices[0][1],last=prices[prices.length-1][1];
   let regularUnits=initial/first,dipUnits=initial/first,regularSpent=initial,dipSpent=initial;
   let lastMonth='',lastDip='',months=0,dips=0,peakQueue=[];
   const chart=[];
   function monthFor(t){const d=new Date(t*1000);return d.getUTCFullYear()+'-'+(d.getUTCMonth()+1)}
   for(let i=0;i<prices.length;i++){
     const [time,price]=prices[i];
     const month=monthFor(time),start=time-30*86400;
     while(peakQueue.length&&peakQueue[0][0]<start)peakQueue.shift();
     peakQueue.push([time,price]);
     const peak=Math.max(...peakQueue.map(p=>p[1]));
     if(month!==lastMonth){
       lastMonth=month;months++;
       regularSpent+=monthly;dipSpent+=monthly;
       regularUnits+=monthly/price;dipUnits+=monthly/price;
     }
     if(extra>0 && lastDip!==month && peak>0 && price<=peak*(1-threshold)){
       lastDip=month;dipUnits+=extra/price;dipSpent+=extra;dips++;
     }
     const row=[time,regularUnits*price,dipUnits*price,regularSpent,dipSpent,regularUnits?regularSpent/regularUnits:null,dipUnits?dipSpent/dipUnits:null,price];
     if(chart.length&&monthFor(chart[chart.length-1][0])===month)chart[chart.length-1]=row;
     else chart.push(row);
   }
   return {
     from:prices[0][0],to:prices[prices.length-1][0],first,last,
     months,dips,regularSpent,dipSpent,regularUnits,dipUnits,
     regularValue:regularUnits*last,dipValue:dipUnits*last,
     regularAverage:regularUnits?regularSpent/regularUnits:null,
     dipAverage:dipUnits?dipSpent/dipUnits:null,chart
   };
 }
 window.KryptoModels={forecast,simulateDCA,FV};
})();