<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
	<title>{{ $title ?? 'Corim promotion - ' }}</title>

    <link rel="stylesheet" href="/vendor/bootstrap-3.4.1/css/bootstrap.min.css">
    <link href="/vendor/questrial/questrial.css" rel="stylesheet" type="text/css" />
	<link rel="stylesheet" href="/medias/css/print.css" type="text/css" media="print" />
	<link rel="stylesheet" href="/medias/css/print-preview.css" type="text/css" media="screen" />
    <link rel="stylesheet" href="/medias/css/smoothness/jquery-ui-1.9.0.custom.min.css" type="text/css" media="screen" />
    <link href="/medias/css/connexion.css" rel="stylesheet" type="text/css" />
    <link href="/medias/css/global.css" rel="stylesheet" type="text/css" />

	<script src="/vendor/jquery/jquery-3.7.1.min.js"></script>
    <script src="/medias/js/jquery.browser.js"></script>
	<script src="/vendor/bootstrap-3.4.1/js/bootstrap.min.js"></script>
    <script src="/vendor/jquery-ui-1.13.3/jquery-ui.min.js"></script>
    <script src="/medias/tablesorter/js/jquery.tablesorter.combined.js"></script>
	<script src="/medias/js/jquery.print-preview.js" type="text/javascript"></script>
    <script src="/medias/js/global.js" type="text/javascript"></script>
    <script src="/medias/js/csrf.js" type="text/javascript"></script>
</head>
<body>
  @yield('content')
  <div class="clear"></div>
</body>
</html>
