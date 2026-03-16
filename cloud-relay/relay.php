<?php
/**
 * Phoenix Live Relay
 * Upload this file to your website, e.g. at:
 *   /phoenix-live/relay.php
 *
 * SETUP:
 *   Preferred: set environment variable API_KEY on your host.
 *   Fallback:  change the default key below.
 */
define('API_KEY',            getenv('API_KEY') ?: 'change-this-to-a-strong-secret');
define('DATA_DIR',           __DIR__ . '/data');
define('MAX_EVENT_AGE_HOURS', 48);

// ---------------------------------------------------------------------------
// Headers
// ---------------------------------------------------------------------------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key, X-API-Key');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ---------------------------------------------------------------------------
// Ensure data directory and .htaccess protection
// ---------------------------------------------------------------------------
if (!is_dir(DATA_DIR)) {
    @mkdir(DATA_DIR, 0755, true);
}
$htaccess = DATA_DIR . '/.htaccess';
if (!file_exists($htaccess)) {
    @file_put_contents($htaccess, "Deny from all\n");
}

$method = $_SERVER['REQUEST_METHOD'];

// ===========================================================================
// POST  –  receive snapshot from Phoenix desktop app
// ===========================================================================
if ($method === 'POST') {

    $apiKey = $_SERVER['HTTP_X_API_KEY']
           ?? $_SERVER['HTTP_X_Api_Key']
           ?? ($_GET['apiKey'] ?? '');

    if ($apiKey !== API_KEY) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $raw = file_get_contents('php://input');
    if (!$raw) {
        http_response_code(400);
        echo json_encode(['error' => 'Empty body']);
        exit;
    }

    $body = json_decode($raw, true);
    if (!$body || empty($body['eventId']) || empty($body['module'])) {
        http_response_code(400);
        echo json_encode(['error' => 'eventId and module are required']);
        exit;
    }

    // Sanitise inputs
    $eventId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $body['eventId']);
    $module  = preg_replace('/[^a-zA-Z0-9_]/',   '', $body['module']);

    if (!$eventId || strlen($eventId) > 120 || !$module) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid eventId or module']);
        exit;
    }

    $dataFile = DATA_DIR . '/event-' . $eventId . '.json';

    // Load existing event data
    $eventData = [];
    if (file_exists($dataFile)) {
        $existing = @file_get_contents($dataFile);
        if ($existing) $eventData = json_decode($existing, true) ?: [];
    }

    // Store snapshot for this module
    $eventData[$module] = [
        'module'      => $module,
        'html'        => $body['html']        ?? '',
        'views'       => $body['views']       ?? null,
        'branding'    => $body['branding']    ?? null,
        'eventName'   => $body['eventName']   ?? '',
        'generatedAt' => $body['generatedAt'] ?? date('c'),
        'updatedAt'   => (int) round(microtime(true) * 1000),
    ];

    // Write (with exclusive lock)
    $ok = file_put_contents($dataFile, json_encode($eventData), LOCK_EX);
    if ($ok === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to write data – check directory permissions']);
        exit;
    }

    echo json_encode(['success' => true, 'module' => $module, 'eventId' => $eventId]);
    exit;
}

// ===========================================================================
// GET  –  serve data to mobile viewer
// ===========================================================================
if ($method === 'GET') {

    cleanOldEvents();

    $action = $_GET['action'] ?? 'snapshot';

    // -----------------------------------------------------------------------
    // GET ?action=events  –  list all known events
    // -----------------------------------------------------------------------
    if ($action === 'events') {
        $events = [];
        foreach (glob(DATA_DIR . '/event-*.json') as $file) {
            $base    = basename($file, '.json');
            $eventId = preg_replace('/^event-/', '', $base);
            $data    = json_decode(@file_get_contents($file), true) ?: [];

            $modules    = array_keys($data);
            $lastUpdate = 0;
            $eventName  = '';

            foreach ($data as $snap) {
                $ts = (int) ($snap['updatedAt'] ?? 0);
                if ($ts > $lastUpdate) {
                    $lastUpdate = $ts;
                    $eventName  = $snap['eventName'] ?? '';
                }
            }

            $events[] = [
                'eventId'    => $eventId,
                'eventName'  => $eventName ?: $eventId,
                'modules'    => $modules,
                'lastUpdate' => $lastUpdate ? date('c', (int) ($lastUpdate / 1000)) : null,
            ];
        }

        // Sort most-recently updated first
        usort($events, fn($a, $b) => strcmp($b['lastUpdate'] ?? '', $a['lastUpdate'] ?? ''));

        echo json_encode(['events' => $events]);
        exit;
    }

    // -----------------------------------------------------------------------
    // GET ?action=snapshot&eventId=xxx  –  return all module data for event
    // -----------------------------------------------------------------------
    if ($action === 'snapshot' && !empty($_GET['eventId'])) {
        $eventId  = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['eventId']);
        $dataFile = DATA_DIR . '/event-' . $eventId . '.json';

        if (!file_exists($dataFile)) {
            echo json_encode(['snapshots' => [], 'eventId' => $eventId]);
            exit;
        }

        $data = json_decode(@file_get_contents($dataFile), true) ?: [];
        echo json_encode(['snapshots' => $data, 'eventId' => $eventId]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action or missing eventId']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

// ---------------------------------------------------------------------------
// Helper: remove event files older than MAX_EVENT_AGE_HOURS
// ---------------------------------------------------------------------------
function cleanOldEvents(): void
{
    $maxAge = MAX_EVENT_AGE_HOURS * 3600;
    $now    = time();
    foreach (glob(DATA_DIR . '/event-*.json') as $file) {
        if (($now - filemtime($file)) > $maxAge) {
            @unlink($file);
        }
    }
}
