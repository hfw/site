<?php

namespace Helix\Site;

/**
 * Static helper functions.
 */
class Util
{

    /**
     * Joins string pieces and formats it as an absolute path.
     *
     * @param string[] $args
     * @return string
     */
    public static function path(...$args): string
    {
        $path = trim(implode('/', $args), '/');
        $path = preg_replace('/\/+/', '/', $path);
        return '/' . $path;
    }

    /**
     * Joins string pieces and formats it as a human friendly slug.
     *
     * @param string[] $args
     * @return string
     */
    public static function slug(...$args): string
    {
        $slug = strtolower(implode('-', $args));
        $slug = preg_replace('/([^a-z0-9]+)/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
