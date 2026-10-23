<?php

declare(strict_types=1);

namespace WpAutoTranslator\Admin;

use WpAutoTranslator\LocaleRegistry;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class Fields
{
    /**
     * @param list<string> $selected
     */
    public static function localeChecklist(
        string $name,
        array $selected,
        string $exclude,
        LocaleRegistry $locales
    ): void {
        echo '<div class="wat-locale-grid">';
        foreach ($locales->all() as $code => $meta) {
            if ($code === $exclude) {
                continue;
            }
            $id = $name . '_' . $code;
            echo '<label for="' . esc_attr($id) . '">';
            echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '[]" value="' . esc_attr($code) . '"';
            checked(in_array($code, $selected, true));
            echo '> ';
            echo esc_html($meta['label']);
            echo ' <code>' . esc_html($code) . '</code>';
            echo '</label>';
        }
        echo '</div>';
    }

    public static function localeSelect(string $name, string $selected, LocaleRegistry $locales): void
    {
        echo '<select name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" required>';
        echo '<option value="">' . esc_html__('Select a locale', 'wp-auto-translator') . '</option>';
        foreach ($locales->all() as $code => $meta) {
            echo '<option value="' . esc_attr($code) . '"';
            selected($selected, $code);
            echo '>' . esc_html($meta['label'] . ' (' . $code . ')') . '</option>';
        }
        echo '</select>';
    }
}
