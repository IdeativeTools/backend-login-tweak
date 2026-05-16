<?php
/** @noinspection PhpPipeOperatorCanBeUsedInspection */

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Ideative\T3BeLogin\Service;

use Ideative\T3BeLogin\LoginBackgroundSource;
use Ideative\T3BeLogin\LoginBoxPosition;
use Ideative\T3BeLogin\LoginHexColor;
use Ideative\T3BeLogin\LoginRemoteBackgroundProvider;
use Ideative\T3BeLogin\Middleware\BackendColorSchemeCookieMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use ReflectionException;
use ReflectionProperty;
use Throwable;
use TYPO3\CMS\Backend\LoginProvider\Event\ModifyPageLayoutOnLoginProviderSelectionEvent;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\SystemResource\Exception\SystemResourceException;
use TYPO3\CMS\Core\SystemResource\Publishing\SystemResourcePublisherInterface;
use TYPO3\CMS\Core\SystemResource\SystemResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\View\ViewInterface;

/**
 * Applies id_be_login extension settings to the backend login screen (PageRenderer, Fluid view)
 * and temporary overrides for EXT:backend login logo resolution.
 */
final readonly class LoginStyleApplicator
{
    /**
     * Bundled fallback when remote URL cannot be built, or when FAL folder mode has no usable images.
     */
    private const string BUNDLED_FALLBACK_BACKGROUND_RESOURCE = 'EXT:id_be_login/Resources/Public/Pictures/road.webp';

    /** @var list<string> */
    private const array LOGIN_BACKGROUND_RASTER_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private LoginAppearanceBackendAlignment $loginAppearanceBackendAlignment,
        private ExtensionConfiguration $extensionConfiguration,
        private LoggerInterface $logger,
        private SystemResourceFactory $systemResourceFactory,
        private SystemResourcePublisherInterface $resourcePublisher,
        private StorageRepository $storageRepository,
    ) {
    }

    /**
     * @param ModifyPageLayoutOnLoginProviderSelectionEvent $event
     * @param PageRenderer $pageRenderer
     * @return void
     * @throws ExtensionConfigurationPathDoesNotExistException|RandomException
     */
    public function apply(ModifyPageLayoutOnLoginProviderSelectionEvent $event, PageRenderer $pageRenderer): void
    {
        $request = $event->getRequest();
        $this->applyLoginDocumentColorSchemeFromCookie($request, $pageRenderer);
        $settings = $this->loginAppearanceBackendAlignment->mergeBackendIntoModuleSettings($this->loadSettings());
        $this->mergeBackendLogoGlobals($settings);
        $this->applyLoginFootnoteOverride($event->getView(), $settings);
        $this->addLoginFootnoteMultilineStyles($pageRenderer);

        $this->addCssInlineBlockIfNonEmpty(
            $pageRenderer,
            'idBeLoginBackground',
            $this->buildBackgroundStyles($request, $settings)
        );
        $this->addCssInlineBlockIfNonEmpty($pageRenderer, 'idBeLoginButton', $this->buildButtonColorStyles($settings));
        $this->addCssInlineBlockIfNonEmpty($pageRenderer, 'idBeLoginBox', $this->buildLoginBoxStyles($settings));
    }

    /**
     * Align login HTML with the last known backend color scheme (see {@see BackendColorSchemeCookieMiddleware}).
     */
    private function applyLoginDocumentColorSchemeFromCookie(
        ServerRequestInterface $request,
        PageRenderer $pageRenderer
    ): void {
        $scheme = $request->getCookieParams()[BackendColorSchemeCookieMiddleware::COOKIE_NAME] ?? '';
        if ($scheme !== 'dark' && $scheme !== 'light') {
            return;
        }

        // PageRenderer::getHtmlTag() is deprecated in v14.3; there is no replacement reader yet.
        // Read the current tag so we only append data-color-scheme and preserve e.g. data-theme on logout.
        $html = $this->readPageRendererHtmlTag($pageRenderer);
        if (str_contains($html, 'data-color-scheme=')) {
            return;
        }

        $attr = htmlspecialchars($scheme, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if (preg_match('/^<html\s([^>]*)>$/i', $html, $matches)) {
            $pageRenderer->setHtmlTag('<html ' . trim($matches[1]) . ' data-color-scheme="' . $attr . '">');

            return;
        }
        if (preg_match('/^<html>$/i', $html)) {
            $pageRenderer->setHtmlTag('<html data-color-scheme="' . $attr . '">');
        }
    }

    private function readPageRendererHtmlTag(PageRenderer $pageRenderer): string
    {
        try {
            $property = new ReflectionProperty(PageRenderer::class, 'htmlTag');

            return trim((string)$property->getValue($pageRenderer));
        } catch (ReflectionException) {
            return '<html lang="en">';
        }
    }

    /**
     * @return array<string, mixed>
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    private function loadSettings(): array
    {
        try {
            $config = $this->extensionConfiguration->get('id_be_login');
            return is_array($config) ? $config : [];
        } catch (ExtensionConfigurationExtensionNotConfiguredException) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function mergeBackendLogoGlobals(array $settings): void
    {
        $logo = trim((string)($settings['loginLogo'] ?? ''));
        $logoAlt = trim((string)($settings['loginLogoAlt'] ?? ''));
        if ($logo === '') {
            return;
        }

        if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend'])
            || !is_array($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend'])
        ) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend'] = [];
        }

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend']['loginLogo'] = $logo;
        if ($logoAlt !== '') {
            $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['backend']['loginLogoAlt'] = $logoAlt;
        }
    }

    /**
     * Override login footnote on the login Fluid view when id_be_login stores a non-empty value
     * (same sanitization as {@see \TYPO3\CMS\Backend\View\AuthenticationStyleInformation::getFooterNote()}).
     *
     * @param array<string, mixed> $settings
     */
    private function applyLoginFootnoteOverride(ViewInterface $view, array $settings): void
    {
        $footnote = trim((string)($settings['loginFootnote'] ?? ''));
        if ($footnote === '') {
            return;
        }
        $footnote = str_replace(["\r\n", "\r"], "\n", strip_tags($footnote));
        $view->assign('loginFootnote', $footnote);
    }

    /**
     * Footnote presentation: default 0.50 opacity; pre-line so textarea line breaks render inside the core paragraph.
     */
    private function addLoginFootnoteMultilineStyles(PageRenderer $pageRenderer): void
    {
        $pageRenderer->addCssInlineBlock(
            'idBeLoginFootnoteNewlines',
            '.typo3-login-footnote { opacity: 0.50; }
.typo3-login-footnote p { white-space: pre-line; }',
            null,
            false,
            true
        );
    }

    private function addCssInlineBlockIfNonEmpty(PageRenderer $pageRenderer, string $name, string $css): void
    {
        if ($css !== '') {
            $pageRenderer->addCssInlineBlock($name, $css, null, false, true);
        }
    }

    /**
     * @param array<string, mixed> $settings
     * @throws RandomException
     */
    private function buildBackgroundStyles(ServerRequestInterface $request, array $settings): string
    {
        $source = trim((string)($settings['loginBackgroundSource'] ?? LoginBackgroundSource::LOCAL));
        if (!LoginBackgroundSource::isValid($source)) {
            $source = LoginBackgroundSource::LOCAL;
        }

        /** @var list<string> $backgroundLayers */
        $backgroundLayers = [];
        if ($source === LoginBackgroundSource::REMOTE) {
            $provider = trim(
                (string)($settings['loginBackgroundRemoteProvider'] ?? LoginRemoteBackgroundProvider::PICSUM)
            );
            if (!LoginRemoteBackgroundProvider::isValid($provider)) {
                $provider = LoginRemoteBackgroundProvider::PICSUM;
            }
            $remoteUri = $this->buildRemoteBackgroundImageUrl($provider);
            if ($remoteUri !== '') {
                // Single layer: random URL only. (Stacking a local file under it showed that file first / instead
                // of the remote image in common cases — e.g. same-origin WebP loading before the HTTP image.)
                $backgroundLayers[] = $remoteUri;
            } else {
                $fallbackUri = $this->resolveBundledRoadFallbackUri($request);
                if ($fallbackUri !== '') {
                    $backgroundLayers[] = $fallbackUri;
                }
            }
        } elseif ($source === LoginBackgroundSource::FAL_FOLDER) {
            $falUri = $this->resolveFalFolderBackgroundUri(
                $request,
                trim((string)($settings['loginBackgroundFalFolder'] ?? ''))
            );
            if ($falUri !== '') {
                $backgroundLayers[] = $falUri;
            }
        } else {
            $backgroundImageResource = trim((string)($settings['loginBackgroundImage'] ?? ''));
            if ($backgroundImageResource === '') {
                return '';
            }
            try {
                $backgroundLayers[] = (string)$this->resourcePublisher->generateUri(
                    $this->systemResourceFactory->createPublicResource($backgroundImageResource),
                    $request,
                );
            } catch (SystemResourceException) {
                $this->logger->warning('id_be_login: login background image "{image}" could not be resolved.', [
                    'image' => $backgroundImageResource,
                ]);
                $fallbackUri = $this->resolveBundledRoadFallbackUri($request);
                if ($fallbackUri !== '') {
                    $backgroundLayers[] = $fallbackUri;
                }
            }
        }

        if ($backgroundLayers === []) {
            return '';
        }

        $backgroundImageValue = implode(
            ', ',
            array_map(
                static fn(string $uri): string => 'url("' . GeneralUtility::sanitizeCssVariableValue($uri) . '")',
                $backgroundLayers,
            )
        );

        $typo3LoginExtras = '';
        if ($source === LoginBackgroundSource::REMOTE) {
            $typo3LoginExtras = '
            background-color: #1f2226;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;';
        }

        return '
            .typo3-login-carousel-control.right,
            .typo3-login-carousel-control.left,
            .card-login { border: 0; }
            .typo3-login { background-image: ' . $backgroundImageValue . ';' . $typo3LoginExtras . '
            }
            .typo3-login-footnote { background-color: #000000; color: #ffffff; }
        ';
    }

    /**
     * Random query fragment so each login page load requests a different image (browser cache).
     * @throws RandomException
     */
    private function buildRemoteBackgroundImageUrl(string $provider): string
    {
        $nonce = rawurlencode(bin2hex(random_bytes(8)));

        return match ($provider) {
            LoginRemoteBackgroundProvider::PICSUM => 'https://picsum.photos/1920/1080?random=' . $nonce,
            LoginRemoteBackgroundProvider::DANIELPETRICA => 'https://random.danielpetrica.com/api/random?cb=' . $nonce,
            default => '',
        };
    }

    /**
     * Publishes the bundled road.webp when present; omitted from CSS if the file cannot be resolved.
     */
    private function resolveBundledRoadFallbackUri(ServerRequestInterface $request): string
    {
        try {
            return (string)$this->resourcePublisher->generateUri(
                $this->systemResourceFactory->createPublicResource(self::BUNDLED_FALLBACK_BACKGROUND_RESOURCE),
                $request,
            );
        } catch (SystemResourceException) {
            $this->logger->notice('id_be_login: bundled background fallback "{path}" could not be resolved.', [
                'path' => self::BUNDLED_FALLBACK_BACKGROUND_RESOURCE,
            ]);
            return '';
        }
    }

    /**
     * Random public URL for a raster file in the folder (non-recursive), or bundled road.webp.
     * Appends a per-request query argument like remote URLs so browsers do not reuse one cached image for every load.
     *
     * @throws RandomException
     */
    private function resolveFalFolderBackgroundUri(
        ServerRequestInterface $request,
        string $combinedFolderIdentifier
    ): string {
        if ($combinedFolderIdentifier === '') {
            return $this->withLoginBackgroundCacheBuster($this->resolveBundledRoadFallbackUri($request));
        }

        $parsed = $this->parseCombinedFolderIdentifier($combinedFolderIdentifier);
        if ($parsed === null) {
            return $this->withLoginBackgroundCacheBuster($this->resolveBundledRoadFallbackUri($request));
        }

        try {
            $folderIdentifier = $parsed['folderIdentifier'];
            $storage = $this->storageRepository->getStorageObject($parsed['storageUid'], [], $folderIdentifier);
        } catch (Throwable $e) {
            $this->logger->notice(
                'id_be_login: FAL login background storage for folder "{folder}" could not be resolved.',
                [
                    'folder' => $combinedFolderIdentifier,
                    'exception' => $e,
                ]
            );

            return $this->withLoginBackgroundCacheBuster($this->resolveBundledRoadFallbackUri($request));
        }

        // Backend login is rendered without an authenticated BE user. StoragePermissionsAspect then enables
        // permission evaluation on storages, so normal FAL APIs deny folder reads (no file mounts) and we
        // always fell back to road.webp. Disable evaluation only while resolving this public login background.
        $previousEvaluatePermissions = $storage->getEvaluatePermissions();
        $storage->setEvaluatePermissions(false);
        try {
            $folder = $storage->getFolder($folderIdentifier);
            $candidates = $this->listRasterImagesInFolder($folder);
            if ($candidates === []) {
                return $this->withLoginBackgroundCacheBuster($this->resolveBundledRoadFallbackUri($request));
            }

            $file = $candidates[random_int(0, count($candidates) - 1)];
            $publicUrl = $file->getPublicUrl();
            if ($publicUrl === null || $publicUrl === '') {
                return $this->withLoginBackgroundCacheBuster($this->resolveBundledRoadFallbackUri($request));
            }

            return $this->withLoginBackgroundCacheBuster($publicUrl);
        } catch (Throwable $e) {
            $this->logger->notice('id_be_login: FAL login background folder "{folder}" could not be read.', [
                'folder' => $combinedFolderIdentifier,
                'exception' => $e,
            ]);

            return $this->withLoginBackgroundCacheBuster($this->resolveBundledRoadFallbackUri($request));
        } finally {
            $storage->setEvaluatePermissions($previousEvaluatePermissions);
        }
    }

    /**
     * Same idea as {@see buildRemoteBackgroundImageUrl()}: unique URL each login request so background is not stuck on one cached file.
     *
     * @throws RandomException
     */
    private function withLoginBackgroundCacheBuster(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $nonce = rawurlencode(bin2hex(random_bytes(8)));
        $param = 'idBeLoginRand=' . $nonce;

        return str_contains($url, '?') ? $url . '&' . $param : $url . '?' . $param;
    }

    /**
     * Same rules as {@see \TYPO3\CMS\Core\Resource\ResourceFactory::getFolderObjectFromCombinedIdentifier()}.
     *
     * @return array{storageUid: int, folderIdentifier: string}|null
     */
    private function parseCombinedFolderIdentifier(string $identifier): ?array
    {
        $parts = GeneralUtility::trimExplode(':', $identifier);
        if ($parts === []) {
            return null;
        }

        if (count($parts) === 2) {
            return [
                'storageUid' => (int)$parts[0],
                'folderIdentifier' => $parts[1],
            ];
        }

        $folderIdentifier = $parts[0];
        if (str_starts_with($folderIdentifier, Environment::getPublicPath() . '/')) {
            $folderIdentifier = PathUtility::stripPathSitePrefix($parts[0]);
        }

        return [
            'storageUid' => 0,
            'folderIdentifier' => $folderIdentifier,
        ];
    }

    /**
     * @return list<File>
     */
    private function listRasterImagesInFolder(Folder $folder): array
    {
        // Login has no backend user file-list context; storage/user filters would hide most files → use full folder listing.
        $files = $folder->getFiles(0, 0, Folder::FILTER_MODE_NO_FILTERS);
        $out = [];
        foreach ($files as $file) {
            if (!$file instanceof File || $file->isMissing()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, self::LOGIN_BACKGROUND_RASTER_EXTENSIONS, true)) {
                continue;
            }
            $out[] = $file;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function buildButtonColorStyles(array $settings): string
    {
        $highlightColor = trim((string)($settings['loginButtonColor'] ?? ''));
        if ($highlightColor === '' || !LoginHexColor::isValid($highlightColor)) {
            return '';
        }
        $color = GeneralUtility::sanitizeCssVariableValue($highlightColor);
        $hsl = static fn(int $lDelta): string => 'hsl(from ' . $color . ' h s calc(l - ' . $lDelta . '))';

        $btnDeclarations = [
            '--typo3-btn-color: #fff;',
            '--typo3-btn-bg: ' . $color . ';',
            '--typo3-btn-border-color: ' . $hsl(5) . ';',
            '--typo3-btn-hover-color: #fff;',
            '--typo3-btn-hover-bg: ' . $hsl(3) . ';',
            '--typo3-btn-hover-border-color: ' . $hsl(8) . ';',
            '--typo3-btn-focus-color: #fff;',
            '--typo3-btn-focus-bg: ' . $hsl(6) . ';',
            '--typo3-btn-focus-border-color: ' . $hsl(11) . ';',
            '--typo3-btn-disabled-color: #fff;',
            '--typo3-btn-disabled-bg: ' . $color . ';',
            '--typo3-btn-disabled-border-color: ' . $hsl(5) . ';',
        ];
        $btnBlock = implode("\n                ", $btnDeclarations);

        return '
            .typo3-login {
                --typo3-login-highlight: ' . $color . ';
            }
            .btn-login {
                ' . $btnBlock . '
            }
            .card-login .card-footer { border-color: ' . $color . '; }
        ';
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function buildLoginBoxStyles(array $settings): string
    {
        $position = $this->normalizeLoginBoxPosition($settings);
        $positionCss = $this->buildLoginBoxPositionStyles($position);

        $opacityRaw = $settings['loginBoxOpacity'] ?? '0.7';
        $opacity = is_numeric($opacityRaw) ? (float)$opacityRaw : 0.7;
        $opacity = max(0.0, min(1.0, $opacity));

        $radiusRaw = trim((string)($settings['loginBoxBorderRadius'] ?? ''));
        $hasRadiusOverride = $radiusRaw !== '' && is_numeric($radiusRaw);
        $radius = $hasRadiusOverride ? max(0, min(64, (int)$radiusRaw)) : null;

        $fillVarCss = $this->buildLoginBoxFillVariableCss($settings);

        $cardDeclarations = [];
        $opacityLayerCss = '';

        // Fade the card surface only: ::before uses --id-be-login-login-box-fill (custom hex or white/black per scheme).
        if ($opacity < 1.0 - 1.0e-6) {
            $opacityCss = (string)round($opacity, 6);
            $cardDeclarations[] = 'background: transparent';
            $cardDeclarations[] = 'position: relative';
            $cardDeclarations[] = 'isolation: isolate';
            $opacityLayerCss = '
.card.card-login::before {
    content: "";
    position: absolute;
    inset: 0;
    z-index: 0;
    background: var(--id-be-login-login-box-fill);
    opacity: ' . $opacityCss . ';
    border-radius: inherit;
    pointer-events: none;
}
.card.card-login > * {
    position: relative;
    z-index: 1;
}';
        } else {
            $cardDeclarations[] = 'background: var(--id-be-login-login-box-fill)';
        }

        if ($hasRadiusOverride && $radius !== null) {
            $radiusCss = $radius === 0 ? '0' : $radius . 'px';
            $cardDeclarations[] = '--typo3-card-border-radius: ' . $radiusCss;
            $cardDeclarations[] = 'border-radius: ' . $radiusCss;
        }

        $cardBlocks = [];
        if ($cardDeclarations !== []) {
            $cardBlocks[] = '.card.card-login { ' . implode('; ', $cardDeclarations) . '; }';
        }
        if ($opacityLayerCss !== '') {
            $cardBlocks[] = trim($opacityLayerCss);
        }
        $cardCss = $cardBlocks !== [] ? implode("\n", $cardBlocks) : '';

        $parts = array_filter(
            [
                $positionCss !== '' ? $positionCss : null,
                $fillVarCss,
                $cardCss !== '' ? $cardCss : null,
            ],
            static fn(?string $s): bool => $s !== null && $s !== ''
        );

        return trim(implode("\n", $parts));
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function normalizeLoginBoxPosition(array $settings): string
    {
        $raw = trim((string)($settings['loginBoxPosition'] ?? 'center'));
        if ($raw === '' || !LoginBoxPosition::isValid($raw)) {
            return 'center';
        }

        return $raw;
    }

    private function buildLoginBoxPositionStyles(string $position): string
    {
        if ($position === 'center') {
            return '';
        }

        return match ($position) {
            'top-left' => '
.typo3-login .typo3-login-container {
    align-items: flex-start;
    justify-content: flex-start;
    padding: 2.5em 1.5em 1.5em 1.5em;
}
.typo3-login .typo3-login-wrap {
    margin-left: 0;
    margin-right: auto;
}',
            'top-right' => '
.typo3-login .typo3-login-container {
    align-items: flex-end;
    justify-content: flex-start;
    padding: 2.5em 1.5em 1.5em 1.5em;
}
.typo3-login .typo3-login-wrap {
    margin-left: auto;
    margin-right: 0;
}',
            'top-center' => '
.typo3-login .typo3-login-container {
    align-items: center;
    justify-content: flex-start;
    padding: 2.5em 1.5em 1.5em 1.5em;
}
.typo3-login .typo3-login-wrap {
    margin-left: auto;
    margin-right: auto;
}',
            'middle-left' => '
.typo3-login .typo3-login-container {
    align-items: flex-start;
    justify-content: center;
}
.typo3-login .typo3-login-wrap {
    margin-left: 0;
    margin-right: auto;
}',
            'middle-right' => '
.typo3-login .typo3-login-container {
    align-items: flex-end;
    justify-content: center;
}
.typo3-login .typo3-login-wrap {
    margin-left: auto;
    margin-right: 0;
}',
            'bottom-left' => '
.typo3-login .typo3-login-container {
    align-items: flex-start;
    justify-content: flex-end;
    padding: 1.5em 1.5em 2.5em 1.5em;
}
.typo3-login .typo3-login-wrap {
    margin-left: 0;
    margin-right: auto;
}',
            'bottom-center' => '
.typo3-login .typo3-login-container {
    align-items: center;
    justify-content: flex-end;
    padding: 1.5em 1.5em 2.5em 1.5em;
}
.typo3-login .typo3-login-wrap {
    margin-left: auto;
    margin-right: auto;
}',
            'bottom-right' => '
.typo3-login .typo3-login-container {
    align-items: flex-end;
    justify-content: flex-end;
    padding: 1.5em 1.5em 2.5em 1.5em;
}
@media (min-width: 768px) {
.typo3-login .typo3-login-container {
    /* Footnote is absolute bottom-end in core — extra space only where it overlaps the card */
    padding-bottom: calc(2.5em + 5.5rem);
}
}
.typo3-login .typo3-login-wrap {
    margin-left: auto;
    margin-right: 0;
}',
            default => '',
        };
    }

    /**
     * Sets --id-be-login-login-box-fill for .card.card-login (solid or ::before opacity layer).
     *
     * @param array<string, mixed> $settings
     */
    private function buildLoginBoxFillVariableCss(array $settings): string
    {
        $custom = trim((string)($settings['loginBoxBackgroundColor'] ?? ''));
        if ($custom !== '' && LoginHexColor::isValid($custom)) {
            $c = GeneralUtility::sanitizeCssVariableValue($custom);

            return '.typo3-login { --id-be-login-login-box-fill: ' . $c . '; }';
        }

        return 'html[data-color-scheme="light"] .typo3-login { --id-be-login-login-box-fill: #ffffff; }' . "\n"
            . 'html[data-color-scheme="dark"] .typo3-login { --id-be-login-login-box-fill: #000000; }' . "\n"
            . 'html:not([data-color-scheme]) .typo3-login { --id-be-login-login-box-fill: light-dark(#ffffff, #000000); }';
    }
}
