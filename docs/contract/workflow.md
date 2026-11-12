# Contrat — workflow

Statut : brouillon (à valider par Victor)
Sources lues (toutes sous `OLD/`) :
- Contrôleurs : `application/controllers/optionner.php` (1-39), `reserver.php` (1-39), `options.php` (1-31), `reservations.php` (1-31), `ask_options.php` (1-18), `ask_reservations.php` (1-18), `denonciations.php` (1-20), `ask_denonciations.php` (1-18), `refused_ask.php` (1-20), `ventes.php` (1-18), `report.php` (1-70), `json.php` (1-47, 97-268, 314-1026, 1216-1300, 1519-1606), `__json.php` (1-48), `__main.php` (1-117), `programme.php` (16-44).
- Modèle : `application/models/model.php` (1-25, 78-101, 370-532, 570-576, 659-769, 824-852, 874-1263, 1387-1459, 1568-1597, 1922-1956, 2036-2064, 2201-2316).
- Vues : `application/views/V_optionner.php`, `V_reserver.php`, `V_options.php`, `V_reservations.php`, `V_ask_options.php`, `V_ask_reservations.php`, `V_Denonciations.php`, `V_ask_denonciations.php`, `V_refused_ask.php`, `V_ventes.php`, `V_Report.php` (intégralité), `V_programme.php` (86-389, 500-672), `template/module_dashboard.php` (1-93), `template/template.php` (11-121), `template/json.php`.
- JS / CSS : `medias/js/global.js` (1-108, 219-253, 400-1095, 1417-1510), `medias/css/global.css` (55-95).
- Config / framework : `application/config/config.php` (127-148, identifiants non recopiés), `application/config/database.php` (33, 45), `index.php` (34, 112, 124), `system/core/Kohana.php` (125-158, 854-981), `system/libraries/drivers/Database.php` (302-325), `system/libraries/drivers/Database/Mysqli.php` (140-165).
- Base locale (lecture seule) : `SHOW CREATE TABLE` de `options`, `d_options`, `reservations`, `d_reservations`, `ventes`, `prospects`, `d_prospects`, `r_prospects`, `appartements`, `mp` ; comptages par statut (aucune donnée personnelle recopiée).

## 1. Périmètre

Le module couvre tout le cycle commercial d'un lot (`appartements`) et des prospects :

- **Dénonciation** : un prescripteur déclare un prospect sur un programme (`d_prospects`). Corim (admin) la valide (copie dans `prospects`) ou la refuse (copie dans `r_prospects`). Les dénonciations validées peuvent être recherchées, re-datées et supprimées.
- **Demande d'option / de réservation** : depuis la fiche programme, un prescripteur (ou l'admin pour le compte d'un prescripteur) demande une option ou une réservation sur un lot libre (`d_options` / `d_reservations`). Le lot passe immédiatement à `opt` / `res`.
- **Validation / refus** des demandes par l'admin : validation → `options` / `reservations` (statut 1) ; refus → suppression de la demande et lot libéré.
- **Option validée** : passage en réservation, ou annulation (lot libéré).
- **Réservation validée** : passage à l'acte (lot `act`, ligne `ventes`), ou annulation (lot libéré).
- **Vente (lot acté)** : annulation (lot libéré, `ventes.statut = 0`).
- **Mise à jour de dates** par l'admin sur toutes les listes (datepicker).
- **Tableau de bord** (compteurs sur l'accueil) et **Rapport** (admin) : nombre et somme des prix des lots par statut et par programme.
- Chaque action admin envoie un email au prescripteur ; chaque demande envoie un email à Corim et un message interne (`mp`) à l'admin.

Aucune expiration automatique : aucune option ne périme (voir § 5.5).

## 2. Rôles et droits

Rôle stocké en session `SessionAcces` (positionné à la connexion, `application/controllers/connexion.php:29`, valeur issue de `privileges.acces`). En base locale : 1 seul utilisateur `acces = 1` (id 1), 264 utilisateurs `acces = 0`.

| Rôle (valeur en base) | Peut faire | Contrôle dans l'ancien code (fichier:ligne) |
|---|---|---|
| Non connecté | Pages : rien (redirection `connexion`). **Actions JSON : toutes** (aucun contrôle, voir § 9). | Pages : `__main.php:46-53` (`isConnected`, `exit`). JSON : `__json.php:8-15` (aucun appel à `isConnected`, qui de plus teste `VBSessionId` jamais positionnée, `__json.php:17-24`). |
| Prescripteur `privileges.acces = 0` | Voir ses propres demandes, options, réservations, ventes, dénonciations, refus ; créer des demandes d'option / réservation / dénonciation depuis une fiche programme. Ne voit pas les colonnes ACTIONS ni les datepickers. | Filtre par `iduser`/`vendeur` quand `$access==='0'` : `model.php:933, 954, 1104, 1121, 1139, 1158-1159, 1172-1173, 1193, 1204, 1214, 1224, 1241, 1251, 1392-1396` ; `refused_ask.php:11-15`. Masquage UI : `$this->isAdmin()` (`__main.php:55-61`) dans les vues. |
| Admin `privileges.acces = 1` | Tout voir ; valider / refuser les demandes ; passer en réservation / acter ; annuler option, réservation, vente ; supprimer une dénonciation ; modifier les dates ; choisir le prescripteur lors d'une demande ; consulter le Rapport. | Uniquement côté vue (`isAdmin()`), sauf `report.php:11-13` (redirection sans `exit`, voir § 9). Le choix du prescripteur est affiché si `isAdmin()` (`V_programme.php:153, 266, 362`) mais accepté de tout appelant (`json.php:903-912, 972-981, 1252-1261`). |

Les droits par programme / lot (`acces`, `acces_lot`) relèvent du module `programmes-lots` ; **aucune action du workflow ne les vérifie** (`json.php:889-1025`, `1240-1299`).

## 3. Écrans et URL

Toutes les pages : méthode GET, gabarit `template/template` (`__main.php:37-39`), `isConnected()` en tête (redirection `SITE_URL.'connexion'` + `exit`, `__main.php:50-51`). Titre de page = `'Corim promotion - '` + suffixe (`__main.php:43`). Les liens internes utilisent `SITE_URL.'<controleur>'` sans `index.php/` ; Kohana accepte aussi `index.php/<controleur>`.

### 3.1 `/ask_options` — Demandes d'options
- Contrôleur `ask_options.php:7-16`, modèle `AskOptions` (`model.php:1116-1133`), vue `V_ask_options.php`.
- Titre : `Les demandes d'options`. H1 : `Demandes d'options`.
- Tableau (`tablesorter-green`) : DATE, PROJET, LIEU, RÉFÉRENCE, NOM ACQUEREUR, ADRESSE ACQUÉREUR, PRESCRIPTEUR, MESSAGE, ACTIONS (admin) (`V_ask_options.php:11-19`). Tri `ORDER BY d_options.date DESC`.
- Admin : DATE = datepicker `id="datepicker_{iddlot}_askoption"` (`:31`) ; boutons `VALIDER` (`valider_ask_option`) et `REJETER` (`rejeter_ask_option`), `rel="{iddlot}-{iduser}"` (`:45`).
- Bouton `Message` si message non vide ; ligne masquée suivante contenant `html_entity_decode(message)` (`:25, 47-49`).
- Vide : `Aucune demande d'option en cours de validation` (`:56`).
- Boîtes modales cachées `#valid_ask_option_box` (« Valider la demande d'option », textarea `f_confirm_ask_option_sms`, libellé « Laisser un message au promoteur : ») et `#rejeter_ask_option_box` (« Refuser la demande d'option », `f_refuser_ask_option_sms`) (`:60-96`). Rendues pour tous les rôles (pas de garde `isAdmin`).

### 3.2 `/ask_reservations` — Demandes de réservations
- `ask_reservations.php:7-16`, `AskResas` (`model.php:1134-1151`), `V_ask_reservations.php`.
- Titre `Les demandes de réservations`, H1 `Demandes de réservations`. Colonnes identiques à 3.1. Datepicker `datepicker_{iddlot}_askresa` (`:31`). Boutons `VALIDER` (`valider_ask_resa`) / `REJETER` (`rejeter_ask_resa`) (`:45`).
- Vide : `Aucune demande de réservation en cours de validation` (`:56`).
- Modales (admin seulement, `:59-100`) : « Valider la demande de réservation » (`f_confirm_ask_resa_sms`), « Refuser la demande de réservation » (`f_refuser_ask_resa_sms`), libellé « Laisser un message au prescripteur : ».

### 3.3 `/ask_denonciations` — Demandes de dénonciations
- `ask_denonciations.php:7-16`, `AskDenonciations` (`model.php:1099-1114`), `V_ask_denonciations.php`.
- Titre `Les demandes de dénonciations`, H1 `Demandes de dénonciations`. Colonnes : DATE, PROJET, LIEU, ACQUÉREUR, ADRESSE (`cpprospect villeprospect`), PRESCRIPTEUR, MESSAGE, ACTIONS (`:11-18, 43`).
- Datepicker `datepicker_{idprospect}_askden` (`:33`). Boutons `valider_ask_denonciations` / `rejeter_ask_denonciations`, `rel="{idprospect}-{iduser}"` (`:46`).
- Vide : `Aucune demande de dénonciation en cours de validation` (`:57`).
- Modales (tous rôles) « Valider la demande de dénonciation » / « Refuser la demande de dénonciation », libellé « Laisser un message au promoteur » (`:61-91`).

### 3.4 `/options` et `/options/refused`
- `options.php:7-17` (statut 1, titre `Options acceptées`) et `:19-29` (statut 0, titre `Options refusées`) ; `Options($user,$acces,$statut)` (`model.php:948-967`) ; `V_options.php`.
- H1 : `Options acceptées` ou `Options annulées` (`V_options.php:3`) — le titre de page dit « refusées », le H1 dit « annulées » (à reproduire tel quel).
- Bouton de bascule : `Options annulées` (→ `options/refused`) ou `Options acceptées` (→ `options`) (`:5-11`).
- Colonnes identiques à 3.1. Datepicker `datepicker_{idlot}_option` (`:39`). Actions admin : `RÉSERVATION` (`passer_en_resa`) et `ANNULER` (`annuler_option`), `rel="{idlot}-{iduser}"` (`:53`). Ces actions s'affichent aussi sur la liste des annulées (voir § 9).
- Aucun message si la liste est vide (`:62`).
- Modales : « Passer l'option en réservation » (`f_passer_en_resa_sms`), « Annuler l'option » (`f_annuler_option_sms`), libellé « Laisser un message au prescripteur » (`:64-95`).

### 3.5 `/reservations` et `/reservations/refused`
- `reservations.php:7-29` (titres `Réservations acceptées` / `Réservations annulées`) ; `Resas()` (`model.php:927-946`) ; `V_reservations.php`.
- H1 `Réservations acceptées|annulées` (`:3`) ; bascule (`:5-11`). Datepicker `datepicker_{idlot}_resa` (`:39`).
- Actions admin : `ACTER` (`passer_en_act`, `rel="{idlot}-resa"`) et `ANNULER` (`annuler_resa`, `rel="{idlot}-{iduser}"`) (`:53`), aussi sur la liste des annulées.
- Modales « Acter le dossier » (`f_passer_en_act_sms`, cachés `passer_en_act_type`, `passer_en_act_lot`) et « Annuler la réservation » (`f_annuler_resa_sms`) (`:64-101`).

### 3.6 `/ventes` — Lots actés
- `ventes.php:7-16`, `Ventes()` (`model.php:1167-1186`), `V_ventes.php`. Titre `Ventes`, H1 `Lots actés`.
- Colonnes : DATE (`ventes.date`), RÉSERVATION (`ventes.date_resa`), PROJET, RÉFÉRENCE, Type, Catégorie, Prix, Client, ACTIONS (`:11-20`). Tri `ORDER BY ventes.date` (ascendant).
- Admin : deux datepickers `datepicker_{appartements.id}_vente` et `_venteresa` (`:31-38`) ; bouton `ANNULER LA VENTE` (`annuler_vente`, `rel="{id}-{vendeur}"`) (`:50`).
- Les `<td>` des colonnes 3 à 8 ne sont pas fermés (`:44-49`, à reproduire ou corriger — markup identique attendu).
- Rien si vide. Modale admin « Annuler la vente » (`f_annuler_la_vente_sms`, cachés `f_annuler_la_vente_idlot`, `f_annuler_la_vente_vendeur`) (`:59-82`).

### 3.7 `/denonciations` — Dénonciations validées
- `denonciations.php:7-18`, `SearchAllProspect()` (`model.php:1387-1405`), `V_Denonciations.php`. Titre `Dénonciations`.
- Lien `Dénonciations refusées` → `refused_ask` (`:5`). Formulaire de recherche `f_recherche_denonciation` : radios `NOM ACQUÉREUR` (1), `RÉSIDENCE` (2), `PRESCRIPTEUR` (3, admin seulement) ; champ `f_recherche_denonciation_search` ; bouton `Rechercher` ; lien `RESET` (`:8-36`). Toutes les radios ont `checked`, donc la dernière affichée est sélectionnée.
- Tableau `#f_recherche_denonciation_result` : Date, Nom, Code Postal, Ville, Programme, Prescripteurs, Action (`:41-47`). Admin : datepicker `datepicker_{idprospect}_den` (`:66`) et bouton `Supprimer` (`delete_denonciation`, `rel="{idprospect}"`) (`:84`). Bouton `Message` (`see_sms2`) et ligne `#see_sms_{idprospect}` (`:56, 88-91`).
- Menu principal : lien `Dénonciations` pour tous (`template.php:110`).

### 3.8 `/refused_ask` — Dénonciations refusées
- `refused_ask.php:7-19` : admin → `GetRefusedAsk()` (`model.php:874-882`), sinon `GetRefusedAskByIdUser(SessionId)` (`:884-896`). Vue `V_refused_ask.php`. Titre `Dénonciations refusées`.
- Lien `Dénonciations` (`:6`) ; colonnes Date, Nom, CP, Ville, Programme, Prescripteur, (message) (`:13-19`). Aucune action.

### 3.9 `/report` — Rapport (admin)
- `report.php:7-68`. Pour chaque programme de `GetProgrammes()` (module programmes-lots) : nombre et somme des prix par statut `''`, `opt`, `res`, `act`, total (`model.php:659-727`, prix = `SUM(REPLACE(prix,' ',''))`).
- Vue `V_Report.php` : H1 `Report` + lien `IMPRIMER` (`javascript:window.print()`) ; vignette `medias/programmes/{key}/thumb/thumb.{jpg|JPG|png|gif}` sinon `Aucune image` ; tableau 3 lignes : `N LOT(S) / LIBRE(S) / OPTION(S) / RÉSA(S) / ACTE(S)`, sommes `number_format(x,2,',',' ')€`, pourcentages `round(x/sum*100,2)%` (`:59-80`).
- Lien `RAPPORT` dans le tableau de bord pour l'admin (`module_dashboard.php:89-93`).

### 3.10 Tableau de bord (accueil `/` ou `/welcome`)
- `__main.php:27-36` : compteurs `CountVentes`, `CountAskOptions`, `CountOptions`, `CountAskResas`, `CountResas`, `CountAskDenonciations`, `CountDenonciations` (`model.php:1153-1262`).
- Vue `template/module_dashboard.php:60-94` : libellés `LOTS ACTÉ`, `DEMANDES D'OPTIONS`, `OPTIONS`, `DEMANDES DE RÉSERVATIONS`, `RÉSERVATIONS`, `DEMANDES DE DÉNONCIATIONS`, `DÉNONCIATIONS`, badge = compteur ; lien actif seulement si compteur > 0 (sinon `javascript:void(0)`) ; classe `list-group-item-warning` + icône `glyphicon-exclamation-sign` sur les demandes en attente.

### 3.11 Fiche programme (déclencheur des demandes, vue du module programmes-lots)
- `programme.php:16-44`, vue `V_programme.php`. Admin : liste des prescripteurs `getPrescripteurs()` (`model.php:1568-1576`).
- Bouton `Dénoncer un prospect` → modale `#denonciations` (`V_programme.php:48, 86-175`) : civilité (radios `Mme & M.` par défaut, `Mme`, `M`, `Melle`), `Nom du prospect *`, `Code postal du prospect *`, `Prénom du prospect *`, `Ville du prospect *`, `Choix du prescripteur` (admin), `Message au promoteur`, caché `f_new_prospect_projet`.
- Grille flexigrid : boutons `Optionner` / `Réserver` (`:537-539`) actifs seulement si la ligne sélectionnée n'a pas la classe `opt|res|act` (`:605-622`). Modales `#optionner` « Optionner un lot » et `#reserver` « Réserver un lot » (`:199-389`) : civilité, `Nom de l'acquéreur *` (`name`), `Code postal de l'acquéreur *` (`cp`), `Prénom de l'acquéreur *` (`prenom`), `Ville de l'acquéreur *` (`adr`), `Choix du prescripteur` (admin), `Message au promoteur` (`message`), cachés `id_option`|`id_reserver`, `id_projet`.
- Couleurs de lignes : `opt` `#ADC42B`, `res` `#B70B61`, `act` `#E01C18` (`:597-603`) ; compteurs `LOTS ACTÉS`, `RÉSERVATIONS`, `OPTIONS` (`:496-498`).

### 3.12 Anciennes pages `/optionner` et `/reserver` (code mort)
- `optionner.php:7-38`, `reserver.php:7-38`, vues `V_optionner.php`, `V_reserver.php`. URL : `optionner`, `optionner/demande/{id}`, `optionner/validation` (POST `name`, `adr`, `message`, `id`) ; idem `reserver`.
- `validation` appelle `AddOption` avec 5 arguments au lieu de 6 (`optionner.php:29` vs `model.php:2201`) et `AddReservation` avec 4 (`reserver.php:29` vs `model.php:2215`) : avertissement PHP « Missing argument » converti en page d'erreur Kohana (`Kohana.php:152`, `index.php:112`) avant toute écriture. Plus aucun lien vers ces pages (seul un bloc commenté `V_programme.php:391-489`). Messages prévus : `Votre demande d'option a bien été prise en compte`, `Votre demande de réservation a bien été prise en compte`.
- Décision proposée : ne pas porter (404), voir § 11.

## 4. Actions AJAX / JSON

Commun à toutes les actions :
- URL appelée par le JS : `/index.php/json/<Action>/` en POST `application/x-www-form-urlencoded` (`global.js:1` `SITE="/"`). Kohana route aussi `/json/<Action>`. N'importe quelle méthode HTTP est routée ; **sans corps POST, la réponse est vide** (test `if ($_POST)`).
- Réponse : `json_encode($callback)` brut, sans saut de ligne (`json.php:1597-1605`, `template/json.php` n'ajoute rien), `Content-Type: text/html; charset=UTF-8` (`Kohana.php:158`). Si `?callback=X` est présent en GET : `X({...});` (JSONP non filtré, voir § 9).
- Convention inversée : **`errors: 1` = succès**, `0` = échec / champs manquants, `2` et `3` selon l'action.
- `$callback` non défini sur certaines branches → notice PHP → page d'erreur Kohana HTTP 500 (`index.php:112`, `Kohana.php:152`) ; indiqué « 500 » ci-dessous.
- Le JS évalue la réponse via `eval('(' + callback + ')')` et affiche un message dans `.call` ou `#call` ; à la fermeture de la modale, redirection vers la liste.
- Aucune authentification, aucun CSRF, aucun contrôle de rôle (`__json.php:8-15`).

| # | Action (json.php) | Paramètres POST | Réponses | Effets en base (model.php) | Message JS (global.js) |
|---|---|---|---|---|---|
| A1 | `AddOption` (889-956) | `civilite`, `name`, `prenom`, `adr` (ville), `cp`, `message`, `id_option` (id lot), `id_projet`, `prescripteur_select` (facultatif ; `0` ou vide = soi) | `{"errors":0}` si `name`¹, `prenom`, `adr`, `message`, `id_option` ou `id_projet` vide ; `{"errors":2}` si lot introuvable ou `statut` non vide/non NULL ; `{"errors":1}` si insert OK ; 500 si insert ≠ 1 | `GetAppartement((int)id)` (2284-2294) ; `INSERT d_options (nom="{civilite} {name} {prenom}", iduser=sender, adresse="{adr} {cp}", message, iddlot, idprojet, date=now)` (2201-2213) ; `INSERT mp` (sender → 1, sujet « Vous avez une demande d'option en attente », message) (2048-2063) ; email E1 ; `UPDATE appartements SET statut='opt'` (2301-2307) | 1 : `La demande d'option a été enregistrée, elle doit être validée par l'administrateur ` (espace final) ; 0 : `Tous les champs sont obligatoires` ; 2 : `Lot non disponible` (1035-1054) |
| A2 | `AddReservation` (958-1025) | idem avec `id_reserver` | idem | idem sur `d_reservations` (2215-2226), mp « Vous avez une demande de réservation en attente », email E2, `statut='res'` | 1 : `La demande de réservation a été enregistrée, elle doit être validée par l'administrateur ` ; 0 / 2 idem (1056-1075) |
| A3 | `AddProspect` (1240-1299) — dénonciation | `f_new_prospect_civilite`, `f_new_prospect_name`, `f_new_prospect_prenom`, `f_new_prospect_cp`, `f_new_prospect_ville`, `f_new_prospect_sms`, `f_new_prospect_projet`, `prescripteur_select` | 0 si nom, prénom, cp, ville ou projet vide ; 3 si sender vide/0 (pas de session et pas de prescripteur) ; 1 si insert OK ; 2 sinon | `INSERT d_prospects VALUES (NULL,'{civilite} {name prenom}','cp','ville','sms','projet','iduser','now')` (1443-1458, SQL concaténé) ; `INSERT mp` sujet « Vous avez une dénonciation en attente » ; email E3 (envoyé **avant** le test du résultat) | 1 : `Le prospect a bien été enregistré` ; 0 : `Les champs marqués d'une astérisque doivent être renseignés` ; 3 : `Votre session a expirée. Merci de vous reconnecter.` (1490-1509) |
| A4 | `AcceptAddOption` (781-838) | `f_confirm_ask_option_lot` (iddlot), `f_confirm_ask_option_user`, `f_confirm_ask_option_sms` | 1 si l'insert copie exactement 1 ligne et le `DELETE d_options` supprime exactement 1 ligne ; 0 sinon (y compris paramètre vide) | `MoveAskOptions` (1074-1097) : `INSERT INTO options (...) SELECT ... FROM d_options JOIN projet WHERE iduser AND iddlot` (statut par défaut 1) ; `UPDATE options SET message=CONCAT(message,'<div class="separator"></div>{sms}')` ; `DELETE d_options` ; lot inchangé (`opt`) ; email E4 | `La validation de l'option a bien été enregistrée` (dans `#call`) / `Une erreur interne est survenue. Veuillez réessayer SVP` (941-958) |
| A5 | `RejetAddOption` (723-779) | `f_refuser_ask_option_lot`, `_user`, `_sms` | 1 si update lot = 1 ; 0 sinon | `RejetAskOptions` (1056-1072) : `DELETE d_options WHERE iduser AND iddlot` puis `UPDATE appartements SET statut=''` ; sms non stocké ; email E5 | `La demande d'option a bien été refusée` (888-905) |
| A6 | `AcceptAddResa` (491-547) | `f_confirm_ask_resa_lot`, `_user`, `_sms` | 1 / 0 ; **3** si lot ou user vide | `MoveAskResa` (1018-1054) : `INSERT reservations SELECT ... FROM d_reservations` (statut défaut 1), concat message (succès requis = 1), `DELETE d_reservations` ; lot inchangé (`res`) ; email E6 | `La validation de la réservation a bien été enregistrée` (560-577) |
| A7 | `RejetAddResa` (373-429) | `f_refuser_ask_resa_lot`, `_user`, `_sms` | 1 / 0 | `RejetAskResa` (969-984) : `DELETE d_reservations`, `statut=''` ; email E7 | `La demande de réservation a bien été refusée` (507-524) |
| A8 | `AcceptAddDenonciation` (431-488) | `f_confirm_ask_denonciation_lot` (idprospect), `_user`, `_sms` | 1 / 0 ; **3** si param vide | `MoveAskDenonciation` (986-1016) : `INSERT prospects SELECT ... FROM d_prospects JOIN projet` (**nouvel** idprospect), concat `smspromoteur` (succès requis = 1), `DELETE d_prospects` ; email E8 | `La validation de la dénonciation a bien été enregistrée` (579-596) |
| A9 | `RejetAddDenonciation` (314-371) | `f_refuser_ask_denonciation_lot` (idprospect), `_user`, `_sms` | 1 / 0 | `RejetAskDenonciation` (900-925) : `REPLACE INTO r_prospects SELECT ... FROM d_prospects WHERE idprospect` (même id) ; **si affected rows == 2 seulement** : concat sms dans `r_prospects`, `DELETE d_prospects` (voir § 9) ; email E9 | `La demande de dénonciation a bien été refusée` (597-614) |
| A10 | `PasserEnResa` (549-605) | `f_passer_en_resa_lot` (idlot), `_user`, `_sms` | 1 / 0 | `PasserEnResa` (476-499) : `INSERT reservations SELECT ... FROM options WHERE iduser AND idlot AND statut=1` (copie la date de l'option) ; `DELETE options` (même filtre) ; `UPDATE appartements SET statut='res'`. Le sms est préparé mais **jamais écrit** (482). Email E10 | `L'option a bien été passée en réservation` (778-795) |
| A11 | `AnnulerOption` (607-663) | `f_annuler_option_lot`, `_user`, `_sms` | 1 / 0 | `AnnulerOption` (501-515) : `UPDATE options SET statut=0 WHERE idlot` (**tous** les prescripteurs) puis `statut=''` ; sms non stocké ; email E11 au `user` posté | `L'option a bien été annulée` (835-852) |
| A12 | `AnnulerResa` (665-721) | `f_annuler_resa_lot`, `_user`, `_sms` | 1 / 0 | `AnnulerResa` (517-531) : `UPDATE reservations SET statut=0 WHERE idlot`, `statut=''` ; email E12 | `La réservation a bien été annulée` (759-776) |
| A13 | `PasserEnActer` (97-175) | `passer_en_act_type` (`resa` depuis l'UI ; `option` accepté), `passer_en_act_lot`, `f_passer_en_act_sms` | 1 si les 3 écritures = 1 ; 0 sinon ; 500 si type ≠ option/resa (table non définie, 755-767) | `GetActClient` (755-768) : `SELECT iduser,nom,adresse,date FROM {table} WHERE idlot AND statut=1` (exactement 1 ligne requise) ; `UPDATE appartements SET statut='act', client=nom, vendeur=iduser` (744-753) ; `DELETE FROM {table} WHERE idlot AND statut=1` (729-742) ; `INSERT ventes VALUES (NULL, iduser, idlot, now, 1, date_resa=date de la résa)` (463-474) ; email E13 au prescripteur | `Le dossier a bien été acté` (454-470) |
| A14 | `Acter` (239-267) | `type`, `idlot` | Toujours 500 : `updateActStatut` appelé avec 2 arguments sur 3 (250 vs 744) | Aucun (crash avant écriture). Appelé par `.push-act` (`global.js:219-253`), classe absente de toutes les vues | `Le dossier a bien été acté` (jamais atteint) |
| A15 | `AnnulerLaVente` (177-237) | `f_annuler_la_vente_idlot`, `f_annuler_la_vente_vendeur`, `f_annuler_la_vente_sms` | 1 ; 0 si param vide ou update lot ≠ 1 ; 500 si update ventes ≠ 1 | `AnnulerVente` (452-461) : `UPDATE appartements SET statut='', client='', vendeur=0 WHERE id AND vendeur` ; `DeleteVente` (441-450) : `UPDATE ventes SET statut=0 WHERE idlot AND iduser` ; email E14 | `La vente a bien été annulée` (400-416) |
| A16 | `DeleteDenonciation` (14-28) | `denonciation` (idprospect) | 1 si ≥ 1 ligne supprimée ; 0 sinon | `DELETE FROM prospects WHERE idprospect` (432-439). Pas d'email | Confirmation « Supprimer une dénonciation » / « Vous êtes sur le point de supprimer cette dénonciation.<br />Continuez ? » ; 1 : alert `Suppression effectuée` + reload ; 0 : alert `bad request` (75-107) |
| A17 | `UpdateDate` (30-46) | `idlot` (id selon type), `type`, `time` (`yyyy-mm-dd 08:00:00`) | 1 si exactement 1 ligne modifiée ; 0 sinon (y compris date identique) ; 500 si type inconnu (`$sql` non défini) | `UpdateDate` (370-403) : `askoption` → `d_options.date WHERE iddlot` ; `askresa` → `d_reservations` ; `askden` → `d_prospects WHERE idprospect` ; `option` → `options WHERE idlot AND statut=1` ; `resa` → `reservations ... statut=1` ; `den` → `prospects` ; `vente` → `ventes.date WHERE idlot` ; `venteresa` → `ventes.date_resa WHERE idlot` | 1 : alert `La date a été modifiée` + reload ; sinon rien (20-44) |
| A18 | `SearchProspect` (1216-1238) | `f_recherche_denonciation_search`, `f_recherche_denonciation_type` (1 nom, 2 programme, 3 prescripteur) | `{"errors":0}` si recherche vide ; `{"errors":1,"call":[...lignes prospects.*, date "%d.%m.%Y %H:%i", projet_titre, prescripteur]}` ; `{"errors":2}` si aucun résultat ; 500 si type hors 1-3 | `SELECT` LIKE `'%{search}%'` sur `prospects` (1407-1440), filtré par `iduser` si non admin | 0 : `Veuillez saisir un mot clé pour effectuer une recherche` ; 2 : `La recherche n'a retourné aucun résultat` (1417-1470) |

¹ `name` vaut toujours au moins `"{civilite} "` (`json.php:894, 963`), donc le test « nom vide » ne se déclenche jamais ; `cp` n'est pas testé.

Confirmations jQuery.confirm avant ouverture des modales (titre / message, boutons `Oui` / `Non`) : `Confirmation de validation` / `Vous êtes sur le point de valider la demande.<br />Continuez ?` (valider option, résa, dénonciation : `global.js:615-621, 689-695, 959-965`) ; `Rejeter l'option`, `Rejeter la réservation`, `Rejeter la dénonciation` / `Vous êtes sur le point de refuser la demande.<br />Continuez ?` (`:906-912, 525-531, 650-658`) ; `Passer l'option en réservation` / `Vous êtes sur le point de passer l'option en réservation.<br />Continuez ?` (`:804-806`) ; `Annuler l'option` / `Vous êtes sur le point d'annuler l'option.<br />Continuez ?` (`:857-859`) ; `Annuler la réservation` / `Vous êtes sur le point d'annuler la réservation.<br />Continuez ?` (`:728-730`) ; `Acter le dossier` / `Vous êtes sur le point d'acter le dossier.<br />Continuez ?` (`:476-478`) ; `Annuler la vente` / `Vous êtes sur le point d'annuler la vente.<br />Continuez ?` (`:422-424`). Erreur générique pour toutes : `Une erreur interne est survenue. Veuillez réessayer SVP`. Redirections à la fermeture : `ask_options`, `ask_reservations`, `ask_denonciations`, `options`, `reservations`, `ventes` (`global.js:439, 493, 546, 636, 673, 710, 745, 821, 874, 927, 980`).

## 5. Données

### 5.1 Tables (schéma local, toutes MyISAM latin1)

| Table | Colonnes utiles | Rôle | Volumétrie locale |
|---|---|---|---|
| `appartements` | `id`, `lot` (varchar = `projet_id`), `prix` (varchar avec espaces), `numero`, `statut` varchar(100), `client` varchar(100), `vendeur` int NULL | Lot et son état commercial | `''` 149, `opt` 6, `res` 55, `act` 1018. `vendeur` NULL sauf `act` |
| `d_options` | `iddoption`, `iduser` smallint défaut 1, `nom` varchar(100), `adresse` varchar(150), `message`, `iddlot` **UNIQUE**, `idprojet`, `date` | Demandes d'option en attente | 0 |
| `options` | `idoption`, `iduser`, `nom`, `adresse`, `message`, `idlot`, `idprojet`, `date`, `statut` int défaut 1 | Options validées (1) / annulées (0) | 1 : 40, 0 : 1716 |
| `d_reservations` | idem `d_options` (`iddresa`, `adresse` varchar(100), `iddlot` UNIQUE) | Demandes de réservation | 0 |
| `reservations` | idem `options` (`idresa`) | Réservations validées / annulées | 1 : 71, 0 : 532 |
| `ventes` | `idvente`, `iduser`, `idlot`, `date` (acte), `statut` défaut 1, `date_resa` | Ventes actées (1) / annulées (0) | 1 : 1014, 0 : 4 |
| `d_prospects` | `idprospect`, `nomprospect`, `cpprospect` varchar(6), `villeprospect`, `smspromoteur`, `idprojet`, `iduser` défaut 1, `date` | Dénonciations en attente | 1 |
| `prospects` | idem | Dénonciations validées | 6668 |
| `r_prospects` | idem (`idprospect` = id d'origine) | Dénonciations refusées | 1949 |
| `mp` | `idemetteur`, `idreceveur`, `sujet`, `message`, `date`, `lu`, `cle`, `parent` | Message interne à l'admin (id 1) à chaque demande | (module communication) |

Lectures croisées : `projet` (`projet_titre`, `projet_lieu`, `projet_id`, `projet_titre_key`), `user` (`name`, `email`) via `getEmailUser` (`model.php:78-83`) et `SelectAllInLot` (`model.php:570-575`, `appartements.* + projet.*`).

### 5.2 Machine à états du lot (`appartements.statut`)

L'état réel d'un lot se lit sur deux tables : `statut` ne distingue pas « demande en attente » de « validée ». États logiques : **L** libre (`''`, NULL toléré par `json.php:922`), **Od** demande d'option (`opt` + ligne `d_options`), **Ov** option validée (`opt` + `options.statut=1`), **Rd** demande de réservation (`res` + `d_reservations`), **Rv** réservation validée (`res` + `reservations.statut=1`), **A** acté (`act` + `ventes.statut=1`).

| De | Vers | Action (json.php) | Rôle autorisé (UI / serveur) | Effets en base | Email |
|---|---|---|---|---|---|
| L | Od | `AddOption` A1 | Prescripteur ou admin (pour un prescripteur) / **aucun contrôle** | `INSERT d_options`, `INSERT mp`, `statut='opt'` | E1 → Corim |
| L | Rd | `AddReservation` A2 | idem | `INSERT d_reservations`, `INSERT mp`, `statut='res'` | E2 → Corim |
| Od | Ov | `AcceptAddOption` A4 | Admin / aucun | `INSERT options (statut 1)`, concat sms, `DELETE d_options` ; statut inchangé | E4 → prescripteur |
| Od | L | `RejetAddOption` A5 | Admin / aucun | `DELETE d_options`, `statut=''` | E5 |
| Rd | Rv | `AcceptAddResa` A6 | Admin / aucun | `INSERT reservations (statut 1)`, concat sms, `DELETE d_reservations` | E6 |
| Rd | L | `RejetAddResa` A7 | Admin / aucun | `DELETE d_reservations`, `statut=''` | E7 |
| Ov | Rv | `PasserEnResa` A10 | Admin / aucun | `INSERT reservations` (copie, date de l'option), `DELETE options statut=1`, `statut='res'` | E10 |
| Ov | L | `AnnulerOption` A11 | Admin / aucun | `options.statut=0` (toutes lignes du lot), `statut=''` | E11 |
| Rv | L | `AnnulerResa` A12 | Admin / aucun | `reservations.statut=0` (toutes lignes du lot), `statut=''` | E12 |
| Rv | A | `PasserEnActer` A13 type `resa` | Admin / aucun | `statut='act'`, `client`, `vendeur` ; `DELETE reservations statut=1` ; `INSERT ventes` | E13 |
| Ov | A | `PasserEnActer` A13 type `option` | Aucune UI / aucun | idem sur `options` | E13 |
| A | L | `AnnulerLaVente` A15 | Admin / aucun | `statut=''`, `client=''`, `vendeur=0` ; `ventes.statut=0` | E14 |
| A | A | `Acter` A14 | Aucune UI | Aucun (crash) | — |
| tout | tout | Modification manuelle / import (module programmes-lots) | — | création : `statut=''`, `client=''`, `vendeur=NULL` (`model.php:2249-2251`) | — |

Aucune transition Od→Rd, Ov→Ov, Rv→Ov, ni retour arrière d'une annulation. Les annulations et refus ne vérifient pas l'état courant du lot.

### 5.3 Machine à états des demandes

| Objet | État (valeur réelle) | Transition | Action | Effet | Email |
|---|---|---|---|---|---|
| Demande d'option | ligne `d_options` | → option validée | A4 | ligne déplacée dans `options` (statut 1, nouvel `idoption`) | E4 |
| | ligne `d_options` | → supprimée (aucune trace hors `mp`/email) | A5 | `DELETE` | E5 |
| Option | `options.statut = 1` | → `0` « annulée » | A11 | update | E11 |
| | `options.statut = 1` | → supprimée (passée en résa) | A10 | copie dans `reservations` puis `DELETE` | E10 |
| | `options.statut = 1` | → supprimée (actée) | A13 `option` | `DELETE` + `ventes` | E13 |
| Demande de résa | ligne `d_reservations` | → réservation validée | A6 | déplacée (statut 1) | E6 |
| | ligne `d_reservations` | → supprimée | A7 | `DELETE` | E7 |
| Réservation | `reservations.statut = 1` | → `0` « annulée » | A12 | update | E12 |
| | `reservations.statut = 1` | → supprimée (actée) | A13 | `DELETE` + `INSERT ventes` | E13 |
| Vente | `ventes.statut = 1` | → `0` | A15 | update | E14 |
| Demande de dénonciation | ligne `d_prospects` | → `prospects` (nouvel id) | A8 | `INSERT ... SELECT`, concat, `DELETE` | E8 |
| | ligne `d_prospects` | → `r_prospects` (même id) | A9 | `REPLACE ... SELECT`, concat, `DELETE` (si affected = 2) | E9 |
| Dénonciation | ligne `prospects` | → supprimée | A16 | `DELETE` | — |

### 5.4 Règles implicites
- Dates : `DATETIME` en heure serveur `date('Y-m-d H:i:s')` (`model.php:2209, 2223, 1453, 468`) ; affichage `DATE_FORMAT(...,'%d.%m.%Y')` ; datepicker admin `dd.mm.yy`, valeur envoyée `yy-mm-dd 08:00:00` (`global.js:20-44`).
- Nom acquéreur stocké `"{civilité} {nom} {prénom}"` ; adresse `"{ville} {code postal}"` (champ `adr` libellé « Ville », `json.php:894-898` ; `V_programme.php:248-263`). Dénonciation : `"{civilité} {nom} {prénom}"` (`model.php:1446`, `json.php:1269`).
- Textes saisis stockés encodés `htmlspecialchars(ENT_QUOTES)` (`model.php:16-19`, utilisé par `AddOption`, `AddProspect`, `Envoyer_Message_Perso`, sms des validations) puis affichés via `html_entity_decode` (`V_ask_options.php:48` etc.). Le message de validation est ajouté en fin : `<div class="separator"></div>{sms}` (`model.php:906, 992, 1024, 1080`), style `.separator` (`global.css:95`). Le message de l'admin n'est stocké que pour les **validations** (option, résa, dénonciation) et le refus de dénonciation ; pas pour les refus option/résa, annulations, passages en résa ou acte.
- Un seul `d_options` / `d_reservations` par lot (index UNIQUE `iddlot`). Rien n'empêche en base une ligne `options.statut=1` et une ligne `d_reservations` sur le même lot (seul le test `statut` vide de A1/A2 l'évite, sans verrou).
- `ventes.iduser` = `appartements.vendeur` = prescripteur de la réservation ; `ventes.date` = date de l'acte (now) ; `ventes.date_resa` = date de la réservation.
- Message interne : `mp.idreceveur = 1` en dur (`json.php:925, 994, 1271`) = l'unique admin local.
- Prix du rapport : `SUM(REPLACE(prix,' ',''))` (`model.php:668`).

### 5.5 Durées et expirations
Aucune : pas de cron dans `OLD/`, aucune comparaison de date (`DATE_SUB`, `INTERVAL`, `strtotime`) dans les contrôleurs et le modèle (recherche sur `application/controllers`, `application/models`). Les options restent actives jusqu'à action manuelle. Voir § 11 pour un éventuel cron côté serveur.

### 5.6 Incohérences constatées en base locale
- 34 `options.statut=1` et 16 `reservations.statut=1` pointent vers des lots supprimés (nettoyage `SupprimerAllParms` inopérant : double échappement `model.php:826-827` puis `840-841`).
- 6 lots `act` sans `ventes.statut=1` ; 2 `ventes.statut=1` vers des lots supprimés ; 4 lots ont plusieurs lignes `ventes` (vente annulée puis revendue) et apparaissent en double dans `/ventes` (jointure sans filtre `ventes.statut`, `model.php:1181-1183`).
- `mp.sujet` : 1599 lignes « Vous avez une demande d&#039;option en attente » (encodé) contre 766 non encodées ; le compteur de messages admin exclut la forme non encodée seulement (`model.php:1948`).

## 6. Emails

Transport commun : SwiftMailer v4 (`application/libraries/swift/lib/swift_required.php`), `Swift_SmtpTransport(EMAIL_SMTP, EMAIL_PORT, 'ssl')` avec `EMAIL_USER` / `EMAIL_PASS` (`config.php:142-146`, valeurs non recopiées ; serveur OVH port 465). Expéditeur `EMAIL` « Corim promotion » (`config.php:143`). Corps HTML + partie texte `strip_tags($body)`. Aucun `mail()` dans le workflow. Un nouvel objet transport est créé à chaque envoi ; un échec SMTP lève une exception → page 500 **après** les écritures en base (sauf E1/E2 : statut du lot non mis à jour, demande déjà insérée).

Sujet = `[Corim promotion] - ` + libellé. `{SIGNATURE}` = la chaîne exacte de `json.php:144` (`Immeuble "Le Cristal"<br />1475 av.Albert Einstein<br />34000  MONTPELLIER<br />Tél : … <br />Mob : …`, numéros à recopier depuis le code, deux espaces avant MONTPELLIER). `{LOGO}` = `https://corim-vente.com/medias/images/logo-corim.png` (`BASE`, `config.php:127`).

**Gabarit N (notification à Corim)** :
```
<table width="500"><tr><td align="left"><img src="{LOGO}" /></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td>{PHRASE}<br /><br />Pour y accéder, vous pouvez cliquer <a href="https://corim-vente.com/messagerie">ici</a></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td align="left">{SIGNATURE}</td></tr></table>
```

**Gabarit P (notification au prescripteur)** :
```
<table width="500"><tr><td align="left"><img src="{LOGO}" /></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td>Bonjour,<br /><br />{PHRASE}<br /><br />{sms}</td></tr><tr><td align="left">&nbsp;</td></tr><tr><td>Programme concerné : {projet_titre}</td></tr><tr><td>{LIGNE2}</td></tr><tr><td align="left">&nbsp;</td></tr><tr><td align="left">{SIGNATURE}</td></tr></table>
```
`{sms}` = message saisi par l'admin, brut (non échappé). `{LIGNE2}` = `Référence lot : {appartements.numero}` ou, pour les dénonciations, `Client : {d_prospects.nomprospect}`. `{projet_titre}` / `{numero}` via `SelectAllInLot(lot)` ; pour les dénonciations via `GetProjetAndProspectByAskProspect` lu **avant** suppression (`model.php:2309-2314`). Destinataire P = `user.email` de l'`iduser` posté (`getEmailUser`).

| # | Déclencheur | Destinataire | Sujet exact | Gabarit / {PHRASE} | Source |
|---|---|---|---|---|---|
| E1 | `AddOption` (lot libre) | `TO` = adresse contact Corim (`config.php:148`) | `[Corim promotion] - Vous avez une demande d'option en attente` | N : `Vous avez reçu un nouveau message concernant une demande d'option` | `json.php:918-945` |
| E2 | `AddReservation` | `TO` | `[Corim promotion] - Vous avez une demande de réservation en attente` | N : `Vous avez reçu un nouveau message concernant une demande de réservation` | `json.php:986-1014` |
| E3 | `AddProspect` (même si l'insert échoue) | `TO` | `[Corim promotion] - Vous avez une demande de dénonciation en attente` | N : `Vous avez reçu un nouveau message concernant une demande de dénonciation` | `json.php:1268-1290` |
| E4 | `AcceptAddOption` succès | prescripteur | `[Corim promotion] - Votre demande d'option a été validée` | P : `Votre demande d'option a été validée.` / Référence lot | `json.php:799-827` |
| E5 | `RejetAddOption` succès | prescripteur | `[Corim promotion] - Votre demande d'option a été refusée` | P : `Votre demande d'option a été refusée.` / Référence lot | `json.php:740-768` |
| E6 | `AcceptAddResa` | prescripteur | `[Corim promotion] - Votre demande de réservation a été validée` | P : `Votre demande de réservation a été validée.` / Référence lot | `json.php:508-536` |
| E7 | `RejetAddResa` | prescripteur | `[Corim promotion] - Votre demande de réservation a été refusée` | P : `Votre demande de réservation a été refusée.` / Référence lot | `json.php:390-418` |
| E8 | `AcceptAddDenonciation` | prescripteur | `[Corim promotion] - Votre demande de dénonciation a été validée` | P : `Votre demande de dénonciation a été validée.` / `Client : {nomprospect}` | `json.php:451-477` |
| E9 | `RejetAddDenonciation` | prescripteur | `[Corim promotion] - Votre demande de dénonciation a été refusée` | P : `Votre demande de dénonciation a été refusée.` / `Client : {nomprospect}` | `json.php:334-360` |
| E10 | `PasserEnResa` | prescripteur | `[Corim promotion] - Votre option a été passée en réservation` | P : `Votre option a été passée en réservation.` / Référence lot | `json.php:566-594` |
| E11 | `AnnulerOption` | prescripteur posté | `[Corim promotion] - Votre option a été annulée` | P : `Votre option a été annulée.` / Référence lot | `json.php:624-652` |
| E12 | `AnnulerResa` | prescripteur posté | `[Corim promotion] - Votre réservation a été annulée` | P : `Votre réservation a été annulée.` / Référence lot | `json.php:682-710` |
| E13 | `PasserEnActer` | prescripteur de la résa/option | `[Corim promotion] - Votre dossier a été acté` | P : `Votre dossier a été acté.` / Référence lot | `json.php:122-153` |
| E14 | `AnnulerLaVente` | vendeur posté | `[Corim promotion] - La vente a été annulée` | P : `La vente a été annulée.` / Référence lot | `json.php:195-223` |

Messages internes associés (`mp`, via `Envoyer_Message_Perso`, `model.php:2048-2063`, `idreceveur = 1`) : « Vous avez une demande d'option en attente » (A1), « Vous avez une demande de réservation en attente » (A2), « Vous avez une dénonciation en attente » (A3, libellé différent de l'email), corps = message du prescripteur. Les envois `mp` vers le prescripteur sont commentés partout (`json.php:119, 192, 331, …`).

## 7. Fichiers

Aucun upload ni téléchargement dans le module. Le Rapport lit seulement `medias/programmes/{projet_titre_key}/thumb/thumb.{jpg,JPG,png,gif}` (`V_Report.php:31-52`, chemins gérés par programmes-lots).

## 8. Design

- Gabarit : `application/views/template/template.php` (Bootstrap 3.3.6 CDN, jQuery 1.11.3 + migrate 1.2.1, jQuery UI 1.11.3 pour le datepicker, tablesorter combiné + thème green, colorbox, jquery.confirm, `medias/css/global.css`, `medias/js/global.js`) (`template.php:11-72`). Tableau de bord : `template/module_dashboard.php` dans `template_welcome.php:81`.
- Vues : `V_ask_options.php`, `V_ask_reservations.php`, `V_ask_denonciations.php`, `V_options.php`, `V_reservations.php`, `V_ventes.php`, `V_Denonciations.php`, `V_refused_ask.php`, `V_Report.php` ; modales de demande dans `V_programme.php:86-389`.
- JS : tous les handlers dans `medias/js/global.js` (voir § 4) ; tablesorter configuré `global.js:6-18` (zebra, filter, `dateFormat: 'ddmmyyyy'`) ; bascule des lignes message `.see_sms` / `.see_sms2` (`:995-1004`).
- CSS : `.success`, `.error` (`global.css:55-87`), `.sms` (`:89`), `.separator` (`:95`) ; couleurs de statut dans `V_programme.php:597-603` et `V_Report.php:2-19, 75-79`.
- Remplacements natifs prévus (CLAUDE.md) : `jquery.confirm` + `colorbox` (enchaînés : confirmation puis modale) → un `<dialog>` de confirmation puis un `<dialog>` formulaire, mêmes textes et mêmes redirections à la fermeture ; `tablesorter` → tri vanilla (+ filtre `filter_external '.search'` à vérifier, aucun champ `.search` trouvé dans ces vues) ; `eval()` des réponses → `fetch` + `response.json()`. Datepicker jQuery UI : à garder ou remplacer, voir § 11.

## 9. Bugs et failles constatés

| Problème | Fichier:ligne | Décision proposée (reproduire / corriger) |
|---|---|---|
| **Toutes les actions JSON sans authentification ni rôle** : n'importe qui peut valider, refuser, annuler, acter, supprimer, re-dater, et déclencher des emails | `__json.php:8-15` ; `json.php:14-1299` | Corriger : `auth` + policy admin sur A4-A17, auth sur A1-A3/A18 |
| Aucun jeton CSRF | toutes les vues § 3, `global.js` | Corriger (CSRF Laravel, en-tête `X-CSRF-TOKEN` dans `fetch`) |
| Usurpation de prescripteur : `prescripteur_select` accepté de tout appelant | `json.php:903-912, 972-981, 1252-1261` | Corriger : pris en compte seulement pour l'admin, et uniquement un prescripteur actif |
| Aucun contrôle d'accès au programme/lot (`acces`, `acces_lot`) ni de cohérence `id_projet` / lot | `json.php:889-1025, 1240-1299` | Corriger (policy du module programmes-lots), à confirmer § 11 |
| **Injection SQL** dans `AddProspect` (concaténation avec `htmlspecialchars` seul, l'antislash n'est pas échappé ; exploitable sans session via `prescripteur_select`) | `model.php:1443-1454` | Corriger (query builder) |
| Antislash non échappé dans les sms concaténés (erreur SQL possible) | `model.php:904/915, 990/1005, 1022/1037, 1078/1091` | Corriger |
| **XSS stockée** : textes encodés à l'insertion puis `html_entity_decode` à l'affichage, et résultats de recherche injectés en HTML | `V_ask_options.php:48`, `V_options.php:56`, `V_ask_reservations.php:48`, `V_reservations.php:56`, `V_ask_denonciations.php:49`, `V_Denonciations.php:90`, `V_refused_ask.php:44` ; `global.js:1438-1451` | Corriger : stocker brut, échapper à l'affichage, ne rendre en HTML que le séparateur (rendu identique) ; migrer les données (décoder les entités) |
| **XSS réfléchie / JSONP** : `?callback=` renvoyé sans filtre en `text/html` | `json.php:1600-1601` | Corriger : ignorer `callback` (pas utilisé par le front) |
| Injection HTML dans les emails (sms brut, envoyés depuis l'adresse officielle, déclenchables sans auth → hameçonnage) | `json.php:139, 209, 346, 404, 463, 522, 580, 638, 696, 754, 813` | Corriger : échapper `{sms}` (`nl2br(e())`) |
| Rapport : redirection non-admin sans `exit` → contenu servi quand même | `report.php:11-13` | Corriger (403/redirection effective) |
| Division par zéro si somme des prix = 0 | `V_Report.php:75-79` | Corriger (afficher `0%`) |
| Identifiants en clair dans le code (connexion BDD, commentaire) | `model.php:8`, `refused_ask.php:20` | Corriger (ne pas porter, rotation à prévoir) |
| Refus de dénonciation : `REPLACE` renvoie 1 à la première tentative (id absent de `r_prospects`) → réponse `0` mais copie déjà faite ; seule une 2e tentative supprime `d_prospects` | `model.php:907-914` | Corriger (succès dès 1 ligne), voir § 11 |
| `Acter` toujours en erreur (argument manquant) et sans `INSERT ventes` | `json.php:250` vs `model.php:744` | Ne pas porter (aucune UI), voir § 11 |
| Pages `optionner` / `reserver` en erreur (signature de `AddOption`/`AddReservation`) | `optionner.php:29`, `reserver.php:29` | Ne pas porter |
| `AnnulerOption` / `AnnulerResa` ignorent `user` et annulent toutes les lignes du lot ; l'email part au `user` posté | `model.php:507, 523` | Reproduire le résultat (1 seule ligne active possible), filtrer par lot + statut 1 |
| Actions `RÉSERVATION`/`ANNULER`/`ACTER` affichées sur les listes annulées (échouent toujours) | `V_options.php:53`, `V_reservations.php:53` | Corriger : masquer sur `refused` (à valider) |
| `PasserEnResa` ne stocke pas le sms (variable préparée puis oubliée) | `model.php:482-486` | Reproduire (pas de stockage) ou corriger : à valider § 11 |
| Test « nom vide » inopérant, `cp` non obligatoire côté serveur alors que marqué `*` | `json.php:894, 914, 963, 982` | Corriger (validation Form Request, tous les champs `*`) |
| Notices PHP → page 500 Kohana avec SQL affiché (callback non défini, type inconnu) | `json.php:188-228, 248-261, 947-949`, `model.php:376-401, 734-739, 760-765` | Corriger : réponse JSON `{"errors":0}` |
| Ordre non transactionnel (MyISAM) : email envoyé avant le changement de statut (A1/A2), email E3 avant test d'insert ; échec SMTP = état partiel | `json.php:923-946, 992-1015, 1269-1291` | Corriger : transaction InnoDB, email en file après commit |
| Course possible option + réservation simultanées sur le même lot | `json.php:920-923, 988-992` | Corriger : `UPDATE ... WHERE statut=''` atomique (ou `lockForUpdate`) |
| `/ventes` duplique un lot revendu (jointure `ventes` sans `statut=1`) ; `UpdateDate` `vente` modifie aussi les ventes annulées | `model.php:1181-1183, 394-399` | Corriger : `ventes.statut = 1` |
| Lien `Message` sur `/refused_ask` et `/denonciations` (recherche) mal câblé : `see_sms` cherche la ligne suivante / `tr#{id}` | `V_refused_ask.php:28-45`, `global.js:1455-1458` | Corriger (comportement visuel identique : afficher le message) |
| Suppression d'un lot ne nettoie pas options/réservations/ventes (double échappement) | `model.php:838-850` | Corriger (FK `ON DELETE`) — à coordonner avec programmes-lots |
| `<td>` non fermés dans `/ventes` ; `<tbody>` fermant écrit `<tbody>` | `V_ventes.php:44-49`, `V_ask_options.php:52` et vues similaires | Corriger le markup sans changer le rendu |
| Titre « Options refusées » vs H1 « Options annulées » | `options.php:27`, `V_options.php:3` | Reproduire |

## 10. Critères d'acceptation

1. Un visiteur non connecté qui appelle `GET /ask_options`, `/ask_reservations`, `/ask_denonciations`, `/options`, `/options/refused`, `/reservations`, `/reservations/refused`, `/ventes`, `/denonciations`, `/refused_ask`, `/report` est redirigé vers `/connexion`.
2. Un visiteur non connecté qui appelle n'importe quelle action `POST /index.php/json/<A1..A18>` n'obtient aucun effet en base et aucun email (401/redirection).
3. Un prescripteur qui appelle A4 à A17 obtient un refus (403) sans effet en base.
4. `POST` sans jeton CSRF valide sur toute action → 419, sans effet.
5. Les routes `/index.php/json/<Action>/` et `/json/<Action>` répondent toutes deux ; la réponse est le JSON brut sans saut de ligne, `{"errors":N}` avec la même valeur de N que l'ancien code pour chaque branche du § 4.
6. A1 sur un lot libre (`statut=''`) avec tous les champs : `{"errors":1}`, une ligne `d_options` avec `nom="{civ} {nom} {prénom}"`, `adresse="{ville} {cp}"`, `iduser` = session, `appartements.statut='opt'`, un `mp` vers l'utilisateur 1 de sujet « Vous avez une demande d'option en attente », un email E1 à l'adresse `TO` avec sujet et corps exacts.
7. A1 sur un lot `opt`, `res` ou `act`, ou id inexistant : `{"errors":2}`, aucune écriture, aucun email.
8. A1 avec un champ obligatoire vide (dont `name` et `cp`) : `{"errors":0}`, aucune écriture.
9. A1 par l'admin avec `prescripteur_select=N` : `d_options.iduser = N` ; par un prescripteur avec `prescripteur_select` : ignoré (`iduser` = session).
10. A2 : mêmes cas que 6-9 sur `d_reservations` avec `statut='res'` et email E2.
11. Deux A1/A2 concurrents sur le même lot : un seul réussit, l'autre reçoit `{"errors":2}`.
12. A3 avec tous les champs : `{"errors":1}`, ligne `d_prospects` (`nomprospect="{civ} {nom} {prénom}"`), `mp` « Vous avez une dénonciation en attente », email E3 ; champ manquant → 0 ; une chaîne contenant `\` et `'` est stockée telle quelle sans erreur SQL.
13. A4 : ligne `d_options` déplacée dans `options` (statut 1, même `iduser`, `nom`, `adresse`, `date`, message suffixé de `<div class="separator"></div>{sms}`), `d_options` vide pour ce lot, lot toujours `opt`, email E4 au prescripteur.
14. A5 : `d_options` supprimée, lot `''`, email E5 ; aucune trace stockée du sms.
15. A6 / A7 : équivalents de 13 / 14 pour les réservations (lot reste `res` / repasse `''`), emails E6 / E7 ; A6 avec param vide → `{"errors":3}`.
16. A8 : ligne déplacée de `d_prospects` vers `prospects` (nouvel id), `smspromoteur` suffixé, email E8 avec `Client : {nomprospect}` ; param vide → `{"errors":3}`.
17. A9 : ligne copiée dans `r_prospects` avec le même `idprospect`, sms suffixé, `d_prospects` supprimée dès le premier appel, réponse 1, email E9.
18. A10 sur une option `statut=1` : nouvelle ligne `reservations` (statut 1, date de l'option), option supprimée, lot `res`, email E10 ; sur un lot sans option active → 0.
19. A11 : `options.statut=0` pour le lot, lot `''`, email E11 ; la ligne apparaît dans `/options/refused`.
20. A12 : `reservations.statut=0`, lot `''`, email E12 ; visible dans `/reservations/refused`.
21. A13 type `resa` : lot `act`, `client` = nom de la résa, `vendeur` = iduser, résa active supprimée, `ventes` (iduser, idlot, date=now, statut 1, date_resa = date de la résa), email E13 ; sans résa active → 0 ; type inconnu → 0 (pas de 500).
22. A15 : lot `''`, `client=''`, `vendeur=0`, `ventes.statut=0`, email E14 ; vendeur ne correspondant pas → 0.
23. A16 : ligne `prospects` supprimée → 1 ; id inexistant → 0.
24. A17 pour chacun des 8 types : seule la colonne prévue est modifiée (filtre `statut=1` pour `option`/`resa`, `ventes.statut=1` pour `vente`/`venteresa`) ; réponse 1 si une ligne modifiée ; type inconnu → 0.
25. A18 : recherche vide → 0 ; type 1/2/3 → `{"errors":1,"call":[...]}` avec les mêmes clés que `prospects.*` + `date` (`%d.%m.%Y %H:%i`), `projet_titre`, `prescripteur` ; prescripteur limité à ses dénonciations ; aucun résultat → 2.
26. Chaque liste § 3.1-3.8 affiche, pour un prescripteur, uniquement ses lignes et sans colonne ACTIONS ni datepicker ; pour l'admin, toutes les lignes avec actions ; ordre de tri identique ; messages « vide » identiques.
27. Les messages saisis contenant `<script>` s'affichent échappés dans toutes les listes et dans les emails ; le séparateur `<div class="separator"></div>` est rendu comme avant.
28. `/ventes` n'affiche qu'une ligne par lot acté (vente active) avec DATE = `ventes.date`, RÉSERVATION = `ventes.date_resa`.
29. `/report` : non-admin → refus sans contenu ; admin → pour chaque programme, comptes et sommes identiques aux requêtes `model.php:659-727` sur le snapshot ; programme sans prix → `0%` sans erreur.
30. Tableau de bord : les 7 compteurs égalent les requêtes `model.php:1153-1262` pour un admin et pour un prescripteur ; lien inactif à 0 ; lien RAPPORT visible seulement pour l'admin.
31. Chaque email E1-E14 (mailer `log`) a l'expéditeur « Corim promotion » `EMAIL`, le sujet exact du § 6, le corps HTML du gabarit à l'octet près (hors échappement de `{sms}`) et une partie texte = `strip_tags` du HTML.
32. Une erreur d'envoi d'email ne laisse aucune écriture partielle : soit la transition est complète et l'email est mis en file, soit rien n'est écrit.
33. `?callback=x` sur une action JSON ne change pas la réponse (pas de JSONP).
34. Les URL `/optionner/*`, `/reserver/*`, `/index.php/json/Acter` n'exécutent aucune écriture (404 ou équivalent selon décision § 11).

## 11. Questions ouvertes

1. **Expiration des options** : le code n'en contient aucune. Existe-t-il un cron sur le serveur de prod (hors dépôt) ou une règle métier (ex. option valable N jours) appliquée à la main ? La table `options` ayant 40 lignes actives dont certaines anciennes (dates de 2013 à 2026), faut-il en introduire une ? (hors iso-fonctionnel par défaut : non).
2. **Refus de dénonciation (A9)** : en prod, les utilisateurs doivent-ils vraiment cliquer deux fois (première réponse « Une erreur interne est survenue ») ? À confirmer avec l'admin ou dans les logs d'accès avant de corriger.
3. `Acter` (A14) et le type `option` de `PasserEnActer` (A13) n'ont aucune UI : les supprimer ou garder l'API ? 6 lots `act` sans vente active : origine (ancien `Acter`, saisie manuelle) ? Faut-il créer les lignes `ventes` manquantes à la migration ?
4. Doit-on contrôler, pour A1-A3, que le prescripteur a accès au programme / lot (`acces`, `acces_lot`) ? L'ancien code ne le fait pas ; la grille du module programmes-lots filtre peut-être déjà l'affichage (`json.php:1519-1596`).
5. Le message admin doit-il être conservé sur `PasserEnResa`, refus d'option / de réservation et annulations (aujourd'hui seulement emailé) ?
6. Les boutons d'action affichés sur `/options/refused` et `/reservations/refused` (inopérants) doivent-ils rester visibles pour l'iso-visuel ?
7. Datepicker jQuery UI (format `dd.mm.yy`) : garder la librairie (aucune contrainte dans CLAUDE.md) ou passer à `<input type="date">` (rendu différent) ?
8. Heure fixe `08:00:00` imposée par la modification de date (`global.js:24`) : à conserver ?
9. Orphelins existants (34 options et 16 réservations actives sur lots supprimés, 2 ventes actives sur lots supprimés, 1599 sujets `mp` encodés) : nettoyer à la migration ou les reprendre tels quels ? Les clés étrangères InnoDB imposent une décision.
10. L'adresse `TO` (destinataire des demandes) et le numéro mobile de la signature sont-ils toujours valides et doivent-ils passer en configuration ?
11. Faut-il garder la 2e copie des numéros de téléphone en dur dans les 14 emails ou les centraliser (contenu identique) ?
12. `options.statut` / `reservations.statut` : y a-t-il d'autres valeurs que 0/1 attendues (aucune en base locale) ? Et `appartements.statut` NULL (toléré par `json.php:922`, absent des données) : normaliser en `''` ?
