@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Lots act&eacute;s</h1>
</div>
@if (count($rows) > 0)
<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green">
	<thead>
    <tr>
    	<td align="center"><b>DATE</b></td>
        <td align="center"><b>R&Eacute;SERVATION</b></td>
        <td align="center"><b>PROJET</b></td>
        <td align="center"><b>R&Eacute;F&Eacute;RENCE</b></td>
        <td align="center"><b>Type</b></td>
        <td align="center"><b>Catégorie</b></td>
        <td align="center"><b>Prix</b></td>
        <td align="center"><b>Client</b></td>
        @if ($admin)<td align="center"><b>ACTIONS</b></td>@endif
    </tr>
    </thead>
    <tbody>
@foreach ($rows as $sale)<tr>@if ($admin)
            <td align="center">
            	<input size="10" type="text" class="datepicker" id="datepicker_{{ $sale->id }}_vente" value="{{ $sale->date }}" />
                <input type="hidden" type="text" class="datepickeralt" value="" />
            </td>
            <td align="center">
            	<input size="10" type="text" class="datepicker" id="datepicker_{{ $sale->id }}_venteresa" value="{{ $sale->resa }}" />
                <input type="hidden" type="text" class="datepickeralt" value="" />
            </td>
            @else<td align="center">{{ $sale->date }}</td><td align="center">{{ $sale->resa }}</td>@endif<td align="center">{{ $sale->projet_titre }}</td><td align="center">{{ $sale->numero }}</td><td align="center">{{ $sale->type }}</td><td align="center">{{ $sale->categorie }}</td><td align="center">{{ $sale->prix }}</td><td align="center">{{ $sale->client }}</td>@if ($admin)<td align="center"><a rel="{{ $sale->id }}-{{ $sale->vendeur }}" href="javascript:void(0)" class="annuler_vente btn btn-warning btn-xs"><b>ANNULER LA VENTE</b></a></td>@endif</tr>@endforeach
	</tbody>
</table>
{{ $rows->links() }}
@endif
@if ($admin)
<div style="display:none">
	<div id="annuler_la_vente_box">
    	<h3>Annuler la vente</h3>
    	<div class="call"></div>
        <form name="f_annuler_la_vente" id="f_annuler_la_vente" method="post" action="#">
        <table align="center" cellpadding="10">
        	<tr>
            	<td align="center">Laisser un message<br />au prescripteur :</td>
                <td align="center"><textarea name="f_annuler_la_vente_sms" cols="60" rows="10">{{ \App\Http\Controllers\Evolutions\AutoMessagesController::text('vente_annulee') }}</textarea></td>
            </tr>
            <tr>
            	<td colspan="2" align="center">
                	<input type="submit" id="submit_f_annuler_la_vente" value="Valider" />
                </td>
            </tr>
        </table>
        <input type="hidden" name="f_annuler_la_vente_idlot" id="annuler_la_vente_idlot" value="" />
        <input type="hidden" name="f_annuler_la_vente_vendeur" id="annuler_la_vente_vendeur" value="" />
        </form>
    </div>
</div>
@endif
@endsection
