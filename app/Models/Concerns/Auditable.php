<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Str;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => $model->writeAudit('created'));
        static::updated(fn ($model) => $model->writeAudit('updated'));
        static::deleted(fn ($model) => $model->writeAudit('deleted'));
    }

    /** Campos que no se registran (ruido o datos sensibles). Cada modelo puede redefinirlo. */
    protected function auditExcept(): array
    {
        return [];
    }

    /** Campos cuyo valor se oculta, pero cuyo cambio sí se registra. */
    protected function auditMasked(): array
    {
        return [];
    }

    public function auditLabel(): string
    {
        return (string) ($this->name ?? $this->title ?? '#' . $this->getKey());
    }

    protected function writeAudit(string $event): void
    {
        $except = array_merge(['created_at', 'updated_at', 'user_id'], $this->auditExcept());

        if ($event === 'updated') {
            $changes = collect($this->getChanges())
                ->except($except)
                ->map(fn ($new, $key) => [
                    $this->auditValue($key, $this->getOriginal($key)),
                    $this->auditValue($key, $new),
                ])
                ->all();

            if (empty($changes)) {
                return;
            }
        } else {
            $changes = collect($this->getAttributes())
                ->except($except)
                ->filter(fn ($value) => $value !== null)
                ->map(fn ($value, $key) => $this->auditValue($key, $value))
                ->all();
        }

        $user = auth()->user();

        AuditLog::create([
            'user_id'        => $user?->id,
            'user_name'      => $user?->name,
            'event'          => $event,
            'auditable_type' => static::class,
            'auditable_id'   => $this->getKey(),
            'label'          => Str::limit($this->auditLabel(), 200, ''),
            'changes'        => $changes,
            'ip_address'     => request()->ip(),
        ]);
    }

    private function auditValue(string $key, mixed $value): mixed
    {
        if (in_array($key, $this->auditMasked(), true)) {
            return $value === null ? null : '••••••';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format($value->format('H:i:s') === '00:00:00' ? 'Y-m-d' : 'Y-m-d H:i');
        }

        return is_string($value) ? Str::limit($value, 200) : $value;
    }
}