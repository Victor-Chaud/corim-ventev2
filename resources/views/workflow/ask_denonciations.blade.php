@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Demandes de dénonciations</h1>
</div>
@if (count($rows) > 0)
<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green"><thead><tr><td align="center"><b>DATE</b></td><td align="center"><b>PROJET</b></td><td align="center"><b>LIEU</b></td><td align="center"><b>ACQU&Eacute;REUR</b></td><td align="center"><b>ADRESSE</b></td><td align="center"><b>PRESCRIPTEUR</b></td><td align="center"><b>MESSAGE</b></td>@if ($admin)<td align="center"><b>ACTIONS</b></td>@endif</tr></thead><tbody>@foreach ($rows as $denonciations)<tr>@if ($admin)
            <td align="center">
            	<input size="10" type="text" class="datepicker" id="datepicker_{{ $denonciations->idprospect }}_askden" value="{{ $denonciations->date }}" />
                <input type="hidden" type="text" class="datepickeralt" value="" />
            </td>
            @else<td align="center">{{ $denonciations->date }}</td>@endif<td align="center">{{ $denonciations->projet_titre }}</td><td align="center">{{ $denonciations->projet_lieu }}</td><td align="center">{{ $denonciations->nomprospect }}</td><td align="center">{{ $denonciations->cpprospect }} {{ $denonciations->villeprospect }}</td><td align="center">{{ $denonciations->name }}</td><td align="center">@if ($denonciations->smspromoteur != '')<a href="javascript:void(0)" class="see_sms btn btn-primary btn-xs">Message</a>@endif</td>@if ($admin)<td align="center"><a rel="{{ $denonciations->idprospect }}-{{ $denonciations->iduser }}" href="javascript:void(0)" class="valider_ask_denonciations btn btn-primary btn-xs"><b>VALIDER</b></a><br /><b><a rel="{{ $denonciations->idprospect }}-{{ $denonciations->iduser }}" href="javascript:void(0)" class="rejeter_ask_denonciations btn btn-warning btn-xs">REJETER</a></b></td>@endif</tr><tr class="hide"><td colspan="{{ $admin ? 9 : 8 }}" class="sms">{{ \App\Http\Controllers\Workflow\PagesController::message($denonciations->smspromoteur) }}</td></tr>@endforeach</tbody></table>@else
    <div class="alert alert-info">Aucune demande de dénonciation en cours de validation</div>
@endif
<div style="display:none">
	<div id="valid_ask_denonciation_box">
    	<h3>Valider la demande de dénonciation</h3>
    	<div id="call"></div>
        <form name="f_confirm_ask_denonciation" id="f_confirm_ask_denonciation" method="post" action="#">
            <div class="form-group">
                <label for="f_confirm_ask_denonciation_sms">Laisser un message au promoteur</label>
                <textarea name="f_confirm_ask_denonciation_sms" rows="10" class="form-control">{{ \App\Http\Controllers\Evolutions\MessagesAutoController::texte('denonciation_validee') }}</textarea>
            </div>
            <div class="text-right">
                <input type="submit" id="submit_f_confirm_ask_denonciation" value="Valider" class="btn btn-primary" />
            </div>
            <input type="hidden" name="f_confirm_ask_denonciation_user" id="f_confirm_ask_denonciation_user" value="" />
            <input type="hidden" name="f_confirm_ask_denonciation_lot" id="f_confirm_ask_denonciation_lot" value="" />
        </form>
    </div>
	<div id="rejeter_ask_denonciation_box">
    	<h3>Refuser la demande de dénonciation</h3>
    	<div class="call"></div>
        <form name="f_refuser_ask_denonciation" id="f_refuser_ask_denonciation" method="post" action="#">
        	<div class="form-group">
                <label for="f_refuser_ask_denonciation_sms">Laisser un message au promoteur</label>
                <textarea name="f_refuser_ask_denonciation_sms" rows="10" class="form-control">{{ \App\Http\Controllers\Evolutions\MessagesAutoController::texte('denonciation_refusee') }}</textarea>
            </div>
            <div class="text-right">
                <input type="submit" id="submit_f_refuser_ask_denonciation" value="Valider" class="btn btn-primary" />
            </div>
            <input type="hidden" name="f_refuser_ask_denonciation_user" id="f_refuser_ask_denonciation_user" value="" />
            <input type="hidden" name="f_refuser_ask_denonciation_lot" id="f_refuser_ask_denonciation_lot" value="" />
        </form>
    </div>
</div>
@endsection
