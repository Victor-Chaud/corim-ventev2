<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Corim promotion - ' }}</title>

    <link rel="stylesheet" href="/vendor/bootstrap-3.4.1/css/bootstrap.min.css" type="text/css">
	<link href="/vendor/questrial/questrial.css" rel="stylesheet" type="text/css" />
	<link rel="stylesheet" href="/medias/css/print.css" type="text/css" media="print" />
	<link rel="stylesheet" href="/medias/css/print-preview.css" type="text/css" media="screen" />
    <link type="text/css" href="/medias/css/jquery.jscrollpane.css" rel="stylesheet" media="all" />
    <link href="/medias/tablesorter/css/theme.green.css" rel="stylesheet" type="text/css" />
    <link href="/medias/css/global.css" rel="stylesheet" type="text/css" />
    <link href="/medias/css/dark-mode.css" rel="stylesheet" type="text/css" />
    <script src="/medias/js/dark-mode.js"></script>
    <script src="/vendor/jquery/jquery-3.7.1.min.js"></script>
    <script src="/medias/js/jquery.browser.js"></script>
	<script src="/vendor/bootstrap-3.4.1/js/bootstrap.min.js"></script>
    <script src="/vendor/jquery-ui-1.13.3/jquery-ui.min.js"></script>
    <script src="/medias/tablesorter/js/jquery.tablesorter.combined.js"></script>
    <script src="/medias/js/jquery.print-preview.js"></script>
    <script src="/medias/js/global.js"></script>
    <script src="/medias/js/csrf.js"></script>
</head>
<body>

    <nav class="navbar navbar-default">
        <div class="container-fluid">
@include('layouts.partials.nav', ['menuRight' => false])
        </div>
      </nav>

    <div class="container">
        <div class="row">
        	<div class="col-xs-12 col-sm-12 col-md-12 form-group">
                <div class="col-xs-6 col-sm-5 col-md-3">
                    @yield('module_programmes')
                </div>
                <div class="col-xs-6 col-sm-7 col-md-9">
                    @yield('module_dashboard')
                </div>
                <div class="col-xs-12 col-sm-12 col-md-12">
                    @yield('content')
                </div>
            </div>
            <footer>
                <div class="col-xs-12 col-sm-12 col-md-12 form-group text-center">
                    <small>Copyright&copy; Corim Promotion - Tous droits réservés</small>
                </div>
            </footer>
        </div>
    </div>
  </div>
</body>
</html>
