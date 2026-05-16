<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\UriValue;
use TYPO3\CMS\Core\Type\Map;

/**
 * Remote login backgrounds load cross-origin images (redirects included). Backend CSP must allow img-src
 * for those hosts or the browser blocks the request (blank background).
 *
 * @see LoginStyleApplicator::buildRemoteBackgroundImageUrl()
 */
return Map::fromEntries([
    Scope::backend(),
    new MutationCollection(
        new Mutation(
            MutationMode::Extend,
            Directive::ImgSrc,
            // Lorem Picsum: entry URL on picsum.photos redirects to *.picsum.photos (e.g. fastly.picsum.photos)
            new UriValue('picsum.photos'),
            new UriValue('*.picsum.photos'),
            // random.danielpetrica.com/api/random responds with redirect; final image is often on Unsplash
            new UriValue('random.danielpetrica.com'),
            new UriValue('images.unsplash.com'),
            new UriValue('*.unsplash.com'),
        ),
    ),
]);
