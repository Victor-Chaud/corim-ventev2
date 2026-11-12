@extends('layouts.connexion')

@section('content')
<div class="animated fadeInDown" id="login">
	<a id="logoff" href="/"></a>
    <form method="post" action="/connexion">
        @csrf
    	 @isset($messages)
			<div class="alert alert-warning">
              <ul>
			  @foreach ($messages as $message)
			  @if ($message !== '')
<li>{!! $message !!}</li>
			  @endif
			  @endforeach
              </ul>
			</div>
		@endisset
        <div class="form-group">
            <label for="identifiant">Votre identifiant</label>
            <input type="text" placeholder="ex. login" required="" name="identifiant" class="form-control" id="identifiant">
        </div>
        <div class="form-group"> 
            <label for="password">Votre mot de passe</label>
            <input type="password" placeholder="ex. X8df!90EO" required="" name="password" class="form-control" id="password"> 
        </div>
        <input type="hidden"  name="action" value="connexion" />
        <button class="btn btn-default btn-warning btn-block" type="submit">Connexion</button>
        <button type="button" class="btn btn-default btn-info btn-block" data-toggle="modal" data-target="#lost-password">
          <small>Mot de passe oublié ?</small>
        </button>
    </form>
</div>

<div class="modal fade" id="lost-password" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myModalLabel">Mot de passe et/ou identifiant oublié ?</h4>
      </div>
      <div class="modal-body">
        <form method="post" action="/connexion">
            @csrf
        	<div class="form-group"> 
                <label for="email">Votre e-mail</label>
                <input type="email" placeholder="Votre e-mail" required="" name="email" class="form-control" id="email"> 
            </div>
            <input type="hidden"  name="action" value="forgottenpassword"/>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Annuler</button>
        <button type="button" class="btn btn-primary" id="lost-password-submit">Valider</button>
      </div>
    </div>
  </div>
</div>
@endsection
