<?php

declare(strict_types=1);

namespace Ideative\T3BeLogin\Controller;

use Ideative\T3BeLogin\LoginBackgroundSource;
use Ideative\T3BeLogin\LoginBoxPosition;
use Ideative\T3BeLogin\LoginHexColor;
use Ideative\T3BeLogin\LoginRemoteBackgroundProvider;
use Ideative\T3BeLogin\Service\LoginAppearanceBackendAlignment;
use Ideative\T3BeLogin\Service\LoginAppearanceSettingsPersistence;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * Backend module: persist login appearance settings (extension configuration).
 */
#[AsController]
final readonly class LoginAppearanceController
{
    private const string FORM_NAME = 'id_be_login_settings';

    /** HTML id for the settings form; doc-header submit button uses the `form` attribute to target it. */
    private const string FORM_ELEMENT_ID = 'id-be-login-settings-form';

    /** Same exception code as TYPO3 when LocalConfiguration (e.g. system/settings.php) is not writable. */
    private const int LOCAL_CONFIGURATION_NOT_WRITABLE_CODE = 1346323822;

    private const string DEFAULT_LOGIN_BOX_OPACITY = '0.7';

    /** Starter-package default footnote (two lines); aligned with {@see ext_conf_template.txt}. */
    private const string DEFAULT_LOGIN_FOOTNOTE = "Made with ♥ in Carouge\nby Idéative";

    private const int LOGIN_BOX_OPACITY_PERCENT_STEP = 5;

    /**
     * Active module tab ids (query / form field `idBeLoginTab`).
     *
     * @var list<string>
     */
    private const array MODULE_TABS = ['images', 'logo', 'colors', 'loginbox', 'footnote', 'about'];

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private ExtensionConfiguration $extensionConfiguration,
        private LoginAppearanceBackendAlignment $loginAppearanceBackendAlignment,
        private LoginAppearanceSettingsPersistence $loginAppearanceSettingsPersistence,
        private FormProtectionFactory $formProtectionFactory,
        private FlashMessageService $flashMessageService,
        private UriBuilder $uriBuilder,
        private PageRenderer $pageRenderer,
        private ComponentFactory $componentFactory,
        private IconFactory $iconFactory,
    ) {
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws RouteNotFoundException
     */
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody) && ($parsedBody['save'] ?? '') === '1') {
            return $this->processSave($request, $parsedBody);
        }

        return $this->renderForm($request);
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws RouteNotFoundException
     */
    private function processSave(ServerRequestInterface $request, array $parsedBody): ResponseInterface
    {
        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $token = (string)($parsedBody['idBeLoginFormToken'] ?? '');
        if (!$formProtection->validateToken($token, self::FORM_NAME, 'save')) {
            $this->addFlashMessage(
                'LLL:EXT:id_be_login/Resources/Private/Language/locallang_be.xlf:module.flash.invalidToken',
                ContextualFeedbackSeverity::ERROR
            );
            return new RedirectResponse($this->buildModuleUri(), 303);
        }

        $settings = $this->readExistingSettings();
        $settings['loginBackgroundSource'] = $this->sanitizeLoginBackgroundSource(
            $parsedBody['loginBackgroundSource'] ?? ''
        );
        $settings['loginBackgroundRemoteProvider'] = $this->sanitizeLoginRemoteBackgroundProvider(
            $parsedBody['loginBackgroundRemoteProvider'] ?? ''
        );
        $settings['loginBackgroundImage'] = $this->sanitizePath((string)($parsedBody['loginBackgroundImage'] ?? ''));
        $settings['loginBackgroundFalFolder'] = $this->sanitizeFalFolderIdentifier(
            (string)($parsedBody['loginBackgroundFalFolder'] ?? '')
        );
        $settings['loginLogo'] = $this->sanitizePath((string)($parsedBody['loginLogo'] ?? ''));
        $settings['loginLogoAlt'] = trim(strip_tags((string)($parsedBody['loginLogoAlt'] ?? '')));

        $settings['loginFootnote'] = $this->sanitizeLoginFootnote((string)($parsedBody['loginFootnote'] ?? ''));

        $settings['loginBoxOpacity'] = $this->sanitizeLoginBoxOpacityPercent(
            $parsedBody['loginBoxOpacityPercent'] ?? ''
        );

        $settings['loginBoxBorderRadius'] = $this->sanitizeBorderRadiusInput($parsedBody['loginBoxBorderRadius'] ?? '');

        $settings['loginBoxPosition'] = $this->sanitizeLoginBoxPosition($parsedBody['loginBoxPosition'] ?? 'center');

        $boxBg = trim((string)($parsedBody['loginBoxBackgroundColor'] ?? ''));
        $settings['loginBoxBackgroundColor'] = $boxBg !== '' && LoginHexColor::isValid($boxBg) ? $boxBg : '';

        $color = trim((string)($parsedBody['loginButtonColor'] ?? ''));
        $settings['loginButtonColor'] = $color !== '' && LoginHexColor::isValid($color) ? $color : '';

        try {
            $this->loginAppearanceSettingsPersistence->write($settings);
            $this->loginAppearanceSettingsPersistence->syncBackendExtensionSharedLoginAppearance($settings);
            $this->addFlashMessage(
                'LLL:EXT:id_be_login/Resources/Private/Language/locallang_be.xlf:module.flash.saved',
                ContextualFeedbackSeverity::OK
            );
        } catch (RuntimeException $e) {
            if ($e->getCode() !== self::LOCAL_CONFIGURATION_NOT_WRITABLE_CODE) {
                throw $e;
            }
            $this->addFlashMessage(
                'LLL:EXT:id_be_login/Resources/Private/Language/locallang_be.xlf:module.flash.saveFailed',
                ContextualFeedbackSeverity::ERROR
            );
        }

        $activeTab = $this->sanitizeActiveModuleTab($parsedBody['idBeLoginTab'] ?? null);

        return new RedirectResponse($this->buildModuleUri($activeTab), 303);
    }

    private function addFlashMessage(string $label, ContextualFeedbackSeverity $severity): void
    {
        $queue = $this->flashMessageService->getMessageQueueByIdentifier();
        $queue->addMessage(
            new FlashMessage(
                $GLOBALS['LANG']->sL($label),
                '',
                $severity,
                true
            )
        );
    }

    /**
     * @throws RouteNotFoundException
     */
    private function buildModuleUri(?string $activeTab = null): string
    {
        $base = (string)$this->uriBuilder->buildUriFromRoute('id_be_login');
        $query = [];
        if ($activeTab !== null && $activeTab !== '' && in_array($activeTab, self::MODULE_TABS, true)) {
            $query['idBeLoginTab'] = $activeTab;
        }
        if ($query === []) {
            return $base;
        }
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base . $separator . http_build_query($query);
    }

    /**
     * @return array<string, string>
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    private function readExistingSettings(): array
    {
        $defaults = [
            'loginBackgroundSource' => LoginBackgroundSource::REMOTE,
            'loginBackgroundRemoteProvider' => LoginRemoteBackgroundProvider::PICSUM,
            'loginBackgroundImage' => 'EXT:id_be_login/Resources/Public/Pictures/road.webp',
            'loginBackgroundFalFolder' => '1:/Demo_Content/',
            'loginBoxOpacity' => self::DEFAULT_LOGIN_BOX_OPACITY,
            'loginBoxBorderRadius' => '24',
            'loginBoxPosition' => 'center',
            'loginLogo' => 'EXT:id_be_login/Resources/Public/Pictures/Ideative-logo.svg',
            'loginLogoAlt' => 'Idéative',
            'loginFootnote' => self::DEFAULT_LOGIN_FOOTNOTE,
            'loginBoxBackgroundColor' => '#dfe9e5',
            'loginButtonColor' => '#6be6b0',
        ];
        try {
            $current = $this->extensionConfiguration->get('id_be_login');
            $merged = $defaults;
            if (is_array($current)) {
                foreach (array_intersect_key($current, $defaults) as $key => $value) {
                    $merged[$key] = (string)$value;
                }
            }
        } catch (ExtensionConfigurationExtensionNotConfiguredException) {
            $merged = $defaults;
        }

        return $this->loginAppearanceBackendAlignment->mergeBackendIntoModuleSettings($merged);
    }

    private function sanitizeLoginBackgroundSource(mixed $value): string
    {
        $v = is_string($value) ? trim($value) : '';
        if ($v === '' || !LoginBackgroundSource::isValid($v)) {
            return LoginBackgroundSource::LOCAL;
        }

        return $v;
    }

    private function sanitizeLoginRemoteBackgroundProvider(mixed $value): string
    {
        $v = is_string($value) ? trim($value) : '';
        if ($v === '' || !LoginRemoteBackgroundProvider::isValid($v)) {
            return LoginRemoteBackgroundProvider::PICSUM;
        }

        return $v;
    }

    private function sanitizePath(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_contains($value, "\n") || str_contains($value, "\r")) {
            return '';
        }

        return $value;
    }

    private function sanitizeFalFolderIdentifier(string $value): string
    {
        $value = trim(str_replace(["\r\n", "\r", "\n"], '', $value));
        if ($value === '') {
            return '';
        }
        if (strlen($value) > 2048) {
            return substr($value, 0, 2048);
        }

        return $value;
    }

    /**
     * Plain text for the login screen footnote (HTML tags stripped), same rules as core backend login footnote.
     */
    private function sanitizeLoginFootnote(string $value): string
    {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if ($value === '') {
            return '';
        }
        $value = strip_tags($value);
        if (strlen($value) > 2000) {
            $value = substr($value, 0, 2000);
        }

        return trim($value);
    }

    /**
     * Form posts 0–100 (percent); stored configuration stays 0–1 (two decimal places).
     */
    private function sanitizeLoginBoxOpacityPercent(mixed $value): string
    {
        if ($value === null || $value === '') {
            return self::DEFAULT_LOGIN_BOX_OPACITY;
        }
        if (!is_numeric($value)) {
            return self::DEFAULT_LOGIN_BOX_OPACITY;
        }
        $percent = (int)round((float)$value);
        $percent = $this->snapLoginBoxOpacityPercentToStep($percent);

        return (string)round($percent / 100, 2);
    }

    private function snapLoginBoxOpacityPercentToStep(int $percent): int
    {
        $percent = max(0, min(100, $percent));

        return (int)(round($percent / self::LOGIN_BOX_OPACITY_PERCENT_STEP) * self::LOGIN_BOX_OPACITY_PERCENT_STEP);
    }

    /**
     * Empty string: do not override TYPO3 default corner radius.
     * "0": square corners. "1"–"64": radius in pixels.
     */
    private function sanitizeBorderRadiusInput(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        $value = is_string($value) ? trim($value) : (string)$value;
        if ($value === '') {
            return '';
        }
        if (!is_numeric($value)) {
            return '';
        }

        return (string)max(0, min(64, (int)$value));
    }

    private function sanitizeLoginBoxPosition(mixed $value): string
    {
        $v = is_string($value) ? trim($value) : '';
        if ($v === '' || !LoginBoxPosition::isValid($v)) {
            return 'center';
        }

        return $v;
    }

    private function sanitizeActiveModuleTab(mixed $value): string
    {
        $v = is_string($value) ? trim($value) : '';
        if ($v !== '' && in_array($v, self::MODULE_TABS, true)) {
            return $v;
        }

        return 'images';
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException|RouteNotFoundException
     */
    private function renderForm(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->moduleTemplateFactory->create($request);
        $languageService = $GLOBALS['LANG'];
        $view->setTitle(
            $languageService->sL('LLL:EXT:id_be_login/Resources/Private/Language/locallang_be.xlf:module.title')
        );

        $settings = $this->readExistingSettings();
        $formProtection = $this->formProtectionFactory->createFromRequest($request);
        $ll = 'LLL:EXT:id_be_login/Resources/Private/Language/locallang_be.xlf:';

        $positionByValue = [];
        foreach (LoginBoxPosition::optionsForForm() as $opt) {
            $positionByValue[$opt['value']] = [
                'value' => $opt['value'],
                'label' => $languageService->sL($ll . 'settings.loginBoxPosition.' . $opt['labelSuffix']),
            ];
        }
        $loginBoxPositionOptions = [];
        foreach (LoginBoxPosition::gridOrder() as $positionValue) {
            $loginBoxPositionOptions[] = $positionByValue[$positionValue];
        }

        $remoteBackgroundOptions = [];
        foreach (LoginRemoteBackgroundProvider::optionsForForm() as $opt) {
            $remoteBackgroundOptions[] = [
                'value' => $opt['value'],
                'label' => $languageService->sL($ll . 'settings.loginBackgroundRemoteProvider.' . $opt['labelSuffix']),
            ];
        }

        $opacityPercent = (string)$this->snapLoginBoxOpacityPercentToStep(
            (int)round((float)($settings['loginBoxOpacity'] ?? self::DEFAULT_LOGIN_BOX_OPACITY) * 100)
        );

        $savedBackgroundSource = trim($settings['loginBackgroundSource'] ?? LoginBackgroundSource::LOCAL);
        if (!LoginBackgroundSource::isValid($savedBackgroundSource)) {
            $savedBackgroundSource = LoginBackgroundSource::LOCAL;
        }
        $queryBackgroundSource = $request->getQueryParams()['idBeLoginBgSource'] ?? '';
        $queryBackgroundSource = is_string($queryBackgroundSource) ? trim($queryBackgroundSource) : '';
        $loginBackgroundSourceDisplay = ($queryBackgroundSource !== '' && LoginBackgroundSource::isValid(
                $queryBackgroundSource
            ))
            ? $queryBackgroundSource
            : $savedBackgroundSource;
        $loginBackgroundIsRemote = $loginBackgroundSourceDisplay === LoginBackgroundSource::REMOTE;
        $loginBackgroundIsFalFolder = $loginBackgroundSourceDisplay === LoginBackgroundSource::FAL_FOLDER;
        $loginBackgroundIsLocal = $loginBackgroundSourceDisplay === LoginBackgroundSource::LOCAL;

        $this->pageRenderer->addCssFile('EXT:id_be_login/Resources/Public/Css/be-login-settings.min.css');
        $this->pageRenderer->addInlineSetting(
            'Wizards',
            'elementBrowserUrl',
            (string)$this->uriBuilder->buildUriFromRoute('wizard_element_browser')
        );
        $this->pageRenderer->loadJavaScriptModule('@typo3/backend/color-picker.js');
        $this->pageRenderer->loadJavaScriptModule('@ideative/id-be-login/login-fal-folder-picker.min.js');
        $this->pageRenderer->loadJavaScriptModule('@ideative/id-be-login/login-appearance-tabs.min.js');

        $activeModuleTab = $this->resolveActiveModuleTabFromRequest($request);

        $this->registerDocHeaderActions($view, $languageService, $ll);

        $view->assignMultiple([
            'formElementId' => self::FORM_ELEMENT_ID,
            'settings' => $settings,
            'formToken' => $formProtection->generateToken(self::FORM_NAME, 'save'),
            'loginBoxPositionOptions' => $loginBoxPositionOptions,
            'loginBackgroundRemoteProviderOptions' => $remoteBackgroundOptions,
            'loginBackgroundSourceDisplay' => $loginBackgroundSourceDisplay,
            'loginBackgroundIsRemote' => $loginBackgroundIsRemote,
            'loginBackgroundIsFalFolder' => $loginBackgroundIsFalFolder,
            'loginBackgroundIsLocal' => $loginBackgroundIsLocal,
            'loginBackgroundSwitchToLocalUri' => $this->buildModuleUriWithBackgroundSourcePreview(
                LoginBackgroundSource::LOCAL
            ),
            'loginBackgroundSwitchToFalFolderUri' => $this->buildModuleUriWithBackgroundSourcePreview(
                LoginBackgroundSource::FAL_FOLDER
            ),
            'loginBackgroundSwitchToRemoteUri' => $this->buildModuleUriWithBackgroundSourcePreview(
                LoginBackgroundSource::REMOTE
            ),
            'loginBoxOpacityPercent' => $opacityPercent,
            'loginBoxOpacityPercentOptions' => $this->buildLoginBoxOpacityPercentOptions(),
            'activeModuleTab' => $activeModuleTab,
            'activeModuleTabIsImages' => $activeModuleTab === 'images',
            'activeModuleTabIsLogo' => $activeModuleTab === 'logo',
            'activeModuleTabIsColors' => $activeModuleTab === 'colors',
            'activeModuleTabIsFootnote' => $activeModuleTab === 'footnote',
            'activeModuleTabIsLoginBox' => $activeModuleTab === 'loginbox',
            'activeModuleTabIsAbout' => $activeModuleTab === 'about',
        ]);

        return $view->renderResponse('Backend/LoginAppearance');
    }

    private function resolveActiveModuleTabFromRequest(ServerRequestInterface $request): string
    {
        $q = $request->getQueryParams()['idBeLoginTab'] ?? '';
        $q = is_string($q) ? trim($q) : '';

        return $this->sanitizeActiveModuleTab($q !== '' ? $q : null);
    }

    /**
     * @throws RouteNotFoundException
     */
    private function registerDocHeaderActions(ModuleTemplate $view, LanguageService $languageService, string $ll): void
    {
        $saveButton = $this->componentFactory->createInputButton()
            ->setName('save')
            ->setValue('1')
            ->setForm(self::FORM_ELEMENT_ID)
            ->setTitle($languageService->sL($ll . 'settings.save'))
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-document-save', IconSize::SMALL));
        $buttonBar = $view->getDocHeaderComponent()->getButtonBar();
        $buttonBar->addButton($saveButton);

        $previewHintText = htmlspecialchars(
            $languageService->sL($ll . 'settings.previewHint'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $previewHintTitle = htmlspecialchars(
            $languageService->sL($ll . 'settings.previewHint.title'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $backendLoginPreviewUri = htmlspecialchars(
            (string)$this->uriBuilder->buildUriFromRoute('login', [], UriBuilder::ABSOLUTE_URL),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        $previewHint = $this->componentFactory->createFullyRenderedButton()
            ->setHtmlSource(
                '<span class="text-body-secondary text-wrap d-inline-block align-middle" style="max-width:36rem;line-height:1.4;border:0;background:transparent;box-shadow:none;font-weight:400;padding:0.125rem 0.5rem;">'
                . '<a href="' . $backendLoginPreviewUri . '" target="_blank" rel="noopener noreferrer" class="text-body-secondary text-decoration-none" title="' . $previewHintTitle . '">'
                . $previewHintText
                . '</a>'
                . '</span>'
            );
        $buttonBar->addButton($previewHint, ButtonBar::BUTTON_POSITION_LEFT, 2);
    }

    /**
     * GET navigation for switching background source preview (not persisted until Save).
     * Query is appended explicitly so it is not dropped by the route generator.
     * @throws RouteNotFoundException
     */
    private function buildModuleUriWithBackgroundSourcePreview(string $source): string
    {
        $base = (string)$this->uriBuilder->buildUriFromRoute('id_be_login');
        $query = [
            'idBeLoginBgSource' => $source,
            'idBeLoginTab' => 'images',
        ];
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base . $separator . http_build_query($query);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function buildLoginBoxOpacityPercentOptions(): array
    {
        $options = [];
        for ($p = 0; $p <= 100; $p += self::LOGIN_BOX_OPACITY_PERCENT_STEP) {
            $options[] = [
                'value' => (string)$p,
                'label' => $p . '%',
            ];
        }

        return $options;
    }
}
