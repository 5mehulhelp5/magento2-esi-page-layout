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
 * Restores ESI theme context from request parameters during ESI processing.
 *
 * When Varnish fetches an ESI block via /page_cache/block/esi, this plugin reads the
 * esi_theme parameter that was appended by the observer, stores it in the EsiContextManager,
 * sets the correct design theme, and adds a layout cache key for proper cache segmentation.
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
     * Read esi_theme from request, store in context, set theme and cache key.
     *
     * Runs before Esi::execute() which internally calls _getBlocks() -> loadLayout().
     * By the time loadLayout() runs, the design theme is overridden so layout files
     * are resolved from the correct theme.
     *
     * @param Esi $subject
     * @return void
     */
    public function beforeExecute(Esi $subject): void
    {
        $esiTheme = $subject->getRequest()->getParam('esi_theme');

        if (!$esiTheme) {
            return;
        }

        $this->esiContextManager->setThemePath($esiTheme);
        $this->design->setDesignTheme($esiTheme, 'frontend');
        $this->layoutCacheKey->addCacheKeys(['esi_theme_' . $esiTheme]);
    }
}
