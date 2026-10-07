<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Support\RegistryPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    /**
     * Show the audit trail of every change made in the registry.
     */
    public function __invoke(Request $request): Response
    {
        $filters = [
            'event' => $request->string('event')->toString(),
            'type' => $request->string('type')->toString(),
            'search' => $request->string('search')->trim()->toString(),
        ];

        $audits = Audit::query()
            ->with(['user', 'patient'])
            ->when($filters['event'] !== '', fn (Builder $query) => $query->where('event', $filters['event']))
            ->when($filters['type'] !== '', fn (Builder $query) => $query->where('auditable_type', $filters['type']))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where('label', 'like', '%'.$filters['search'].'%'))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Audit $audit): array => RegistryPresenter::audit($audit));

        return Inertia::render('audits/Index', [
            'audits' => $audits,
            'filters' => $filters,
        ]);
    }
}
