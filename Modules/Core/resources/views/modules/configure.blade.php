@extends('core::layouts.master')

@section('title', 'Configuration - ' . $module->name)

@section('header', 'Configuration du Module')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('cores.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="{{ route('cores.modules.index') }}">Modules</a></li>
    <li class="breadcrumb-item"><a href="{{ route('cores.modules.show', $module->slug) }}">{{ $module->name }}</a></li>
    <li class="breadcrumb-item active">Configuration</li>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Configuration : {{ $module->name }}</h5>
                </div>
                <div class="card-body">
                    @if(empty($defaultConfig))
                        <div class="alert alert-info">
                            Ce module ne possède pas de fichier de configuration éditable via cette interface.
                        </div>
                    @else
                        <form action="{{ route('cores.modules.configure.update', $module->slug) }}" method="POST">
                            @csrf
                            
                            @foreach($defaultConfig as $key => $value)
                                <div class="mb-3">
                                    <label for="config_{{ $key }}" class="form-label">{{ ucfirst(str_replace('_', ' ', $key)) }}</label>
                                    @if(is_bool($value))
                                        <select class="form-select" id="config_{{ $key }}" name="{{ $key }}">
                                            <option value="1" {{ ($config[$key] ?? $value) ? 'selected' : '' }}>Oui</option>
                                            <option value="0" {{ !($config[$key] ?? $value) ? 'selected' : '' }}>Non</option>
                                        </select>
                                    @elseif(is_array($value))
                                         <textarea class="form-control" id="config_{{ $key }}" name="{{ $key }}" rows="3" readonly disabled>{{ json_encode($value) }}</textarea>
                                         <small class="text-muted">Les configurations de type tableau ne sont pas éditables directement.</small>
                                    @else
                                        <input type="text" class="form-control" id="config_{{ $key }}" name="{{ $key }}" value="{{ $config[$key] ?? $value }}">
                                    @endif
                                </div>
                            @endforeach

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('cores.modules.show', $module->slug) }}" class="btn btn-outline-secondary">Annuler</a>
                                <button type="submit" class="btn btn-primary">Enregistrer</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
