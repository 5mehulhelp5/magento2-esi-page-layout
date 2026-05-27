<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\EsiPageLayout\Plugin;

use Hryvinskyi\EsiPageLayout\Api\EsiContextManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Layout\LayoutCacheKeyInterface;
use Magento\PageCache\Controller\Block\Esi;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Restores ESI theme, store, and customer authentication context during ESI processing.
 *
 * When Varnish fetches an ESI block via /page_cache/block/esi, this plugin reads
 * the esi_theme, esi_store and esi_auth parameters appended by the observer, restores
 * the design theme and current store, and adds layout cache keys so Magento's internal
 * layout cache is segmented per theme, store and logged-in/out state - matching the
 * URL-level segmentation that Varnish already performs.
 */
class RestoreEsiContextPlugin
{
    /**
     * @param EsiContextManagerInterface $esiContextManager
     * @param DesignInterface $design
     * @param LayoutCacheKeyInterface $layoutCacheKey
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly EsiContextManagerInterface $esiContextManager,
        private readonly DesignInterface $design,
        private readonly LayoutCacheKeyInterface $layoutCacheKey,
        private readonly StoreManagerInterface $storeManager
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
        $this->restoreStoreContext($subject);
        $this->restoreThemeContext($subject);
        $this->segmentCacheByAuthParam($subject);
    }

    /**
     * Read esi_store from the request, switch the active store, and add a store cache key.
     *
     * Required when multiple stores share the same hostname without a store code in the URL
     * path: the ESI subrequest would otherwise be resolved against the host's default store,
     * not the store the parent page was rendered for.
     *
     * @param Esi $subject
     * @return void
     */
    private function restoreStoreContext(Esi $subject): void
    {
        $esiStore = $subject->getRequest()->getParam('esi_store');

        if (!$esiStore) {
            return;
        }

        try {
            $this->storeManager->setCurrentStore($esiStore);
        } catch (NoSuchEntityException $e) {
            return;
        }

        $this->layoutCacheKey->addCacheKeys(['esi_store_' . $esiStore]);
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
