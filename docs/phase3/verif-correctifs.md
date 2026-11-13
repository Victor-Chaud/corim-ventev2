# Vérification des correctifs de phase 3

Vérificateur : agent de vérification, 2026-11-12. Périmètre : correctifs apportés après `revue-securite.md`, `verif-a.md` et `verif-b.md` (sécurité back-end et front). Aucun fichier de l'application ni test existant n'a été modifié. Tests ajoutés : `tests/Feature/Phase3/FixChecks/FixChecksTest.php`.

## 1. Verdict

**FAIL** : 0 bloquant, 2 moyens, 2 mineurs.

Les correctifs principaux tiennent. L'API publique reste octet pour octet compatible, avec ou sans cache. La réinitialisation par lien signé résiste à l'expiration, au rejeu et à la falsification. jQuery 3.7.1 tourne sans erreur sur 25 pages. `composer verify` est vert.

Les deux écarts moyens sont simples à corriger :
- le lien « IMPRIMER » de `/report` est bloqué par la CSP ;
- la réponse de « mot de passe oublié » révèle encore l'existence d'un compte quand l'e-mail est saisi avec une autre casse.

## 2. Ce qui a été vérifié

### Qualité

- `cd NEW && php8.5 /usr/bin/composer verify`, lancé avant l'ajout de mes tests :
  - Pint passe ;
  - PHPStan : 0 erreur ;
  - Pest : **405/405** (10 520 assertions) ;
  - `composer audit` : aucune vulnérabilité.

### API publique

- **Fixtures** (VC1, passe) : les 16 fixtures du manifeste, demandées à froid puis depuis le cache, donnent le même statut, le même Content-Type et le même corps. Le corps est identique à la fixture pour `nextjscorim_city`, `first_default_empty`, `first_default_prog` et `search_allnews`. Les réponses portent `Content-Security-Policy: default-src 'none'; sandbox` et jamais `X-Content-Type-Options`. Les tests existants `tests/Feature/PublicApi/FixtureReplayTest.php` (C1 à C28) passent eux aussi.
- **Cache sans mélange** (VC2, passe) : 9 requêtes de paramètres et de points d'entrée différents sont rejouées dans les deux ordres. Chacune donne exactement sa réponse calculée sans cache. Exemples : `firstsearch` contre `search` contre `search/index` avec la même chaîne de requête, ou `withref` des deux côtés. La clé est `path + QUERY_STRING` brute (`LegacyApiController.php:42`).
- **Aucune erreur mise en cache** (VC3, passe) : une panne SQL simulée sur la première requête donne un 500, qui n'est pas mémorisé. La requête suivante renvoie la fixture exacte. `action=email` ne passe jamais par le cache (`LegacyApiController.php:38-40`).
- **`url` refusée** (VC4, passe) : avec `<` ou `>`, y compris sous forme de tableau `url[]`, aucune ligne n'est écrite dans `docs` sur `search`, `search/index` et `firstsearch`. La réponse vaut `MyCallback3({"errors":"Votre adresse email n'est pas valide","data":null});`. Deux appels valides donnent bien deux lignes : la réponse n'est pas servie depuis le cache.
- **Limite d'écriture** (VC5, passe) : au-delà de 10 écritures par minute et par IP, plus rien n'est inséré dans `docs` (`LegacyApiController.php:100`).

### Réinitialisation du mot de passe

- **Tests existants** : `PasswordResetTest` C20, C20b, C21, C22 et C23 passent. Ils couvrent le lien expiré après 61 min, la signature modifiée, l'id modifié, le rejeu après usage (403), les 8 caractères minimum, la double saisie et un seul mail par période et par compte.
- **Empreinte d'un autre compte** (VC7, passe) : un lien correctement signé pour le compte B, mais qui porte l'empreinte du lien de A, est refusé en GET comme en POST (403). Aucun mot de passe n'est modifié.
- **Logs** (VC8, passe) : avec le mailer `log`, le mot de passe choisi n'apparaît dans aucun message journalisé, ni sur une saisie en erreur ni sur une saisie réussie.
- **Limite de fréquence** (VC9, passe) : 25 POST sur le lien finissent en 429 (`throttle:20,1`, `routes/modules/auth-users-privileges.php:12`).
- **Gabarit du mail** : `resources/views/auth-users-privileges/mail/reinitialisation.blade.php` reprend la coque legacy (logo `medias/images/logo-corim.png`, `table width="500"`, pied « Le Cristal » identique à `OLD/application/controllers/json.php:137,144`). La partie texte est `strip_tags` du HTML, comme `->addPart(strip_tags($body), 'text/plain')` dans le legacy. Le lien n'expose aucun mot de passe.
- **`CheckIfExist`** : la réponse ne contient plus d'`id` et elle est la même pour un e-mail connu, inconnu ou en double (C21).
- **Connexion** : au-delà de 20 échecs depuis une même IP, même un bon couple est refusé (`LoginController.php:42-44`, test S2).

### Front et JS

- **`node --check public/medias/js/global.js`** : OK.
- **Retrait des commentaires** : je compare les jetons JavaScript (acorn, commentaires ignorés) de `global.js` et de la copie d'avant, `/tmp/claude-1000/-home-etiseo-www-corim-vente/c5404bc8-2ce2-4241-975f-cd2bdcde797e/scratchpad/global.js.bak`. Résultat : **11 077 jetons identiques**. Le comportement est donc inchangé.
- **Diff de `global.js` contre `OLD/medias/js/global.js`**, uniquement les changements attendus :
  - `.attr('value',x)` devient `.val(x)` ;
  - `.attr('checked','checked')` et `removeAttr('checked')` deviennent `.prop('checked', …)` ;
  - `.size()` devient `.length` ;
  - les boutons du workflow sont désactivés pendant l'appel ;
  - le datepicker est initialisé au focus.

  Aucun sélecteur `[checked]` ou `[value]` ne dépend de l'attribut. Seuls `:checked` sont utilisés (`global.js:195,260`).
- **Plugins modifiés** :
  - `jquery.print-preview.js:93` : `.load(fn)` devient `.on('load', fn)` ;
  - `jquery.mousewheel.js:75` : `$.event.handle` devient `$.event.dispatch`.
- **API jQuery retirées en 3.x**, recherchées dans `global.js`, `app.js`, `csrf.js` et tous les plugins chargés (colorbox, confirm, flexigrid, tablesorter, jscrollpane, mousewheel, print-preview, uploadify) :
  - `.live(` : présent seulement dans colorbox 1.6.3, dans une branche gardée par `t.isFunction(t.fn.on)`. Il n'est donc jamais exécuté ;
  - `$.browser` : flexigrid et `print-preview.js:174`, fourni par `jquery.browser.js`, chargé dans les trois layouts juste après jQuery ;
  - `.error(` : `console.error` dans tablesorter et `$.error` dans uploadify, qui existent ;
  - `$.parseJSON` : tablesorter, gardé par `if ($.parseJSON)` ;
  - `.selector` : seulement des options `c.selector*` de tablesorter ;
  - aucun `.die(`, `.andSelf(`, `.size()`, `.load(fn)`, `.unload(`, `.toggle(fn, fn)`, `.success/.error/.complete` de jqXHR ou `.attr('value'|'checked'|'selected'|'disabled'` ;
  - `.bind(`, `.unbind(`, `.delegate(`, `$.isArray`, `$.isFunction`, `$.trim`, `$.type` et `isWindow` sont seulement dépréciés et existent en 3.7.1.
- **Contrôle visuel** :
  - Mise en place : serveur `php8.5 -S 127.0.0.1:8791` avec un routeur du scratchpad qui force `SET SESSION TRANSACTION READ ONLY` sur chaque connexion MySQL, sessions en fichier, cache `array`, mail `log`, `APP_DEBUG=false` et session admin forgée sans passer par le formulaire.
  - Chromium headless (Playwright, `chromium-1254`), requêtes hors localhost bloquées.
  - Sans erreur JS, sans violation CSP et sans requête locale en échec (jQuery 3.7.1 sur chaque page) : tableau de bord, fiche programme NATURA (flexigrid présent, 34 lignes chargées), `/options` (tablesorter actif, 6 datepickers, calendrier ouvert au focus), `/gestion_programmes`, `/denonciations`, `/reservations`, `/ventes`, `/gestion_privilege`, `/messagerie`, `/gestion_clients`, `/gestion_prospects`, `/gestion_promoteurs`, `/flash_info`, `/ajouter_programme`, `/messages_automatiques`, `/ask_options`, `/contact`, `/app`, `/connexion`, la page de réinitialisation (200) et un lien falsifié (403).
  - Seule erreur sur `/emailing` et `/flash_info/add` : `CKEDITOR is not defined`, parce que le CDN est bloqué dans ce contrôle (accès réseau interdit).
  - Après le contrôle : serveur arrêté, sessions de test supprimées, aucune écriture en base.
  - Captures : `/tmp/claude-1000/-home-etiseo-www-corim-vente/c5404bc8-2ce2-4241-975f-cd2bdcde797e/scratchpad/verif-correctifs/`.

### CSP

- **Pages sans CKEditor** : `script-src` ne contient pas `'unsafe-inline'` (VC12). Il contient `'unsafe-inline'` sur `/emailing` et `/flash_info/add` (VC13). `style-src` garde `'unsafe-inline'`, comme prévu.
- **Scripts inline** (relevé exhaustif de `resources/views`, VC13, passe) :
  - 6 balises `<script>` inline ;
  - 3 portent `nonce="{{ Vite::cspNonce() }}"` : `programme.blade.php:354`, `ajouter_clients.blade.php:12`, `clients.blade.php:57` ;
  - 3 n'ont pas de nonce et sont sur des pages CKEditor en `'unsafe-inline'` : `emailing.blade.php:109`, `flash_info_add.blade.php:64`, `flash_info_edit.blade.php:71`.
- **Attributs `on*=`** (relevé exhaustif) : 4 sources distinctes (`this.select()`, `this.nextElementSibling.submit();return false;`, `HideToolbar();`, `ShowToolbar();`), toutes hachées dans `SecurityHeaders.php:12-19`. Les URL `javascript:void(0)` et `javascript:void(0);` sont hachées aussi. Il manque `javascript:window.print()` (voir M1).
- **HTML généré par JS** : `global.js:1728` produit `onclick="Recherche()"`, sans hash. Ce code est mort : `Recherche_Ville()` n'est appelée nulle part et la route `json/Recherche_Ville` n'existe pas.

### Autres correctifs

- **`/medias`** : sert doc, docx, xls et xlsx en pièce jointe avec le bon type (S3). La traversée est refusée : `../`, `%2e%2e`, `..%2f` donnent 404 (VC11a, passe).
- **`/messages_automatiques`** : `withErrors()->withInput()` et `old()` dans la vue (`AutoMessagesController.php:52-54`, `messages_auto.blade.php:10-12,23`). Le test existant passe.
- **Export CSV des clients** : `PrescriberApiController::cellule()` (`ClientsController.php:113`, test S4).
- **`passwords:wrap-legacy`** et `User::checkPassword` (test S5, passe). En base de dev : 241 hash sha256 bruts et 1 bcrypt.
- **`mp.cle`** : `bin2hex(random_bytes(20))` dans `Workflow/JsonController.php:270`, `ProspectsController.php:106` et `MessagingController.php:40`.
- **`public/.htaccess`** : sans commentaire. `.env.example` : `SESSION_SECURE_COOKIE=true` et `MAIL_MAILER=log`.

### Règle « aucun commentaire »

- `app`, `routes`, `config`, `database`, `tests`, `resources`, `public/medias/js/{global,app,csrf,jquery.browser}.js` et `public/.htaccess` ne contiennent aucun commentaire.
- La recherche de `//`, `/*`, `{{--`, `<!--` et `#` en début de ligne ne remonte que de faux positifs : des motifs `glob('…/*')`, des chaînes et des URL.
- `jquery.mousewheel.js` garde son en-tête de licence d'origine. C'est un plugin tiers, donc exclu.

## 3. Écarts

### Bloquant

Aucun.

### Moyens

**M1. Le lien « IMPRIMER » de `/report` ne fonctionne plus (CSP)**
- **Où** : `resources/views/workflow/report.blade.php:24` (`<a href="javascript:window.print()">`) et `app/Http/Middleware/SecurityHeaders.php:12-19`.
- **Attendu** : le clic ouvre l'impression, comme dans le legacy.
- **Constaté** : il n'y a pas de hash pour `javascript:window.print()`. Chromium affiche « Running the JavaScript URL violates the following Content Security Policy directive 'script-src … » et `window.print` n'est pas appelé (contrôle visuel, page `report`).
- **Test** : VC12 (échoue).
- **Correction** : ajouter `'sha256-loNEcvOiwPZFEqTNJYE+/sdSv0X/6zDcCFyxAG+oGiw='` à `INLINE_HANDLERS`.

**M2. « Mot de passe oublié » révèle l'existence d'un compte quand la casse de l'e-mail diffère**
- **Où** : `app/Http/Controllers/Auth/PasswordController.php:90` (`$user->email ?? $email`).
- **Attendu** : même réponse quel que soit l'e-mail (contrat F4, correctif demandé).
- **Constaté** : la recherche MySQL ignore la casse (`utf8mb4_general_ci`).
  - Avec `CONNU@Exemple.test`, la réponse affiche l'adresse stockée `connu@exemple.test`.
  - Avec `INCONNU@Exemple.test`, elle affiche la saisie telle quelle.
  - La différence prouve que le compte existe et donne son adresse exacte. Les espaces en fin de saisie ont le même effet (collation PAD SPACE).
- **Test** : VC6 (échoue).
- **Correction** : afficher toujours l'adresse saisie (`$email` de la session), jamais `$user->email`.

### Mineurs

**m1. Les fichiers réels dont le nom contient `..` ne sont plus servis**
- **Où** : `routes/modules/medias.php:13` (`abort_if(str_contains($path, '..'), 404)`).
- **Attendu** : le legacy (Apache) servait ces fichiers. `realpath` et la vérification de préfixe, lignes 15-19, bloquent déjà la traversée.
- **Constaté** : 6 fichiers de `storage/app/medias` répondent 404. Exemples : `programmes/f3d746…/plans/plan-A003..pdf`, `programmes/52af1d…/documents/COUSTAUDE Notice descriptive Apt 08.08.2019.D..pdf`, `upload/1443617726BOREAL.perspective_PC_Av..jpg`. Tous les programmes concernés sont archivés (`archive = 1`), d'où la gravité mineure.
- **Test** : VC11b (échoue). VC11a (passe) montre que la traversée reste bloquée sans cette ligne.
- **Correction** : refuser seulement un segment égal à `..` (`in_array('..', explode('/', $path), true)`) ou s'en remettre à `realpath`.

**m2. Un hash sha256 enveloppé devient lui-même un mot de passe valide**
- **Où** : `app/Models/User.php:52`.
- **Constaté** : après `passwords:wrap-legacy --apply`, le hash vaut `bcrypt(sha256)`. `Hash::check($password, …)` accepte alors la chaîne sha256 brute comme mot de passe. Quiconque détient l'ancien hash (dump legacy) peut se connecter sans connaître le mot de passe. Avant l'enveloppe, ce n'était pas possible.
- **Test** : VC10 (échoue).
- **Correction** : marquer les hash enveloppés, par exemple avec un préfixe `legacy:` devant le bcrypt. Pour ces hash, ne tester que `Hash::check(legacyHash($password), …)`.

## 4. Points non vérifiables en local

- **CKEditor 4.5.9** : le chargement réel depuis `cdn.ckeditor.com`, la correspondance du hash SRI `sha384-8Piqwm…` (avec `crossorigin="anonymous"`, il faut aussi un en-tête CORS du CDN), et le bon fonctionnement de l'éditeur sous la CSP `'unsafe-inline'` (iframe wysiwyg, `frame-src 'self'`). Accès réseau interdit ici. À contrôler en préproduction sur `/emailing`, `/flash_info/add` et `/flash_info/edit/{id}`.
- **En-têtes réels derrière Apache** : absence de `nosniff` sur le JSONP et compression.
- **Cache de 300 s** : un changement de statut d'un lot est visible des sites partenaires avec jusqu'à 5 minutes de retard. C'est le comportement demandé ; je le signale pour la recette.
- **Hors production** (mailer `log`), les liens de réinitialisation valides une heure sont écrits dans `storage/logs/laravel.log`. Ce n'est pas le mot de passe (VC8), mais il vaut mieux purger ces logs en préproduction s'ils contiennent de vrais comptes.

## 5. Tests ajoutés

`tests/Feature/Phase3/FixChecks/FixChecksTest.php`, 14 tests : 10 passent, 4 échouent.

- Passent : VC1, VC2, VC3, VC4, VC5, VC7, VC8, VC9, VC11a, VC13.
- Échouent (écarts ci-dessus) : VC6 (M2), VC10 (m2), VC11b (m1), VC12 (M1).

Commande : `cd NEW && flock storage/framework/pest.lock php8.5 vendor/bin/pest tests/Feature/Phase3/VerifCorrectifs`. Avec ces tests, `composer verify` échouera tant que les 4 écarts ne sont pas corrigés.
