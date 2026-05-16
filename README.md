[![TYPO3 14.3 LTS](https://img.shields.io/badge/TYPO3-14.3%20LTS-orange.svg?logo=typo3)](https://get.typo3.org/version/14)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-informational)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html)

<img src="Resources/Public/Icons/Extension.svg" alt="id_be_login extension" width="120px"/>

# id_be_login (Ideative TYPO3 Backend Login Tweak)

**Current release:** `1.1.1` (see `composer.json`).
TYPO3 backend extension that adds an **admin-only module** to customize the **administrator login screen** (appearance only; authentication is unchanged).

**Extension key:** `id_be_login`
**Composer package:** `ideative/t3-be-login`

## Requirements

- TYPO3 **14.3 LTS** / **v14.3+** (`typo3/cms-backend`, `typo3/cms-core`, …)
- **PHP 8.4+**

## Where to find it

**System → Login appearance** (`/module/system/login-appearance`).
Access: users with admin privileges.

## What you can configure

| Area           | What it does                                                                                                                                                                                                                                                                                                       |
|----------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Background** | **Local** file path (public resource), **FAL folder** (random JPG/JPEG/PNG/WebP among direct children), or **remote** random image (Lorem Picsum / danielpetrica API). CSP is extended for the remote hosts. Unauthenticated FAL reads use a short permission bypass only while resolving a public background URL. |
| **Logo**       | Path to SVG or raster logo; optional alt text.                                                                                                                                                                                                                                                                     |
| **Colors**     | Login card background (hex or default light/dark), sign-in button highlight color.                                                                                                                                                                                                                                 |
| **Footnote**   | Plain text below the card (HTML stripped; line breaks kept).                                                                                                                                                                                                                                                       |
| **Login box**  | Position (3×3 grid + list), opacity (5% steps), corner radius (px). **Bottom-right** placement adds extra bottom space on wide viewports so the card does not cover the footnote (core positions the footnote absolute bottom-end from 768px up).                                                                  |
| **About**      | Short description of the extension and publisher contact block (with logo).                                                                                                                                                                                                                                        |

Settings are stored in **`$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['id_be_login']`**.
**Background image**, **login logo**, and **sign-in button color** are **kept in sync** with the corresponding options in **EXT:backend** (`loginBackgroundImage`, `loginLogo`, `loginHighlightColor`) when you save in this module or when values are read, so Site Settings and this module stay aligned.

Since `1.0.3`, bundled public assets are located under `Resources/Public/Pictures/` and extension-configuration defaults point to:

- `EXT:id_be_login/Resources/Public/Pictures/road.webp`
- `EXT:id_be_login/Resources/Public/Pictures/Ideative-logo.svg`

## UX details

- **Save** lives in the **document header** (TYPO3 default pattern); the form uses a single POST with form protection. Flash feedback after save uses a **session** flash message so it still appears after the **POST → redirect → GET** flow.
- **“Test in private browsing”** in the header links to the backend login URL and includes a tooltip explaining how to open it in a private/incognito window to preview without the current session.
- Backend **color scheme** (light/dark) can be reflected on the login page via a **cookie** set while a backend user is logged in, so the login card matches TYPO3’s theme when appropriate.
- The module **settings UI** (tabs, segmented background-source controls, buttons) relies on **core backend / Bootstrap styling** so **light and dark** backend themes stay readable. Layout-only tweaks live in `Resources/Public/Css/be-login-settings(.min).css` (color picker width, login-box position grid).

## Languages

- English (default XLF)
- French: `fr.locallang_be.xlf`, `Modules/fr.login_appearance.xlf`

## Installation

Via Composer (path or VCS repository as in your project):

```bash
composer require ideative/t3-be-login:^1.0
```

Activate the extension in the Extension Manager if needed, then open **System → Login appearance**.

## License

GPL-2.0-or-later (see `composer.json`).
