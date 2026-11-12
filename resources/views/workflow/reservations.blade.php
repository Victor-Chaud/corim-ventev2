@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Réservations {{ $type === 0 ? 'annulées' : 'acceptées' }}</h1>
</div>
<div class="pull-right form-group">
@if ($type === 0)
	<a href="/reservations" class="btn btn-success">Réservations acceptées</a>
@else
	<a href="/reservations/refused" class="btn btn-warning">Réservations annulées</a>
@endif
	<a href="/reservations/refusees" class="btn btn-danger">Réservations refusées</a>
</div>
<div class="clearfix"></div>
@if (count($rows) > 0)
<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green"><thead><tr><td align="center"><b>DATE</b></td><td align="center"><b>PROJET</b></td><td align="center"><b>LIEU</b></td><td align="center"><b>R&Eacute;F&Eacute;RENCE</b></td><td align="center"><b>NOM ACQUEREUR</b></td><td align="center"><b>ADRESSE ACQU&Eacute;REUR</b></td><td align="center"><b>PRESCRIPTEUR</b></td><td align="center"><b>MESSAGE</b></td>@if ($admin)<td align="center"><b>ACTIONS</b></td>@endif</tr></thead><tbody>@foreach ($rows as $reservation)<tr>@if ($admin)
            <td align="center">
            	<input size="10" type="text" class="datepicker" id="datepicker_{{ $reservation->idlot }}_resa" value="{{ $reservation->date }}" />
                <input type="hidden" type="text" class="datepickeralt" value="" />
            </td>
            @else<td align="center">{{ $reservation->date }}</td>@endif<td align="center">{{ $reservation->projet_titre }}</td><td align="center">{{ $reservation->projet_lieu }}</td><td align="center">{{ $reservation->numero }}</td><td align="center">{{ $reservation->nom }}</td><td align="center">{{ $reservation->adresse }}</td><td align="center">{{ $reservation->name }}</td><td align="center">@if ($reservation->message != '')<a href="javascript:void(0)" class="see_sms btn btn-primary btn-xs">Message</a>@endif</td>@if ($admin)<td align="center"><a rel="{{ $reservation->idlot }}-resa" href="javascript:void(0)" class="passer_en_act btn btn-primary btn-xs">ACTER</a><a rel="{{ $reservation->idlot }}-{{ $reservation->iduser }}" href="javascript:void(0)" class="annuler_resa btn btn-warning btn-xs">ANNULER</a></td>@endif</tr><tr class="hide"><td colspan="{{ $admin ? 10 : 9 }}" class="sms">{{ \App\Http\Controllers\Workflow\PagesController::message($reservation->message) }}</td></tr>@endforeach</tbody></table>@endif
<div style="display:none">
	<div id="passer_en_act_box">
    	<h3>Acter le dossier</h3>
    	<div class="call"></div>
        <form name="f_passer_en_act" id="f_passer_en_act" method="post" action="#">
        <table align="center" cellpadding="10">
        	<tr>
            	<td align="center">Laisser un message<br />au prescripteur :</td>
                <td align="center"><textarea name="f_passer_en_act_sms" cols="60" rows="10"></textarea></td>
            </tr>
            <tr>
            	<td colspan="2" align="center">
                	<input type="submit" id="submit_f_passer_en_act" value="Valider" />
                </td>
            </tr>
        </table>
        <input type="hidden" name="passer_en_act_type" id="passer_en_act_type" value="" />
        <input type="hidden" name="passer_en_act_lot" id="passer_en_act_lot" value="" />
        </form>
    </div>
	<div id="annuler_resa_box">
    	<h3>Annuler la réservation</h3>
    	<div class="call"></div>
        <form name="f_annuler_resa" id="f_annuler_resa" method="post" action="#">
        <table align="center" cellpadding="10">
        	<tr>
            	<td align="center">Laisser un message<br />au prescripteur :</td>
                <td align="center"><textarea name="f_annuler_resa_sms" cols="60" rows="10">{{ \App\Http\Controllers\Evolutions\MessagesAutoController::texte('reservation_annulee') }}</textarea></td>
            </tr>
            <tr>
            	<td colspan="2" align="center">
                	<input type="submit" id="submit_f_annuler_resa" value="Valider" />
                </td>
            </tr>
        </table>
        <input type="hidden" name="f_annuler_resa_user" id="f_annuler_resa_user" value="" />
        <input type="hidden" name="f_annuler_resa_lot" id="f_annuler_resa_lot" value="" />
        </form>
    </div>
</div>
@endsection
