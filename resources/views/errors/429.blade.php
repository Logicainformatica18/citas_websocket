@extends('errors.layout')

@section('title', 'Demasiadas solicitudes')

@section('code', '429')
@section('message', 'Demasiadas solicitudes')

@section('description')
Has realizado demasiadas solicitudes en poco tiempo.<br>
Espera unos segundos antes de intentarlo nuevamente.
@endsection
