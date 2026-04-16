<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons\Presets;

use Pushery\WireKit\Contracts\IconPreset;

/**
 * Tabler preset — outline style, 5700+ icons.
 *
 * @see https://tabler.io/icons
 */
final class TablerPreset implements IconPreset
{
    public function icons(): array
    {
        return [
            // Navigation & Actions
            'close' => 'tabler-x',
            'menu' => 'tabler-menu-2',
            'search' => 'tabler-search',
            'chevron-down' => 'tabler-chevron-down',
            'chevron-up' => 'tabler-chevron-up',
            'chevron-left' => 'tabler-chevron-left',
            'chevron-right' => 'tabler-chevron-right',
            'check' => 'tabler-check',
            'plus' => 'tabler-plus',
            'minus' => 'tabler-minus',

            // Status & Feedback
            'info' => 'tabler-info-circle',
            'success' => 'tabler-circle-check',
            'warning' => 'tabler-alert-triangle',
            'danger' => 'tabler-circle-x',

            // Objects & Visibility
            'user' => 'tabler-user',
            'calendar' => 'tabler-calendar',
            'trash' => 'tabler-trash',
            'edit' => 'tabler-edit',
            'eye' => 'tabler-eye',
            'eye-off' => 'tabler-eye-off',
            'upload' => 'tabler-upload',
            'download' => 'tabler-download',
            'sort-asc' => 'tabler-sort-ascending',
            'sort-desc' => 'tabler-sort-descending',
            'filter' => 'tabler-filter',
            'external-link' => 'tabler-external-link',
        ];
    }

    public function requires(): string
    {
        return 'ryangjchandler/blade-tabler-icons';
    }
}
