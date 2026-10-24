<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode', 'nama'])]
class Fakultas extends Model
{
    public function prodi(): HasMany
    {
        return $this->hasMany(Prodi::class);
    }
}
