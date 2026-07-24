# Praise Him More

Static site for the Praise Him More website with a more reliable YouTube livestream experience.

## Features in this branch

- Primary YouTube tabs: `Videos` and `Livestream`.
- Livestream tab is only shown when a live broadcast is detected.
- Radio fade in/out transition is set to 3.5 seconds.
- If livestream embedding is disabled, users can still open the relevant YouTube page directly.
- Site uses a server endpoint to confirm live status instead of iframe probing.

## Files

- `index.html`: main site and UI logic.
- `youtube-live-status.php`: server endpoint used by the site to verify active livestreams.
- `youtube-live-status.php` cache: `.youtube-live-cache.json` (created automatically).

## GitHub Pages (static preview)

The GitHub Pages deployment is still useful for previewing layout and interactivity.

- GitHub Pages cannot run server-side PHP.
- When hosted on GitHub Pages, the livestream check will gracefully degrade and hide the Livestream tab.

## Deploy to GoDaddy (recommended production)

1. Upload all files at the repository root to your GoDaddy hosting directory.
2. Ensure PHP is enabled on the host and that `youtube-live-status.php` is reachable.
3. Configure `YOUTUBE_API_KEY` in your PHP environment:
   - GoDaddy cPanel: add an environment variable for the account/site, or
   - Add a `.user.ini` / hosting-specific config if available:
     ```ini
     YOUTUBE_API_KEY="YOUR_YOUTUBE_DATA_API_V3_KEY"
     ```
   - Optional: `YOUTUBE_CHANNEL_ID` can also be set, otherwise the script defaults to
     `UCmRXtIEIHqHUkGFxixH35qQ`.
4. Optional but recommended: confirm write permission for the site root so
   `.youtube-live-cache.json` can be written.
5. Verify deployment:
   - Open `https://your-domain.example/youtube-live-status.php?channel=UCmRXtIEIHqHUkGFxixH35qQ`
   - Expected response:
     ```json
     {"live":false,"videoId":"","checkedAt":1680000000,"cached":false,"source":"youtube-api"}
     ```
6. Push updates by committing to `main` and letting GitHub Pages / GoDaddy publish your site as normal.
