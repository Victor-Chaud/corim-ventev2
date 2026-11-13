# Conventions de la refonte (socle figé)

Règle : le même projet, à l'identique, en Laravel 13 / PHP 8.5. Rien ajouté, rien supprimé, mêmes textes, mêmes URL, même HTML. Les « questions ouvertes » des contrats sont tranchées par cette règle : on reproduit le comportement visible, on corrige uniquement ce qui plante (500, var_dump) en produisant le résultat que le code visait, et la sécurité vient de Laravel sans changer le rendu.

## Nommage

Les URL, les paramètres de route et de requête, les noms des champs de formulaire, les tables et colonnes de la base, les clés des réponses JSON/CSV des API, le HTML rendu (id, classes héritées, textes) et tous les textes visibles restent en français, tels quels. Tout le reste est en anglais : classes, namespaces, dossiers et fichiers PHP et Blade, méthodes, fonctions, variables, propriétés, constantes, variables passées aux vues, noms de routes, clés internes (cache, rate limiter, session flash), commandes artisan, helpers et datasets de tests.

Glossaire imposé :

| Français | Anglais |
|---|---|
| programme, projet | program |
| lot | lot |
| appartement | apartment |
| prix | price |
| statut | status |
| demande | request (variable : `pendingRequest`) |
| dénonciation | referral |
| prospect | prospect |
| option | option |
| réservation | reservation |
| vente | sale |
| acte | deed |
| annuler | cancel |
| valider | approve |
| refuser, rejeter | reject |
| refus | rejection |
| prescripteur, promoteur (au sens compte) | prescriber |
| acquéreur | buyer |
| vendeur | seller |
| clé | key |
| empreinte | fingerprint |
| fichier | file |
| plan | floor plan (`plan` reste pour les fichiers de plans) |
| vignette | thumbnail |
| diaporama | slideshow |
| droits, accès | access |
| privilège | privilege |
| messagerie | messaging |
| message automatique | auto message |
| signature | signature |
| livraison | delivery |
| ville | city |
| nom | name |
| prénom | first name |
| adresse | address |
| sujet | subject |
| texte | text |
| ligne | row |
| colonne | column |
| en-tête | header |
| données | data |
| erreur | error |
| aperçu | preview |
| modèle (de fichier) | template |
| mettre à jour | update |
| ajouter | add |
| supprimer | delete |
| archiver | archive |
| réinitialisation | password reset |

Restent volontairement en français : les noms de fichiers de migration (enregistrés dans la table `migrations`), les noms des disques de stockage (`programmes`, `upload`, `news`, liés aux URL `/medias/...`), les alias SQL lus comme des colonnes (`prescripteur`, `date_fr`, `nb_option`, `resa`...), les valeurs envoyées par `global.js` (`type` = `askoption`, `venteresa`...), les noms des contrats `docs/contract/*.md`, et dans les fichiers JS hérités les fonctions appelées depuis le HTML (`Recherche`...).

## Outils
- Toujours `php8.5` (jamais `php`), `php8.5 /usr/local/bin/composer`, `php8.5 artisan`, `php8.5 vendor/bin/pest`.
- Base de dev `corim_new` (données prod importées), base de test `corim_new_test`, connexion `legacy` en lecture seule (`sql_corim_vente_`). Utilisateur MySQL `corim_new` par socket, sans mot de passe. Ne jamais lancer `migrate:fresh`, `legacy:import` ou une écriture contre `corim_new` sans accord du lead.
- Mail : mailer `log` uniquement. Jamais de SMTP réel.
- Aucun commentaire dans le code (`//`, `/* */`, docblocks, `{{-- --}}`). `php8.5 vendor/bin/pint` avant de rendre.
- Aucun accès réseau, aucune connexion au serveur de prod, aucun commit.

## Propriété des fichiers (un module = ses fichiers, rien d'autre)
- Routes : `routes/modules/<module>.php` (chargé automatiquement par `routes/web.php`).
- Contrôleurs : `app/Http/Controllers/<Module>/`. Form requests : `app/Http/Requests/<Module>/`. Mails : `app/Mail/<Module>/`. Vues : `resources/views/<module>/` (y compris les vues d'email).
- Tests : écrits par l'agent de suivi dans `tests/Feature/<Module>/`. Les builders ne les modifient pas ; un test jugé faux est signalé dans `docs/suivi.md` section « Contestations ».
- Interdit de modifier sans passer par le lead : `app/Models/User.php`, `app/Providers/`, `app/Support/`, `app/Http/Middleware/`, `bootstrap/`, `config/`, `database/`, `resources/views/layouts/`, `public/`, `routes/web.php`, `routes/modules/medias.php`, `composer.json`.

## Données
- Tables et colonnes legacy inchangées (`user`, `projet`, `appartements`, `options`, …). Pas de clés étrangères. Utiliser le query builder (`DB::table('appartements')`) avec paramètres liés ; pas de modèles Eloquent partagés en dehors de `App\Models\User`.
- Toutes les valeurs renvoyées par l'API publique sont des chaînes (`(string)`), comme l'ancien code (PDO renvoie des entiers natifs).

## Socle disponible
- `App\Models\User` : table `user`, `access()` (`'1'` admin / `'0'` commercial), `isAdmin()`, `isActive()`, `checkPassword($pwd)` (hash legacy accepté puis rehashé), `User::legacyHash($pwd)`.
- Middleware : `auth` (invité → `/connexion`), `admin` (non admin → `/`), `guest` (connecté → `/`). En-têtes de sécurité ajoutés au groupe web (`App\Http\Middleware\SecurityHeaders`) ; les routes JSONP le retirent avec `->withoutMiddleware(\App\Http\Middleware\SecurityHeaders::class)` (pas de `nosniff` sur le JSONP).
- `App\Support\LegacyJson::respond($data, $callback = null, $contentType = 'text/html; charset=UTF-8')` : `json_encode` sans option, enveloppe JSONP seulement si le callback est un identifiant JS valide.
- Gabarits : `@extends('layouts.template')` (pages internes), `layouts.template_welcome` (accueil, sections `module_programmes`, `module_dashboard`, `content`), `layouts.connexion`. Passer `title` à la vue (défaut `Corim promotion - `). Le menu, le compteur de messages et l'outil toolbar viennent d'un view composer : ne pas les recalculer.
- CSRF : `@csrf` dans chaque formulaire ; les appels jQuery AJAX reçoivent l'en-tête automatiquement (`public/medias/js/csrf.js`). Les URL legacy `/index.php/...` fonctionnent sans route spéciale.
- Fichiers envoyés : `storage/app/medias/{programmes,upload,news,clients,csv,export}` (hors `public/`). Lecture publique par `/medias/{programmes|upload|news}/...` (route `routes/modules/medias.php`, extensions pdf/jpg/jpeg/png/gif). Écriture : chemins choisis par le serveur, liste blanche d'extensions et de types MIME, authentification.
- JS legacy : `public/medias/js/global.js` est copié tel quel ; il appelle des URL `json/<Action>` en POST/GET. Garder les mêmes URL et les mêmes réponses pour que ce JS fonctionne sans modification. Un remplacement natif (uploadify, colorbox, confirm, tablesorter, flexigrid) se fait dans `public/medias/js/` uniquement avec accord du lead.

## Tests en parallèle
- Une seule base de test partagée : toujours lancer Pest sous verrou, `flock storage/framework/pest.lock php8.5 vendor/bin/pest ...`, sinon les migrations de deux agents se télescopent.
- Les URL `/index.php/...` sont réécrites par le middleware global `StripIndexPhp` (aussi dans les tests) : ne pas déclarer de routes en double.
- PHPStan ignore `missingType.iterableValue` et `missingType.generics` (pas de docblocks autorisés).
- `App\Support\Access` : `canSeeProgram($user, $programId)`, `canSeeLot($user, $lotId)`.
- Middleware `LogoutInactive` sur le groupe web : un compte passé à `statut=0` est déconnecté à la requête suivante.
- Données importées telles quelles, avec les entités HTML de l'ancien `htmlspecialchars` : afficher `{{ html_entity_decode($valeur, ENT_QUOTES) }}` (jamais `{!! !!}`) pour le même rendu sans XSS ; enregistrer les nouvelles saisies comme l'ancien code (même normalisation) pour rester homogène.
- Mail : l'expéditeur par défaut est celui de l'ancien code (`contact@corim-promotion.com`, « Corim promotion », `config('mail.from')`) ; ne pas le redéfinir dans les Mailables. Le destinataire interne Corim (constante legacy `EMAIL`) est `config('mail.corim.address')`.
- URL générées : racine forcée sur `APP_URL` (`URL::forceRootUrl` dans `AppServiceProvider`), donc jamais de préfixe `/index.php` dans les liens et redirections, comme l'ancien `SITE_URL`. `APP_URL` doit valoir l'URL publique en prod (`https://corim-vente.com`).
- `public/medias/js/app.js` : URL `http://corim-vente.com/...` remplacées par des chemins relatifs (`/index.php/firstsearch`, `/medias/programmes/...`), seule modification du JS legacy.
- Aucun envoi réel : hors production, `AppServiceProvider` force le mailer `log` même si `MAIL_MAILER` vaut autre chose (`array` reste permis pour les tests). Interdits : `mail()`, Swift, sockets, appels HTTP sortants, `queue:work`, exécution du code `OLD/`.
