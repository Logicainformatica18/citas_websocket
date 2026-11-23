<?php

namespace App\Support\Eloquent;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * BelongsToMany que no escribe en la tabla pivote si el modelo padre no está guardado.
 *
 * JobOffer cancela el INSERT de las ofertas cuyo título se excluye (rol no TI). Los ~70 scrapers
 * hacen JobOffer::updateOrCreate(...) y luego $offer->languages()->syncWithoutDetaching(...): sin
 * esto, ese sync insertaría job_offer_id = NULL y el scraper fallaría. Con el padre guardado se
 * comporta exactamente igual que BelongsToMany.
 */
class SavedParentBelongsToMany extends BelongsToMany
{
    private const NO_CHANGES = ['attached' => [], 'detached' => [], 'updated' => []];

    public function attach($ids, array $attributes = [], $touch = true)
    {
        if ($this->parent->exists) {
            parent::attach($ids, $attributes, $touch);
        }
    }

    public function detach($ids = null, $touch = true)
    {
        return $this->parent->exists ? parent::detach($ids, $touch) : 0;
    }

    public function sync($ids, $detaching = true)
    {
        return $this->parent->exists ? parent::sync($ids, $detaching) : self::NO_CHANGES;
    }

    public function syncWithoutDetaching($ids)
    {
        return $this->parent->exists ? parent::syncWithoutDetaching($ids) : self::NO_CHANGES;
    }

    public function syncWithPivotValues($ids, array $values, bool $detaching = true)
    {
        return $this->parent->exists ? parent::syncWithPivotValues($ids, $values, $detaching) : self::NO_CHANGES;
    }

    public function updateExistingPivot($id, array $attributes, $touch = true)
    {
        return $this->parent->exists ? parent::updateExistingPivot($id, $attributes, $touch) : 0;
    }

    public function toggle($ids, $touch = true)
    {
        return $this->parent->exists ? parent::toggle($ids, $touch) : self::NO_CHANGES;
    }
}
