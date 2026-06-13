<div class="activity-card card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex align-items-start">
            <div class="activity-icon me-3">
                <i class="fas {{ $activity->icon }} fa-2x"></i>
            </div>
            
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="mb-1">
                            {{ ucfirst(str_replace('_', ' ', $activity->description)) }}
                        </h6>
                        @if($activity->module)
                            <span class="badge bg-primary me-1">{{ $activity->module }}</span>
                        @endif
                        @if($activity->subject_type)
                            <span class="badge bg-secondary">{{ class_basename($activity->subject_type) }}</span>
                        @endif
                    </div>
                    <small class="text-muted">{{ $activity->created_at->format('d/m/Y H:i') }}</small>
                </div>

                @if($activity->causer)
                    <div class="mb-2">
                        <small class="text-muted">
                            <i class="fas fa-user me-1"></i>
                            {{ $activity->causer->name }}
                        </small>
                    </div>
                @endif

                @if($detailed && $activity->properties && $activity->properties->isNotEmpty())
                    <div class="mt-3">
                        <strong>Détails :</strong>
                        <pre class="bg-light p-2 rounded small mt-2"><code>{{ json_encode($activity->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</code></pre>
                    </div>
                @endif

                @if($activity->ip_address)
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="fas fa-network-wired me-1"></i>
                            IP: {{ $activity->ip_address }}
                        </small>
                    </div>
                @endif

                <div class="mt-3">
                    <a href="{{ route('cores.activities.show', $activity) }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye me-1"></i>Voir les détails
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>