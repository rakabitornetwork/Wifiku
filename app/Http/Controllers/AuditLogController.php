<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search'));
        $logName = (string) $request->query('log');

        $activities = Activity::query()
            ->with('causer')
            ->when($logName !== '', fn ($query) => $query->where('log_name', $logName))
            ->when($search !== '', fn ($query) => $query->where('description', 'like', "%{$search}%"))
            ->orderByDesc('id')
            ->paginate($this->perPage(25))
            ->withQueryString()
            ->through(fn (Activity $activity): array => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'event' => $activity->event,
                'description' => $activity->description,
                'causer' => $activity->causer?->name ?? 'Sistem',
                'subject' => $activity->subject_type ? class_basename($activity->subject_type).' #'.$activity->subject_id : null,
                'changes' => $activity->attribute_changes?->toArray() ?: null,
                'properties' => $activity->properties?->toArray() ?: null,
                'created_at' => $activity->created_at,
            ]);

        return Inertia::render('AuditLog/Index', [
            'activities' => $activities,
            'logNames' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name'),
            'filters' => ['search' => $search, 'log' => $logName],
        ]);
    }
}
