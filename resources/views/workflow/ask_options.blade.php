@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Demandes d'options</h1>
</div>
@if (count($rows) > 0)
<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green"><thead><tr><td align="center"><b>DATE</b></td><td align="center"><b>PROJET</b></td><td align="center"><b>LIEU</b></td><td align="center"><b>R&Eacute;F&Eacute;RENCE</b></td><td align="center"><b>NOM ACQUEREUR</b></td><td align="center"><b>ADRESSE ACQU&Eacute;REUR</b></td><td align="center"><b>PRESCRIPTEUR</b></td><td align="center"><b>MESSAGE</b></td>@if ($admin)<td align="center"><b>ACTIONS</b></td>@endif</tr></thead><tbody>@foreach ($rows as $option)<tr>@if ($admin)
            <td align="center">
            	<input size="10" type="text" class="datepicker" id="datepicker_{{ $option->iddlot }}_askoption" value="{{ $option->date }}" />
                <input type="hidden" type="text" class="datepickeralt" value="" />
            </td>
            @else<td align="center">{{ $option->date }}</td>@endif<td align="center">{{ $option->projet_titre }}</td><td align="center">{{ $option->projet_lieu }}</td><td align="center">{{ $option->numero }}</td><td align="center">{{ $option->nom }}</td><td align="center">{{ $option->adresse }}</td><td align="center">{{ $option->name }}</td><td align="center">@if ($option->message != '')<a href="javascript:void(0)" class="see_sms btn btn-primary btn-xs">Message</a>@endif</td>@if ($admin)<td align="center"><a rel="{{ $option->iddlot }}-{{ $option->iduser }}" href="javascript:void(0)" class="valider_ask_option btn btn-primary btn-xs"><b>VALIDER</b></a><br /><b><a rel="{{ $option->iddlot }}-{{ $option->iduser }}" href="javascript:void(0)" class="rejeter_ask_option btn btn-warning btn-xs">REJETER</a></b></td>@endif</tr><tr class="hide"><td colspan="{{ $admin ? 9 : 8 }}" class="sms">{{ \App\Http\Controllers\Workflow\PagesController::message($option->message) }}</td></tr>@endforeach</tbody></table>
{{ $rows->links() }}@else
    <div class="alert alert-info">Aucune demande d'option en cours de validation</div>
@endif
<div style="display:none">
	<div id="valid_ask_option_box">
    	<h3>Valider la demande d'option</h3>
    	<div id="call"></div>
        <form name="f_confirm_ask_option" id="f_confirm_ask_option" method="post" action="#">
        <table align="center" cellpadding="10">
        	<tr>
            	<td align="center">Laisser un message<br />au promoteur :</td>
                <td align="center"><textarea name="f_confirm_ask_option_sms" cols="60" rows="10">{{ \App\Http\Controllers\Evolutions\AutoMessagesController::text('option_validee') }}</textarea></td>
            </tr>
            <tr>
            	<td colspan="2" align="center">
                	<input type="submit" id="submit_f_confirm_ask_option" value="Valider" />
                </td>
            </tr>
        </table>
        <input type="hidden" name="f_confirm_ask_option_user" id="f_confirm_ask_option_user" value="" />
        <input type="hidden" name="f_confirm_ask_option_lot" id="f_confirm_ask_option_lot" value="" />
        </form>
    </div>
	<div id="rejeter_ask_option_box">
    	<h3>Refuser la demande d'option</h3>
    	<div class="call"></div>
        <form name="f_refuser_ask_option" id="f_refuser_ask_option" method="post" action="#">
        <table align="center" cellpadding="10">
        	<tr>
            	<td align="center">Laisser un message<br />au promoteur :</td>
                <td align="center"><textarea name="f_refuser_ask_option_sms" cols="60" rows="10">{{ \App\Http\Controllers\Evolutions\AutoMessagesController::text('option_refusee') }}</textarea></td>
            </tr>
            <tr>
            	<td colspan="2" align="center">
                	<input type="submit" id="submit_f_refuser_ask_option" value="Valider" />
                </td>
            </tr>
        </table>
        <input type="hidden" name="f_refuser_ask_option_user" id="f_refuser_ask_option_user" value="" />
        <input type="hidden" name="f_refuser_ask_option_lot" id="f_refuser_ask_option_lot" value="" />
        </form>
    </div>
</div>
@endsection
