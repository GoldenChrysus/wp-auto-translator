<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

/**
 * Holds HTML comments aside while another sanitizer runs.
 *
 * WordPress's kses removes comments, and Gutenberg stores block structure in them.
 * Comments do not execute, so the original comment text can be restored afterwards.
 */
final class HtmlComments
{
    public static function preserve(string $content, callable $sanitize): string
    {
        $comments = [];
        $protected = preg_replace_callback(
            '/<!--.*?-->/s',
            static function (array $match) use (&$comments): string {
                $token = 'watcomment' . count($comments) . 'token';
                $comments[$token] = $match[0];
                return $token;
            },
            $content
        );

        if (! is_string($protected)) {
            return $sanitize($content);
        }

        $clean = $sanitize($protected);
        if (! is_string($clean)) {
            return $protected;
        }

        return strtr($clean, $comments);
    }
}
