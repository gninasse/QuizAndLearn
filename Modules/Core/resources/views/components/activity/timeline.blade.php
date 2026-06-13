<div class="activity-timeline">
    @forelse($activities as $activity)
        <div class="timeline-item mb-4">
            <div class="d-flex">
                <!-- Icône et ligne -->
                <div class="timeline-marker me-3">
                    <div class="timeline-icon bg-{{ $activity->badge_color }}">
                        <i class="fas {{ str_replace('text-' . $activity->badge_color, '', $activity->icon) }} text-white"></i>
                    </div>
                    @if(!$loop->last)
                        <div class="timeline-line"></div>
                    @endif
                </div>

                <!-- Contenu -->
                <div class="timeline-content flex-grow-1">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="badge bg-{{ $activity->badge_color }} me-2">
                                        {{ ucfirst(str_replace('_', ' ', $activity->description)) }}
                                    </span>
                                    @if($activity->module)
                                        <span class="badge bg-primary">{{ $activity->module }}</span>
                                    @endif
                                </div>
                                <small class="text-muted">{{ $activity->created_at->diffForHumans() }}</small>
                            </div>

                            @if($showUser && $activity->causer)
                                <div class="d-flex align-items-center mb-2">
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" 
                                         style="width: 25px; height: 25px; font-size: 11px;">
                                        {{ strtoupper(substr($activity->causer->name, 0, 1)) }}
                                    </div>
                                    <small><strong>{{ $activity->causer->name }}</strong></small>
                                </div>
                            @endif

                            @if($activity->subject_type)
                                <div class="text-muted small">
                                    <i class="fas fa-box me-1"></i>
                                    {{ class_basename($activity->subject_type) }}
                                    @if($activity->subject_id)
                                        #{{ $activity->subject_id }}
                                    @endif
                                </div>
                            @endif

                            @if($activity->properties && $activity->properties->isNotEmpty())
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-link p-0" 
                                            type="button" 
                                            data-bs-toggle="collapse" 
                                            data-bs-target="#details-{{ $activity->id }}">
                                        <i class="fas fa-chevron-down me-1"></i>Voir les détails
                                    </button>
                                    <div class="collapse mt-2" id="details-{{ $activity->id }}">
                                        <pre class="bg-light p-2 rounded small mb-0"><code>{{ json_encode($activity->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="text-center text-muted py-4">
            <i class="fas fa-inbox fa-3x mb-3"></i>
            <p>Aucune activité enregistrée</p>
        </div>
    @endforelse
</div>

<style>
.timeline-marker {
    position: relative;
}

.timeline-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.timeline-line {
    position: absolute;
    left: 50%;
    top: 40px;
    bottom: -20px;
    width: 2px;
    background: #e0e0e0;
    transform: translateX(-50%);
}
</style>