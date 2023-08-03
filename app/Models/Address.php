<?php

namespace App\Models;

use App\Constants\DatabaseTableConstant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $user_id
 * @property string|null $address
 * @property string|null $village_or_barangay
 * @property string|null $city_or_municipality
 * @property string|null $state_or_province
 * @property string|null $zip_or_postal_code
 * @property string|null $country
 * @property-read string|null $complete_address
 *
 * Model relationships
 * @property-read User|null $user
 */
class Address extends BaseModel
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = DatabaseTableConstant::ADDRESSES;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'complete_address'
    ];

    /**
     * Get the user's full name.
     */
    protected function completeAddress(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $address = $attributes['address'] ?? '';
                $villageOrBarangay = $attributes['village_or_barangay'] ?? '';
                $cityOrMunicipality = $attributes['city_or_municipality'] ?? '';
                $stateOrProvince = $attributes['state_or_province'] ?? '';
                $zipOrPostalCode = $attributes['zip_or_postal_code'] ?? '';
                $country = $attributes['country'] ?? '';

                return "{$address} {$villageOrBarangay} {$cityOrMunicipality} {$stateOrProvince} {$zipOrPostalCode} {$country}";
            }
        );
    }

    /**
     * The user of this address.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
