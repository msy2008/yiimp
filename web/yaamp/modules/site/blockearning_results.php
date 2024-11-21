<?php

echo <<<end
<style type="text/css">
tr.ssrow.filtered { display: none; }
.actions { width: 120px; text-align: right; }
table.dataGrid a.red { color: darkred; }
table.totals { margin-top: 8px; margin-left: 16px; display: inline-block; }
table.totals th { text-align: left; width: 100px; }
table.totals td { text-align: right; }
table.totals tr.red td { color: darkred; }
.page .footer { width: auto; }
</style>
end;

$coin_id = getiparam('id');

$saveSort = $coin_id ? 'false' : 'true';

showTableSorter('maintable', "{
	tableClass: 'dataGrid',
	widgets: ['zebra','filter','Storage','saveSort'],
	textExtraction: {
		5: function(node, table, n) { return $(node).attr('data'); },
		6: function(node, table, n) { return $(node).attr('data'); }
	},
	widgetOptions: {
		saveSort: {$saveSort},
		filter_saveFilters: {$saveSort},
		filter_external: '.search',
		filter_columnFilters: false,
		filter_childRows : true,
		filter_ignoreCase: true
	}
}");

echo <<<end
<thead>
<tr>
<th data-sorter="numeric">UID</th>
<th data-sorter="" width="20"></th>
<th data-sorter="text">Coin</th>
<th data-sorter="text">Address</th>
<th data-sorter="currency">Amount</th>
<th data-sorter="numeric">Blocks</th>
<th data-sorter="">Status</th>
<th data-sorter="numeric">Time</th>
</tr>
</thead><tbody>
end;

$coin_id = getiparam('id');
$sqlFilter = $coin_id ? "AND coinid={$coin_id}": '';
$limit = $coin_id ? '' : 'LIMIT 1500';

$earnings = getdbolist('db_earnings', "status!=2 $sqlFilter ORDER BY create_time DESC $limit");

$total = 0.; $total_btc = 0.; $totalimmat = 0.; $totalfees = 0.; $totalstake = 0.;

foreach($earnings as $earning)
{
	$coin = getdbo('db_coins', $earning->coinid);
	if(!$coin) continue;

	if ($coin->symbol === 'DOGM') {  
        $user = getdbo('db_accountsdogm', $earning->userid);  
        } elseif ($coin->symbol === 'DOGE') {  
        $user = getdbo('db_accountsdoge', $earning->userid);  
        } else {   
        $user = getdbo('db_accounts', $earning->userid);  
        }   
        if (!$user) continue;  

	$block = getdbo('db_blocks', $earning->blockid);
	if(!$block) continue;

        $d = datetoa3($earning->create_time);
	
	$coinimg = CHtml::image($coin->image, $coin->symbol, array('width'=>'16'));
        $coinlink = $coin->name;

        $blockHeight = $block->height;
        $color = getBlockHeightColor($blockHeight);

	echo '<tr class="ssrow">';
        echo '<td width="24">'.$user->id.'</td>';
	echo "<td>$coinimg</td>";
	echo "<td><b>$coinlink</b>&nbsp;($coin->symbol_show)</td>";
        if ($coin->symbol === 'DOGM' || $coin->symbol === 'DOGE') {  
        echo '<td><b>'.$user->username.'</b></td>';
        } else {
	echo '<td><b><a href="/?address='.$user->username.'">'.$user->username.'</a></b></td>';
        }
	echo '<td>'.$earning->amount.'</td>';
        echo '<td style="color: ' . $color . ';">' . $block->height . '</td>';
	echo '<td data="'.$block->height.'">'."$block->category ($block->confirmations)</td>";
        echo '<td data="'.$earning->create_time.'">'.$d.'&nbsp;ago</td>';
	echo '</td>';
	echo "</tr>";

	if($block->category == 'immature') {
		$total += (double) $earning->amount;
		$total_btc += (double) $earning->amount * $earning->price;
		$totalimmat += (double) $earning->amount;
	}
	if($block->category == 'generate') {
		$total += (double) $earning->amount;
		$total_btc += (double) $earning->amount * $earning->price;
	}
	else if($block->category == 'stake' || $block->category == 'generated') {
		$totalstake += (double) $earning->amount;
	}
}

        echo '</tbody><tfoot>';
        echo '<tr><th colspan="9">';
        echo count($earnings).' records';
        if (count($earnings) >= 1000) echo " ($limit)";
        echo '</th></tr>';
        echo '</tfoot></table>';

if ($coin_id) {
	$coin = getdbo('db_coins', $coin_id);
	if (!$coin) exit;
	$symbol = $coin->symbol;
	$feepct = yaamp_fee($coin->algo);
	$totalfees = ($total / ((100 - $feepct) / 100.)) - $total;

	$cleared = dboscalar("SELECT SUM(balance) FROM accounts WHERE coinid={$coin->id}");

	echo '<div class="totals" align="right">';

	echo '<table class="totals">';
	echo '<tr><th>Immature</th><td>'.bitcoinvaluetoa($totalimmat)." $symbol</td></tr>";
	echo '<tr><th>Total owed</th><td>'.bitcoinvaluetoa($total)." $symbol</td></tr>";
	echo '<tr><th>Pool Fees '.round($feepct,1).'%</th><td>'.bitcoinvaluetoa($totalfees)." $symbol</td></tr>";
	if ($coin->rpcencoding == 'POS')
		echo '<tr><th>Stake</th><td>'.bitcoinvaluetoa($totalstake)." $symbol</td></tr>";
	echo '</tr></table>';

	echo '<table class="totals">';
	echo '<tr><th>Balance</th><td>'.bitcoinvaluetoa($coin->balance)." $symbol</td></tr>";
	echo '<tr><th>Cleared</th><td>'.bitcoinvaluetoa($cleared)." $symbol</td></tr>";
	$exchange = $total - $totalimmat;
	echo '<tr><th title="Available = (Balance - Cleared - in exchange)">Available</th>';
	echo '<td>'.bitcoinvaluetoa($coin->balance - $exchange - $cleared)." $symbol</td></tr>";
	echo '</tr></table>';
	echo '</div>';
}
