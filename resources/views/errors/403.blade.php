@extends('errors.layout')

@section('title', 'Acceso restringido')

@section('code', '403')
@section('message', 'No tienes permisos')

@section('description')
No estás autorizado para acceder a esta sección.<br>
Si crees que esto es un error, contacta al administrador.
@endsection
