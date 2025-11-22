<?php
// Initialize variables
$pager = '';

// Redirect if coin not found
if (!$coin) $this->goback();

// Set page title
$this->pageTitle = $coin->name . ' Block Explorer';

// Get parameters
$start = (int) getiparam('start');
$validShowOptions = [21, 101, 501, 1001];
$show = isset($_GET['show']) && in_array(($_GET['show'] = (int)$_GET['show']), $validShowOptions) 
    ? $_GET['show'] 
    : 21;

// Add favicon and styles
echo <<<END
<script type="text/javascript">
$(function() {
    $('#favicon').remove();
    $('head').append('<link href="{$coin->image}" id="favicon" rel="shortcut icon">');
});
</script>
<style type="text/css">
/* Table styling */
table.dataGrid2 { margin-top: 0; }
span.monospace { font-family: monospace; }

/* Search container */
#search-container {
    width: 100%;
    max-width: 800px;
    margin: 16px 0;
}

/* Horizontal search form */
#search-form {
    display: flex;
    gap: 8px;
}

/* Pagination styling */
#pager {
    margin: 16px 0;
    text-align: left;
    width: 100%;
}

/* Show more button styling */
.show-more {
    margin: 16px 0;
    text-align: left;
}
.show-more a {
    display: inline-block;
    padding: 6px 12px;
    background: #2c2f33;
    border: 1px solid #4a4a4a;
    border-radius: 3px;
    color: #cccccc;
    text-decoration: none;
    font-size: 0.9em;
}
.show-more a:hover {
    background: #3d4147;
    border-color: #5a5a5a;
    color: #ffffff;
}
</style>
END;

// Check if multi-algo support is needed
$multiAlgos = $coin->multialgos || versionToAlgo($coin, 0) !== false;

// Main container
echo '<br/>';
echo '<div class="main-left-box">';
echo '<div class="main-left-title">' . $coin->name . ' Explorer</div>';
echo '<div class="main-left-inner" style="padding-left: 8px; padding-right: 8px;">';

// Block table
echo '<table class="dataGrid2">';
echo "<thead><tr><th>Height</th><th>Age</th><th>Type</th>";
if ($multiAlgos) echo "<th>Algo</th>";
echo "<th>Tx</th><th>Size</th><th>Value Out</th><th>Difficulty</th><th>Extracted by</th></tr></thead>";

// RPC connection
$remote = new WalletRPC($coin);

// Calculate display range
$endHeight = max(1, $coin->block_height - $show + 1);

// Display recent blocks
for($i = $coin->block_height; $i >= $endHeight; $i--) {
    $hash = $remote->getblockhash($i);
    if(!$hash) continue;

    $block = $remote->getblock($hash);
    if(!$block) continue;

    $d = datetoa2($block['time']);
    $tx = count($block['tx']);
    $block_size = isset($block['size']) ? $block['size'] : 0;
    $diff = $block['difficulty'];
    $algo = versionToAlgo($coin, $block['version']);
    
    // Block type detection
    $type = '';
    if (arraySafeval($block,'nonce',0) > 0) $type = 'PoW';
    else if (isset($block['auxpow'])) $type = 'Aux';
    else if (isset($block['mint']) || strstr(arraySafeVal($block,'flags',''), 'proof-of-stake')) $type = 'PoS';

    // Special case for ZEC
    if ($type == '' && $coin->symbol=='ZEC') $type = 'PoW';

    // Address extraction
    $address = 'Unknown';
    foreach ($block['tx'] as $txid) {
        $tx_data = $remote->getrawtransaction($txid, 1);
        if (!$tx_data) continue;
        
        foreach ($tx_data['vout'] as $vout) {
            if (isset($vout['scriptPubKey']['addresses']) && count($vout['scriptPubKey']['addresses']) > 0) {
                $address = "<span class='monospace'>" . $vout['scriptPubKey']['addresses'][0] . "</span>";
                break 2;
            }
        }
    }

    // Calculate total value out
    $value_out = 0;
    foreach ($block['tx'] as $txid) {
        $tx_data = $remote->getrawtransaction($txid, 1);
        if (!$tx_data) continue;
        
        foreach ($tx_data['vout'] as $vout) {
            $value_out += $vout['value'];
        }
    }

    // Format value output
    $value_out_formatted = ($value_out == floor($value_out)) 
        ? number_format($value_out, 0) 
        : number_format($value_out, 8);

    // Format difficulty and size
    $diff_formatted = number_format($diff, 2);
    $size_kb = number_format($block_size / 1024, 2);

    // Output table row
    echo '<tr class="ssrow">';
    echo '<td>' . $coin->createExplorerLink($i, ['height'=>$i]) . '</td>';
    echo '<td>' . $d . '</td>';
    echo '<td>' . $type . '</td>';
    if ($multiAlgos) echo "<td>$algo</td>";
    echo '<td>' . $tx . '</td>';
    echo '<td>' . $size_kb . ' kB</td>';
    echo '<td>' . $value_out_formatted . '</td>';
    echo '<td>' . $diff_formatted . '</td>';
    echo '<td>' . $address . '</td>';
    echo '<td><span class="monospace"></span></td>';
    echo "</tr>";
}

// Close table
echo "</table>";

// Generate "Show last X blocks..." button
function generateShowMoreLink($currentShow, $coin) {
    $optionsMap = [
        21 => ['next' => 101, 'text' => 'Show last 100 blocks...'],
        101 => ['next' => 501, 'text' => 'Show last 500 blocks...'],
        501 => ['next' => 1001, 'text' => 'Show last 1000 blocks...'],
        1001 => null
    ];
    
    $option = $optionsMap[$currentShow] ?? null;
    if (!$option) return '';
    
    // Preserve existing parameters
    $params = ['show' => $option['next']];
    if (isset($_GET['symbol'])) $params['symbol'] = $_GET['symbol'];
    if (isset($_GET['id'])) $params['id'] = $_GET['id'];
    
    return $coin->createExplorerLink($option['text'], $params);
}

$showMoreLink = generateShowMoreLink($show, $coin);

// Output show more button
if ($showMoreLink) {
    echo '<div class="show-more">';
    echo $showMoreLink;
    echo '</div>';
}

// Search container
$actionUrl = $coin->visible ? '/explorer/' . $coin->symbol : '/explorer/search?id=' . $coin->id;
echo '<div id="search-container">';
echo '<form id="search-form" action="' . $actionUrl . '" method="POST">';
echo '<input type="text" name="height" class="main-text-input" placeholder="Block Height" style="width: 80px;">';
echo '<input type="text" name="txid" class="main-text-input" placeholder="Transaction Hash" style="width: 400px;">';
echo '<input type="submit" value="Search" class="main-submit-button" style="width: 100px;">';
echo '</form></div>';

// Auto refresh control
$refreshKey = 'coin_explorer_refresh';
$refreshInterval = isset($_COOKIE[$refreshKey]) ? (int)$_COOKIE[$refreshKey] : 60;
?>

<div style="margin-top:20px;padding:15px;background:rgba(33, 37, 41, 0.7);border-radius:4px;border:1px solid #4a4a4a;">
    <h3 style="margin-top:0;font-size:1.1em;color:#ffffff;">Auto Refresh Settings</h3>
    <div style="display:flex;align-items:center;gap:15px;flex-wrap:wrap;">
        <div>
            <label style="font-weight:500;color:#cccccc;">Refresh interval:</label>
            <input type="number" id="refreshTime" min="0" value="<?=$refreshInterval?>" 
                style="width:80px;padding:5px;background:#2c2f33;color:#ffffff;
                border:1px solid #4a4a4a;border-radius:3px;">
            <span style="color:#aaaaaa;font-size:0.9em;">seconds (0=disable)</span>
        </div>
        <div>
            <button id="applyRefresh" 
                style="padding:6px 15px;background:#2c2f33;color:#ffffff;
                border:1px solid #4a4a4a;border-radius:3px;cursor:pointer;">
                Apply
            </button>
            <span id="refreshStatus" 
                style="margin-left:15px;color:#888888;font-size:0.9em;"></span>
        </div>
    </div>
</div>

<script>
// Auto refresh functionality
(function() {
    const STORAGE_KEY = "coin_explorer_refresh";
    let refreshInterval = <?=$refreshInterval?>;
    
    const refreshTimeInput = document.getElementById('refreshTime');
    const applyButton = document.getElementById('applyRefresh');
    const statusElement = document.getElementById('refreshStatus');
    
    refreshTimeInput.value = refreshInterval;
    
    let countdownTimer = null;
    let countdownSeconds = refreshInterval;
    
    const updateCountdown = () => {
        statusElement.textContent = `Refreshing in ${countdownSeconds}s...`;
    };
    
    const startCountdown = () => {
        clearInterval(countdownTimer);
        countdownSeconds = refreshInterval;
        updateCountdown();
        
        countdownTimer = setInterval(() => {
            countdownSeconds--;
            updateCountdown();
            
            if(countdownSeconds <= 0) {
                clearInterval(countdownTimer);
                statusElement.textContent = "Refreshing...";
                setTimeout(() => {
                    window.location.reload(true);
                }, 500);
            }
        }, 1000);
    };
    
    const applyConfig = () => {
        const newInterval = parseInt(refreshTimeInput.value);
        
        if(isNaN(newInterval) || newInterval < 0) {
            statusElement.textContent = "Invalid interval";
            refreshTimeInput.focus();
            return;
        }
        
        // Save to cookie
        document.cookie = `${STORAGE_KEY}=${newInterval}; path=/`;
        refreshInterval = newInterval;
        
        if(refreshInterval > 0) {
            startCountdown();
        } else {
            statusElement.textContent = "Auto refresh disabled";
            clearInterval(countdownTimer);
        }
    };
    
    applyButton.addEventListener('click', applyConfig);
    refreshTimeInput.addEventListener('keypress', e => {
        if(e.key === 'Enter') applyConfig();
    });
    
    if(refreshInterval > 0) {
        startCountdown();
    }
})();
</script>

<?php
echo '</div>';
echo '</div>';
