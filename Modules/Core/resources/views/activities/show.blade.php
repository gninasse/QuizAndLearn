<div class="activity-detail">
    <div class="row">
        <!-- Informations principales -->
        <div class="col-md-6">
            <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-info-circle me-2"></i>Informations générales</h6>
            <table class="table table-sm table-borderless">
                <tr>
                    <th width="150">Généré le</th>
                    <td>{{ $activity->created_at->format('d/m/Y H:i:s') }}</td>
                </tr>
                <tr>
                    <th width="150">Expire le</th>
                    <td>{{ $activity->expires_at?->format('d/m/Y H:i:s') }}</td>
                </tr>
                <tr>
                    <th>Module</th>
                    <td><span class="badge bg-info">{{ strtoupper($activity->module) }}</span></td>
                </tr>
                <tr>
                    <th>Action</th>
                    <td>
                        <span class="badge bg-{{ $activity->badge_color }}">
                            <i class="fas {{ $activity->icon }} me-1"></i>
                            {{ $activity->description }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Sujet</th>
                    <td>{{ class_basename($activity->subject_type) }} (ID: {{ $activity->subject_id }})</td>
                </tr>
            </table>
        </div>

        <!-- Contexte utilisateur -->
        <div class="col-md-6">
            <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-user-tag me-2"></i>Contexte Utilisateur</h6>
            <table class="table table-sm table-borderless">
                <tr>
                    <th width="150">Utilisateur</th>
                    <td>
                        @if($activity->causer)
                            <strong>{{ $activity->causer->name }} {{ $activity->causer->last_name }}</strong><br>
                            <small class="text-muted">{{ $activity->causer->email }}</small>
                        @else
                            <span class="text-muted">Système</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Rôles</th>
                    <td>
                        @if($activity->causer_roles)
                            @foreach($activity->causer_roles as $role)
                                <span class="badge bg-secondary">{{ $role }}</span>
                            @endforeach
                        @else
                            -
                        @endif
                    </td>
                </tr>
                <tr>
                    <th>Adresse IP</th>
                    <td><code>{{ $activity->properties['ip'] ?? $activity->ip_address ?? 'N/A' }}</code></td>
                </tr>
            </table>
        </div>
    </div>

    @if(!empty($activity->properties['old']) || !empty($activity->properties['attributes']))
    <div class="row mt-4">
        <div class="col-12">
            <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-exchange-alt me-2"></i>Changements détectés</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="bg-light">
                        <tr>
                            <th>Champ</th>
                            <th>Ancienne valeur</th>
                            <th>Nouvelle valeur</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $old = $activity->properties['old'] ?? [];
                            $new = $activity->properties['attributes'] ?? [];
                            $fields = array_unique(array_merge(array_keys($old), array_keys($new)));
                        @endphp
                        @foreach($fields as $field)
                            @if($field !== 'updated_at')
                            <tr>
                                <td class="fw-bold">{{ $field }}</td>
                                <td class="text-danger">
                                    @if(isset($old[$field]))
                                        {{ is_array($old[$field]) ? json_encode($old[$field]) : $old[$field] }}
                                    @else
                                        <em class="text-muted">aucune</em>
                                    @endif
                                </td>
                                <td class="text-success">
                                    @if(isset($new[$field]))
                                        {{ is_array($new[$field]) ? json_encode($new[$field]) : $new[$field] }}
                                    @else
                                        <em class="text-muted">aucune</em>
                                    @endif
                                </td>
                            </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <div class="row mt-4">
        <div class="col-12">
            <h6 class="text-primary border-bottom pb-2 mb-3"><i class="fas fa-laptop-code me-2"></i>Détails techniques</h6>
            <div class="bg-light p-3 rounded">
                <p class="mb-1"><strong>User Agent:</strong> <small>{{ $activity->user_agent ?? $activity->properties['user_agent'] ?? 'N/A' }}</small></p>
                <p class="mb-0"><strong>Toutes les propriétés:</strong></p>
                <pre class="mb-0 mt-2" style="font-size: 0.8rem;">{{ json_encode($activity->properties, JSON_PRETTY_PRINT) }}</pre>
                <pre class="mb-0 mt-2" style="font-size: 0.8rem;">{{ json_encode($activity->context, JSON_PRETTY_PRINT) }}</pre>
            </div>
        </div>
    </div>
</div>
