@extends('layouts.login')

@section('content')
<div class="animated fadeInDown" id="login">
	<a id="logoff" href="/"></a>
	@if (! empty($invalid))
		<div class="alert alert-warning">Ce lien de réinitialisation n'est plus valide. Vous pouvez refaire une demande depuis « Mot de passe oublié ? ».</div>
		<a class="btn btn-default btn-info btn-block" href="/connexion"><small>Retour au formulaire de connexion</small></a>
	@elseif (! empty($done))
		<div class="alert alert-info">Votre nouveau mot de passe est enregistré.</div>
		<a class="btn btn-default btn-warning btn-block" href="/connexion">Retour au formulaire de connexion</a>
	@else
    <form method="post" action="{{ request()->getRequestUri() }}">
        @csrf
		@isset($message)
			<div class="alert alert-warning">
              <ul>
<li>{{ $message }}</li>
              </ul>
			</div>
		@endisset
        <div class="form-group">
            <label for="password">Votre nouveau mot de passe</label>
            <input type="password" required="" minlength="8" name="password" class="form-control" id="password" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirmez votre mot de passe</label>
            <input type="password" required="" minlength="8" name="password_confirmation" class="form-control" id="password_confirmation" autocomplete="new-password">
        </div>
        <button class="btn btn-default btn-warning btn-block" type="submit">Valider</button>
    </form>
	@endif
</div>
@endsection
