<?php

namespace App\Tenancy;

use Illuminate\Http\Request;

class CurrentPharmacy
{
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $this->normalizeId($id);
    }

    public function resolveFromRequest(Request $request): void
    {
        $user = $request->user();
        $userPharmacyId = $user ? data_get($user, 'pharmacy_id') : null;

        if ($userPharmacyId !== null) {
            $resolved = $this->normalizeId($userPharmacyId);

            if ($resolved !== null) {
                $this->setResolvedValue($resolved);
                return;
            }
        }

        $session = $request->hasSession() ? $request->session() : null;

        foreach (['current_pharmacy_id', 'filament.current_pharmacy_id', 'pharmacy_id'] as $key) {
            if ($session?->has($key)) {
                $resolved = $this->normalizeId($session->get($key));

                if ($resolved !== null) {
                    $this->setResolvedValue($resolved);
                    return;
                }
            }
        }

        foreach (['X-Pharmacy-Id', 'X-Tenant-Id'] as $header) {
            if ($request->headers->has($header)) {
                $resolved = $this->normalizeId($request->header($header));

                if ($resolved !== null) {
                    $this->setResolvedValue($resolved);
                    return;
                }
            }
        }

        $this->setId(null);
    }

    private function normalizeId(mixed $id): ?int
    {
        if (! is_numeric($id)) {
            return null;
        }

        $normalized = (int) $id;

        return $normalized > 0 ? $normalized : null;
    }

    private function setResolvedValue(mixed $id): void
    {
        $this->setId($id);
    }
}
