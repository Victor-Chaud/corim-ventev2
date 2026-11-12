@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>&Eacute;dition d'un client</h1>
</div>
@if (isset($error))
<div class="alert alert-warning">{!! htmlspecialchars($error, ENT_NOQUOTES) !!}</div>
@elseif (isset($success))
<div class="alert alert-success">{!! htmlspecialchars($success, ENT_NOQUOTES) !!}</div>
@endif
@if ($client)
@php($v = fn ($field) => html_entity_decode((string) $client->$field, ENT_QUOTES))
<div class="col-xs-12 col-sm-12 col-md-12">
<form action="{{ url('gestion_clients/edition_client/'.$client->idclient) }}" method="post">
	@csrf
	<div class="col-xs-12 col-sm-6 col-md-6">
    	<div class="form-group">
        	<label for="date">DATE</label>
            <input type="text" name="date" id="date" value="{{ $v('date') }}" class="form-control" />
        </div>
    	<div class="form-group">
        	<label for="source">SOURCE</label>
            <input type="text" name="source" id="source" value="{{ $v('source') }}" class="form-control" />
        </div>
    	<div class="form-group">
        	<label for="projets">PROJET(S)</label>
            <input type="text" name="projets" id="projets" value="{{ $v('projets') }}" class="form-control" />
        </div>
    	<div class="form-group">
        	<label for="civilite">CIVILIT&Eacute;</label>
            <input type="text" name="civilite" id="civilite" value="{{ $v('civilite') }}" class="form-control" />
        </div>
    	<div class="form-group">
        	<label for="nom">NOM</label>
            <input type="text" name="nom" id="nom" value="{{ $v('nom') }}" class="form-control" />
        </div>
    	<div class="form-group">
        	<label for="adresse">ADRESSE</label>
            <input type="text" name="adresse" id="adresse" value="{{ $v('adresse') }}" class="form-control" />
        </div>
    	<div class="form-group">
        	<label for="tel">T&Eacute;L</label>
            <input type="text" name="tel" id="tel" value="{{ $v('tel') }}" class="form-control" />
        </div>
    	<div class="form-group">
        	<label for="email">EMAIL</label>
            <input type="text" name="email" id="email" value="{{ $v('email') }}" class="form-control" />
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-6">
    	<div class="form-group">
        	<label for="type_achat">TYPE D'ACHAT</label>
            <input type="text" name="type_achat" id="type_achat" value="{{ $v('type_achat') }}" class="form-control" />
        </div>
    	<div class="form-group">
        	<label for="type_logement">TYPE DE LOGEMENT</label>
            <input type="text" name="type_logement" id="type_logement" value="{{ $v('type_logement') }}" class="form-control" />
        </div>
    	<div class="form-group">
        	<label for="budget">BUDGET</label>
            <input type="text" name="budget" id="budget" value="{{ $v('budget') }}" class="form-control" />
        </div>
        <div class="form-group">
        	<label for="observation">OBSERVATION</label>
            <textarea name="observation" id="observation" rows="5" class="form-control">{{ $v('observation') }}</textarea>
        </div>
        <div class="form-group">
        	<label for="reponse">R&Eacute;PONSE</label>
            <textarea name="reponse" id="reponse" rows="5" class="form-control">{{ $v('reponse') }}</textarea>
        </div>
        <div class="form-group text-center">
        	<input type="submit" value="Enregistrer le client" class="btn btn-primary" />
        </div>
    </div>
    
    
</form>
</div>
@endif
@endsection
