@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Modifier le prescripteur {{ $client ? html_entity_decode((string) $client->name, ENT_QUOTES) : '' }}</h1>
</div>
@if ($error)
<div class="alert alert-warning" role="alert">{{ $error }}</div>
@elseif ($success)
<div class="alert alert-success" role="alert">{{ $success }}</div>
@endif
@if ($client)
<div class="col-xs-12 col-sm-12 col-md-12 form-group">
<form name="f_edit_promoteur" action="/gestion_promoteurs/modifier/{{ $client->id }}" method="post">
    @csrf
    <div class="col-xs-12 col-sm-6 col-md-6">
        <div class="form-group">
            <label for="f_edit_promoteur_pseudo">Identifiant de connexion</label>
            <input type="text" name="f_edit_promoteur_pseudo" id="f_edit_promoteur_pseudo" value="{{ html_entity_decode((string) $client->pseudo, ENT_QUOTES) }}" class="form-control" />
        </div>
        <div class="form-group">
            <label for="f_edit_promoteur_name">Nom du prescripteur</label>
            <input type="text" name="f_edit_promoteur_name" id="f_edit_promoteur_name" value="{{ html_entity_decode((string) $client->name, ENT_QUOTES) }}" class="form-control" />
        </div>
        <div class="form-group">
            <label for="f_edit_promoteur_email">E-mail</label>
            <input type="email" name="f_edit_promoteur_email" id="f_edit_promoteur_email" value="{{ html_entity_decode((string) $client->email, ENT_QUOTES) }}" class="form-control" />
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-6">
        <div class="form-group">
            <label for="f_edit_promoteur_pwd">Mot de passe</label>
            <input type="password" name="f_edit_promoteur_pwd" id="f_edit_promoteur_pwd" value="" class="form-control" />
            <p class="text-info">Si le champs est laissé vide, le mot de passe actuel sera conservé.</p>
        </div>
        <div class="form-group">
            <label for="f_edit_promoteur_statut">Statut</label>
            <select name="f_edit_promoteur_statut" id="f_edit_promoteur_statut" class="form-control">
                <option value="1" @if ((int) $client->statut === 1)selected="selected"@endif>Activer</option>
                <option value="0" @if ((int) $client->statut === 0)selected="selected"@endif>Désactiver</option>
            </select>
        </div>
        <div class="form-group text-center">
        	<input type="submit" value="ENREGISTRER" class="btn btn-primary" />
        </div>
    </div>
</form>
</div>
@include('evolutions.partials.cle_api', ['client' => $client])
@endif
@endsection
