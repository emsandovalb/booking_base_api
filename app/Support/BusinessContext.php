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
            Business::resolveBySlug($slug),
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

    /**
     * A context is only valid when it resolves to a real, active business.
     * A missing slug is NOT valid here: tenant-scoped data must never be
     * reachable without an explicit, resolved business context. Callers
     * that legitimately don't need a business (e.g. auth endpoints) must
     * check hasSlug() themselves rather than relying on isValid().
     */
    public function isValid(): bool
    {
        return $this->hasSlug() && $this->business !== null;
    }

    /**
     * Scopes a query to the resolved business. Fails closed: if this
     * context does not resolve to a business (no slug, or an unknown
     * slug), the query is constrained to return nothing rather than
     * falling back to an unscoped, cross-tenant result set. Callers
     * should still check isValid() first so they can return a clean
     * 4xx instead of a silently empty result.
     */
    public function applyTo(Builder $query, string $column = 'business_id'): Builder
    {
        $businessId = $this->currentBusinessId();

        if ($businessId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($column, $businessId);
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
