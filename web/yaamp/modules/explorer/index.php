<?php
JavascriptFile("/yaamp/ui/js/jquery.metadata.js");
JavascriptFile("/yaamp/ui/js/jquery.tablesorter.widgets.js");

echo <<<EOT
<script>function wallet_peers(id){window.open("/explorer/peers?id="+id,"peers","width=400,height=600,location=no,menubar=no,resizable=yes,status=no,toolbar=no");}</script>
<style>
a.low{color:red;font-weight:bold;}
.outstanding-amount{cursor:pointer;}
.outstanding-amount:hover{color:#4caf50;}
.auto-refresh-settings{margin-top:20px;padding:15px;background:rgba(33,37,41,0.7);border-radius:4px;border:1px solid #4a4a4a;}
.auto-refresh-settings h3{margin-top:0;font-size:1.1em;color:#fff;}
.refresh-controls{display:flex;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:5px;}
.refresh-input-group{display:flex;align-items:center;gap:8px;}
.refresh-input-group label{font-weight:500;color:#ccc;}
.refresh-input-group input{width:80px;padding:5px;background:#2c2f33;color:#fff;border:1px solid #4a4a4a;border-radius:3px;}
.refresh-hint{color:#aaa;font-size:.9em;}
.apply-button{padding:6px 15px;background:#2c2f33;color:#fff;border:1px solid #4a4a4a;border-radius:3px;cursor:pointer;transition:all .2s;}
.apply-button:hover{background:#3d4147;border-color:#5a5a5a;}
.countdown-display{color:#4caf50;font-weight:bold;font-size:.9em;}
.countdown-timer{color:#fff;font-weight:bold;}
</style>

<br/><div class="main-left-box"><div class="main-left-title">Block Explorer</div><div class="main-left-inner">
EOT;

showTableSorter('maintable', "{tableClass:'dataGrid2',textExtraction:{7:function(n,t,e){return\$(n).attr('data');},10:function(n,t,e){return\$(n).attr('data');}}}");

echo <<<EOT
<table class="dataGrid2"><thead><tr>
<th width="30" data-sorter=""></th><th>Name</th><th>Symbol</th><th>Algo</th><th>Version</th><th>Height</th><th>Age</th><th>Difficulty</th>
<th>Outstanding</th><th>Connections</th><th>Network Hash</th><th data-sorter=""></th>
</tr></thead><tbody>
EOT;

$list = getdbolist('db_coins', "enable and visible order by name");
foreach($list as $coin) {
    if($coin->symbol == 'BTC' || !empty($coin->symbol2)) continue;
    
    $coin->version = formatWalletVersion($coin);
    
    // Network hash rate
    if(!$coin->network_hash) {
        $remote = new WalletRPC($coin);
        if($remote) {
            $info = $remote->getmininginfo();
            if(isset($info['networkhashps'])) {
                $coin->network_hash = $info['networkhashps'];
            } elseif(isset($info['netmhashps'])) {
                $coin->network_hash = floatval($info['netmhashps']) * 1e6;
            }
            if($coin->network_hash) controller()->memcache->set("yiimp-nethashrate-{$coin->symbol}", $coin->network_hash, 60);
        }
    }
    
    // Outstanding data
    $outstanding_data = controller()->memcache->get("yiimp-outstanding-{$coin->symbol}");
    if($outstanding_data === false) {
        $remote = new WalletRPC($coin);
        if($remote) {
            try {
                $txoutsetinfo = $remote->gettxoutsetinfo();
                if(isset($txoutsetinfo['total_amount']) && isset($txoutsetinfo['height'])) {
                    $outstanding_data = ['amount' => $txoutsetinfo['total_amount'], 'height' => $txoutsetinfo['height']];
                }
            } catch(Exception $e) {
                try {
                    $info = $remote->getinfo();
                    if(isset($info['moneysupply'])) {
                        $outstanding_data = ['amount' => $info['moneysupply'], 'height' => $coin->block_height];
                    }
                } catch(Exception $e2) {
                    $outstanding_data = ['amount' => 0, 'height' => 0];
                }
            }
            controller()->memcache->set("yiimp-outstanding-{$coin->symbol}", $outstanding_data, $outstanding_data['amount'] ? 3600 : 300);
        }
    }
    $outstanding = floor($outstanding_data['amount'] ?? 0);
    
    // Create Outstanding display (default shows only value, hover shows height)
    $outstanding_display = $outstanding;
    if(($outstanding_data['height'] ?? 0) > 0) {
        $outstanding_display = "<span class='outstanding-amount' title='At block height {$outstanding_data['height']}'>" . $outstanding . "</span>";
    }
    
    // Age calculation
    $age_display = 'unknown';
    $block_time = controller()->memcache->get("yiimp-blocktime-{$coin->symbol}");
    if($block_time === false) {
        $remote = new WalletRPC($coin);
        if($remote) {
            try {
                $bestblockhash = $remote->getbestblockhash();
                if($bestblockhash) {
                    $block = $remote->getblock($bestblockhash);
                    if(isset($block['time'])) {
                        $block_time = $block['time'];
                        controller()->memcache->set("yiimp-blocktime-{$coin->symbol}", $block_time, 30);
                    }
                }
            } catch(Exception $e) {
                $block_time = 0;
                controller()->memcache->set("yiimp-blocktime-{$coin->symbol}", $block_time, 60);
            }
        }
    }
    
    if($block_time > 0) {
        $time_diff = time() - $block_time;
        if($time_diff < 60) $age_display = $time_diff . 's';
        elseif($time_diff < 3600) $age_display = floor($time_diff / 60) . 'm';
        elseif($time_diff < 86400) $age_display = floor($time_diff / 3600) . 'h';
        else $age_display = floor($time_diff / 86400) . 'd';
    }
    
    // Output row
    echo '<tr class="ssrow"><td><img src="'.$coin->image.'" width="18"></td>';
    echo '<td><b>'.$coin->createExplorerLink($coin->name).'</a></b></td>';
    echo '<td><b>'.$coin->symbol.'</b></td>';
    echo '<td>'.$coin->algo.'</td><td>'.$coin->version.'</td><td>'.$coin->block_height.'</td>';
    echo '<td data="'.$block_time.'">'.$age_display.'</td>';
    
    $diffnote = ($coin->algo == 'equihash' || $coin->algo == 'quark') ? '*' : '';
    echo '<td data="'.$coin->difficulty.'">'.Itoa2($coin->difficulty, 3).$diffnote.'</td>';
    echo '<td>'.$outstanding_display.'</td>';
    
    $cnx_class = (intval($coin->connections) > 3) ? '' : 'low';
    $peers_link = CHtml::link($coin->connections, "javascript:wallet_peers({$coin->id});", ['class'=>$cnx_class]);
    echo '<td>'.$peers_link.'</td>';
    
    $nethash_sfx = $coin->network_hash ? strtoupper(Itoa2($coin->network_hash)).'H/s' : '';
    echo '<td data="'.$coin->network_hash.'">'.$nethash_sfx.'</td>';
    
    echo '<td>';
    if(!empty($coin->link_bitcointalk)) echo CHtml::link('forum', $coin->link_bitcointalk, ['target'=>'_blank']);
    elseif(!empty($coin->link_site)) echo CHtml::link('site', $coin->link_site, ['target'=>'_blank']);
    echo '</td></tr>';
}

echo <<<EOT
</tbody></table>
<p style="font-size:.8em;">
    &nbsp;* Unified difficulty based on the hash target (might be different than wallet one)<br/>
    &nbsp;+ Outstanding: total coin supply (from gettxoutsetinfo, cached for 1 hour)
</p>

<div class="auto-refresh-settings">
    <h3>Auto Refresh Settings</h3>
    <div class="refresh-controls">
        <div class="refresh-input-group">
            <label for="refreshTime">Refresh interval:</label>
            <input type="number" id="refreshTime" min="0" max="3600" value="60">
            <span class="refresh-hint">seconds (0=disable)</span>
        </div>
        <button id="applyRefresh" class="apply-button">Apply</button>
        <span id="countdownDisplay" class="countdown-display">Refreshing in <span id="countdownTimer" class="countdown-timer">60</span> s...</span>
    </div>
</div>

</div></div>

<script>
(function() {
    const STORAGE_KEY = "explorer_block_refresh";
    let refreshInterval = 60, countdownTimer = null, countdownSeconds = 60;
    const refreshTimeInput = document.getElementById('refreshTime');
    const applyButton = document.getElementById('applyRefresh');
    const countdownDisplay = document.getElementById('countdownDisplay');
    const countdownTimerElement = document.getElementById('countdownTimer');
    
    function getCookie(name) {
        const value = '; ' + document.cookie;
        const parts = value.split('; ' + name + '=');
        return parts.length === 2 ? parts.pop().split(';').shift() : null;
    }
    
    function setCookie(name, value, days = 365) {
        const d = new Date();
        d.setTime(d.getTime() + (days * 86400000));
        document.cookie = name + "=" + value + ";expires=" + d.toUTCString() + ";path=/";
    }
    
    function startCountdown() {
        clearInterval(countdownTimer);
        countdownSeconds = refreshInterval;
        if(refreshInterval > 0) {
            countdownTimerElement.textContent = countdownSeconds;
            countdownDisplay.style.display = 'inline';
            countdownTimer = setInterval(() => {
                countdownSeconds--;
                countdownTimerElement.textContent = countdownSeconds;
                if(countdownSeconds <= 0) {
                    clearInterval(countdownTimer);
                    setTimeout(() => window.location.reload(true), 1000);
                }
            }, 1000);
        } else {
            countdownDisplay.style.display = 'none';
        }
    }
    
    function loadSettings() {
        const savedInterval = getCookie(STORAGE_KEY);
        if(savedInterval !== null) {
            const interval = parseInt(savedInterval);
            if(!isNaN(interval) && interval >= 0) {
                refreshInterval = interval;
                refreshTimeInput.value = interval;
            }
        }
        startCountdown();
    }
    
    function applyConfig() {
        const newInterval = parseInt(refreshTimeInput.value);
        if(isNaN(newInterval) || newInterval < 0 || newInterval > 3600) {
            alert("Invalid interval (0-3600 seconds)");
            refreshTimeInput.focus();
            return;
        }
        setCookie(STORAGE_KEY, newInterval);
        refreshInterval = newInterval;
        startCountdown();
    }
    
    loadSettings();
    applyButton.addEventListener('click', applyConfig);
    refreshTimeInput.addEventListener('keypress', e => { if(e.key === 'Enter') applyConfig(); });
})();
</script>

<br><br><br><br><br><br><br><br><br><br>
EOT;

