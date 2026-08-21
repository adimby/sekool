<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\BirthDatePrecision;
use App\Domain\Identity\Enums\Sex;
use App\Domain\Identity\PublicId\FanabePublicId;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory, HasVersion4Uuids;

    protected $table = 'persons';

    protected static function newFactory(): PersonFactory
    {
        return PersonFactory::new();
    }

    protected $fillable = [
        'public_id',
        'first_name',
        'last_name',
        'birth_date',
        'birth_date_precision',
        'sex',
        'preferred_language',
        'phone_e164',
        'email',
        'merged_into_person_id',
        'deceased_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'birth_date_precision' => BirthDatePrecision::class,
            'sex' => Sex::class,
            'deceased_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $person): void {
            if ($person->public_id === null) {
                $person->public_id = FanabePublicId::generate()->canonical();
            }
        });
    }

    public function account(): HasOne
    {
        return $this->hasOne(UserAccount::class);
    }

    public function publicIdFormatted(): string
    {
        return FanabePublicId::fromCanonical($this->public_id)->formatted();
    }
}
