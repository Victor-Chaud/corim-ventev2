# Revue finale code et sécurité (phase 3)

Relecteur : agent de revue, 2026-11-12. Périmètre : `app/`, `routes/`, `config/`, `bootstrap/`, `resources/views/`, `public/` (hors bibliothèques tierces), `composer.json`. Lecture du code et commandes en lecture seule uniquement. Aucun fichier de l'application n'a été modifié.

## 1. Verdict

**FAIL** : 1 bloquant, 6 moyens, 13 mineurs.

Le bloquant est une XSS réfléchie sur l'API publique `search?action=email`. Elle se corrige par un en-tête, sans toucher au corps des réponses. Le reste du code est sain : les requêtes sont paramétrées, les contrôles d'accès sont présents et les uploads sont maîtrisés.

## 2. Ce qui a été vérifié

- **Routes et droits** (`php8.5 artisan route:list -v`, 126 routes).
  - Toutes les pages d'administration et les actions `json/*` d'administration sont sous `auth` + `admin` (`EnsureAdmin`).
  - Les routes sans `auth` sont celles attendues : `connexion`, la réinitialisation sous `throttle:20,1`, `app`, l'API publique et `api/v1/*` sous `throttle:600,1`, et `medias/*`.
- **IDOR**, filtres vérifiés un par un :
  - `programme/show` : `Access::canSeeProgramme` (`ProgrammeController.php:203`).
  - `json/JsonDatagrid` : filtre `acces_lot` (`ProgrammesLots/JsonController.php:128`).
  - `json/AddOption` et `json/AddReservation` : `canSeeLot` et cohérence lot/projet (`Workflow/JsonController.php:243,251`).
  - `json/AddProspect` : `canSeeProgramme` (`ProspectsController.php:84`).
  - `json/SearchProspect` : filtre `iduser` (`:49`).
  - Messagerie : `readable()` et `idreceveur` (`MessagerieController.php:21-29,98`).
  - Listes du workflow : filtre `$colonneUser` (`Workflow/PagesController.php:203-207`).
  - Tableau de bord : `$filter` (`Public/PagesController.php:20`).
  - `api/v1` : données limitées au détenteur de la clé (`PrescripteurApiController.php:96-97,138`).
  - Aucun IDOR trouvé.
- **CSRF**
  - Toutes les actions qui modifient des données exigent POST (`posted()`, `isMethod('post')` ou `post() === []`). Un GET ne fait donc rien. Le jeton CSRF de Laravel est actif sur le groupe web.
  - Seule l'API publique est sortie du CSRF et de la session (`routes/modules/public-api.php:14`).
- **Injection SQL** : 16 appels `*Raw` / `DB::raw` relus.
  - Ils n'interpolent que des constantes ou des noms de table internes.
  - `$sortorder` est limité à `asc`/`desc` (`ProgrammesLots/JsonController.php:123,133`).
  - `whereRaw` est construit avec des colonnes constantes et des bindings (`EmailingController.php:115-121`).
  - Aucune commande shell. Aucun chemin construit depuis une entrée sans `safeName`/`realpath`.
- **XSS côté serveur** : 50 `{!! !!}` relus. Ils ne sortent que des constantes, `htmlspecialchars`, `SafeHtml::clean`, des entiers ou des attributs fixes. Les messages saisis passent par `SafeHtml` (liste blanche DOM, schémas d'URL filtrés). Aucune sortie Blade dans un contexte JS ou `on*=` hors entiers. `eval()` dans `global.js` ne reçoit que la sortie de `json_encode`.
- **Uploads**
  - Tous sous `auth` + `admin` avec CSRF.
  - Nom de fichier filtré (`safeName`), extension et MIME (finfo) en liste blanche (`ProgrammeFiles.php:51-62`).
  - Fichiers stockés dans `storage/app/medias`, hors de `public/`. Aucun `.php` dans `public/` hors `index.php`.
  - La route `medias` résout le chemin avec `realpath` et vérifie le préfixe, avec une liste blanche d'extensions (`routes/modules/medias.php:7-12`). Pas de traversée possible.
- **Authentification**
  - Régénération de session à la connexion et invalidation à la déconnexion (`LoginController.php:64,71-73`).
  - Limite de 5 essais par identifiant et IP. Comptes désactivés déconnectés (`LogoutInactive`). Jeton « remember » désactivé.
  - Hash legacy vérifié avec `hash_equals` puis réhaché en bcrypt (`User.php:163-175`). `pwdshow` absent.
- **En-têtes** : `X-Frame-Options`, `nosniff`, `Referrer-Policy`, HSTS si HTTPS (`SecurityHeaders.php`).
- **Limites de débit**
  - `api/v1` : 60 appels par heure et par clé, clés stockées en sha256, révocables (`PrescripteurApiController.php:41-56`).
  - Le CSV de l'API neutralise les formules (`cellule()`).
- **Dépendances** : `php8.5 /usr/bin/composer audit` ne signale aucune vulnérabilité.
- **Configuration** : `config/app.php:9` met `debug` à false par défaut. `.env` est ignoré par git.
- **Performance de `/denonciations` (admin)**
  - Une seule requête avec jointures, pas de N+1 (`Workflow/PagesController.php:83-92,200-215`).
  - Le datepicker n'est initialisé qu'au focus (`public/medias/js/global.js:20`).

## 3. Écarts

### Bloquant

**B1. XSS réfléchie sur `/search?action=email` et `/index.php/search?action=email`**
- **Où** : `app/Http/Controllers/PublicApi/LegacyApiController.php:226-234`, `SearchController.php:38,51`, `app/Support/LegacyJson.php:11,17`, `routes/modules/public-api.php:14`.
- **Ce qui se passe** : le paramètre `url` est renvoyé tel quel dans `data`, avec le Content-Type `text/html`. `json_encode` n'échappe pas `<`. Il n'y a ni CSP ni `nosniff`, car `SecurityHeaders` est retiré sur ces routes. Aucune écriture en base n'est nécessaire : il suffit d'un e-mail invalide.
- **Preuve** : `GET /search?action=email&email=x&url=<img src=x onerror=alert(document.domain)>` renvoie `200 text/html` avec le corps `MyCallback3({"errors":"Votre adresse email n'est pas valide","data":"<img src=x onerror=alert(document.domain)>"});`. Requête rejouée sur le noyau HTTP de Laravel, sans écriture en base.
- **Impact** : le script s'exécute sur l'origine `corim-vente.com`. Il peut lire le jeton CSRF d'une page interne et appeler les actions d'administration (par exemple `json/AddPromoteur`) si un administrateur connecté clique sur le lien.
- **Côté `firstsearch`** : la même réflexion existe, mais elle est servie en `application/json`, donc elle n'est pas interprétée par un navigateur moderne.
- **Correction proposée** : ajouter `Content-Security-Policy: default-src 'none'; sandbox` sur toutes les réponses de l'API publique. Le corps reste octet pour octet identique et le JSONP via `<script src>` n'est pas concerné. Refuser en plus un `url` contenant `<` ou `>`.

### Moyens

**M1. La réinitialisation du mot de passe peut être déclenchée par n'importe qui**
- **Où** : `app/Http/Controllers/Auth/PasswordController.php:31,78-89`.
- **Ce qui se passe** : `CheckIfExist` place `reset_user_id` en session dès que l'e-mail existe. Il renvoie aussi l'`id` utilisateur, ce qui permet d'énumérer les comptes. `NewAccountSetting` change alors immédiatement le mot de passe du compte, administrateur compris, et l'envoie par mail. Un anonyme qui connaît un e-mail bloque donc l'accès du titulaire jusqu'à ce qu'il lise son courrier.
- **Ce qu'attendait le contrat** : F4 demandait un lien signé à usage unique et une réponse identique, que l'e-mail existe ou non.
- **Correction proposée** : passer par le broker `Password` de Laravel (lien signé, mot de passe changé seulement au clic) et répondre toujours le même message.

**M2. Pulvérisation de mots de passe non limitée**
- **Où** : `LoginController.php:41-45`.
- **Ce qui se passe** : la clé de limitation est `identifiant|IP`. Une même IP peut donc tester un mot de passe courant sur les 242 comptes sans aucune limite.
- **Correction proposée** : ajouter une seconde limite par IP seule, par exemple 20 POST par minute sur `connexion`.

**M3. Pas de CSP et bibliothèques front avec vulnérabilités connues**
- **Où** : `app/Http/Middleware/SecurityHeaders.php:15-21`, `resources/views/layouts/template.blade.php:18-25`.
- **Ce qui manque** : aucune `Content-Security-Policy`, alors que la checklist l'exige.
- **Bibliothèques concernées** :
  - jQuery 1.11.3 (CVE-2015-9251, CVE-2019-11358, CVE-2020-11022/11023) ;
  - jQuery UI 1.11.3 (CVE-2016-7103, CVE-2021-41182/41183/41184) ;
  - Bootstrap 3.3.6 (CVE-2018-14040/14042, CVE-2019-8331) ;
  - CKEditor 4.5.9 chargé depuis `cdn.ckeditor.com`, sans SRI ;
  - `composer audit` ne couvre pas ces fichiers.
- **Correction proposée** : ajouter une CSP (`script-src 'self' cdn.ckeditor.com 'unsafe-eval'` le temps que `global.js` utilise `eval`, et `frame-ancestors 'self'`) et un attribut `integrity` sur CKEditor. Prévoir la montée vers jQuery 1.12.4 + migrate et Bootstrap 3.4.1, sans changement visuel.

**M4. Hash legacy conservés en base**
- **Où** : `app/Models/User.php:158-170`.
- **Ce qui se passe** : les comptes qui ne se reconnectent pas gardent un sha256 rapide, avec un sel global unique. En cas de fuite de la base, ces mots de passe se cassent vite.
- **Correction proposée** : migration unique qui remplace chaque hash par `Hash::make(<hash legacy>)`. La vérification devient alors `Hash::check(User::legacyHash($pwd), …)`, puis réhachage normal.

**M5. Documents Office acceptés à l'envoi mais jamais servis**
- **Où** : `app/Http/Controllers/ProgrammesLots/ProgrammeFiles.php:29` contre `routes/modules/medias.php:12`.
- **Ce qui se passe** : l'envoi de documents accepte doc, docx, xls et xlsx, mais la route `medias` ne sert que pdf, jpg, jpeg, png et gif. Les liens affichés sur la fiche programme renvoient donc une 404. Aucun fichier de ce type n'existe aujourd'hui (341 pdf et 50 jpg dans `documents`).
- **Correction proposée** : aligner les deux listes. Soit retirer les formats Office à l'envoi, soit les servir en `Content-Disposition: attachment`.

**M6. RGPD : export et purge absents**
- **Ce qui existe** : la suppression d'un client (`ClientsController.php:92-100`), l'export CSV global des clients (`:102-123`) et la suppression d'une dénonciation (`Workflow/JsonController.php:185-193`).
- **Ce qui manque** :
  - aucun export par personne ;
  - aucune purge de l'acquéreur dans `d_options`, `options`, `reservations`, `r_*`, `prospects` et `mp` ;
  - aucune durée de conservation.
- **Correction proposée** : à décider avec le client, puisque c'est hors périmètre « à l'identique ». Ce point n'a pas été construit.

### Mineurs

1. **API publique sans cache**
   - Où : `FirstsearchController.php:50-57`, `SearchController.php`.
   - Constat : chaque appel `nextjscorim` relance la requête complète. CLAUDE.md demande un cache.
   - Correction proposée : `Cache::remember` d'environ 60 s, avec une clé sur la chaîne de requête.
2. **Écriture anonyme dans `docs`**
   - Où : `LegacyApiController.php:232`.
   - Constat : `action=email` insère dans `docs` sans authentification, jusqu'à 600 fois par minute et par IP.
   - Correction proposée : un limiteur dédié, par exemple 5 par minute, pour cette action.
3. **Injection de formules dans l'export des clients**
   - Où : `ClientsController.php:109-113`.
   - Constat : l'export CSV ne neutralise pas les formules (`=`, `+`, `-`, `@`), alors que `PrescripteurApiController::cellule()` existe déjà.
   - Correction proposée : réutiliser `cellule()`.
4. **Lot modifié enregistré sans encodage**
   - Où : `LotsController.php:172-189` (modification) contre `:434-443` (création).
   - Constat : la modification enregistre les valeurs brutes, alors que la création les encode. C'est le même comportement que le legacy `UpdateAppartement`. Du HTML saisi par un admin atteint alors la réponse `text/html` de `nextjscorim` et le `href` du plan dans `ProgrammesLots/JsonController.php:141-143`, où `numero` n'est pas échappé.
   - Correction proposée : encoder comme `insertLot` et passer l'URL du plan dans `e()`.
5. **Clés de message prévisibles**
   - Où : `Workflow/JsonController.php:270`, `ProspectsController.php:106`.
   - Constat : la clé `cle` vaut `sha1(sel.time().émetteur)`. Elle est prévisible et identique pour deux messages envoyés dans la même seconde. `MessagerieController::send()` utilise pourtant `random_bytes` (`:40`).
   - Correction proposée : passer par `MessagerieController::send()`.
6. **Déconnexion par GET**
   - Où : `routes/modules/auth-users-privileges.php:9-10`.
   - Constat : `connexion/deconnection` et `logout` répondent en GET, donc un site tiers peut déconnecter l'utilisateur. Ce sont les URL legacy.
   - Correction proposée : accepter tel quel, ou passer par un formulaire POST.
7. **Réglages de production à poser**
   - Où : `.env.example`.
   - Constat : `APP_DEBUG=true`, `SESSION_SECURE_COOKIE=false`, aucun proxy de confiance configuré (le HSTS dépend de `isSecure()`). Seules les pages d'erreur 404 et 503 sont personnalisées. 419, 429 et 500 gardent la page générique de Laravel, ce qui est acceptable.
   - Correction proposée : dans le `.env` de production, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, et `trustProxies` si un proxy termine le TLS.
8. **Mots de passe dans les logs hors production**
   - Où : `AppServiceProvider.php:38-40`, `PasswordController.php:87-96`, `Promoteurs/JsonController.php:55`.
   - Constat : hors production, les mails partent dans le log. Les mots de passe générés et les e-mails des clients se retrouvent en clair dans `storage/logs/laravel.log` sur la préproduction.
   - Correction proposée : mailer `array` ou piège SMTP en préproduction, ou purge des logs.
9. **Question secrète toujours exposée**
   - Où : `auth-users-privileges.php:14-15`, `PasswordController.php:36-70`.
   - Constat : `CheckQuestion` et `CheckAnswer` sont toujours en place, alors que F5 et Q2 sont encore ouverts. Ils permettent d'énumérer les e-mails, et la réponse est comparée en clair (toutes les réponses sont vides aujourd'hui).
   - Correction proposée : supprimer ces deux routes une fois Q2 tranché.
10. **Route `medias` coûteuse et documents publics**
    - Où : `routes/modules/medias.php:6`.
    - Constat : la route est dans le groupe web, donc chaque PDF ou image demandé par un site partenaire ouvre une session en base. Les documents et visuels des programmes restent publics via `md5(titre)`, une clé devinable, comme dans le legacy.
    - Correction proposée : `withoutMiddleware` session/cookies sur cette route. Envisager `auth` sur `documents`, puisque seuls les plans doivent rester publics.
11. **Clé API dans l'URL**
    - Où : `PrescripteurApiController.php:40`.
    - Constat : la clé est acceptée en `?cle=`, c'est le choix du plan d'action. Elle apparaît donc dans les logs d'accès d'Apache.
    - Correction proposée : masquer le paramètre `cle` dans le format de log du vhost.
12. **Qualité du code**
    - Code mort : `Access::programmeIds` et `Access::lotIds` (`app/Support/Access.php:20-28`).
    - Deux parseurs CSV quasi identiques : `LotsController::csvRows:316` et `ClientsController::rows:175`.
    - La recherche de vignette est répétée quatre fois : `PrivilegeController.php:197`, `PromoteursController.php:39`, `Workflow/PagesController.php:166-175`, `ProgrammeFiles::thumb`.
    - Environ 7 helpers `normalize()` privés identiques.
    - `User::acces()` refait une requête à chaque appel (`User.php:143-151`). Elle est appelée plusieurs fois par requête : middleware, composer de vue, contrôleur.
    - `public/.htaccess` contient des commentaires `#`, contraires à la règle « aucun commentaire ».
    - Correction proposée : supprimer le code mort, factoriser ces doublons, mémoriser `acces()` sur l'instance et retirer les commentaires.
13. **Vignette en majuscules introuvable**
    - Où : `ProgrammeFiles.php:103,111`, `ProgrammeController.php:133`.
    - Constat : `saveThumb` garde l'extension telle qu'envoyée (`thumb.JPG`), mais `modifier` ne cherche que `jpg`, `png` et `gif`. La vignette existante n'est alors pas affichée.
    - Correction proposée : enregistrer l'extension en minuscules.

## 4. Points non vérifiables en local

- En-têtes réels derrière Apache : HSTS, compression gzip, absence de `nosniff` sur le JSONP.
- Exécution PHP impossible dans `storage/` : la racine du vhost doit être `public/`.
- `.env` de production.
- SPF, DKIM et DMARC du SMTP.
- Présence du worker de file d'attente.
- Vérification visuelle qu'une CSP n'empêche ni CKEditor ni `global.js` de fonctionner (à faire en préproduction).
