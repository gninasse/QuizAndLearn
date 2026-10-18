@extends('errors::minimal')

@section('code', '403')
@section('title', 'Accès refusé')
@section('message', (isset($exception) && $exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.') ? $exception->getMessage() : 'Vous n’avez pas les droits nécessaires pour consulter cette page.')
