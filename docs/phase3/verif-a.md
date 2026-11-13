# Vérification phase 3 : VerifA (public-api, auth-users-privileges, programmes-lots, public, évolutions lots 2, 3 et 4)

Date : 2026-11-12. Agent : VerifA. Aucun fichier de l'application modifié.

## 1. Verdict

**FAIL**, sans écart bloquant : 0 bloquant, 2 moyens, 5 mineurs. Le comportement visible et l'API publique sont conformes. Les écarts portent sur un défaut de service de fichiers, deux points de la checklist sécurité et des finitions.

## 2. Ce qui a été vérifié

- **Suites existantes du périmètre** : `PublicApi`, `Auth`, `ProgramsLots`, `Public`, `Evolutions/Lot2`, `Lot3`, `Lot4` : 184/184 au vert (`flock storage/framework/pest.lock php8.5 vendor/bin/pest tests/Feature/PublicApi tests/Feature/Auth tests/Feature/ProgramsLots tests/Feature/Public tests/Feature/Evolutions/Lot{2,3,4}*`). Les 38 critères de `public-api.md` §10, dont le rejeu des fixtures `tests/Fixtures/legacy-api/`, sont déjà couverts. Aucun écart d'API n'est apparu, donc aucun rejeu supplémentaire.
- **Matrice des droits** (`tests/Feature/Phase3/CheckA/AccessMatrixTest.php`, gardé tel quel, au vert, 1 400 assertions). Chaque route de l'application est classée public, commercial ou admin. Résultats : un invité est renvoyé vers `/connexion` en GET comme en POST, sans effet en base, sans mail ni job. Un commercial est renvoyé vers `/` sur chaque route admin, sans contenu ni effet. Admin et commercial ouvrent leurs routes sans 500. Les identifiants devinés (programme, lots, prospects, messages d'un autre prescripteur) ne donnent rien à lire et rien à modifier.
- **Balisage Blade comparé aux vues legacy** (comparaison normalisée balise par balise, script dans le scratchpad de la session). Vues comparées : `V_gestion_programme`, `V_ajouter_programme`, `V_modifier_programme`, `V_gestion_diaporama`, `V_programme`, `V_gerer_lots`, `V_modifier_lot`, `V_ajouter_appartement`, `V_ajouter_appartements`, `V_ajouter_plan`, `V_connexion`, `V_Gestion_privilege`, `V_Cgu` (identique), `V_Mentions` (constantes substituées, texte identique), `V_app`, `welcome_content`, `template/template.php`, `template/connexion.php`, `template/template_welcome.php`. Écarts constatés, tous voulus : chemins absolus au lieu de `SITE_URL`, bibliothèques CDN servies en local, `meta csrf-token` et `csrf.js`, commentaires HTML et blocs morts retirés, uploadify remplacé par `<input type="file">`, case « Terrain » envoyée en `terrasse` (contrat C16), entrée de menu « Messages automatiques » (lot 7b), bloc de description aéré (lot 7a). Les blocs réservés à l'admin de `V_programme.php` (`isAdmin`) sont aux mêmes endroits dans `programme.blade.php:107,219,314`.
- **Échappement** : aucun `{!! !!}` sur une donnée utilisateur dans le périmètre (`grep -rn '{!!'`). Les seuls cas sont des constantes, des messages passés par `htmlspecialchars` ou des attributs fixes. Les noms de fichiers envoyés sont échappés (`partials/fichiers.blade.php`, `ajouter_plan.blade.php:8`).
- **E-mails** : E1 (`UpdateUserPrivilege`) et E2 (`NewAccountSetting`) sont identiques au HTML de `OLD/application/controllers/json.php:1111-1123` et `:1456-1468` : sujet, expéditeur « Corim promotion », table de 500 px, pied « Le Cristal », partie texte en `strip_tags` (`resources/views/auth-users-privileges/mail/acces.blade.php`, `app/Mail/Auth/AccessMail.php`).
- **Uploads** : routes admin uniquement, chemins choisis par le serveur, liste blanche extension et MIME réel (`ProgramFiles::accepts`). Un vrai fichier PHP renommé en `.png`, `.jpg`, `.pdf` ou `.csv` est refusé sur les 8 routes d'upload, y compris `ajouter_plan/uploadify`, `ajouter_lots/uploadify` et le diaporama (`ContractTest.php`, test 1). Les envois légitimes passent sur ces mêmes routes (test 2). `/medias` ne sert ni `import.csv`, ni `.php`, ni `../` non encodé (test 3).
- **Évolutions** : import par en-tête et mise à jour par référence (`LotsController::importer`, `LotImport::header`), renommage des plans (`LotImport::lotForPlan`, `ProgramsLots/LotsController.php:274-289`), API prescripteur (clé hachée, révocation, cache 1 h vidé par `PrivilegeController.php:54` et `Promoteurs/JsonController.php:90`, limites par clé et par adresse, CSV neutralisé). Le code est relu et les tests `Evolutions` sont au vert.
- **Points déjà tranchés**, non remontés : compteurs du tableau de bord, `/administration/*` et `/rechercher*` en 404, mailer `log` hors production, SupprimerLot qui garde les `acces_lot` comme l'ancien code (test C12).

## 3. Écarts

### Bloquant

Aucun.

### Moyen

**M1. Documents Word et Excel acceptés à l'envoi mais jamais téléchargeables.**
- Où : `routes/modules/medias.php:12` (liste `pdf, jpg, jpeg, png, gif`) et `app/Http/Controllers/ProgramsLots/ProgramFiles.php:28` (`documents` accepte `doc, docx, xls, xlsx`).
- Attendu : un fichier déposé dans `documents/` s'affiche sur la fiche programme et s'ouvre, comme dans l'ancien code qui servait tout `medias/` (`OLD/application/controllers/modifier_programme.php:93`). Le contrat `programmes-lots.md` §9 (ligne 469) autorise `doc(x)/xls(x)` dans `documents`.
- Constaté : l'envoi réussit et le lien apparaît sur la fiche, mais `GET /medias/programmes/{key}/documents/x.docx` répond 404.
- Test : `tests/Feature/Phase3/CheckA/GapsTest.php` A1, 4 cas, échouent.
- Correction : ajouter `doc, docx, xls, xlsx` à la liste de la route `/medias`, servis en pièce jointe (`Content-Disposition: attachment`).

**M2. Pas d'en-tête Content-Security-Policy.**
- Où : `app/Http/Middleware/SecurityHeaders.php:13-20`.
- Attendu : la checklist sécurité de `CLAUDE.md` demande « security headers (CSP, HSTS, X-Frame-Options, Referrer-Policy) ».
- Constaté : X-Frame-Options, nosniff, Referrer-Policy et HSTS (en HTTPS) sont présents, mais aucune CSP.
- Test : `GapsTest.php` A2, échoue.
- Correction : poser au minimum `Content-Security-Policy: frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self'`. Cela ne casse ni les scripts en ligne ni CKEditor sur CDN. À durcir plus tard.

### Mineur

**m1. API publique sans cache serveur.**
- Où : `app/Http/Controllers/PublicApi/FirstsearchController.php:21-23` (aucun `Cache::`).
- Attendu : `CLAUDE.md` (« Secure instead with parameterised queries, rate limiting, cache ») et `public-api.md` ligne 69.
- Constaté : chaque appel `nextjscorim` relance la requête SQL. Seule la limite `throttle:600,1` protège.
- Test : `GapsTest.php` A3, échoue.
- Correction : `Cache::remember` de courte durée (60 s par exemple) sur le corps de la réponse, avec la query string brute comme clé. Les en-têtes restent inchangés.

**m2. `json/CheckIfExist` renvoie l'identifiant du compte.**
- Où : `app/Http/Controllers/Auth/PasswordController.php:32`.
- Attendu : critère C20 de `auth-users-privileges.md` (« l'id utilisateur n'est jamais exposé »).
- Constaté : la réponse contient `"id":"<id>"`.
- Test : `GapsTest.php` A4, échoue.
- Correction : renvoyer un `id` neutre (par exemple `"0"`) et faire lire à `NewAccountSetting` l'id gardé en session, sans comparer `idClient`. `global.js` continue de fonctionner.

**m3. Un anonyme qui connaît seulement l'e-mail réinitialise le mot de passe d'un compte.**
- Où : `PasswordController.php:77-90`.
- Attendu : critère C22 du contrat auth (lien signé, à usage unique, qui expire) et faille F4. Le lead a remplacé ce critère par un mot de passe généré côté serveur et envoyé par mail (`suivi.md`, Contestations), ce qui supprime la prise de contrôle du compte.
- Constaté : `CheckIfExist` puis `NewAccountSetting`, envoyés par un tiers, changent le mot de passe tout de suite. Le titulaire est bloqué jusqu'à ce qu'il lise le mail. Ce comportement existait déjà dans l'ancien code.
- Test : `GapsTest.php` A5, échoue.
- Correction : n'appliquer le nouveau mot de passe qu'après un clic sur un lien signé envoyé dans le même mail E2, sans changer les textes de la modale. Sinon, garder le comportement et retirer A5 si le lead confirme sa décision.

**m4. `/medias` répond 500 sur un `../` encodé.**
- Où : `routes/modules/medias.php:8`. `$disk->path()` lève `League\Flysystem\PathTraversalDetected`, qui n'est pas intercepté.
- Attendu : 404, comme pour les autres chemins invalides.
- Constaté : `GET /medias/programmes/{key}/plans/..%2F..%2F.env` répond 500. Aucune fuite (page générique), mais l'erreur part dans les logs à chaque tentative.
- Test : `GapsTest.php` A7, échoue.
- Correction : `abort_if(str_contains($path, '..'), 404)` avant `$disk->path()`.

**m5. Commentaires dans `public/.htaccess`.**
- Où : `public/.htaccess:8,12,16,21` (commentaires `#` du squelette Laravel).
- Attendu : la règle « aucun commentaire » de `CLAUDE.md` et de `conventions.md`.
- Test : `GapsTest.php` A6, échoue.
- Correction : supprimer les 4 lignes `#`.

## 4. Points non vérifiables en local

- **Réglages de prod** : `.env` doit avoir `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true` et `APP_URL=https://corim-vente.com`. Aujourd'hui `.env.example` et `.env` ont `SESSION_SECURE_COOKIE=false`, et le test Auth C27 ne passe que grâce à `phpunit.xml:32`. `APP_URL` sert aussi de base au logo et au lien des mails E1 et E2.
- **Vhost** : redirection http vers https, pas d'exécution PHP dans `storage/` ni dans `public/medias`, gzip déjà déclaré dans `public/.htaccess`.
- **Rendu visuel** à 1280 px et 375 px de chaque page du périmètre face à l'ancienne application. La comparaison faite ici porte sur le balisage, pas sur le rendu.
- **Mail E1** : sans `pwdshow`, la ligne « Mot de passe : » part toujours vide. C'était déjà le cas pour 83 des 99 comptes actifs. La question Q4 du contrat (lien « définir mon mot de passe ») reste à trancher avec la cliente.
- **API prescripteur** : la clé passe dans l'URL (`?cle=`), elle apparaîtra donc dans les journaux d'accès Apache. Choix assumé le 2026-11-03, à prendre en compte dans la rotation des logs.
