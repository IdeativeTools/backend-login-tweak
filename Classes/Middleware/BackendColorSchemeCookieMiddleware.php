<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Ideative\T3BeLogin\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Routing\BackendEntryPointResolver;

/**
 * Persists the backend user's color scheme (dark / light / auto) in a cookie so the login page
 * can set {@code data-color-scheme} on {@code <html>}. Core only adds that attribute when a user
 * is logged in; without it, {@code light-dark()} follows the OS, so a TYPO3 "Dark" preference with
 * a light OS theme yields a white login card.
 */
final readonly class BackendColorSchemeCookieMiddleware implements MiddlewareInterface
{
    public const string COOKIE_NAME = 'id_be_login_be_color_scheme';

    public function __construct(
        private BackendEntryPointResolver $backendEntryPointResolver,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $user = $GLOBALS['BE_USER'] ?? null;
        if (!$user instanceof BackendUserAuthentication || empty($user->user['uid'])) {
            return $response;
        }

        $scheme = (string)($user->uc['colorScheme'] ?? 'auto');
        if (!in_array($scheme, ['auto', 'light', 'dark'], true)) {
            $scheme = 'auto';
        }

        $current = $request->getCookieParams()[self::COOKIE_NAME] ?? null;
        if ($current === $scheme) {
            return $response;
        }

        $normalizedParams = $request->getAttribute('normalizedParams');
        if (!$normalizedParams instanceof NormalizedParams) {
            return $response;
        }

        $cookie = new Cookie(
            self::COOKIE_NAME,
            $scheme,
            $GLOBALS['EXEC_TIME'] + 63072000,
            $this->backendEntryPointResolver->getPathFromRequest($request),
            '',
            $normalizedParams->isHttps(),
            true,
            false,
            Cookie::SAMESITE_LAX,
        );

        return $response->withAddedHeader('Set-Cookie', $cookie->__toString());
    }
}
