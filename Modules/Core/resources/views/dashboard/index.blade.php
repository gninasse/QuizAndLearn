@extends('core::layouts.master')

@section('header', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="#">Accueil</a></li>
    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
@endsection

@section('content')
    <!--begin::Row-->
    <div class="row">
        <!--begin::Col-->
        <div class="col-lg-3 col-6">
            <!--begin::Small Box Widget 1-->
            <div class="small-box text-bg-primary">
                <div class="inner">
                    <h3>{{ $stats['users_count'] }}</h3>
                    <p>Utilisateurs</p>
                </div>
                <i class="small-box-icon bi bi-people"></i>
                <a href="{{ route('cores.users.index') }}" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                    Plus d'info <i class="bi bi-link-45deg"></i>
                </a>
            </div>
            <!--end::Small Box Widget 1-->
        </div>
        <!--end::Col-->
        <div class="col-lg-3 col-6">
            <!--begin::Small Box Widget 2-->
            <div class="small-box text-bg-success">
                <div class="inner">
                    <h3>{{ $stats['roles_count'] }}</h3>
                    <p>Rôles</p>
                </div>
                <i class="small-box-icon bi bi-shield-lock"></i>
                <a href="{{ route('cores.roles.index') }}" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                    Plus d'info <i class="bi bi-link-45deg"></i>
                </a>
            </div>
            <!--end::Small Box Widget 2-->
        </div>
        <!--end::Col-->
        <div class="col-lg-3 col-6">
            <!--begin::Small Box Widget 3-->
            <div class="small-box text-bg-warning">
                <div class="inner">
                    <h3>{{ $stats['modules_count'] }}</h3>
                    <p>Modules</p>
                </div>
                <i class="small-box-icon bi bi-box-seam"></i>
                <a href="{{ route('cores.modules.index') }}" class="small-box-footer link-dark link-underline-opacity-0 link-underline-opacity-50-hover">
                    Plus d'info <i class="bi bi-link-45deg"></i>
                </a>
            </div>
            <!--end::Small Box Widget 3-->
        </div>
        <!--end::Col-->
        <div class="col-lg-3 col-6">
            <!--begin::Small Box Widget 4-->
            <div class="small-box text-bg-danger">
                <div class="inner">
                    <h3>{{ $stats['activities_today'] }}</h3>
                    <p>Activités aujourd'hui</p>
                </div>
                <i class="small-box-icon bi bi-clock-history"></i>
                <a href="{{ route('cores.activities.index') }}" class="small-box-footer link-light link-underline-opacity-0 link-underline-opacity-50-hover">
                    Plus d'info <i class="bi bi-link-45deg"></i>
                </a>
            </div>
            <!--end::Small Box Widget 4-->
        </div>
        <!--end::Col-->
    </div>
    <!--end::Row-->
    <!--begin::Row-->
    <div class="row">
        <!-- Start col -->
        <div class="col-lg-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title"><i class="bi bi-clock-history me-2"></i>Dernières Activités</h3>
                    <div class="card-tools">
                        <a href="{{ route('cores.activities.index') }}" class="btn btn-tool btn-sm">
                            <i class="bi bi-list"></i> Voir tout
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Utilisateur</th>
                                    <th>Module</th>
                                    <th>Action</th>
                                    <th>Sujet</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentActivities as $activity)
                                    <tr>
                                        <td>{{ $activity->created_at->diffForHumans() }}</td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                {{ $activity->causer ? $activity->causer->name : 'Système' }}
                                            </span>
                                        </td>
                                        <td><span class="badge bg-info text-dark">{{ $activity->module }}</span></td>
                                        <td>
                                            <span class="badge bg-{{ $activity->badge_color }}">
                                                <i class="{{ $activity->icon }} me-1"></i> {{ $activity->description }}
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</small>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">Aucune activité récente</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.Start col -->
    </div>
    <!-- /.row (main row) -->
@endsection

@push('css')
    <!-- jsvectormap -->
    <link
      rel="stylesheet"
      href="{{ asset('plugins/jsvectormap/css/jsvectormap.min.css') }}"
    />
    <!-- apexcharts -->
    <link
      rel="stylesheet"
      href="{{ asset('plugins/apexcharts/apexcharts.css') }}"
    />
@endpush

@push('js')
    <!-- Aucun script requis pour la version simplifiée -->
@endpush
