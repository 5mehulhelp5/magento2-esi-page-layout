# Varnish ESI Theme Context for Magento 2

Ensures correct theme context and cache segmentation for Varnish ESI block requests in multi-theme Magento setups.

## Problem

When Varnish fetches ESI blocks via `/page_cache/block/esi`, the request does not carry any theme context.
In a multi-theme store (e.g. `Vendor/base` and `Venor/balckfriday`), ESI blocks may be rendered and cached using the wrong theme, causing incorrect markup or broken styles.

Additionally, blocks with `ttl` are rendered inline during full-page generation even though they will be replaced by `<esi:include>` tags — wasting server resources.

## Solution

This module solves both issues with three components:

### 1. Observer: `ProcessLayoutRenderElement`

Replaces the core `Magento\PageCache\Observer\ProcessLayoutRenderElement` (disabled in frontend scope). Adds an `esi_theme` parameter directly to the ESI URL during `<esi:include>` tag generation, so the theme path is carried through to the ESI request.

### 2. Plugin: `RestoreEsiContextPlugin`

Before plugin on `Magento\PageCache\Controller\Block\Esi::execute()`. Reads `esi_theme` from the request, sets the design theme via `DesignInterface::setDesignTheme()`, and adds a layout cache key (`esi_theme_{path}`) so layout cache is segmented per theme.

### 3. Plugin: `SkipRenderLayoutElementPlugin`

Around plugin on `Magento\Framework\View\Layout::renderNonCachedElement()`. When Varnish full-page cache is active and the page is cacheable, blocks with a TTL are skipped (return empty string) since they will be served via ESI includes instead.

## Installation

```bash
composer require hryvinskyi/magento2-esi-page-layout
bin/magento module:enable Hryvinskyi_EsiPageLayout
bin/magento setup:di:compile
bin/magento cache:flush
```

## Configuration

No configuration required. The module activates automatically when Varnish full-page cache is enabled.

## Requirements

- Magento 2.4.x
- PHP 8.1+
