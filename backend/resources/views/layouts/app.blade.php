{{--
    Livewire 4 full-page layout, resolved via the `layouts::` namespace
    (config/livewire.php -> component_layout => layouts::app).
    The Livewire component body arrives as $slot; chrome comes from layouts.admin.
--}}
@extends('layouts.admin')

@section('content')
    {{ $slot }}
@endsection