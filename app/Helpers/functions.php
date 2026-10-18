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
