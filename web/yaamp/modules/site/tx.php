<?php

require dirname(__FILE__).'/../../ui/lib/pageheader.php';

$user = getuserparam(getparam('address'));
if(!$user) return;

$this->pageTitle = $user->username.' | '.YAAMP_SITE_NAME;

echo "<div class='main-left-box'>";
echo "<div class='main-left-title'>Last 60 days transactions history: $user->username</div>";
echo "<div class='main-left-inner'>";

$list = getdbolist('db_payouts', "account_id={$user->id} ORDER BY time DESC");

echo '<table class="dataGrid2">';

echo "<thead>";
echo "<tr>";
echo "<th></th>";
echo "<th>Time</th>";
echo "<th align=right>Amount</th>";
echo "<th align='center'>Coins</th>";
echo "<th>Tx</th>";
echo "</tr>";
echo "</thead>";

$bitcoin = getdbosql('db_coins', "symbol='BTC'");

$total = 0;
foreach($list as $payout)
{
        $coin = getdbo('db_coins', $payout->idcoin);
	$d = datetoa3($payout->time);
	$amount = dogecoinvaluetoa($payout->amount);

	echo "<tr class='ssrow'>";
	echo "<td width=18></td>";
	echo "<td><b>$d ago</b></td>";
	echo "<td align=right><b>$amount</b></td>";
        echo '<td align="center"><b style="color: ' . getColorCode($coin->symbol) . ';">' . $coin->symbol . '</b></td>';
        $payout_tx = substr($payout->tx, 0, 36).'...';
        $url = $coin->createExplorerLink($payout_tx, array('txid'=>$payout->tx), array('target'=>'_blank'));
	echo '<td style="font-family: monospace;">'.$url.'</td>';

	echo "</tr>";
	$total += $payout->amount;
}

$total = dogecoinvaluetoa($total);

echo "<tr class='ssrow' style='border-top: 2px solid #eee;'>";
echo "<td width=18></td>";
echo "<td><b>Merged Mining:</b></td>";

echo "<td align=right><b>$total</b></td>";
echo "<td align=right><b>IFC+DOGM+DOGE</b></td>";
echo "<td></td>";

echo "</tr>";

echo "</table><br>";
echo "</div></div><br>";


