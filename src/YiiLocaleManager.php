<?php

declare(strict_types=1);

/**
 * YiiLocaleManager.php
 *
 * PHP Version 8.1
 *
 * @copyright 2010-2026 Blackcube
 * @license https://www.blackcube.io/license
 * @link https://www.blackcube.io
 */

namespace Blackcube\Ssr;

use Blackcube\Dcore\Entities\Language;
use Locale as IntlLocale;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\I18n\Locale;
use Yiisoft\I18n\LocaleProvider;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Centralized locale manager — single source for set/get.
 * Supported locales come from Language::query()->active() (DB).
 * Persistence between requests via session.
 * Resolution order: session > Accept-Language > none.
 * Inspired by Yiisoft\Yii\Middleware\Locale, but does not use the URL path.
 */
final class YiiLocaleManager
{
    private const SESSION_KEY = 'locale';

    /** @var array<string, Language>|null */
    private static ?array $supportedLanguages = null;

    public function __construct(
        private readonly LocaleProvider $localeProvider,
        private readonly SessionInterface $session,
        private readonly ?TranslatorInterface $translator = null,
    ) {}

    /**
     * Set the current locale. Posted only when a valid content/tag is found.
     * Persists to session.
     */
    public function set(string $lang): void
    {
        if (!$this->isSupported($lang)) {
            return;
        }
        $this->localeProvider->set(new Locale($lang));
        $this->translator?->setLocale($lang);
        $this->session->set(self::SESSION_KEY, $lang);
    }

    /**
     * Get the current locale (primary language code).
     */
    public function get(): string
    {
        return $this->localeProvider->get()->language();
    }

    /**
     * Restore the locale at request start: session > Accept-Language > nothing.
     */
    public function resolve(ServerRequestInterface $request): void
    {
        $session = $this->session->get(self::SESSION_KEY);
        $accept = $request->getHeaderLine('Accept-Language');
        $browser = $accept === '' ? '' : (string) IntlLocale::getPrimaryLanguage(IntlLocale::acceptFromHttp($accept) ?: '');
        $lang = match (true) {
            is_string($session) && $this->isSupported($session) => $session,
            $browser !== '' && $this->isSupported($browser) => $browser,
            default => null,
        };
        if ($lang !== null) {
            $this->localeProvider->set(new Locale($lang));
            $this->translator?->setLocale($lang);
        }
    }

    private function isSupported(string $lang): bool
    {
        if (self::$supportedLanguages === null) {
            self::$supportedLanguages = [];
            foreach (Language::query()->active()->each() as $language) {
                self::$supportedLanguages[$language->getId()] = $language;
            }
        }
        return isset(self::$supportedLanguages[$lang]);
    }
}
