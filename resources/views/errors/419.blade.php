@extends('errors.layout')

@section('title', 'Sesión expirada')

@section('code', '419')
@section('message', 'Sesión expirada')

@section('description')
Tu sesión ha expirado por inactividad.<br>
Por favor, vuelve a intentar la acción.
@endsection
