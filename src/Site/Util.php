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
     * @param string ...$args
     * @return string
     */
    public static function path(string ...$args): string
    {
        $path = trim(implode('/', $args), '/');
        $path = preg_replace('/\/+/', '/', $path);
        return '/' . $path;
    }

    /**
     * Formats a string as a human-friendly slug.
     *
     * @param string $string
     * @return string
     */
    public static function slug(string $string): string
    {
        $slug = strtolower($string);
        $slug = preg_replace('/([^a-z0-9]+)/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }
}
