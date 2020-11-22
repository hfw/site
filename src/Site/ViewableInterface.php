<?php

namespace Helix\Site;

/**
 * The instance can render content.
 */
interface ViewableInterface {

    /**
     * Returns what would be rendered.
     *
     * @return string
     */
    public function getContent (): string;

    /**
     * Directly outputs content.
     */
    public function render (): void;
}