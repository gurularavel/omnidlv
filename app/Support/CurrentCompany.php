<?php

namespace App\Support;

use App\Models\Company;
use RuntimeException;

/**
 * Holds the tenant for the current request or job. Registered as a scoped singleton so it
 * resets between requests on long-running workers (queue, Reverb, Octane).
 */
class CurrentCompany
{
    private ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function check(): bool
    {
        return $this->company !== null;
    }

    public function getOrFail(): Company
    {
        return $this->company ?? throw new RuntimeException('No current company is set.');
    }

    /** Runs $callback with $company as tenant and restores the previous tenant afterwards. */
    public function run(Company $company, callable $callback): mixed
    {
        $previous = $this->company;
        $this->company = $company;

        try {
            return $callback($company);
        } finally {
            $this->company = $previous;
        }
    }
}
