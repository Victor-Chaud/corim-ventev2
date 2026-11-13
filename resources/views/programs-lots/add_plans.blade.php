@extends('layouts.template')

@section('content')
<div class="page-header">
    <h1>Ajouter les plans en masse <small>Programme {{ html_entity_decode($program->projet_titre, ENT_QUOTES) }} à {{ html_entity_decode($program->projet_lieu, ENT_QUOTES) }}</small></h1>
</div>
@if (isset($error))
    <div class="alert alert-warning">{{ $error }}</div>
@elseif (isset($success))
    <div class="alert alert-success">{{ $success }}</div>
@endif
<div class="col-xs-12 col-sm-12 col-md-12 form-group">
@if (count($lots) > 0)
        <form action="/ajouter_plan/show/{{ $program->projet_titre_key }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="col-xs-12 col-sm-6 col-md-6">
                <div class="form-group">
                    <label for="csv_upload">Ajouter vos plans au format PDF</label>
                    <input type="file" name="plan_upload[]" id="plan_upload" multiple="multiple" />
                </div>
                <input type="hidden" name="name" value="{{ $program->projet_id }}" />
                <input type="hidden" name="departement" value="{{ html_entity_decode((string) $program->dept, ENT_QUOTES) }}" />
                <input type="hidden" name="ville" value="{{ html_entity_decode($program->projet_lieu, ENT_QUOTES) }}" />
            </div>
            <div class="col-xs-12 col-sm-6 col-md-6 text-right">
                <input type="submit" value="Enregistrer en masse" class="btn btn-primary" />
            </div>
        </form>
@else
        <div class="alert alert-warning">
            <p>Veuillez tout d'abord enregistrer les lots pour y associer les plans</p>
        </div>
@endif
</div>
<div class="col-xs-12 col-sm-12 col-md-12 form-group">
    <ul class="list-group">
@foreach ($lots as $lot)
@if ($lot->exists)
                <li class="list-group-item"><a target="_blank" href="/medias/programmes/{{ $program->projet_titre_key }}/plans/{{ $lot->plan }}">{{ $lot->plan }}</a> <span class="badge">OK</span></li>
@else
                <li class="list-group-item">Le fichier <b style="color:#F60">{{ $lot->plan }}</b> est manquant</li>
@endif
@endforeach
    </ul>
</div>
@endsection
