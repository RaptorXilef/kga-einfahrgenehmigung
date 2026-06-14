# Permit New Getter

| **Suchen nach (Alt)** | **Ersetzen durch (Neu: Getter)** |
| --- | --- |
| `$permit->owner->name` | `$permit->getOwnerName()` |
| `$permit->owner->email` | `$permit->getOwnerEmail()` |
| `$permit->owner->parzelle` | `$permit->getPlotNumber()` |
| `$permit->vehicle->typ` | `$permit->getVehicleType()` |
| `$permit->vehicle->kennzeichen` | `$permit->getLicensePlate()` |
| `$permit->vehicle->firma` | `$permit->getCompany()` |
| `$permit->validity->von` | `$permit->getValidFrom()` |
| `$permit->validity->bis` | `$permit->getValidUntil()` |
| `$permit->validity->preis` | `$permit->getPrice()` |
| `$permit->validity->zweck` | `$permit->getPurpose()` |
| `$permit->status->current` | `$permit->getStatus()` |
| `$permit->status->is_suspended` | `$permit->isSuspended()` |
| `$permit->status->suspension_reason` | `$permit->getSuspensionReason()` |
| `$permit->erstellt->format` | `$permit->getCreatedAt()->format` |
