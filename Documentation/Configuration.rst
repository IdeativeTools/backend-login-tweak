:navigation-title: Configuration
..  _configuration:

=============
Configuration
=============

This extension provides an admin-only backend module to configure the TYPO3
login appearance. Settings are stored in:

..  code-block:: php

    $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['id_be_login']

The module is available at:

**System > Login appearance**

..  _configuration-overview:

Configuration overview
======================

The module allows you to configure:

- Background source (local path, FAL folder, or remote provider)
- Background image path / FAL folder
- Login logo path and alt text
- Login card background color
- Login button color
- Login box position, opacity, and border radius
- Login footnote

The extension also keeps shared fields aligned with EXT:backend where
applicable (background image, logo, login highlight color).

..  _configuration-defaults:

Default values (ext_conf_template.txt)
======================================

Current shipped defaults include:

..  code-block:: text

    loginBackgroundSource = remote
    loginBackgroundRemoteProvider = picsum
    loginBackgroundImage = EXT:id_be_login/Resources/Public/Pictures/road.webp
    loginLogo = EXT:id_be_login/Resources/Public/Pictures/Ideative-logo.svg
    loginLogoAlt = Idéative
    loginBoxPosition = center
    loginBoxOpacity = 0.7
    loginBoxBorderRadius = 24


..  _configuration-remote-background:

Remote background providers
===========================

When using the remote background source, the extension supports:

- ``picsum``
- ``danielpetrica``

The extension adjusts CSP directives for configured remote image hosts.

..  _configuration-fal-folder:

FAL folder mode
===============

When the source is set to ``fal_folder``, the extension selects a random image
from direct files in the configured folder and supports the following raster
formats:

- ``jpg``
- ``jpeg``
- ``png``
- ``webp``


Screenshots
===========

..  figure:: images/Login-screen-tweaks1.jpg
    :alt: Login screen tweaks
    :class: with-border

    Login screen tweaks.


..  figure:: images/Login-screen-tweaks2.jpg
    :alt: Login screen tweaks
    :class: with-border

    Login screen tweaks.


..  figure:: images/Login-screen-tweaks3.jpg
    :alt: Login screen tweaks
    :class: with-border

    Login screen tweaks.
