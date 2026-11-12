@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Dénonciations</h1>
</div>
<a href="/refused_ask" class="pull-right btn btn-primary">Dénonciations refusées</a>
<h2>Consultation de vos dénonciations <small>Rechercher une dénonciation</small></h2>
<div class="form-group">
<form name="f_recherche_denonciation" id="f_recherche_denonciation" method="post" action="#" class="form-inline">
<div class="checkbox">
	<label>
    	<input type="radio" name="f_recherche_denonciation_type" checked="checked" value="1"> NOM ACQU&Eacute;REUR
  	</label>
</div>
<div class="checkbox">
	<label>
    	<input type="radio" name="f_recherche_denonciation_type" checked="checked" value="2"> R&Eacute;SIDENCE
  	</label>
</div>
@if (auth()->user()?->isAdmin())
<div class="checkbox">
	<label>
    	<input type="radio" name="f_recherche_denonciation_type" checked="checked" value="3"> PRESCRIPTEUR
  	</label>
</div>
@endif
<div class="form-group">
	<label class="sr-only" for="f_recherche_denonciation_search">Rechercher</label>
    <input type="text" class="form-control" id="f_recherche_denonciation_search" name="f_recherche_denonciation_search" placeholder="Rechercher">
</div>
<input type="submit" value="Rechercher" id="f_recherche_denonciation_submit" class="btn btn-primary" />
<a href="/denonciations" class="btn btn-primary">RESET</a>
</form>
</div>
<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green" id="f_recherche_denonciation_result">
<thead> 
<tr> 
    <th data-sorter="shortDate" data-date-format="ddmmyyyy">Date</th> 
    <th>Nom</th> 
    <th>Code Postal</th> 
    <th>Ville</th> 
    <th>Programme</th>
    <th>Prescripteurs</th>
    <th>Action</th>
</tr> 
</thead> 
<tbody> 
</tbody>
</table>
@endsection
