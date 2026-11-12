<?php

declare(strict_types=1);

namespace App\Validators;

final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    public function __construct(private readonly array $data)
    {
    }

    private function val(string $f): string
    {
        $v = $this->data[$f] ?? '';
        return is_string($v) ? trim($v) : '';
    }

    private function fail(string $f, string $msg): static
    {
        $this->errors[$f] ??= $msg;
        return $this;
    }

    public function required(string $f, string $label): static
    {
        return $this->val($f) === '' ? $this->fail($f, "El camp «{$label}» és obligatori.") : $this;
    }

    public function maxLen(string $f, string $label, int $max): static
    {
        return mb_strlen($this->val($f)) > $max ? $this->fail($f, "El camp «{$label}» no pot superar els {$max} caràcters.") : $this;
    }

    public function email(string $f, string $label, bool $required = true): static
    {
        $v = $this->val($f);
        if ($v === '') {
            return $required ? $this->required($f, $label) : $this;
        }
        if (mb_strlen($v) > 190 || !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            return $this->fail($f, "El camp «{$label}» no és una adreça de correu vàlida.");
        }
        return $this;
    }

    public function phone(string $f, string $label): static
    {
        $v = $this->val($f);
        return ($v !== '' && !preg_match('/^[0-9+()\s.-]{6,30}$/', $v)) ? $this->fail($f, "El camp «{$label}» no és un telèfon vàlid.") : $this;
    }

    public function intRange(string $f, string $label, int $min, int $max, bool $required = false): static
    {
        $v = $this->val($f);
        if ($v === '') {
            return $required ? $this->required($f, $label) : $this;
        }
        if (!preg_match('/^\d+$/', $v) || (int) $v < $min || (int) $v > $max) {
            return $this->fail($f, "El camp «{$label}» ha de ser un número entre {$min} i {$max}.");
        }
        return $this;
    }

    public function date(string $f, string $label): static
    {
        $v = $this->val($f);
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return ($d === false || $d->format('Y-m-d') !== $v) ? $this->fail($f, "El camp «{$label}» no és una data vàlida.") : $this;
    }

    public function time(string $f, string $label): static
    {
        return !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $this->val($f)) ? $this->fail($f, "El camp «{$label}» no és una hora vàlida.") : $this;
    }

    public function season(string $f, string $label): static
    {
        $v = $this->val($f);
        if (!preg_match('/^(\d{4})-(\d{4})$/', $v, $m) || (int) $m[2] !== (int) $m[1] + 1) {
            return $this->fail($f, "El camp «{$label}» ha de tenir el format 2026-2027.");
        }
        return $this;
    }

    public function inList(string $f, string $label, array $allowed, bool $required = true): static
    {
        $v = $this->val($f);
        if ($v === '') {
            return $required ? $this->required($f, $label) : $this;
        }
        return !in_array($v, array_map('strval', $allowed), true) ? $this->fail($f, "El valor del camp «{$label}» no és vàlid.") : $this;
    }

    public function password(string $f, string $confirmField = 'password_confirmation', int $min = 8): static
    {
        $v = $this->data[$f] ?? '';
        if (!is_string($v) || $v === '') {
            return $this->fail($f, 'Cal introduir una contrasenya.');
        }
        if (strlen($v) < $min || strlen($v) > 128 || !preg_match('/[A-Za-z]/', $v) || !preg_match('/\d/', $v)) {
            return $this->fail($f, "La contrasenya ha de tenir almenys {$min} caràcters, amb lletres i números.");
        }
        if (!hash_equals($v, (string) ($this->data[$confirmField] ?? ''))) {
            return $this->fail($confirmField, 'Les contrasenyes no coincideixen.');
        }
        return $this;
    }

    public function error(string $f, string $msg): static
    {
        return $this->fail($f, $msg);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
