Phoenix Live – Cloud Relay: Deployment Guide
============================================

## What these files do

  relay.php          → PHP API that stores live results on your web server.
                       The Phoenix desktop app POSTs to it; mobile viewers poll it.
  phoenix-viewer.html → Self-contained HTML page for mobile WebView / browser.
                       Shows live results from the relay with auto-refresh every 3 s.

## One-time server setup (one.com or any PHP host)

1. Create a folder on your server, e.g.  /phoenix-live/

2. Open relay.php and change the API key on line 10:
     define('API_KEY', 'change-this-to-a-strong-secret');
   Pick any long random string (e.g. 32+ characters).

3. Upload BOTH files to that folder via SFTP or the one.com File Manager:
     /phoenix-live/relay.php
     /phoenix-live/phoenix-viewer.html

4. Verify it works by opening a browser and visiting:
     https://yoursite.com/phoenix-live/relay.php?action=events
   You should see:  {"events":[]}
   (The /data/ subdirectory is created automatically on first use.)

## Phoenix app setup (do this before each event)

1. In Phoenix, click  ☁️ Cloud Relay  (next to the SFTP Settings button).

2. Fill in:
   • Relay URL  →  https://yoursite.com/phoenix-live/relay.php
   • API Key    →  the secret you set in relay.php
   • Event ID   →  a short slug for this event, e.g.  pump-track-round-3
                   (letters, numbers, hyphens only – change for each event)

3. Click  Test Connection  to confirm the relay is reachable.

4. Click  Save Settings  then tick  Enable Cloud Relay.

5. Publish results as normal. Phoenix will push data to the relay automatically
   after every WebSocket snapshot. The status line below the library will show:
     Cloud Relay: ✅ Live · last push 2s ago · event: pump-track-round-3

## Mobile app integration

Option A – Hosted viewer (simplest):
  Give this URL to your mobile app WebView:
    https://yoursite.com/phoenix-live/phoenix-viewer.html?eventId=YOUR-EVENT-ID

  The relayUrl is auto-detected because the HTML and relay.php are in the same folder.

Option B – Bundled asset (works offline / no URL bar):
  Copy phoenix-viewer.html into your app's assets, then load:
    file:///android_asset/phoenix_viewer.html
      ?relayUrl=https://yoursite.com/phoenix-live/relay.php
      &eventId=YOUR-EVENT-ID
  (iOS uses the WKWebView loadFileURL or a localhost server approach.)

Option C – Event picker (no eventId in URL):
  Just open:
    https://yoursite.com/phoenix-live/phoenix-viewer.html
  The viewer shows a list of all active events and lets the viewer tap to select one.

## Changing events

  • Set a new Event ID in Cloud Relay settings for each new event.
  • Old event data is automatically cleaned up after 48 hours.
  • The mobile viewer event picker will only show recent events.

## Data & bandwidth notes

  • Logos are stripped from relay payloads by default to minimise upload size.
    Enable "Include event logos" in settings if branding in the viewer is needed.
  • The footer banner image is always stripped (decorative only, can exceed 1 MB).
  • Duplicate snapshots are skipped – only changed data is pushed to the server.

## Troubleshooting

  "Connection error" in Test Connection
    → Check the Relay URL is the full path to relay.php (not just the folder).
    → Ensure PHP is enabled on the hosting plan.

  HTTP 401 Unauthorized
    → The API Key in Phoenix doesn't match the API_KEY in relay.php.

  HTTP 500 / cannot create /data/ directory
    → one.com should allow PHP to create subdirectories. Check folder permissions
      (should be 755). You can also create the /data/ subdirectory manually.

  Mobile viewer shows "No live events"
    → Make sure Cloud Relay is enabled in Phoenix and results have been published
      at least once.
    → Check that the relayUrl parameter points to relay.php, not the viewer.

  Mobile viewer shows stale data
    → Data is polled every 3 seconds. If the Phoenix app is closed or relay is
      disabled, the last pushed data remains visible until it expires (48 h).
