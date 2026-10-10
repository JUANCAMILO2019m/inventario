<?php

namespace App\Models\Concerns;

use App\Models\User;

trait TracksUser
{
    public static function bootTracksUser(): void
    {
        static::creating(function ($model) {
            if ($model->user_id === null && auth()->check()) {
                $model->user_id = auth()->id();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}