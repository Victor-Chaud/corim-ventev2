@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Gestion des programmes <small><a href="/ajouter_programme">Ajouter un programme</a></small></h1>
</div>
@foreach (['programmes' => $programmes, 'archives' => $archives] as $bloc => $liste)
@if (count($liste) > 0)
<h4>{{ $bloc === 'programmes' ? 'PROGRAMMES EN COURS' : 'PROGRAMMES ARCHIVÉS' }}</h4><table class="table table-striped table-hover" width="100%">@foreach ($liste as $programme)<tr><td align="center"><a href="/modifier_programme/show/{{ $programme->projet_titre_key }}/" class="thumbnail"><img src="{{ \App\Http\Controllers\ProgrammesLots\ProgrammeFiles::thumb($programme->projet_titre_key) }}" alt="vignette" width="120" /></a></td><td align="center"><a href="/modifier_programme/show/{{ $programme->projet_titre_key }}/"><span>{{ html_entity_decode((string) $programme->projet_titre, ENT_QUOTES) }}<br />{{ html_entity_decode((string) $programme->projet_lieu, ENT_QUOTES) }}</span></a><br />({{ $programme->projet_id }})</td></td><td align="center">@if ($bloc === 'programmes')<a rel="{{ $programme->projet_titre_key }}" href="#" class="push_archivage">ARCHIVER LE PROJET</a>@else<a rel="{{ $programme->projet_titre_key }}" href="#" class="push_desarchivage">RÉACTIVER LE PROJET</a>@endif</td><td align="center"><a href="/gerer_lots/show/{{ $programme->projet_titre_key }}">GÉRER LES LOTS</a></td><td align="center"><a href="/ajouter_lots/show/{{ $programme->projet_titre_key }}">AJOUTER EN MASSE</a><br /><a href="/ajouter_plan/show/{{ $programme->projet_titre_key }}">AJOUTER LES PLANS</a></td></tr>@endforeach
		</table>
@endif
@endforeach
@endsection
