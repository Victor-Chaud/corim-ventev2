<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>Corim promotion - API prescripteur</title>
	<link rel="stylesheet" href="/vendor/bootstrap-3.4.1/css/bootstrap.min.css">
	<link href="/vendor/questrial/questrial.css" rel="stylesheet" type="text/css" />
	<link href="/medias/css/global.css" rel="stylesheet" type="text/css" />
</head>
<body>
<div class="container">
	<div class="page-header">
		<img src="/medias/images/logo-corim.png" height="40" alt="Corim Immobilier" />
		<h1>API prescripteur <small>lecture seule</small></h1>
	</div>

	<h3>Accès</h3>
	<p>Chaque prescripteur reçoit une clé personnelle de Corim. Elle se transmet de l'une de ces deux façons :</p>
	<ul>
		<li>en-tête <code>Authorization: Bearer VOTRE_CLE</code> (recommandé pour un site ou un logiciel) ;</li>
		<li>paramètre <code>?cle=VOTRE_CLE</code> dans l'adresse (pour un outil qui ne sait que coller un lien).</li>
	</ul>
	<p>La clé vaut mot de passe : ne la publiez pas dans le code d'une page web. Corim peut la révoquer à tout moment ; une nouvelle clé coupe l'ancienne.</p>

	<h3>Adresses</h3>
	<ul>
		<li><code>GET {{ $base }}/lots</code> : les lots des programmes en cours auxquels le prescripteur a accès sur la plateforme.</li>
		<li><code>GET {{ $base }}/denonciations</code> : ses dénonciations (en attente, validées, refusées).</li>
	</ul>
	<p>Filtres de <code>/lots</code> : <code>programme</code>, <code>type</code>, <code>ville</code> (recherche partielle, sans tenir compte des accents ni de la casse), <code>minBudget</code>, <code>maxBudget</code> (en euros).</p>
	<p>Format : paramètre <code>format=json</code>, <code>format=csv</code> (séparateur point-virgule, UTF-8) ou <code>format=html</code>. Sans paramètre, la réponse est en JSON si l'en-tête <code>Accept</code> contient <code>application/json</code>, sinon une page HTML lisible.</p>

	<h3>Exemples</h3>
	<pre>curl -H "Authorization: Bearer VOTRE_CLE" "{{ $base }}/lots?format=json&amp;ville=montpellier&amp;maxBudget=250000"
curl "{{ $base }}/denonciations?cle=VOTRE_CLE&amp;format=csv"</pre>

	<h3>Champs de <code>/lots</code></h3>
	<p><code>programme</code>, <code>ville</code>, <code>livraison_programme</code>, <code>description_programme</code>, <code>vignette</code> (URL ou null), <code>reference</code>, <code>type</code>, <code>categorie</code>, <code>etage</code>, <code>surface</code>, <code>prix</code> (nombre), <code>prix_m2</code> (nombre ou null), <code>rdc</code>, <code>jardin</code>, <code>terrasse</code>, <code>stationnement</code>, <code>cellier</code>, <code>exposition</code>, <code>accession</code>, <code>livraison</code>, <code>statut</code> (libre, option, réservé, vendu), <code>plan</code> (URL du PDF ou null).</p>

	<h3>Champs de <code>/denonciations</code></h3>
	<p><code>statut</code> (en attente, validée, refusée), <code>acquereur</code>, <code>code_postal</code>, <code>ville</code>, <code>programme</code>, <code>ville_programme</code>, <code>date_demande</code>, <code>date_refus</code> (ou null).</p>

	<h3>Fréquence et limites</h3>
	<p>Les données sont à jour en temps réel : chaque appel reflète l'état de la plateforme à cet instant. Interrogez le flux aussi souvent que nécessaire, par exemple une fois par heure. Au-delà de 60 appels par heure et par clé, l'API répond 429.</p>

	<h3>Codes de réponse</h3>
	<ul>
		<li><code>200</code> : réponse normale ;</li>
		<li><code>401</code> : clé absente, inconnue ou révoquée ;</li>
		<li><code>405</code> : méthode autre que GET (l'API est en lecture seule) ;</li>
		<li><code>429</code> : trop d'appels, réessayer après le délai indiqué par <code>Retry-After</code>.</li>
	</ul>
</div>
</body>
</html>
