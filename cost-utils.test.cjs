"use strict";
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const K=require('./cost-utils.js');
const row=(id,date,symbol,type,quantity,czk)=>({id,date,symbol,type,quantity,czk});

test('price per BTC / ETH = CZK transferred divided by received quantity',()=>{
 assert.equal(K.impliedUnitPrice(row('1','2026-11-05','BTC','received',0.005,7500)),1500000);
 assert.equal(K.impliedUnitPrice(row('2','2026-11-05','ETH','buy',0.05,2500)),50000);
 assert.equal(K.impliedUnitPrice(row('3','2026-11-05','BTC','sent',0.005,7500)),null);
 assert.equal(K.impliedUnitPrice(row('4','2026-11-05','BTC','received',0,7500)),null);
 assert.equal(K.impliedUnitPrice(row('5','2026-11-05','ETH','received',0.1,0)),null);
});

test('weighted average is total CZK divided by total coins, not sum or mean of unit rates',()=>{
 const rows=[
  row('1','2026-01-01','BTC','received',0.005,5000),
  row('2','2026-02-01','BTC','buy',0.01,20000),
  row('3','2026-02-01','ETH','received',0.1,4000),
  row('4','2026-03-01','BTC','sent',0.001,1300)
 ];
 const s=K.transferTotals(rows);
 assert.equal(s.BTC.czk,25000);
 assert.equal(s.BTC.quantity,0.015);
 assert.ok(Math.abs(s.BTC.average-25000/0.015)<1e-6);
 assert.equal(s.BTC.count,2);
 assert.equal(s.ETH.average,40000);
});

test('current holding estimate reduces book value for outgoing units at weighted-average cost',()=>{
 const rows=[
  row('1','2026-01-01','BTC','received',1,100),
  row('2','2026-01-02','BTC','received',1,300),
  row('3','2026-01-03','BTC','sent',1,9999),
  row('4','2026-01-04','BTC','buy',0.5,150)
 ];
 const out=K.estimatedHoldingCost(rows,'BTC',1.5);
 assert.equal(out.cost,350);
 assert.equal(out.average,350/1.5);
 assert.equal(out.reason,null);
});

test('ROI estimate is withheld if ledger does not reconcile to saved portfolio',()=>{
 const rows=[row('1','2026-01-01','BTC','received',1,100)];
 assert.equal(K.estimatedHoldingCost(rows,'BTC',2).cost,null);
 assert.equal(K.estimatedHoldingCost(rows,'BTC',2).reason,'balance-mismatch');
 assert.equal(K.estimatedHoldingCost(rows,'BTC',0).cost,0);
});

test('unknown transfer CZK / negative inventory invalidates estimate',()=>{
 const missing=[row('1','2026-01-01','ETH','received',1,0)];
 assert.equal(K.estimatedHoldingCost(missing,'ETH',1).reason,'unknown-transfer-czk');
 const negative=[row('1','2026-01-01','BTC','received',0.2,200),row('2','2026-01-02','BTC','sent',0.5,100)];
 assert.equal(K.estimatedHoldingCost(negative,'BTC',0.3).reason,'missing-earlier-incoming');
});

test('UI has derived per-unit column and totals, clear caveats, and preserves manual cost preference',()=>{
 const html=fs.readFileSync('index.php','utf8'),app=fs.readFileSync('app.js','utf8');
 assert.match(html,/transactions-summary/);
 assert.match(html,/Odhad ceny za 1 BTC \/ ETH/);
 assert.match(html,/cost-utils\.js/);
 assert.match(app,/K\.impliedUnitPrice\(t\)/);
 assert.match(app,/K\.transferTotals\(rows\)/);
 assert.match(app,/manual!==null\?manual:derived\.cost/);
 assert.match(app,/Odhad z hodnot převodů/);
 assert.match(html,/hodnoty.*převodů|Přijatá hodnota BTC/i);
});
