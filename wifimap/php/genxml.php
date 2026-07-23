<?php
declare(strict_types=1);
require __DIR__ . '/dbinfo.php';

// --- Connect ---
$mysqli = new mysqli($server, $username, $password, $database);
if ($mysqli->connect_errno) {
    http_response_code(500);
    exit('DB connection failed');
}
$mysqli->set_charset('utf8mb4');

// --- Helpers ---
function post(string $key, string $default = ''): string {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

function dateToEpochMs(string $ymd, bool $endOfDay = false): string {
    if ($ymd === '') return $endOfDay ? '32503679995000' : '0';
    $dt = DateTime::createFromFormat('Y-m-d', $ymd);
    if (!$dt) return $endOfDay ? '32503679995000' : '0';
    $seconds = (int)$dt->format('U') + ($endOfDay ? 86400 : 0);
    return (string)($seconds * 1000);
}

// --- Inputs ---
$search   = '%' . post('search_input') . '%';
$vendor   = '%' . post('vendor_input') . '%';
$preset   = '%' . post('predefined_search') . '%';
$band     = post('band', '%');
$connected = '%' . post('connected_clients') . '%';
$probing   = '%' . post('probing_clients') . '%';
$fromMs   = dateToEpochMs(post('selected_fromtime'), false);
$toMs     = dateToEpochMs(post('selected_totime'), true);

// --- Build security filter ---
$open      = post('open_network')       === 'yes';
$wep       = post('wep_network')        === 'yes';
$wpaWps    = post('wpa_wps_network')    === 'yes';
$wpaNoWps  = post('wpa_no_wps_network') === 'yes';

$clauses = [];
if ($open)     $clauses[] = "(CAPABILITIES NOT LIKE '%WEP%' AND CAPABILITIES NOT LIKE '%WPA%')";
if ($wep)      $clauses[] = "(CAPABILITIES LIKE '%WEP%')";
if ($wpaWps)   $clauses[] = "(CAPABILITIES LIKE '%WPA%' AND CAPABILITIES LIKE '%WPS%' AND CAPABILITIES NOT LIKE '%WEP%')";
if ($wpaNoWps) $clauses[] = "(CAPABILITIES LIKE '%WPA%' AND CAPABILITIES NOT LIKE '%WPS%' AND CAPABILITIES NOT LIKE '%WEP%')";

// If nothing checked, return nothing
if (empty($clauses)) {
    header('Content-type: text/xml;charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><markers/>';
    exit;
}
$securityFilter = '(' . implode(' OR ', $clauses) . ')';

// --- One safe, parameterised query ---
$sql = "SELECT * FROM network
        WHERE (SSID LIKE ? OR BSSID LIKE ?)
          AND LASTTIME BETWEEN ? AND ?
          AND BAND LIKE ?
          AND CONNECTED_CLIENTS LIKE ?
          AND PROBING_CLIENTS  LIKE ?
          AND VENDOR           LIKE ?
          AND PREDEFINED_SEARCH LIKE ?
          AND $securityFilter";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param(
    'sssssssss',
    $search, $search, $fromMs, $toMs, $band,
    $connected, $probing, $vendor, $preset
);
$stmt->execute();
$result = $stmt->get_result();

// --- Build XML (kept for JS compatibility) ---
$dom  = new DOMDocument('1.0', 'UTF-8');
$root = $dom->appendChild($dom->createElement('markers'));

while ($row = $result->fetch_assoc()) {
    $node = $dom->createElement('marker');
    foreach ($row as $col => $val) {
        $node->setAttribute(strtolower($col), (string)$val);
    }
    $root->appendChild($node);
}

header('Content-type: text/xml;charset=UTF-8');
echo $dom->saveXML();
