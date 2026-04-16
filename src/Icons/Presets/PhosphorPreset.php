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
        ];
    }

    public function requires(): string
    {
        return 'codeat3/blade-phosphor-icons';
    }
}
