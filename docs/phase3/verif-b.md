# Vérification phase 3 : VerifB (workflow, clients-prospects, promoteurs, communication)

Date : 2026-11-12. Vérificateur : VerifB. Aucun fichier de l'application modifié.

## 1. Verdict

**PASS avec réserve mineure.** Aucun écart bloquant ni moyen. Un écart mineur sur l'écran « Messages automatiques » (évolution lot 7b), avec un test qui échoue.

## 2. Ce qui a été vérifié

Tests ajoutés dans `tests/Feature/Phase3/CheckB/` (commande : `flock storage/framework/pest.lock php8.5 vendor/bin/pest tests/Feature/Phase3/VerifB`) : 20 tests, 19 passent, 1 échoue (VB-E1, écart ci-dessous). Les dossiers des modules et des évolutions passent toujours : `Workflow`, `ClientsProspects`, `Promoteurs`, `Communication`, `Evolutions` + VerifB = 250 tests, 249 passent.

**Workflow** (`WorkflowTest.php`, VB-W1 à VB-W9, fichier de la session du 2026-11-10 gardé tel quel)
- Nom et code postal facultatifs sur AddOption / AddReservation, comme `OLD/application/controllers/json.php:907-917`.
- Cycle complet option → validation → réservation → acte → annulation de la vente : mêmes écritures que `model.php` (dates reprises de la demande, message suffixé du séparateur, `ventes.date_resa`, lot remis à `''`), cinq e-mails aux sujets legacy dans l'ordre.
- Aucune transition hors machine à états (7 cas) : refus sans écriture ni e-mail.
- Double appel (double clic) sur PasserEnResa, AnnulerOption, AnnulerResa, PasserEnActer, AnnulerLaVente : second appel `{"errors":0}`, rien réécrit, un seul e-mail (le lot 1 couvre déjà les validations et refus dans `Evolutions/Lot1DoubleClickTest.php`).
- Droits : un prescripteur ne peut ni optionner ni réserver un lot hors de `acces_lot`, ni dénoncer sur un programme hors de `acces`, ni lire les dénonciations d'un autre via `SearchProspect` (types 1, 2, 3).

**JS legacy ↔ routes NEW** (`JsRoutesTest.php`, VB-J1 à VB-J4)
- Sur les 41 actions `json/<Action>` appelées par `public/medias/js/global.js`, 37 répondent 200 en POST ; les 4 non portées et documentées (`Acter`, `DeleteEvent`, `Recherche`, `Recherche_Ville`, voir `docs/suivi.md` et contrats workflow §11 / communication §4.2 / public) répondent 404. Elles n'ont aucun déclencheur dans les vues.
- Les 17 actions du workflow appelées par `global.js` renvoient `{"errors":N}` en `text/html`, évaluable par `eval('(' + callback + ')')`.
- Toutes les pages ciblées par `location = SITE+"..."` et les liens construits par `global.js` existent (`ventes`, `reservations`, `ask_*`, `options`, `gestion_privilege` en 200 ; `agenda/classique` redirige vers `/`, agenda non porté).
- `app.js` : `/index.php/firstsearch` relatif, JSONP `MyFirstCallback`, plans sous `/medias/programmes/`, plus aucun `http://` ; le formulaire `public/app.blade.php:93-94` envoie bien `action=app` et `projet`.
- `diff OLD/medias/js/global.js NEW/public/medias/js/global.js` : seules différences = datepicker créé au focus (lot 6) et anti double clic (lot 1). `SITE` vaut `"/"` dans les deux. Tous les `id` utilisés par les gestionnaires du workflow existent dans les vues NEW ; les `id` absents (`answer`, `informations`, `backforgottenpassword`, `selectChoiceMonth/Year`, `submit_option`, `submit_reservation`, `topmenu`, `header`, `close`) appartiennent à des écrans non portés ou hors périmètre (connexion, agenda, `/optionner`, `/reserver`, barre d'outils).
- Champs des formulaires de demande (`f_add_option`, `f_add_reservation`, `f_new_prospect` dans `programmes-lots/programme.blade.php`) identiques à `V_programme.php`.

**Évolutions** (`EvolutionsTest.php`)
- VB-E2 : les 11 boutons de décision du workflow (`#submit_f_*`) sont désactivés au clic et réactivés sur erreur HTTP ou réponse `errors != 1` (`global.js:405-984`).
- VB-E3 : les modales sont pré-remplies avec le message type, échappé dans le `<textarea>` (pas d'injection `</textarea><script>`).
- Lots 7b et 7c déjà couverts par `Evolutions/Lot7bMessagesTest.php` (9 tests) et `Lot7cRejectionsTest.php` (7 tests, dont admin voit tout / prescripteur seulement les siens).

**HTML rendu face aux vues legacy, par rôle**
- Comparaison des signatures de balises (nom, `id`, `class`, `name`, `type`, `href`, `rel`, `colspan`) entre 24 vues `OLD/application/views/V_*.php` et leurs vues Blade (script dans le scratchpad de la session, sortie relue ligne à ligne). Écarts restants tous expliqués : `SITE_URL` remplacé par `/`, `@csrf`, formulaires POST de suppression (client, flash info) à la place des liens GET, uploadify remplacé par `<input type="file">` + `fetch` (`clients-prospects/ajouter_clients.blade.php:12-33`), `hidden_pwd` retiré de `modifier_promoteur` (pas de `pwdshow`), boutons « Options refusées » / « Réservations refusées » (lot 7c), ligne de barre de progression commentée en HTML retirée du rapport, `<script src=ckeditor/ckeditor.js>` local d'`V_emailing.php` remplacé par le CDN du gabarit.
- Branches par rôle relues vue par vue (`ask_options`, `ask_reservations`, `ask_denonciations`, `options`, `reservations`, `ventes`, `denonciations`, `refused_ask`, `messagerie`) : colonne ACTIONS, datepickers, modales et liens d'envoi réservés à l'admin comme dans OLD ; testé par `Workflow/PagesTest.php` C26 et `Communication/MessagingTest.php` C3, C4, C8.

**E-mails** : déclencheur, destinataire, sujet, corps HTML et texte de chaque e-mail des contrats déjà vérifiés à l'octet par les tests existants, relus : workflow E1 à E14 (`Workflow/EmailsTest.php` C31, corps du lot 7b), E3 dénonciation (`ClientsProspects/ProspectsTest.php` C18), promoteurs (`Promoteurs/AddTest.php` C14, `ModifierTest.php` C27), communication (`SendTest.php` C13, `EmailingTest.php` C15-C16 en file, `ContactAgendaTest.php` C26).

**Checklist sécurité**
- Droits par programme / lot : `Access::canSeeLot` (`Workflow/JsonController.php:233`), `Access::canSeeProgram` (`ClientsProspects/ProspectsController.php:83`), listes filtrées sur l'utilisateur (`Workflow/PagesController.php:209-213`), messagerie limitée à l'émetteur ou au destinataire (`Communication/MessagingController.php:22-30`). Toutes les décisions admin sont sous le middleware `admin` (`routes/modules/workflow.php:24-39`).
- CSRF : tests 419 existants (Workflow C4, Promoteurs C30, Communication C29, Clients C10) ; jeton envoyé par `csrf.js` (chargé dans les trois gabarits) et par l'en-tête du `fetch` d'upload.
- Échappement : tous les `{!! !!}` des vues du périmètre relus. Ils ne sortent que des constantes, des attributs `selected` / `checked`, des messages d'erreur fixes du contrôleur ou des valeurs passées par `e()` / `htmlspecialchars`. Les messages du workflow passent par `PagesController::message()` (`e()` sur chaque partie, séparateur seul en HTML).
- SQL : aucune requête interpolée avec une saisie. Les `selectRaw` / `whereRaw` n'interpolent que des noms de tables ou de colonnes constants (`PagesController.php:33-198`, `EmailingController.php:121` avec liaisons `?`, `PrescribersController.php:33` lié).
- Upload CSV clients : authentifié admin, nom de fichier fixé par le serveur, extension `csv` + liste blanche MIME (`ClientsController.php:130-138`).

## 3. Écarts

### Bloquant
Aucun.

### Moyen
Aucun.

### Mineur

**M1. « Messages automatiques » : un texte trop long est perdu sans message.**
- NEW : `app/Http/Controllers/Evolutions/AutoMessagesController.php:44` (`$request->validate(... 'max:5000')`) et `resources/views/evolutions/messages_auto.blade.php` (aucun affichage de `$errors`, pas de `old()`).
- Attendu : comme les autres formulaires de l'application (`alert alert-warning`, par exemple `communication/emailing.blade.php:10`), une erreur visible et les textes saisis conservés (évolution lot 7b, `docs/ameliorations.md` §3).
- Constaté : la validation redirige vers la page précédente sans message ; rien n'est enregistré et les neuf textes saisis sont effacés. Dans le test (sans page précédente), la redirection mène au tableau de bord.
- Test : `CheckB/EvolutionsTest.php` VB-E1 (échoue).
- Correction proposée : afficher `$errors` dans un `alert alert-warning` et réafficher les saisies avec `old($cle, $textes[$cle])`.

## 4. Remarques (pas des écarts) et points à arbitrer

- `/options/refused` et `/reservations/refused` : la balise `<title>` passe de « Options refusées » (`OLD/application/controllers/options.php:27`) à « Options annulées ». Le `<h1>` était déjà « annulées ». Cohérent avec le lot 7c, puisque « Options refusées » désigne maintenant la nouvelle page `/options/refusees`. À confirmer par le lead.
- Le bouton « Valider » d'une nouvelle dénonciation (`#submit_f_new_prospect`, `global.js:1526`) n'est pas protégé contre le double clic, et `ProspectsController::add` n'a pas de dédoublonnage : deux clics créent deux demandes, comme dans OLD. Le lot 1 ne visait que les boutons de validation, de refus et d'annulation, donc ce n'est pas un écart. À proposer si la cliente le souhaite.
- AddProspect avec une session expirée : OLD répondait `{"errors":3}` (« Votre session a expirée »). NEW répond 419 (CSRF) ou 401 avant le contrôleur, et le message ne s'affiche plus. C'est un effet direct de la protection CSRF et de l'authentification, accepté au titre de la sécurité.
- Sur les données de prod, 34 options actives et 16 réservations actives pointent vers des lots supprimés (requête en lecture sur `legacy`). Elles n'apparaissent dans aucune liste (jointure sur `appartements`), comme dans OLD. Ce point est à reprendre dans la répétition de migration.

## 5. Points non vérifiables en local

- Comportement réel dans un navigateur : enchaînement `jquery.confirm` puis colorbox, désactivation effective des boutons au clic, datepicker créé au focus, upload par `fetch`, CKEditor chargé depuis le CDN, à vérifier à la main en préproduction (admin et prescripteur).
- Comparaison visuelle des pages (1280 px et 375 px) avec l'ancienne application.
- Emailing : débit réel, réessais et worker de file (`QUEUE_CONNECTION=database`) avec le vrai fournisseur SMTP ; SPF, DKIM et DMARC.
- Affichage des e-mails du workflow (police, signature) dans plusieurs logiciels de messagerie.
