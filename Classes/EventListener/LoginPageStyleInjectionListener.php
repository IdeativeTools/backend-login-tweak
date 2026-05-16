<?php

declare(strict_types=1);

namespace Ideative\T3BeLogin\EventListener;

use Ideative\T3BeLogin\Service\LoginStyleApplicator;
use Random\RandomException;
use TYPO3\CMS\Backend\LoginProvider\Event\ModifyPageLayoutOnLoginProviderSelectionEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsEventListener(identifier: 'id-be-login/login-page-styles', event: ModifyPageLayoutOnLoginProviderSelectionEvent::class)]
final readonly class LoginPageStyleInjectionListener
{
    public function __construct(
        private LoginStyleApplicator $loginStyleApplicator,
        private PageRenderer $pageRenderer,
    ) {
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException|RandomException
     */
    public function __invoke(ModifyPageLayoutOnLoginProviderSelectionEvent $event): void
    {
        $this->loginStyleApplicator->apply($event, $this->pageRenderer);
    }
}
