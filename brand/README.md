# Lawzeek brand

This folder is not shipped in the plugin zip.

## The mark

A logic gate drawn as a "D": two inputs on the left, one output on the right.

## Colours

| Name | Hex | Used for |
|---|---|---|
| Dark brown | `#2A2219` | App-icon square, wordmark, admin text and dark buttons |
| Cream | `#F7F4ED` | Gate body, admin page background |
| Yellow | `#F5BC41` | Input pins (left) |
| Purple | `#7473ED` | Output wire and dot (right), admin accents |

Admin buttons with white text use darker purples, `#5A58D6` and `#4644B8`, so the text meets WCAG AA contrast.

## Files

| Path | What it is |
|---|---|
| `source/` | The original logo files: app icon, light and dark lockups, preview, colour notes |
| `../assets/images/logo.svg` | Plugin logo (the app icon, with an accessible name) |
| `src/icon.html`, `src/banner.html` | Sources for the WordPress.org listing images |
| `../.wordpress-org/` | Rendered icon and banners for WordPress.org; rebuild them with `npm run brand:wporg` |

The one-colour WordPress admin menu icon is the same mark redrawn with filled shapes only, because WordPress recolours menu icons by their fill. It is kept in `includes/class-lzcrs-admin.php` (`MENU_ICON`).
