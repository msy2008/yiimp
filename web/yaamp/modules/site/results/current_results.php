<?php

$defaultalgo = user()->getState('yaamp-algo');

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Pool Status</div>";
echo "<div class='main-left-inner'>";

showTableSorter('maintable1', "{
	tableClass: 'dataGrid2',
	textExtraction: {
		4: function(node, table, n) { return $(node).attr('data'); },
		8: function(node, table, n) { return $(node).attr('data'); }
	}
}");

echo <<<END
<thead>
<tr>
<th>Algo</th>
<th data-sorter="numeric" align="right">Type</th>
<th data-sorter="numeric" align="right">Port</th>
<th data-sorter="numeric" align="right">Coins</th>
<th data-sorter="numeric" align="right">Miners</th>
<th data-sorter="numeric" align="right">Hashrate</th>
<th data-sorter="numeric" align="right">Network</th>	
<th data-sorter="numeric" align="right">Fees*</th>
</tr>
</thead>
END;

$best_algo = '';
$best_norm = 0;

$algos = array();
foreach(yaamp_get_algos() as $algo)
{
	$algo_norm = yaamp_get_algo_norm($algo);

	$price = controller()->memcache->get_database_scalar("current_price-$algo",
		"select price from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));

	$norm = $price*$algo_norm;

	$algos[] = array($norm, $algo);
}

$total_coins = 0;
$total_miners = 0;

$showestimates = false;

echo "<tbody>";
foreach($algos as $item)
{
	$norm = $item[0];
	$algo = $item[1];

	$coinsym = '';
	$coins = getdbocount('db_coins', "enable and visible and auto_ready and algo=:algo", array(':algo'=>$algo));
	if ($coins == 1) {
		$coin = getdbosql('db_coins', "enable and visible and auto_ready and algo=:algo", array(':algo'=>$algo));
		$coinsym = empty($coin->symbol2) ? $coin->symbol : $coin->symbol2;
		$coinsym = '<span title="'.$coin->name.'">'.$coinsym.'</a>';
	}

	if (!$coins) continue;

	$workers = getdbocount('db_workers', "algo=:algo", array(':algo'=>$algo));

	$hashrate = controller()->memcache->get_database_scalar("current_hashrate-$algo",
		"select hashrate from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));
	$hashrate_sfx = $hashrate? Itoa2($hashrate).'h/s': '-';

	$price = controller()->memcache->get_database_scalar("current_price-$algo",
		"select price from hashrate where algo=:algo order by time desc limit 1", array(':algo'=>$algo));

	$price = $price? mbitcoinvaluetoa(take_yaamp_fee($price, $algo)): '-';
	$norm = mbitcoinvaluetoa($norm);

	$t = time() - 24*60*60;

	$avgprice = controller()->memcache->get_database_scalar("current_avgprice-$algo",
		"select avg(price) from hashrate where algo=:algo and time>$t", array(':algo'=>$algo));
	$avgprice = $avgprice? mbitcoinvaluetoa(take_yaamp_fee($avgprice, $algo)): '-';

	$total1 = controller()->memcache->get_database_scalar("current_total-$algo",
		"SELECT SUM(amount*price) AS total FROM blocks WHERE time>$t AND algo=:algo AND NOT category IN ('orphan','stake','generated')",
		array(':algo'=>$algo)
	);

	$hashrate1 = controller()->memcache->get_database_scalar("current_hashrate1-$algo",
		"select avg(hashrate) from hashrate where time>$t and algo=:algo", array(':algo'=>$algo));

	$fees = yaamp_fee($algo);
	$port = getAlgoPort($algo);

	if($defaultalgo == $algo)
		echo "<tr style='cursor: pointer; background-color: #41464b;' onclick='javascript:select_algo(\"$algo\")'>";
	else
		echo "<tr style='cursor: pointer' class='ssrow' onclick='javascript:select_algo(\"$algo\")'>";

	echo "<td><b>$algo</b></td>";
        echo "<td align=center style='font-size: .8em; background-color: #41464b;'></td>";
        echo "<td align=center style='font-size: .8em; background-color: #41464b;'></td>";
        echo "<td align=center style='font-size: .8em; background-color: #41464b;'></td>";
        echo "<td align=center style='font-size: .8em; background-color: #41464b;'></td>";
        echo "<td align=center style='font-size: .8em; background-color: #41464b;'></td>";
        echo "<td align=center style='font-size: .8em; background-color: #41464b;'></td>";
        echo "<td align=center style='font-size: .8em; background-color: #41464b;'></td>";
	echo "</tr>";

        if ($coins > 0){
        $list = getdbolist('db_coins', "enable and visible and auto_ready and algo=:algo order by id", array(':algo'=>$algo));
   
        foreach($list as $coin){
        $name = substr($coin->name, 0, 12);
        $symbol = $coin->getOfficialSymbol();
        echo "<tr>";
        echo "<td align='left' valign='top' style='font-size: .8em;'><img width='16' src='".$coin->image."'>  <b>$name</b> <span style='font-size: .8em'></span></td>";
        $port_count = getdbocount('db_stratums', "algo=:algo and symbol=:symbol", array(':algo'=>$algo,':symbol'=>$symbol));
        $port_db = getdbosql('db_stratums', "algo=:algo and symbol=:symbol", array(':algo'=>$algo,':symbol'=>$symbol));

        if($coin->auxpow && $coin->auto_ready)
            echo "<td align='right' style='font-size: .8em;'>AUXPOW</td>";
        else
            echo "<td align='right' style='font-size: .8em;'>POW</td>";

        if($port_count == 1)
            echo "<td align='right' style='font-size: .8em;'>.$port_db->port.</td>";
        else
            echo "<td align='right' style='font-size: .8em;'>$port</td>";

        echo "<td align='right' style='font-size: .8em;'>$symbol</td>";

        if($port_count == 1)
            echo "<td align='right' style='font-size: .8em;'>.$port_db->workers.</td>";
        else
            echo "<td align='right' style='font-size: .8em;'>$workers</td>";

        $pool_hash = yaamp_coin_rate($coin->id);
        $pool_hash_sfx = $pool_hash? Itoa2($pool_hash).'h/s': '';

        $pool_hash_pow = yaamp_pool_rate_pow($coin->algo);
        $pool_hash_pow_sfx = $pool_hash_pow? Itoa2($pool_hash_pow).'h/s': '';

        if($coin->auxpow && $coin->auto_ready)
            echo "<td align='right' style='font-size: .8em; opacity: 0.6;'>$pool_hash_pow_sfx</td>";          
        else
            echo "<td align='right' style='font-size: .8em;'>$pool_hash_sfx</td>";

	    $network_hash = controller()
                ->memcache
                ->get("yiimp-nethashrate-{$coin->symbol}");
            if (!$network_hash)
            {
                $remote = new WalletRPC($coin);
                if ($remote) $info = $remote->getmininginfo();
                if (isset($info['networkhashps']))
                {
                    $network_hash = $info['networkhashps'];
                    controller()
                        ->memcache
                        ->set("yiimp-nethashrate-{$coin->symbol}", $info['networkhashps'], 60);
                }
                else if (isset($info['netmhashps']))
                {
                    $network_hash = floatval($info['netmhashps']) * 1e6;
                    controller()
                        ->memcache
                        ->set("yiimp-nethashrate-{$coin->symbol}", $network_hash, 60);
                }
		else
		{
		    $network_hash = $coin->difficulty * 0x100000000 / ($min_ttf? $min_ttf: 60);
		}
            }
            $network_hash = $network_hash ? Itoa2($network_hash) . 'h/s' : '';
            echo "<td align='right' style='font-size: .8em;' data='$pool_hash'>$network_hash</td>";	
            echo "<td align='right' style='font-size: .8em;'>{$fees}%</td>";
        echo "</tr>";
    }
} 
	
	$total_coins += $coins;
	$total_miners += $workers;
}

echo "</tbody>";

echo "<td style='color: gray; pointer-events: none;'><b>all</b></td>"; 
echo "<td align=right style='font-size: .8em;'>Merged Mining</td>";
echo "<td></td>";
echo "<td align=right style='font-size: .8em;'>$total_coins</td>";
echo "<td align=right style='font-size: .8em;'>$total_miners</td>";
echo "<td></td>";
echo "</tr>";

echo "</table>";
echo '<p style="font-size: .8em;">&nbsp;* Are the fees real ? Do not believe,verify it ! Use Earnings verify it !</p>';
echo "</div></div><br>";
?>


<?php if (!$showestimates): ?>
<style type="text/css">

</style>

<?php endif; ?>

