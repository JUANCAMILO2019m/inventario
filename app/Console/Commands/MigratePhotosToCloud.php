<?php

namespace App\Console\Commands;

use App\Models\Animal;
use App\Models\Product;
use App\Services\ImageStorage;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

class MigratePhotosToCloud extends Command
{
    protected $signature = 'photos:migrate';
    protected $description = 'Sube a Cloudinary las fotos guardadas localmente';

    public function handle(): int
    {
        if (!ImageStorage::enabled()) {
            $this->error('Configura las variables CLOUDINARY_* en el .env primero.');
            return self::FAILURE;
        }

        foreach ([[Product::class, 'products'], [Animal::class, 'animals']] as [$model, $folder]) {
            $model::whereNotNull('photo')
                ->where('photo', 'not like', 'http%')
                ->each(function ($item) use ($folder) {
                    $path = public_path('storage/' . $item->photo);

                    if (!is_file($path)) {
                        $this->warn("Sin archivo local: {$item->photo} ({$item->name})");
                        return;
                    }

                    $file = new UploadedFile($path, basename($path), null, null, true);
                    $item->photo = ImageStorage::store($file, $folder);
                    $item->save();

                    $this->info("Migrada: {$item->name}");
                });
        }

        return self::SUCCESS;
    }
}