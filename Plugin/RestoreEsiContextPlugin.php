<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\EsiPageLayout\Plugin;

use Hryvinskyi\EsiPageLayout\Api\EsiContextManagerInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Layout\LayoutCacheKeyInterface;
use Magento\PageCache\Controller\Block\Esi;

/**
 * Restores ESI theme and customer authentication context during ESI processing.
 *
 * When Varnish fetches an ESI block via /page_cache/block/esi, this plugin reads
 * the esi_theme and esi_auth parameters appended by the observer, restores the
 * design theme, and adds layout cache keys so Magento's internal layout cache is
 * segmented per theme and per logged-in/out state - matching the URL-level
 * segmentation that Varnish already performs.
 */
class RestoreEsiContextPlugin
{
    /**
     * @param EsiContextManagerInterface $esiContextManager
     * @param DesignInterface $design
     * @param LayoutCacheKeyInterface $layoutCacheKey
     */
    public function __construct(
        private readonly EsiContextManagerInterface $esiContextManager,
        private readonly DesignInterface $design,
        private readonly LayoutCacheKeyInterface $layoutCacheKey
    ) {
    }

    /**
     * Restore theme and add cache keys for theme and authentication state.
     *
     * Runs before Esi::execute() which internally calls _getBlocks() -> loadLayout().
     * By the time loadLayout() runs, the design theme is overridden so layout files
     * are resolved from the correct theme, and the layout cache keys are in place so
     * the cache is segmented per theme and per logged-in/out state.
     *
     * @param Esi $subject
     * @return void
     */
    public function beforeExecute(Esi $subject): void
    {
        $this->restoreThemeContext($subject);
        $this->segmentCacheByAuthParam($subject);
    }

    /**
     * Read esi_theme from request, store it in context, set theme and add a theme cache key.
     *
     * @param Esi $subject
     * @return void
     */
    private function restoreThemeContext(Esi $subject): void
    {
        $esiTheme = $subject->getRequest()->getParam('esi_theme');

        if (!$esiTheme) {
            return;
        }

        $this->esiContextManager->setThemePath($esiTheme);
        $this->design->setDesignTheme($esiTheme, 'frontend');
        $this->layoutCacheKey->addCacheKeys(['esi_theme_' . $esiTheme]);
    }

    /**
     * Add a layout cache key derived from the esi_auth URL parameter.
     *
     * The observer bakes the auth state into the ESI URL so Varnish caches logged-in
     * and logged-out responses separately. Mirroring it here keeps Magento's internal
     * layout cache aligned with the URL-level segmentation.
     *
     * @param Esi $subject
     * @return void
     */
    private function segmentCacheByAuthParam(Esi $subject): void
    {
        $esiAuth = $subject->getRequest()->getParam('esi_auth');

        if ($esiAuth === null) {
            return;
        }

        $this->layoutCacheKey->addCacheKeys(['esi_auth_' . ((int)$esiAuth === 1 ? 1 : 0)]);
    }
}
