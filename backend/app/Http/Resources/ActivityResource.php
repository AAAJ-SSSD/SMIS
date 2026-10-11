<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/**
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $properties = $this->properties?->toArray() ?? [];

        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'event' => $this->event,
            'description' => $this->description,
            'causer' => $this->personSummary($this->causer),
            'subject' => $this->personSummary($this->subject),
            'old' => $properties['old'] ?? null,
            'new' => $properties['attributes'] ?? null,
            'context' => collect($properties)->except(['old', 'attributes'])->all() ?: null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function personSummary(mixed $model): ?array
    {
        if (! $model instanceof User) {
            return null;
        }

        return ['id' => $model->ulid, 'name' => $model->name, 'email' => $model->email];
    }
}
