<?php

declare(strict_types=1);

namespace App\Helpers;

final class Paginator
{
    public int $pages;
    public int $offset;

    public function __construct(public readonly int $total, public int $page, public readonly int $perPage)
    {
        $this->pages = max(1, (int) ceil($total / $perPage));
        $this->page = min(max(1, $page), $this->pages);
        $this->offset = ($this->page - 1) * $perPage;
    }

    public static function make(int $total, mixed $page, ?int $perPage = null): self
    {
        return new self($total, (int) $page, $perPage ?? (int) Config::get('per_page', 15));
    }
}
