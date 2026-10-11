<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditLogIndexRequest;
use App\Http\Resources\ActivityResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $activities = Activity::query()
            ->with(['causer', 'subject'])
            ->when($filters['log_name'] ?? null, fn (Builder $q, string $log) => $q->where('log_name', $log))
            ->when($filters['event'] ?? null, fn (Builder $q, string $event) => $q->where('event', $event))
            ->when($filters['causer'] ?? null, function (Builder $q, string $ulid) {
                $causerId = User::withTrashed()->where('ulid', $ulid)->value('id');
                $q->where('causer_type', (new User)->getMorphClass())->where('causer_id', $causerId ?? 0);
            })
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return ActivityResource::collection($activities);
    }
}
