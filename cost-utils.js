"use strict";
(function(root){
  const incoming=t=>t?.type==='received'||t?.type==='buy';
  const outgoing=t=>t?.type==='sent'||t?.type==='sell';
  const valid=n=>typeof n==='number'&&Number.isFinite(n);

  // Částka v CZK z historie převodu / připsání, nikoli ověřený nákupní náklad.
  function impliedUnitPrice(transaction){
    if(!incoming(transaction))return null;
    const quantity=Number(transaction?.quantity),czk=Number(transaction?.czk);
    return valid(quantity)&&quantity>0&&valid(czk)&&czk>0 ? czk/quantity : null;
  }

  // Sčítáme částky a množství. Ceny za 1 BTC/ETH se NESČÍTAJÍ:
  // správná průměrná cena = celková CZK hodnota / celkový počet jednotek.
  function transferTotals(transactions,symbols=['BTC','ETH']){
    const result={};
    for(const symbol of symbols)result[symbol]={symbol,quantity:0,czk:0,count:0,missing:0,average:null};
    for(const t of transactions||[]){
      if(!incoming(t)||!result[t.symbol])continue;
      const entry=result[t.symbol],price=impliedUnitPrice(t);
      if(price===null){entry.missing++;continue;}
      entry.quantity+=Number(t.quantity);
      entry.czk+=Number(t.czk);
      entry.count++;
    }
    for(const sym of symbols){
      const item=result[sym];
      item.average=item.quantity>0&&item.missing===0?item.czk/item.quantity:null;
    }
    return result;
  }

  // Orientační pořizovací základ aktuálně drženého množství.
  // U odchozích kusů se snižuje průběžný náklad metodou váženého průměru.
  // Nepočítá realizovaný zisk ani poplatky nebo přesuny mezi vlastními peněženkami.
  function estimatedHoldingCost(transactions,symbol,holdingQuantity){
    const holding=Number(holdingQuantity);
    if(!valid(holding)||holding<0)return {cost:null,average:null,reason:'invalid-holding'};
    if(holding===0)return {cost:0,average:null,reason:null};
    const rows=(transactions||[]).filter(t=>t.symbol===symbol&&(incoming(t)||outgoing(t)))
      .slice().sort((a,b)=>String(a.date).localeCompare(String(b.date))||
        (incoming(a)?0:1)-(incoming(b)?0:1)||String(a.id||'').localeCompare(String(b.id||'')));
    if(!rows.length)return {cost:null,average:null,reason:'no-history'};
    let quantity=0,cost=0,receipts=0;
    for(const t of rows){
      const q=Number(t.quantity);
      if(!valid(q)||q<=0)return {cost:null,average:null,reason:'unknown-quantity'};
      if(incoming(t)){
        const price=impliedUnitPrice(t);
        if(price===null)return {cost:null,average:null,reason:'unknown-transfer-czk'};
        quantity+=q;
        cost+=Number(t.czk);
        receipts++;
      } else {
        const tolerance=Math.max(2e-8,quantity*1e-9);
        if(q>quantity+tolerance)return {cost:null,average:null,reason:'missing-earlier-incoming'};
        const fraction=Math.min(1,q/quantity);
        cost*=1-fraction;
        quantity=Math.max(0,quantity-q);
      }
    }
    const tolerance=Math.max(2e-8,holding*1e-7);
    if(!receipts||Math.abs(quantity-holding)>tolerance)
      return {cost:null,average:null,reason:'balance-mismatch',ledgerQuantity:quantity};
    if(cost<=0)return {cost:null,average:null,reason:'unknown-cost'};
    return {cost,average:cost/holding,reason:null,ledgerQuantity:quantity,method:'transfer-estimate'};
  }

  const api={impliedUnitPrice,transferTotals,estimatedHoldingCost};
  if(typeof module!=='undefined'&&module.exports)module.exports=api;
  if(root)root.KryptoCost=api;
})(typeof window!=='undefined'?window:undefined);
