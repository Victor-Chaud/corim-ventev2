@extends('layouts.template')

@php($d = \App\Http\Controllers\Communication\SafeHtml::clean(...))
@php($v = fn ($k) => (string) ($callback[$k] ?? ''))
@section('content')
<div class="page-header">
  <h1>Emailing</h1>
</div>
@if ($erreurs)
<div class="alert alert-warning">@foreach ($erreurs as $erreur){!! $erreur !!}@endforeach</div>
@endif
@if ($valid)
<div class="alert alert-success">{{ $valid }}</div>
@endif
<div class="col-xs-12 col-sm-12 col-md-12 form-group">
<form name="f_new_message" method="post" action="/emailing">
@csrf
<div class="form-group">
	<label for="objet">Objet du message</label>
    <input type="text" value="{{ $v('objet') }}" name="objet" id="objet" class="form-control" />
</div>
<div class="form-group">
	<label>Destinataire</label>
</div>
<div class="col-xs-12 col-sm-6 col-md-6 form-group">
    <div class="form-group">
        <div class="radio">
          <label>
            <input type="radio" name="method" value="prescripteur" {!! $v('method') === 'prescripteur' ? 'checked="checked"' : '' !!} />
            Prescripteur(s)
          </label>
          <select name="prescripteur_select" id="prescripteur_select" class="form-control">
              <option value="0">Choisir</option>
              <option value="all">Tous les prescripteurs</option>
              @foreach ($users as $user)
<option value="{{ html_entity_decode((string) $user->email, ENT_QUOTES) }}" {!! $v('prescripteur_select') !== '' && $v('prescripteur_select') === html_entity_decode((string) $user->email, ENT_QUOTES) ? 'selected="selected"' : '' !!}>{{ $d($user->name) }}</option>
              @endforeach
           </select>
        </div>
    </div>
    <div class="form-group">
        <div class="radio">
          <label>
            <input type="radio" name="method" value="client" id="push_radio_client" {!! $v('method') === 'client' ? 'checked="checked"' : '' !!} />
            Client(s)
          </label>
          <select name="client_select" id="client_select" class="form-control">
              <option value="0">Choisir</option>
              <option value="all">Tous les clients</option>
              @foreach ($clients as $client)
<option value="{{ html_entity_decode((string) $client->email, ENT_QUOTES) }}" {!! $v('client_select') !== '' && $v('client_select') === html_entity_decode((string) $client->email, ENT_QUOTES) ? 'selected="selected"' : '' !!}>{{ $d($client->nom) }}</option>
              @endforeach
          </select>
        </div>
    </div>
    <div class="form-group">
        <div class="radio">
          <label>
            <input type="radio" name="method" value="programme" {!! $v('method') === 'programme' ? 'checked="checked"' : '' !!} />
            Prescripteur(s) par programme
          </label>
          <select name="programme_select" id="programme_select" class="form-control">
              <option value="0">Choisir</option>
              @foreach ($programmes as $programme)
<option value="{{ $programme->projet_id }}">{{ $d($programme->projet_titre) }}</option>
              @endforeach
          </select>
        </div>
    </div>
</div>
<div class="col-xs-12 col-sm-6 col-md-6 form-group">
    <div class="form-group" id="search-by-keyword">
        <div class="radio">
          <label>
            <input type="radio" name="method" value="keywords" {!! $v('method') === 'keywords' ? 'checked="checked"' : '' !!} />
            Recherche par Mots-clé
          </label>
          <div class="form-group">
            <input type="text" name="keyword_select1" id="keyword_select1" value="" class="form-control" placeholder="Mot-clé n°1" />
          </div>
          <div class="form-group">
            <input type="text" name="keyword_select2" id="keyword_select2" value="" class="form-control" placeholder="Mot-clé n°2" />
          </div>
          <div class="form-group">
            <input type="text" name="keyword_select3" id="keyword_select3" value="" class="form-control" placeholder="Mot-clé n°3" />
          </div>
        </div>
        <div class="form-group">
            <a href="javascript:void(0)" id="keyword_select_push" class="btn btn-primary">Rechercher</a>
            <a href="javascript:void(0)" id="vider_la_liste" class="btn btn-primary">Vider la liste</a>
        </div>
        <input type="hidden" name="keyword_select" id="keyword_select" value="{{ $v('keyword_select') }}" />
        <div id="callback_list_client">
        @if ($v('keyword_select') !== '')
<b>{{ count(explode(';', $v('keyword_select'))) }} personne(s) dans la liste</b>
        @endif
        </div>
        <div id="callback_client"></div>
    </div>
</div>
<div class="clearfix"></div>
<div class="form-group">
	<label for="message">Votre message</label>
    <textarea cols="80" rows="14" name="message" id="message">{{ $v('message') }}</textarea>
</div>
<input type="submit" value="ENVOYER LE MESSAGE" name="submit" id="submit" class="btn btn-primary">
</form>
</div>
<script type="text/javascript">
    CKEDITOR.replace( 'message', {width: '100%', height: '300px'} );
</script>
@endsection
