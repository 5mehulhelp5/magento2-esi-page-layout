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

## Known Issues with Third-Party Modules

### Amasty: Incorrect Entity-Specific Handle Registration

> **Warning:** Avoid using Amasty modules if you want your site to be solid and have good performance.

Amasty modules (`amasty/module-shop-by-brand`, `amasty/shopby`) have a bug in their category controllers
where they call `addPageLayoutHandles()` with both `type` and `id` parameters in a single call without
setting `$entitySpecific = false`:

```php
// Amasty (BROKEN) — marks ALL handles as entity-specific, including page-type handles
$page->addPageLayoutHandles(['type' => $type, 'id' => $category->getId()], 'catalog_category_view');
```

Magento core does this correctly with separate calls:

```php
// Magento core (CORRECT) — type handles are NOT entity-specific, only id handles are
$page->addPageLayoutHandles(['type' => $pageType], null, false);
$page->addPageLayoutHandles(['id' => $category->getId()]);
```

This causes `catalog_category_view_type_layered` to be registered as an entity-specific handle.
Magento's `ProcessLayoutRenderElement` observer then strips it from ESI URLs via `array_diff()`,
so when Varnish fetches the ESI block, the layout is loaded without the layered navigation handle,
resulting in broken block rendering on category and brand pages.

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
