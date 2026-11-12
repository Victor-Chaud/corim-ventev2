<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DISPONIBILITES</title>
    <link rel="stylesheet" href="/vendor/bootstrap-3.4.1/css/bootstrap.min.css">
    <link href="/vendor/questrial/questrial.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="/font-awesome-4.5.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/medias/css/awesome-bootstrap-checkbox.css">
    <link rel="stylesheet" href="/medias/css/app.css">
    <script src="/vendor/jquery/jquery-3.7.1.min.js"></script>
	<script src="/vendor/bootstrap-3.4.1/js/bootstrap.min.js"></script>
    <script src="/medias/js/app.js"></script>
</head>
<body>
<div class="container-fluid">
	<div class="row">
    	<div class="col-xs-12 col-md-12 col-lg-12 form-group" id="new-search">
        	<button id="new-search-btn" type="button" class="btn btn-primary pull-right">RECHERCHE</button>
        </div>
        <div class="clearfix"></div>
        <div class="col-xs-12 col-md-12 col-lg-12" id="search">
            <form id="form-special" action="#" method="post">
                <div class="clearfix">
                	<label>TYPOLOGIE</label>
                </div>
                <div class="pull-left">
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" class="styled" id="checkbox-t1" value="T1" name="typologie[]">
                        <label for="checkbox-t1">
                            T1
                        </label>
                    </div>
                </div>
                <div class="pull-left">
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" class="styled" id="checkbox-t2" value="T2" name="typologie[]">
                        <label for="checkbox-t2">
                            T2
                        </label>
                    </div>
                </div>
                <div class="pull-left">
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" class="styled" id="checkbox-t3" value="T3" name="typologie[]">
                        <label for="checkbox-t3">
                            T3
                        </label>
                    </div>
                </div>
                <div class="pull-left">
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" class="styled" id="checkbox-t4" value="T4" name="typologie[]">
                        <label for="checkbox-t4">
                            T4
                        </label>
                    </div>
                </div>
                <div class="pull-left">
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" class="styled" id="checkbox-t5" value="T5" name="typologie[]">
                        <label for="checkbox-t5">
                            T5
                        </label>
                    </div>
                </div>
                <div class="pull-left">
                    <div class="checkbox checkbox-primary">
                        <input type="checkbox" class="styled" id="checkbox-t6" value="T6" name="typologie[]">
                        <label for="checkbox-t6">
                            T6
                        </label>
                    </div>
                </div>
                <div class="clearfix"></div>
                <div class="form-group">
                	<label for="budget">BUDGET</label>
                    <select id="budget" name="budget" class="form-control">
                    	<option value="0">Tous critères</option>
                        <option value="1">- de 150 000 €</option>
                        <option value="2">150 000 à 200 000 €</option>
                        <option value="3">200 000 à 250 000 €</option>
                        <option value="4">250 000 à 300 000 €</option>
                        <option value="5">+ 300 000 €</option>
                   </select>
                </div>
                <div class="form-group">
              		<button type="submit" class="btn btn-primary">RECHERCHER</button>
                </div>
                <input type="hidden" name="action" value="app" />
                <input type="hidden" name="projet" id="projet" value="" />
            </form>
        </div>
        <div class="col-xs-12 col-md-12 col-lg-12 form-group hide" id="result-box">
            <table class="table table-bordered" id="result">
            <thead>
                <tr id="table-head">
                    <th>TYPE</th>
                    <th>REF</th>
                    <th>SUPERFICIE</th>
                    <th>PRIX</th>
                    <th>EXPOSITION</th>
                    <th>PLAN</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
