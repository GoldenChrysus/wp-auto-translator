<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class PostTypes
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $types = ['post', 'page'];
        if (! function_exists('apply_filters')) {
            return $types;
        }

        $filtered = apply_filters('wat_translatable_post_types', $types);
        if (! is_array($filtered)) {
            return $types;
        }

        $clean = [];
        foreach ($filtered as $type) {
            if (is_string($type) && $type !== '' && $type !== 'revision' && $type !== 'attachment') {
                $clean[] = $type;
            }
        }

        return array_values(array_unique($clean));
    }
}
