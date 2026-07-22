<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Phosphor preset — regular weight, 1500+ icons in 6 styles.
 *
 * Uses the regular weight (no suffix) for all aliases.
 *
 * @see https://phosphoricons.com
 */
final class PhosphorPreset implements IconPreset
{
    public function icons(): array
    {
        return [
            // Navigation & Actions
            'close' => 'phosphor-x',
            'menu' => 'phosphor-list',
            'search' => 'phosphor-magnifying-glass',
            'chevron-down' => 'phosphor-caret-down',
            'chevron-up' => 'phosphor-caret-up',
            'chevron-left' => 'phosphor-caret-left',
            'chevron-right' => 'phosphor-caret-right',
            'check' => 'phosphor-check',
            'plus' => 'phosphor-plus',
            'minus' => 'phosphor-minus',

            // Status & Feedback
            'info' => 'phosphor-info',
            'success' => 'phosphor-check-circle',
            'warning' => 'phosphor-warning',
            'danger' => 'phosphor-x-circle',

            // Objects & Visibility
            'user' => 'phosphor-user',
            'calendar' => 'phosphor-calendar-blank',
            'trash' => 'phosphor-trash',
            'edit' => 'phosphor-pencil-simple',
            'eye' => 'phosphor-eye',
            'eye-off' => 'phosphor-eye-slash',
            'upload' => 'phosphor-upload-simple',
            'download' => 'phosphor-download-simple',
            'sort-asc' => 'phosphor-sort-ascending',
            'sort-desc' => 'phosphor-sort-descending',
            'filter' => 'phosphor-funnel',
            'external-link' => 'phosphor-arrow-square-out',

            // common dashboard icons.
            'home' => 'phosphor-house',
            'moon' => 'phosphor-moon',
            'sun' => 'phosphor-sun',
            'book-open' => 'phosphor-book-open',
            'sign-out' => 'phosphor-sign-out',
            'megaphone' => 'phosphor-megaphone',
            'map' => 'phosphor-map-trifold',
            'file-text' => 'phosphor-file-text',

            // Common semantic aliases (v2.6.4) — shared keyset with every base
            // preset so they resolve without stacking; `live` stays marketing.
            'copy' => 'phosphor-copy',
            'globe' => 'phosphor-globe',
            'book' => 'phosphor-book-open',
            'lightbulb' => 'phosphor-lightbulb',

            // SaaS app aliases (v2.9.0) — shared keyset with every base preset.
            // Phosphor names: gear (settings) + squares-four (dashboard).
            'settings' => 'phosphor-gear',
            'gear' => 'phosphor-gear',
            'dashboard' => 'phosphor-squares-four',
            'billing' => 'phosphor-credit-card',
            'credit-card' => 'phosphor-credit-card',

            // Infrastructure & system — mapped to Phosphor's icon names.
            'server' => 'phosphor-server',
            'database' => 'phosphor-database',
            'cloud' => 'phosphor-cloud',
            'shield' => 'phosphor-shield-check', // parity with the base `shield` alias
            'shield-check' => 'phosphor-shield-check',
            'inbox' => 'phosphor-tray', // phosphor calls the inbox glyph `tray`
            'bolt' => 'phosphor-lightning', // phosphor calls it `lightning`
            'refresh' => 'phosphor-arrows-clockwise', // phosphor's rotate glyph
            // Media controls (WIRE-226).
            'play' => 'phosphor-play',
            'pause' => 'phosphor-pause',
            'stop' => 'phosphor-stop',
            'speaker' => 'phosphor-speaker-high',
            'mute' => 'phosphor-speaker-slash',
            'microphone' => 'phosphor-microphone',
        ];
    }

    public function requires(): string
    {
        return 'codeat3/blade-phosphor-icons';
    }
}
