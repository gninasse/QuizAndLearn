@extends('core::layouts.master')

@section('title', 'Contenus — '.$group->name)
@section('header', 'Contenus '.term('of_group'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('cores.groups.index') }}">{{ term('Groups') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $group->name }}</li>
@endsection

@section('content')
{{-- Interface de l'arbre des contenus : à venir (Task 9). --}}
<div id="group-contents-app"
     data-url-data="{{ route('cores.groups.contents.data', $group->id) }}">
    <h2 class="h5">{{ $group->name }}</h2>
</div>
@endsection
