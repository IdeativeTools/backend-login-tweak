# Changelog

All notable changes to this project are documented in this file.

## [1.1.1] - 2026-05-17

- **First public version for TER**

## [1.0.3] - 2026-05-10

### Fixed

- **Bundled background fallback path:** `LoginStyleApplicator` now resolves the built-in image from `Resources/Public/Pictures/road.webp` (new directory structure).

### Changed

- **Default extension configuration values:** `loginBackgroundImage` and `loginLogo` now point to bundled `EXT:id_be_login/Resources/Public/Pictures/*` assets.
- **Version bump:** `composer.json` updated to `1.0.3`.


## [1.0.2] - 2026-04-22

### Fixed

- **Login appearance module (dark mode):** Tabs and background-source controls no longer use custom CSS that forced light-theme nav colours on TYPO3 semantic tokens; the UI now uses core `.nav-tabs`, `.btn-group`, `btn-default`, and `active` so contrast is correct in light and dark backend schemes.
- **Flash messages:** Save / error messages after submitting the settings form are queued with **`storeInSession: true`** so they still show after the **303 redirect** (PRG).
- **Login box “bottom right”:** From **768px** viewport width up, the main container gets extra **bottom padding** so the login card sits above TYPO3’s **absolutely positioned footnote** (`inset-inline-end` / bottom corner), avoiding overlap.

### Changed

- **Public CSS:** Renamed `login-appearance-backend(.min).css` → **`be-login-settings(.min).css`** (clearer name; new URL helps caches pick up the updated bundle).

## [1.0.1] - 2026-04-20

### Fixed

- **Fluid:** Inline `<style>` blocks in `LoginAppearance.html` contained `{` / `}` that Fluid parsed as template syntax, so placeholders such as `{loginBackgroundSwitchToLocalUri}` were output literally. Styles were moved out of the template (see below).
- **Login box position grid:** Each cell is now a `<label>` around the radio so the clickable and hover area matches the full square (not only the small input).

### Changed

- **Backend CSS:** Module-specific rules live in `Resources/Public/Css/be-login-settings.css`; the backend loads `be-login-settings.min.css`. Covers color-picker width and position grid layout (tabs/buttons use core backend styles as of **1.0.2**).
- **Login appearance UI (1.0.1):** Custom grey/purple chrome for tabs and background-source buttons; **superseded in 1.0.2** by core styling for theme compatibility.
- **Forms:** Removed obsolete `autocorrect` attributes from backend color picker inputs.
- **JavaScript:** Production loads minified ES modules (`login-appearance-tabs.min.js`, `login-fal-folder-picker.min.js`); sources are kept alongside for editing.

## [1.0.0] - 2026-04-19

### Added

- Backend module **Login appearance** under System (admin): tabbed UI for background (local / FAL / remote), logo, colors, footnote, login box layout, and **About** (extension summary + publisher info with SVG logo).
- **Document header** Save action and **private browsing** preview link with tooltip.
- **Alignment** of shared fields with **EXT:backend** extension configuration (`loginBackgroundImage`, `loginLogo`, `loginHighlightColor` ↔ module fields).
- **French translations** for backend labels: `fr.locallang_be.xlf`, `Modules/fr.login_appearance.xlf`.
- Remote background **Content-Security-Policy** mutations for required image hosts.
- **Backend color scheme cookie** middleware so the login page can follow light/dark preference when possible.
- Shared **`LoginHexColor`** helper and small refactors in services/controller (no functional change intended).
