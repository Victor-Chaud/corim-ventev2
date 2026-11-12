<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

	<title>{{ $title ?? 'Corim promotion - ' }}</title>

    <link rel="stylesheet" href="/vendor/bootstrap-3.4.1/css/bootstrap.min.css">
	<link rel="stylesheet" href="/vendor/bootstrap-select-1.13.18/css/bootstrap-select.min.css">
    <link href="/vendor/questrial/questrial.css" rel="stylesheet" type="text/css" />
	<link rel="stylesheet" href="/medias/css/print.css" type="text/css" media="print" />
	<link rel="stylesheet" href="/medias/css/print-preview.css" type="text/css" media="screen" />
    <link rel="stylesheet" href="/medias/css/smoothness/jquery-ui-1.9.0.custom.min.css" type="text/css" media="screen" />

	<script src="/vendor/jquery/jquery-3.7.1.min.js"></script>
    <script src="/medias/js/jquery.browser.js"></script>
	<script src="/vendor/bootstrap-3.4.1/js/bootstrap.min.js"></script>
    <script src="/vendor/jquery-ui-1.13.3/jquery-ui.min.js"></script>
	<script src="/vendor/bootstrap-select-1.13.18/js/bootstrap-select.min.js"></script>
	<script src="/vendor/bootstrap-select-1.13.18/js/i18n/defaults-fr_FR.min.js"></script>

    <script src="https://cdn.ckeditor.com/4.5.9/full-all/ckeditor.js" integrity="sha384-8PiqwmW629Fhngu9AcccHOGujU1YP1TBkDprFFtK2S9Js8+vyIMuymnZ+WTl4PbE" crossorigin="anonymous"></script>

    <script src="/medias/tablesorter/js/jquery.tablesorter.combined.js"></script>
    <link href="/medias/tablesorter/css/theme.green.css" rel="stylesheet" type="text/css" />
    <link href="/medias/colorbox/colorbox.css" rel="stylesheet" type="text/css" />
    <script src="/medias/colorbox/jquery.colorbox-min.js"></script>
    <script src="/medias/uploadify/jquery.uploadify-3.1.min.js"></script>
    <link href="/medias/uploadify/uploadify.css" rel="stylesheet" type="text/css" />
    @switch($segment1)
    @case('connexion')
    	<link href="/medias/css/connexion.css" rel="stylesheet" type="text/css" />
    @break
    @case('rechercher')
    	<link href="/medias/css/recherche.css" rel="stylesheet" type="text/css" />
    @break
    @case('messagerie')
    	<link href="/medias/css/messagerie.css" rel="stylesheet" type="text/css" />
    @break
    @case('contact')
    	<link href="/medias/css/contact.css" rel="stylesheet" type="text/css" />
    @break
    @case('agenda')
    	<link href="/medias/css/agenda.css" rel="stylesheet" type="text/css" />
    @break
    @case('programme')
    	<link href="/medias/flexigrid/css/flexigrid.pack.css" rel="stylesheet" type="text/css" />
        <script src="/medias/flexigrid/js/flexigrid.pack.js"></script>
    @break
    @case('gestion_programmes')
    <link type="text/css" href="/medias/css/jquery.jscrollpane.css" rel="stylesheet" media="all" />
    <script type="text/javascript" src="/medias/js/jquery.mousewheel.js"></script>
	<script type="text/javascript" src="/medias/js/jquery.jscrollpane.min.js"></script>
    @break
    @endswitch
    <link href="/medias/confirm/jquery.confirm.css" rel="stylesheet" type="text/css" />
    <script src="/medias/confirm/jquery.confirm.js"></script>
    <link href="/medias/css/global.css" rel="stylesheet" type="text/css" />
	<script src="/medias/js/jquery.print-preview.js"></script>
    <script src="/medias/js/global.js"></script>
    <script src="/medias/js/csrf.js"></script>
</head>
<body>

  <nav class="navbar navbar-default">
	<div class="container-fluid">
@include('layouts.partials.nav', ['menuRight' => true])
    </div>
  </nav>

  <div class="container">
      <div class="row">
      	  <div class="col-xs-12 col-sm-12 col-md-12 form-group">
          	@yield('content')
          </div>
          <footer class="no-print">
          	<div class="col-xs-12 col-sm-12 col-md-12 form-group text-center">
            	<small>Copyright&copy; Corim Promotion - Tous droits réservés</small>
            </div>
          </footer>
      </div>
  </div>

</body>
</html>
