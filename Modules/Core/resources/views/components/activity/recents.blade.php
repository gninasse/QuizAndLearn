<div class="recent-activities">
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-history me-2"></i>
                Activités Récentes
                @if($module)
                    <span class="badge bg-primary ms-2">{{ ucfirst($module) }}</span>
                @endif
            </h5>
            <a href="{{ route('cores.activities.index', $module ? ['module' => $module] : []) }}" 
               class="btn btn-sm btn-outline-primary">
                Voir tout
            </a>
        </div>
        <div class="list-group list-group-flush">
            @forelse($activities as $activity)
                <div class="list-group-item">
                    <div class="d-flex align-items-start">
                        <i class="fas {{ $activity->icon }} me-3 mt-1"></i>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div>
                                    <span class="badge bg-{{ $activity->badge_color }} me-1">
                                        {{ ucfirst(str_replace('_', ' ', $activity->description)) }}
                                    </span>
                                    @if($activity->causer)
                                        <small class="text-muted">par {{ $activity->causer->name }}</small>
                                    @endif
                                </div>
                                <small class="text-muted">{{ $activity->created_at->diffForHumans() }}</small>
                            </div>
                            
                            @if($activity->subject_type)
                                <div class="small text-muted">
                                    {{ class_basename($activity->subject_type) }}
                                    @if($activity->subject_id)
                                        #{{ $activity->subject_id }}
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="list-group-item text-center text-muted py-4">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <p class="mb-0">Aucune activité récente</p>
                </div>
            @endforelse
        </div>
        <div class="card-footer bg-white border-0 text-center">
            <a href="{{ route('cores.activities.index', $module ? ['module' => $module] : []) }}" 
               class="btn btn-sm btn-link">
                Voir l'historique complet <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>