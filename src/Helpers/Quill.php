<?php

declare(strict_types=1);

/**
 * Quill.php
 *
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */
namespace Blackcube\Ssr\Helpers;

/**
 * Class Quill
 * Clean HTML content from Quill editor
 */
class Quill {
    /**
     * @param string|null $htmlContent
     * @param array $keepTags list of tags to keep default to ['p'] needed for aria
     * @return string|null
     */
    public static function toRaw(
        ?string $htmlContent,
        array $keepTags = ['p']
    ): ?string
    {
        if ($htmlContent !== null) {
            $cleanHtml = self::cleanHtml($htmlContent);
            $cleanHtml = strip_tags($cleanHtml, $keepTags);
            return $cleanHtml;
        } else {
            return $htmlContent;
        }
    }

    /**
     * Remove style in tags, empty tags, span tags
     *
     * @param string|null $htmlContent
     * @param bool $removeStyles remove styling
     * @param bool $removeEmptyTags remove empty tags
     * @param bool $removeSpan remove spans but keep content
     * @return string|null
     */
    public static function cleanHtml(
        ?string $htmlContent,
        bool $removeStyles = true,
        bool $removeEmptyTags = true,
        bool $removeSpan = true
    ): ?string
    {
        if ($htmlContent !== null) {
            $cleanHtml = $htmlContent;
            if ($removeStyles === true) {
                $cleanHtml = preg_replace('/style="([^"])*"/', '', $cleanHtml);
            }
            if ($removeEmptyTags === true) {
                $cleanHtml = preg_replace('/<([\S]+)([^>]*)>[\s|&nbsp;|<br(\s|\/)*>]*<\/\1>/', '', $cleanHtml);
            }
            if ($removeSpan === true) {
                $cleanHtml = preg_replace('/<span[^>]*>([^<]*)<\/span>/', '${1}', $cleanHtml);
            }
            return $cleanHtml;
        } else {
            return $htmlContent;
        }
    }
}