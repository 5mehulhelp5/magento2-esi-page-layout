<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */
declare(strict_types=1);

namespace Hryvinskyi\EsiPageLayout\Api;

/**
 * Request-scoped service for storing ESI context data (theme path)
 * that needs to be passed between plugins during ESI request processing.
 */
interface EsiContextManagerInterface
{
    /**
     * Set the theme path for the current ESI request.
     *
     * @param string $themePath
     * @return void
     */
    public function setThemePath(string $themePath): void;

    /**
     * Get the theme path for the current ESI request.
     *
     * @return string|null
     */
    public function getThemePath(): ?string;
}
