# Protest Itinerary Map

Create and display protest march itineraries on interactive maps with route planning, attendance tracking, and email notifications.

## Interactive Map Builder

- Drag-and-drop waypoint editor with address search via Nominatim geocoding
- Automatic route calculation between waypoints using OpenRouteService (foot-walking profile)
- Choose from 5 waypoint types: Start, End, Checkpoint, Meeting Point, Rest Stop
- Support for up to 50 waypoints per itinerary (ORS limit)

## Attendance & Notifications

- "I'll be there" one-click attendance counter with IP-based duplicate protection
- Two-step email subscription: protest updates + optional union newsletter opt-in
- Admin notification panel to send email blasts to confirmed protest subscribers
- Automatic data retention: protest emails deleted 24 hours after event, union emails deleted 1 year after event

## Public Display

- Embed maps via `[protest_map]` shortcode or Gutenberg block
- Optional sidebar listing all waypoints with click-to-zoom interaction
- Iframe embedding support with per-itinerary and global toggles
- Paris metro lines overlay using live IDFM open data
- CARTO Positron base tiles for a clean, readable map

## Key Features

- **Multilingual:** Works with content in any language
- **Translation-Ready:** All strings are internationalized (POT + French translation included)
- **Secure:** Nonce verification on all AJAX endpoints, capability checks, input sanitization, and prepared SQL queries
- **GitHub Updates:** Automatic updates from GitHub releases
- **Privacy-First:** Subscriber emails are automatically purged after configurable retention periods via WP-Cron

## Requirements

- A free [OpenRouteService](https://openrouteservice.org/dev/#/signup) API key for route calculations
- WordPress 6.0 or higher
- PHP 7.4 or higher

## Installation

1. Upload the `protest-itinerary-map` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Protest Itineraries → Settings** and enter your OpenRouteService API key
4. Create your first itinerary via **Protest Itineraries → Add New**

## FAQ

### Where do I get an OpenRouteService API key?

Sign up for free at [openrouteservice.org](https://openrouteservice.org/dev/#/signup). The free tier allows 2,000 requests per day, which is more than enough for most use cases.

### Can I embed a map on another site?

Yes. Enable global iframe embedding in **Protest Itineraries → Settings**, then enable it per itinerary in the Embed Options meta box. Copy the provided iframe code.

### How does the metro overlay work?

The metro overlay fetches live data from the Île-de-France Mobilités (IDFM) open data API. Click the metro toggle button on the map to show Paris metro lines and stations with their official colors.

### When are subscriber emails deleted?

Protest-only subscribers are automatically deleted 24 hours after the event date. Union newsletter subscribers are deleted 1 year after the event date. Unconfirmed subscriptions are purged after 48 hours.

### Does it work with Guilamu Bug Reporter?

Yes. If [Guilamu Bug Reporter](https://github.com/guilamu/guilamu-bug-reporter) is installed, it automatically registers for AI-assisted bug reporting. The plugin provides its version, slug, and GitHub repository to the bug reporter.

### Can I customize the map appearance?

Set default map center coordinates and zoom level in **Protest Itineraries → Settings** using the address autocomplete field.

## Project Structure

```
.
├── protest-itinerary-map.php       # Main plugin file, hooks, AJAX handlers
├── uninstall.php                   # Cleanup on uninstall
├── README.md
├── admin
│   ├── admin-meta-box.php          # Itinerary info meta box template
│   ├── admin-subscribers.php       # Subscribers admin page template
│   ├── admin.css                   # Admin styles
│   └── admin.js                    # Admin map builder logic
├── assets
│   └── icons
│       ├── checkpoint.svg          # Checkpoint waypoint icon
│       ├── end.svg                 # End waypoint icon
│       ├── meeting-point.svg       # Meeting point waypoint icon
│       ├── rest-stop.svg           # Rest stop waypoint icon
│       └── start.svg               # Start waypoint icon
├── block
│   ├── block.json                  # Gutenberg block metadata
│   ├── editor.js                   # Block editor script
│   └── render.php                  # Block server-side render
├── iframe
│   └── iframe-map.php              # Iframe embed template
├── includes
│   ├── class-github-updater.php    # GitHub auto-updates
│   ├── class-iframe-endpoint.php   # Iframe rewrite rules and rendering
│   ├── class-meta-box.php          # Meta box registration and save logic
│   ├── class-notification.php      # Email notification helpers
│   ├── class-post-type.php         # Custom post type registration
│   ├── class-settings.php          # Plugin settings page
│   ├── class-shortcode.php         # [protest_map] shortcode
│   ├── class-subscriber.php        # Subscriber DB table and CRUD
│   └── class-vote-handler.php      # Attendance vote AJAX handler
├── languages
│   ├── protest-itinerary-map-fr_FR.mo  # French translation (binary)
│   ├── protest-itinerary-map-fr_FR.po  # French translation (source)
│   └── protest-itinerary-map.pot       # Translation template
└── public
    ├── public-map.php              # Frontend map template
    ├── public.css                  # Frontend styles
    └── public.js                   # Frontend map rendering and interactions
```

## Changelog

### 1.0.0
- Initial release
- Interactive map builder with drag-and-drop waypoints
- OpenRouteService route calculation (foot-walking profile)
- Nominatim address search and geocoding
- 5 waypoint types with custom SVG icons
- Attendance counter with IP duplicate protection
- Two-step email subscription (protest + union opt-in)
- Email notifications to subscribers
- Automatic subscriber purge (24h protest, 1yr union, 48h unconfirmed)
- Iframe embedding support
- `[protest_map]` shortcode and Gutenberg block
- Paris metro lines overlay via IDFM open data
- CARTO Positron tiles
- Plugin settings page under Protest Itineraries menu
- GitHub auto-updater
- Guilamu Bug Reporter integration
- French translation (POT + PO)

## License

This project is licensed under the GNU Affero General Public License v3.0 (AGPL-3.0) - see the [LICENSE](LICENSE) file for details.

---

<p align="center">
  Made with love for the WordPress community
</p>
