<?php

namespace App\Support;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;

class BusinessContext
{
    public function __construct(
        private readonly ?string $slug,
        private readonly ?Business $business,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $slug = self::cleanSlug($request->query('business_slug'));
        if ($slug === null) {
            $slug = self::cleanSlug($request->header('X-Business-Slug'));
        }

        if ($slug === null) {
            return new self(null, null);
        }

        return new self(
            $slug,
            Business::query()->where('slug', $slug)->first(),
        );
    }

    public function hasSlug(): bool
    {
        return $this->slug !== null;
    }

    public function slug(): ?string
    {
        return $this->slug;
    }

    public function business(): ?Business
    {
        return $this->business;
    }

    public function currentBusiness(): ?Business
    {
        return $this->business();
    }

    public function businessId(): ?int
    {
        return $this->business?->id;
    }

    public function currentBusinessId(): ?int
    {
        return $this->businessId();
    }

    public function requireBusiness(): ?Business
    {
        if ($this->hasSlug() && $this->business === null) {
            throw (new ModelNotFoundException())->setModel(Business::class, [$this->slug]);
        }

        return $this->business;
    }

    public function isValid(): bool
    {
        return !$this->hasSlug() || $this->business !== null;
    }

    public function applyTo(Builder $query, string $column = 'business_id'): Builder
    {
        if (!$this->hasSlug()) {
            return $query;
        }

        if ($this->currentBusinessId() === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($column, $this->currentBusinessId());
    }

    public function userCanManageBusiness(User $user, Business $business): bool
    {
        return $this->userHasBusinessRole($user, $business, ['owner', 'admin']);
    }

    public function userHasBusinessRole(User $user, Business $business, array|string $roles): bool
    {
        $roles = Arr::wrap($roles);

        return $user->businesses()
            ->where('businesses.id', $business->id)
            ->wherePivot('status', 'active')
            ->wherePivotIn('role', $roles)
            ->exists();
    }

    private static function cleanSlug(mixed $value): ?string
    {
        $slug = is_string($value) ? trim($value) : trim((string) $value);

        return $slug === '' ? null : $slug;
    }
}
