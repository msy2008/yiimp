<?php

function WriteBoxHeader($title)
{
	echo "<div class='main-left-box'>";
	echo "<div class='main-left-title'>$title</div>";
	echo "<div class='main-left-inner'>";
}

$address = getparam('address');
if (empty($address)) {
    return;
}

$user = getuserparam($address);
if (!$user) {
    return ;
}

$userId = $user['id'];
$mainAddress = $user['username'];
$balance = dogecoinvaluetoa($user['balance']);

$userdogm = getdbosql('db_accountsdogm', "id={$userId}");
$dogmAddress = $userdogm['username'];
$dogmbalance = dogecoinvaluetoa($userdogm['balance']);

$userdoge = getdbosql('db_accountsdoge', "id={$userId}");
$dogeAddress = $userdoge['username'];
$dogebalance = dogecoinvaluetoa($userdoge['balance']);
 
$headerContent = "<div style='text-align: center; white-space: nowrap;'>" .
                 "Miner User UID: " . $userId . "<br>" .
                 "Main POW Address(<span style='color: " . getColorCode('IFC') . ";'>IFC</span>): " . htmlspecialchars($mainAddress) . "<br>" .
                 "AUXPOW Address(<span style='color: " . getColorCode('DOGM') . ";'>DOGM</span>): " . htmlspecialchars($dogmAddress) . "<br>" .
                 "AUXPOW Address(<span style='color: " . getColorCode('DOGE') . ";'>DOGE</span>): " . htmlspecialchars($dogeAddress) .
                 "</div>";

WriteBoxHeader($headerContent);

$refcoin = getdbo('db_coins', $user->coinid);

echo "<table class='dataGrid2'>";
echo "<thead>";
echo "<tr>";
echo "<th></th>";
echo "<th>Name</th>";
echo "<th align=right>Immature</th>";
echo "<th align=right>Confirmed</th>";
echo "<th align=right>Total</th>";
echo "<th align=right>Next Payout</th>";
echo "<th align=right>Total Unpaid</th>";
echo "</tr>";
echo "</thead>";

$total_pending = 0;
	$t1 = microtime(true);
        
	    $list = dbolist("select coinid from earnings where userid=$user->id group by coinid");
 
		foreach($list as $item)
		{
			$coin = getdbo('db_coins', $item['coinid']);
			if(!$coin) continue;

			$name = substr($coin->name, 0, 12);

			$confirmed = controller()->memcache->get_database_scalar("wallet_confirmed-$user->id-$coin->id",
				"select sum(amount) from earnings where status=1 and userid=$user->id and coinid=$coin->id");

			$unconfirmed = controller()->memcache->get_database_scalar("wallet_unconfirmed-$user->id-$coin->id",
				"select sum(amount) from earnings where status=0 and userid=$user->id and coinid=$coin->id");

			$total = $confirmed + $unconfirmed;

			$confirmed = dogecoinvaluetoa($confirmed);
			$unconfirmed = dogecoinvaluetoa($unconfirmed);
			$total = dogecoinvaluetoa($total);
                        $totalBalance = $balance + $total;
                        $dogmtotalBalance = $dogmbalance + $total;
                        $dogetotalBalance = $dogebalance + $total;
			echo "<tr class='ssrow'>";
			echo "<td width=18><img width=16 src='$coin->image'></td>";
                        echo "<td><b><a href='/site/block?id=$coin->id' title='$coin->version'>$name</a></b></td>";
			echo "<td align=right style='font-size: .8em;'>$unconfirmed</td>";
			echo "<td align=right style='font-size: .8em;'>$confirmed</td>";
			echo "<td align=right style='font-size: .8em;'>$total</td>";
                        if ($coin->symbol == 'DOGM') {  
                            echo "<td align=right style='font-size: .8em;'>$dogmbalance</td>";
                            echo "<td align=right style='font-size: .8em;'>$dogmtotalBalance</td>";
                        } else if ($coin->symbol == 'DOGE') {
                            echo "<td align=right style='font-size: .8em;'>$dogebalance</td>";
                            echo "<td align=right style='font-size: .8em;'>$dogetotalBalance</td>";
                        } else {
                            echo "<td align=right style='font-size: .8em;'>$balance</td>";
                            echo "<td align=right style='font-size: .8em;'>$totalBalance</td>";
                        }
			echo "</tr>";
		}

	$d1 = microtime(true) - $t1;
	controller()->memcache->add_monitoring_function('wallet_results-1', $d1);


////////////////////////////////////////////////////////////////////////////

$total_paid = controller()->memcache->get_database_scalar("wallet_total_paid-$user->id",
	"select sum(amount) from payouts where account_id=$user->id");
$total_paid = dogecoinvaluetoa($total_paid);
echo "<tr class='ssrow' style='border-top: 1px solid #eee;'>";
echo "<td colspan=3 style='white-space: nowrap;'><b>Payouts history (up to 60 days of data)</b></td>";
echo "<td align=right style='font-size: .8em;'></td>";
echo "<td align=right style='font-size: .8em;'></td>";
echo "<td align=right style='font-size: .8em; white-space: nowrap;'><a href='javascript:main_wallet_tx()'>Click here to show more payouts</a></td>";
echo "</tr>";
echo "</table>";
echo "</div>";

////////////////////////////////////////////////////////////////////////////

$header = "Last 24 Hours Payouts: ".$user->username;
WriteBoxHeader($header);

$t = time()-24*60*60;
$list = getdbolist('db_payouts', "account_id={$user->id} AND time>$t ORDER BY time DESC");

echo "<table  class='dataGrid2'>";

echo "<thead>";
echo "<tr>";
echo "<th align=right>Time</th>";
echo "<th align=right>Amount</th>";
echo "<th align=center>Coins</th>";
echo "<th>Tx</th>";
echo "</tr>";
echo "</thead>";
 
$total = 0; $firstid = 999999999;
foreach($list as $payout)
{
        $coin = getdbo('db_coins', $payout->idcoin);
	$d = datetoa3($payout->time);
	$amount = dogecoinvaluetoa($payout->amount);  
	$firstid = min($firstid, (int) $payout->id);

	echo '<tr class="ssrow">';
	echo '<td align="right"><b>'.$d.'&nbsp;ago</b></td>';
	echo '<td align="right"><b>'.$amount.'</b></td>';
        echo '<td align="center"><b style="color: ' . getColorCode($coin->symbol_show) . ';">' . $coin->symbol_show . '</b></td>';
	$payout_tx = substr($payout->tx, 0, 36).'...';
        $link = $coin->createExplorerLink($payout_tx, array('txid'=>$payout->tx), array('target'=>'_blank'));
	echo '<td style="font-family: monospace;">'.$link.'</td>';
	echo '</tr>';

	$total += $payout->amount;
}

$amount = dogecoinvaluetoa($total);

echo <<<end
<tr class="ssrow">
<td align="right"><b>Merged Mining:</td>
<td align="right"><b>{$amount}</b></td>
<td align="right"><b>IFC+DOGM+DOGE</b></td>
<td></td>
</tr>
end;

// Search extra Payouts which were not in the db (yiimp payout check command)
// In this case, the id are greater than last 24h ones and the fee column is filled
$list_extra = getdbolist('db_payouts', "account_id={$user->id} AND id>$firstid AND fee > 0.0 ORDER BY time DESC");

if (!empty($list_extra)) {

	echo <<<end
	<tr class="ssrow" style="color: darkred;">
	<th colspan="3"><b>Extra payouts detected in the last 24H to explain negative balances (buggy Wallets)</b></th>
	</tr>
	<tr class="ssrow">
	<td colspan="3" style="font-size: .9em; padding-bottom: 8px;">
	Some wallets (UFO,LYB) have a problem and don't always confirm a transaction in the requested time.<br/>
	<!-- Please be honest and continue mining to handle these extra transactions sent to you. --><br/>
	</th>
	</tr>
	<tr class="ssrow">
	<th align="right">Time</th> <th align="right">Amount</th> <th>Tx</th>
	</tr>
end;

	$total = 0.0;
	foreach($list_extra as $payout)
	{
		$d = datetoa3($payout->time);
		$amount = dogecoinvaluetoa($payout->amount);

		echo '<tr class="ssrow">';
		echo '<td align="right"><b>'.$d.' ago</b></td>';
		echo '<td align="right"><b>'.$amount.'</b></td>';
		$payout_tx = substr($payout->tx, 0, 20).'...';
		$link = $refcoin->createExplorerLink($payout_tx, array('txid'=>$payout->tx), array(), true);

		echo '<td style="font-family: monospace;">'.$link.'</td>';
		echo '</tr>';

		$total += $payout->amount;
	}

	$amount = dogecoinvaluetoa($total);

	echo <<<end
	<tr class="ssrow" style="color: darkred;">
	<td align="right">Total:</td>
	<td align="right"><b>{$amount}</b></td>
	<td></td>
	</tr>
end;
}
echo "</table><br>";
echo "</div>";

echo "</div><br>";






