@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Options {{ $type === 0 ? 'annulées' : 'acceptées' }}</h1>
</div>
<div class="pull-right form-group">
@if ($type === 0)
	<a href="/options" class="btn btn-success">Options acceptées</a>
@else
	<a href="/options/refused" class="btn btn-warning">Options annulées</a>
@endif
	<a href="/options/refusees" class="btn btn-danger">Options refusées</a>
</div>
<div class="clearfix"></div>
@if (count($rows) > 0)
<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green"><thead><tr><td align="center"><b>DATE</b></td><td align="center"><b>PROJET</b></td><td align="center"><b>LIEU</b></td><td align="center"><b>R&Eacute;F&Eacute;RENCE</b></td><td align="center"><b>NOM ACQUEREUR</b></td><td align="center"><b>ADRESSE ACQU&Eacute;REUR</b></td><td align="center"><b>PRESCRIPTEUR</b></td><td align="center"><b>MESSAGE</b></td>@if ($admin)<td align="center"><b>ACTIONS</b></td>@endif</tr></thead><tbody>@foreach ($rows as $option)<tr>@if ($admin)
            <td align="center">
            	<input size="10" type="text" class="datepicker" id="datepicker_{{ $option->idlot }}_option" value="{{ $option->date }}" />
                <input type="hidden" type="text" class="datepickeralt" value="" />
            </td>
            @else<td align="center">{{ $option->date }}</td>@endif<td align="center">{{ $option->projet_titre }}</td><td align="center">{{ $option->projet_lieu }}</td><td align="center">{{ $option->numero }}</td><td align="center">{{ $option->nom }}</td><td align="center">{{ $option->adresse }}</td><td align="center">{{ $option->name }}</td><td align="center">@if ($option->message != '')<a href="javascript:void(0)" class="see_sms btn btn-primary btn-xs">Message</a>@endif</td>@if ($admin)<td align="center"><a rel="{{ $option->idlot }}-{{ $option->iduser }}" href="javascript:void(0)" class="passer_en_resa btn btn-primary btn-xs">R&Eacute;SERVATION</a><a rel="{{ $option->idlot }}-{{ $option->iduser }}" href="javascript:void(0)" class="annuler_option btn btn-warning btn-xs">ANNULER</a></td>@endif</tr><tr class="hide"><td colspan="{{ $admin ? 10 : 9 }}" class="sms">{{ \App\Http\Controllers\Workflow\PagesController::message($option->message) }}</td></tr>@endforeach</tbody></table>@endif
<div style="display:none">
	<div id="passer_en_resa_box">
    	<h3>Passer l'option en réservation</h3>
    	<div class="call"></div>
        <form name="f_passer_en_resa" id="f_passer_en_resa" method="post" action="#">
            <div class="form-group">
                <label for="f_passer_en_resa_sms">Laisser un message au prescripteur</label>
                <textarea name="f_passer_en_resa_sms" class="form-control" rows="10"></textarea>
            </div>
            <div class="text-right">
                <input type="submit" id="submit_f_passer_en_resa" value="Valider" class="btn btn-primary" />
            </div>
            <input type="hidden" name="f_passer_en_resa_user" id="f_passer_en_resa_user" value="" />
            <input type="hidden" name="f_passer_en_resa_lot" id="f_passer_en_resa_lot" value="" />
        </form>
    </div>
	<div id="annuler_option_box">
    	<h3>Annuler l'option</h3>
    	<div class="call"></div>
        <form name="f_annuler_option" id="f_annuler_option" method="post" action="#">
            <div class="form-group">
                <label for="f_annuler_option_sms">Laisser un message au prescripteur</label>
                <textarea name="f_annuler_option_sms" class="form-control" rows="10">{{ \App\Http\Controllers\Evolutions\MessagesAutoController::texte('option_annulee') }}</textarea>
            </div>
            <div class="text-right">
                <input type="submit" id="submit_f_annuler_option" value="Valider" class="btn btn-primary" />
            </div>
            <input type="hidden" name="f_annuler_option_user" id="f_annuler_option_user" value="" />
            <input type="hidden" name="f_annuler_option_lot" id="f_annuler_option_lot" value="" />
        </form>
    </div>
</div>
@endsection
