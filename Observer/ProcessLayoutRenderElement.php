<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\EsiPageLayout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Base64Json;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\EntitySpecificHandlesList;
use Magento\PageCache\Model\Config;

/**
 * Replaces Magento\PageCache\Observer\ProcessLayoutRenderElement to inject
 * esi_theme parameter directly into ESI URLs.
 *
 * The original observer generates ESI include tags for blocks with ttl, but the
 * resulting ESI requests lack theme context, so Varnish may serve blocks rendered
 * with the wrong theme. This observer adds esi_theme to the ESI URL during
 * URL generation for proper theme-aware cache segmentation.
 */
class ProcessLayoutRenderElement implements ObserverInterface
{
    /**
     * @var bool|null
     */
    private ?bool $isVarnishEnabled = null;

    /**
     * @param Config $config
     * @param EntitySpecificHandlesList $entitySpecificHandlesList
     * @param Json $jsonSerializer
     * @param Base64Json $base64jsonSerializer
     * @param DesignInterface $design
     */
    public function __construct(
        private readonly Config $config,
        private readonly EntitySpecificHandlesList $entitySpecificHandlesList,
        private readonly Json $jsonSerializer,
        private readonly Base64Json $base64jsonSerializer,
        private readonly DesignInterface $design
    ) {
    }

    /**
     * Replace the output of the block, containing ttl attribute, with ESI tag.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        /** @var \Magento\Framework\View\Layout $layout */
        $layout = $event->getLayout();

        if (!$this->config->isEnabled() || !$layout->isCacheable()) {
            return;
        }

        $name = $event->getElementName();
        /** @var AbstractBlock $block */
        $block = $layout->getBlock($name);
        $transport = $event->getTransport();

        if (!$block instanceof AbstractBlock) {
            return;
        }

        $blockTtl = $block->getTtl();
        $output = $transport->getData('output');

        if (isset($blockTtl) && $this->isVarnishEnabled()) {
            $output = $this->wrapEsi($block, $layout);
        } elseif ($block->isScopePrivate()) {
            $output = sprintf(
                '<!-- BLOCK %1$s -->%2$s<!-- /BLOCK %1$s -->',
                $block->getNameInLayout(),
                $output
            );
        }

        $transport->setData('output', $output);
    }

    /**
     * Generate ESI include tag with theme context parameter.
     *
     * @param AbstractBlock $block
     * @param \Magento\Framework\View\Layout $layout
     * @return string
     */
    private function wrapEsi(AbstractBlock $block, \Magento\Framework\View\Layout $layout): string
    {
        $handles = $layout->getUpdate()->getHandles();
        $pageSpecificHandles = $this->entitySpecificHandlesList->getHandles();

        $params = [
            'blocks' => $this->jsonSerializer->serialize([$block->getNameInLayout()]),
            'handles' => $this->base64jsonSerializer->serialize(
                array_values(array_diff($handles, $pageSpecificHandles))
            ),
        ];

        $themePath = $this->design->getDesignTheme()->getThemePath();
        if ($themePath) {
            $params['esi_theme'] = $themePath;
        }

        $url = $block->getUrl('page_cache/block/esi', $params);

        // Varnish does not support ESI over HTTPS must change to HTTP
        $url = ($url && str_starts_with($url, 'https')) ? 'http' . substr($url, 5) : $url;

        return sprintf('<esi:include src="%s" />', $url);
    }

    /**
     * Check if Varnish cache engine is enabled.
     *
     * @return bool
     */
    private function isVarnishEnabled(): bool
    {
        if ($this->isVarnishEnabled === null) {
            $this->isVarnishEnabled = ($this->config->getType() === Config::VARNISH);
        }

        return $this->isVarnishEnabled;
    }
}
