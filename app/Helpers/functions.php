<?php

declare(strict_types=1);

use App\Helpers\Csrf;
use App\Helpers\Request;
use App\Helpers\View;
use App\Services\AuthService;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/', array $query = []): string
{
    $query = array_filter($query, fn ($v) => $v !== '' && $v !== null && $v !== []);
    return Request::basePath() . '/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query) : '');
}

function absolute_url(string $path = '/'): string
{
    $base = (string) \App\Helpers\Config::get('url');
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . $path;
    $v = is_file($file) ? filemtime($file) : 1;
    return Request::basePath() . '/assets/' . $path . '?v=' . $v;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function old(string $key, mixed $default = ''): mixed
{
    return View::$old[$key] ?? $default;
}

function field_error(string $key): string
{
    return View::$errors[$key] ?? '';
}

function current_user(): ?array
{
    return AuthService::user();
}

function is_admin(): bool
{
    return AuthService::isAdmin();
}

function fmt_date(?string $d): string
{
    return $d ? date('d/m/Y', strtotime($d)) : '';
}

function fmt_time(?string $t): string
{
    return $t ? substr($t, 0, 5) : '';
}

function fmt_datetime(?string $d): string
{
    return $d ? date('d/m/Y H:i', strtotime($d)) : '';
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(bool $c): string
{
    return $c ? ' checked' : '';
}

function in_old(string $key, int|string $id, array $fallback = []): bool
{
    $list = View::$old[$key] ?? $fallback;
    return in_array((string) $id, array_map('strval', (array) $list), true);
}

/**
 * Camp de formulari amb etiqueta, valor recuperat i error associat.
 * Opcions: type, required, help, rows (textarea), attrs (array), value (substitueix old()).
 */
function input_field(string $name, string $label, array $o = []): string
{
    $type = $o['type'] ?? 'text';
    $value = array_key_exists('value', $o) ? $o['value'] : old($name, '');
    $err = field_error($name);
    $id = 'f-' . $name;
    $attrs = '';
    foreach (($o['attrs'] ?? []) as $k => $v) {
        $attrs .= ' ' . e($k) . '="' . e($v) . '"';
    }
    $desc = trim(($err ? "err-$name " : '') . (!empty($o['help']) ? "help-$name" : ''));
    $common = ' id="' . $id . '" name="' . e($name) . '"' . (!empty($o['required']) ? ' required' : '')
        . ($err ? ' aria-invalid="true"' : '') . ($desc ? ' aria-describedby="' . $desc . '"' : '') . $attrs;
    $html = '<div class="field' . ($err ? ' has-error' : '') . '"><label for="' . $id . '">' . e($label)
        . (!empty($o['required']) ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>';
    if ($type === 'textarea') {
        $html .= '<textarea' . $common . ' rows="' . (int) ($o['rows'] ?? 4) . '">' . e($value) . '</textarea>';
    } else {
        $html .= '<input type="' . e($type) . '"' . $common . ($type === 'password' ? '' : ' value="' . e($value) . '"') . '>';
    }
    if (!empty($o['help'])) {
        $html .= '<p class="help" id="help-' . e($name) . '">' . e($o['help']) . '</p>';
    }
    if ($err) {
        $html .= '<p class="field-error" id="err-' . e($name) . '">' . e($err) . '</p>';
    }
    return $html . '</div>';
}

/** @param array<string|int,string> $options valor => text */
function select_field(string $name, string $label, array $options, array $o = []): string
{
    $current = array_key_exists('value', $o) ? $o['value'] : old($name, '');
    $err = field_error($name);
    $id = 'f-' . $name;
    $html = '<div class="field' . ($err ? ' has-error' : '') . '"><label for="' . $id . '">' . e($label)
        . (!empty($o['required']) ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>'
        . '<select id="' . $id . '" name="' . e($name) . '"' . (!empty($o['required']) ? ' required' : '')
        . ($err ? ' aria-invalid="true" aria-describedby="err-' . e($name) . '"' : '') . '>';
    if (!empty($o['blank'])) {
        $html .= '<option value="">' . e($o['blank']) . '</option>';
    }
    foreach ($options as $v => $text) {
        $html .= '<option value="' . e($v) . '"' . selected($current, $v) . '>' . e($text) . '</option>';
    }
    $html .= '</select>';
    if ($err) {
        $html .= '<p class="field-error" id="err-' . e($name) . '">' . e($err) . '</p>';
    }
    return $html . '</div>';
}

function status_badge(string $status): string
{
    $cls = ['draft' => 'neutral', 'published' => 'success', 'cancelled' => 'danger', 'finished' => 'info'][$status] ?? 'neutral';
    return '<span class="badge badge-' . $cls . '">' . e(\App\Helpers\Labels::status($status)) . '</span>';
}

function active_badge(mixed $active): string
{
    return (int) $active === 1 ? '<span class="badge badge-success">Actiu</span>' : '<span class="badge badge-neutral">Inactiu</span>';
}

function human_size(int $bytes): string
{
    return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
}

/** Formulari POST amb CSRF i, opcionalment, confirmació (gestionada per JavaScript). */
function post_form_open(string $path, string $confirm = '', string $class = 'inline-form'): string
{
    return '<form method="post" action="' . e(url($path)) . '" class="' . e($class) . '"'
        . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field();
}

/** Icona SVG en línia (traç, estil Lucide). Decorativa: s'amaga als lectors de pantalla. */
function icon(string $name, string $class = ''): string
{
    static $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
        'calendar' => '<rect x="3" y="4.5" width="18" height="16.5" rx="2.5"/><path d="M3 10h18M8 2.5v4M16 2.5v4"/>',
        'ball' => '<circle cx="12" cy="12" r="9.5"/><path d="m12 7.8 3.9 2.8-1.5 4.6H9.6L8.1 10.6z"/><path d="M12 7.8V2.7M15.9 10.6l4.8-1.6M14.4 15.2l3 4.1M9.6 15.2l-3 4.1M8.1 10.6 3.3 9"/>',
        'dumbbell' => '<path d="M6.5 6.5v11M17.5 6.5v11M3.5 9v6M20.5 9v6M6.5 12h11"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 8.5 3 8.5H3S6 15 6 8"/><path d="M10 20.5a2.2 2.2 0 0 0 4 0"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/><path d="M16 4.7a3.5 3.5 0 0 1 0 6.6M18 14.4c2.2.7 3.5 2.6 3.5 5.6"/>',
        'tag' => '<path d="M3 12.6V4a1 1 0 0 1 1-1h8.6a1 1 0 0 1 .7.3l7.4 7.4a1 1 0 0 1 0 1.4l-8.6 8.6a1 1 0 0 1-1.4 0L3.3 13.3a1 1 0 0 1-.3-.7z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
        'shield' => '<path d="M12 3 4.5 6v6c0 4.5 3.2 7.8 7.5 9 4.3-1.2 7.5-4.5 7.5-9V6z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
        'key' => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M16 7l3 3M14 9l2 2"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.5 7 8.5 6 8.5-6"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2.5"/><circle cx="9" cy="9" r="1.8"/><path d="m21 15-4.5-4.5L5 21"/>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7.5V12l3 2"/>',
        'list' => '<path d="M9 6h12M9 12h12M9 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="M4 20h4L19.5 8.5a2.1 2.1 0 0 0-3-3L5 17z"/><path d="m14.5 7.5 3 3"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'save' => '<path d="M5 3h11l4 4v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M8 3v5h7V3M8 21v-7h8v7"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'filter' => '<path d="M3 5h18l-7 8.5V20l-4-2v-4.5z"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'check' => '<path d="m4.5 12.5 5 5L20 7"/>',
        'download' => '<path d="M12 3v12m0 0-4.5-4.5M12 15l4.5-4.5M4 20h16"/>',
        'upload' => '<path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M4 20h16"/>',
        'arrow-up' => '<path d="M12 19V5m-6 6 6-6 6 6"/>',
        'arrow-down' => '<path d="M12 5v14m6-6-6 6-6-6"/>',
        'send' => '<path d="m21 3-9.5 18-2.5-7.5L1.5 11z"/><path d="m21 3-12 10.5"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.5-3.5L3 10M4 13a8 8 0 0 0 14.5 3.5L21 14"/><path d="M3 4v6h6M21 20v-6h-6"/>',
        'smartphone' => '<rect x="6.5" y="2.5" width="11" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'chevron-right' => '<path d="m9 5 7 7-7 7"/>',
        'arrow-left' => '<path d="M19 12H5m6 6-6-6 6-6"/>',
        'eye-off' => '<path d="M3 3l18 18M10.6 5.2A9.8 9.8 0 0 1 12 5c5 0 9 4.5 10 7a12 12 0 0 1-3 4M6.5 6.8A12.6 12.6 0 0 0 2 12c1 2.5 5 7 10 7a9.6 9.6 0 0 0 4-.9"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 2.5v3M12 18.5v3M2.5 12h3M18.5 12h3M5.3 5.3l2.1 2.1M16.6 16.6l2.1 2.1M18.7 5.3l-2.1 2.1M7.4 16.6l-2.1 2.1"/>',
        'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '') . '" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}
