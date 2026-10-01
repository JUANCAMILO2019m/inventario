<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

trait HasPhoto
{
    public function getPhotoUrlAttribute(): ?string
    {
        if (blank($this->photo)) {
            return null;
        }

        return str_starts_with($this->photo, 'http')
            ? $this->photo
            : Storage::url($this->photo);
    }
}