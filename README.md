# WooCommerce Google Address Autocomplete

A small fork of the original **WooCommerce Google Address Autocomplete** plugin by Simon Harper / SRH Design.

Upstream source:

https://gist.github.com/SRHDesign/9697c3d0c20ea93ec3494593caef8fd1

The original plugin adds Google Places address autocomplete to WooCommerce checkout using WooCommerce's native address autocomplete provider API. It supports both classic and block checkout. The upstream code is licensed under GPL-2.0-or-later.

## Changes from upstream

This fork intentionally stays close to the original implementation.

Changes made here:

- Fixed Google Places session token handling by including the session token in Place Details requests.

Future changes will be listed here as they are made.

## Google API key security

This plugin calls the Google Places API directly from the customer's browser.

The API key is therefore visible to visitors and must be restricted in Google Cloud.

Recommended configuration:

- Application restriction: Websites (HTTP referrers)
- Restrict usage to the domains where the checkout is hosted
- API restriction: Places API (New) only
- Use a dedicated API key for this plugin

See Google's API security guidance:
https://developers.google.com/maps/api-security-best-practices