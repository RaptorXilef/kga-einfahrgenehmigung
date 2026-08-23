<?php

declare(strict_types=1);

namespace App\Core\Security;

use Stringable;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class Sanitizer
{
    /**
     * Entfernt alle HTML-Tags und unsichtbare Leerzeichen. (Für Namen, IDs, einfache Texte)
     */
    public static function string(mixed $input): string
    {
        $str = \is_string($input) ? $input : (\is_scalar($input) || $input instanceof Stringable ? (string) $input : '');

        return \trim(\strip_tags($str));
    }

    /**
     * Bereinigt E-Mail-Adressen von ungültigen Zeichen.
     */
    public static function email(mixed $input): string
    {
        $str = \is_string($input) ? $input : (\is_scalar($input) || $input instanceof Stringable ? (string) $input : '');
        $sanitized = \filter_var(\trim($str), \FILTER_SANITIZE_EMAIL);

        return $sanitized !== false ? $sanitized : '';
    }

    /**
     * Erlaubt Formatierungen für WYSIWYG-Editoren, blockiert aber <script>, <iframe> etc.
     */
    public static function html(mixed $input): string
    {
        $inputStr = \is_string($input) ? $input : (\is_scalar($input) || $input instanceof Stringable ? (string) $input : '');

        if (\trim($inputStr) === '') {
            return '';
        }

        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowElement('a', ['href', 'title', 'target', 'rel'])
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height'])
            ->allowAttribute('class', '*')
            ->allowAttribute('style', '*');

        $sanitizer = new HtmlSanitizer($config);

        return \trim($sanitizer->sanitize($inputStr));
    }

    public static function slugify(string $filename): string
    {
        $info = \pathinfo($filename);

        $nameRaw = $info['filename'] ?? '';
        $name = \is_string($nameRaw) ? $nameRaw : '';

        $extRaw = $info['extension'] ?? '';
        $ext = \is_string($extRaw) && $extRaw !== '' ? '.' . \strtolower($extRaw) : '';

        $name = \mb_strtolower($name, 'UTF-8');
        $name = \str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $name);

        $replaced = \preg_replace('/[^a-z0-9]+/', '-', $name);
        $name = \is_string($replaced) ? $replaced : $name;

        $replaced2 = \preg_replace('/-+/', '-', $name);
        $name = \is_string($replaced2) ? $replaced2 : $name;

        return \trim($name, '-') . $ext;
    }
}
