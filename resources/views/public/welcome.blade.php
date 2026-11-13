@extends('layouts.template_welcome')

@section('module_programmes')
<div id="programmes">
    @foreach ($programs as $program)<div class="item_programme"><a href="/programme/show/{{ $program->projet_titre_key }}" class="vignette"><img src="{{ $program->thumb }}" alt="vignette" width="120" class="img-thumbnail" /></a><a class="btn btn-info btn-xs btn-block" href="/programme/show/{{ $program->projet_titre_key }}">{{ html_entity_decode((string) $program->projet_titre, ENT_QUOTES) }}<span>{{ html_entity_decode((string) $program->projet_lieu, ENT_QUOTES) }}</span></a></div>@endforeach
</div>
@endsection

@section('module_dashboard')
<div class="list-group">
@foreach ($counters as [$page, $count, $label, $ask])
	<a href="{{ $count > 0 ? '/'.$page : 'javascript:void(0)' }}" class="list-group-item{{ $ask ? ($count > 0 ? ' list-group-item-warning' : ' ') : '' }}">
		<span class="badge">{{ $count }}</span>
		@if ($ask){!! $count > 0 ? '<span aria-hidden="true" class="glyphicon glyphicon-exclamation-sign"></span>' : '' !!} @endif{!! $label !!}
	</a>
@endforeach
	@if ($admin)
		<a href="/report" class="list-group-item">
			RAPPORT
		</a>
	@endif
</div>
@endsection
