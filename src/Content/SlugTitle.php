<?php

declare(strict_types=1);

namespace Leaf\Content;

/**
 * Turns a file or folder name into a display title.
 *
 * @internal
 */
final class SlugTitle
{
    /** Turn a file or folder name into a display title: "-" and "_" read as spaces. */
    public static function fromSlug(string $slug): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $slug));
    }
}
