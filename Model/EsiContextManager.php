<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\EsiPageLayout\Model;

use Hryvinskyi\EsiPageLayout\Api\EsiContextManagerInterface;

/**
 * @inheritDoc
 */
class EsiContextManager implements EsiContextManagerInterface
{
    /**
     * @var string|null
     */
    private ?string $themePath = null;

    /**
     * @inheritDoc
     */
    public function setThemePath(string $themePath): void
    {
        $this->themePath = $themePath;
    }

    /**
     * @inheritDoc
     */
    public function getThemePath(): ?string
    {
        return $this->themePath;
    }
}
