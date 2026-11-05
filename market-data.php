<?php
declare(strict_types=1);
// Public market data only. No portfolio data or credentials leave this server.
function marketPrices(): ?array {
 $j=fetchJson('https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum&vs_currencies=czk&include_24hr_change=true&include_last_updated_at=true');
 if(isset($j['bitcoin']['czk'],$j['ethereum']['czk'])) {
  $out=['source'=>'CoinGecko'];
  foreach(['BTC'=>'bitcoin','ETH'=>'ethereum'] as $coin=>$id){if((float)$j[$id]['czk']<=0)return null;$out[$coin]=['price'=>(float)$j[$id]['czk'],'change24h'=>isset($j[$id]['czk_24h_change'])?(float)$j[$id]['czk_24h_change']:null,'quote_at'=>$j[$id]['last_updated_at']??time()];}return $out;
 }
 $j=fetchJson('https://api.coinbase.com/v2/exchange-rates?currency=USD');$rates=$j['data']['rates']??[];
 if(isset($rates['CZK'],$rates['BTC'],$rates['ETH'])&&(float)$rates['BTC']>0&&(float)$rates['ETH']>0&&(float)$rates['CZK']>0){$out=['source'=>'Coinbase'];foreach(['BTC','ETH'] as $coin)$out[$coin]=['price'=>(float)$rates['CZK']/(float)$rates[$coin],'change24h'=>null,'quote_at'=>time()];return $out;}
 $j=fetchJson('https://min-api.cryptocompare.com/data/pricemultifull?fsyms=BTC,ETH&tsyms=CZK');
 if(!isset($j['RAW']['BTC']['CZK']['PRICE'],$j['RAW']['ETH']['CZK']['PRICE']))return null;$out=['source'=>'CryptoCompare / CoinDesk Data'];foreach(['BTC','ETH'] as $coin){$x=$j['RAW'][$coin]['CZK'];$out[$coin]=['price'=>(float)$x['PRICE'],'change24h'=>isset($x['CHANGEPCT24HOUR'])?(float)$x['CHANGEPCT24HOUR']:null,'quote_at'=>$x['LASTUPDATE']??time()];}return $out;
}
function marketHistory(string $coin,string $period): ?array {
 $days=['1D'=>1,'7D'=>7,'1M'=>30,'3M'=>90,'6M'=>180,'1Y'=>365,'5Y'=>1826,'15Y'=>5479][$period];$start=time()-$days*86400;
 if($days<=365){$id=$coin==='BTC'?'bitcoin':'ethereum';$j=fetchJson('https://api.coingecko.com/api/v3/coins/'.$id.'/market_chart?vs_currency=czk&days='.$days);if(is_array($j['prices']??null)){$p=[];foreach($j['prices'] as $x)if(count($x)>=2&&is_numeric($x[1])&&(float)$x[1]>0)$p[]=[(int)floor((float)$x[0]/1000),(float)$x[1]];if(count($p)>1)return ['points'=>$p,'symbol'=>$coin,'period'=>$period,'source'=>'CoinGecko · CZK','coverage_partial'=>$p[0][0]>$start+max(7200,$days*86400*.05)];}}
 // Kraken's public OHLC has at most 720 candles. Weekly data is labelled as such.
 // Converted history uses today's FX rate and is NOT historical CZK performance.
 $fx=cached('usd-czk',3600,static function(){$j=fetchJson('https://api.coinbase.com/v2/exchange-rates?currency=USD');$rate=$j['data']['rates']['CZK']??null;return is_numeric($rate)&&(float)$rate>0?['rate'=>(float)$rate,'at'=>time()]:null;});
 $rate=$fx['data']['rate']??null;if(!is_numeric($rate)||($fx['stale']??false))return null;
 $interval=$days<=7?60:($days<=365?1440:10080);$pair=$coin==='BTC'?'XBTUSD':'ETHUSD';$j=fetchJson('https://api.kraken.com/0/public/OHLC?pair='.$pair.'&interval='.$interval.'&since='.$start);if(!empty($j['error'])||!is_array($j['result']??null))return null;$p=[];foreach($j['result'] as $key=>$candles){if($key==='last'||!is_array($candles))continue;foreach($candles as $c)if((int)$c[0]>=$start&&is_numeric($c[4])&&(float)$c[4]>0)$p[]=[(int)$c[0],(float)$c[4]*(float)$rate];}
 if(count($p)<2)return null;usort($p,static fn($a,$b)=>$a[0]<=>$b[0]);return ['points'=>$p,'symbol'=>$coin,'period'=>$period,'source'=>'Kraken · USD přepočtené dnešním kurzem Coinbase USD/CZK '.round((float)$rate,4),'coverage_partial'=>$p[0][0]>$start+max($interval*60*2,$days*86400*.05),'fx_current'=>true,'interval_minutes'=>$interval];
}
