# Corim Vente : demandes d'amélioration de la cliente

Liste reçue par mail le 30 octobre 2026, avant le rendez-vous du mardi 3 novembre 2026. Pour chaque demande : ce que dit le code et la base aujourd'hui, la façon de procéder proposée, les points à trancher avec la cliente et une estimation.

Contexte de périmètre : la refonte suit la règle « même projet, rien de plus » (voir `CLAUDE.md`). Ces quatre demandes sont des évolutions. **Tout est soumis à l'acceptation du devis.** Décision (30 octobre 2026) : si le devis est accepté, ces évolutions sont livrées **avant la mise en production** de la refonte, sur la nouvelle version (`NEW/`) uniquement, comme un lot chiffré et recetté à part. On ne touche pas à l'ancienne application.

| # | Demande | Taille estimée | Dépend de |
|---|---|---|---|
| 1 | Passerelle API pour partager les flux avec les prescripteurs | 3 jours pour la base (consultation du stock), jusqu'à 4 avec les dossiers du prescripteur ; options en plus (voir section 1) | réponses de la cliente sur le contenu, les destinataires et le format |
| 2 | Description des programmes : forme et mise en page | 0,5 jour | rien (simple mise en page retenue) |
| 3 | Messagerie : messages automatiques et message au prescripteur (police, signature) | 1,5 à 2 jours | exemple de mail fautif, texte de signature |
| 4 | Suivi des refus d'options et de réservations | 1 jour | rien |

Ordre conseillé : 2, 4 et 3 d'abord (petits, sans inconnue, visibles tout de suite par les utilisateurs), puis 1 une fois le besoin précisé.

---

## 1. Passerelle API pour les prescripteurs

> « Créer une passerelle API pour pouvoir partager les flux avec nos prescripteurs »

### Ce qui existe

- L'API publique actuelle (`firstsearch?action=nextjscorim`) donne le stock libre de **tous** les programmes actifs, sans authentification. Elle sert les sites COCOON et corim-promotion et doit rester figée (contrat octet pour octet, voir `docs/contract/public-api.md`).
- Chaque prescripteur ne voit pourtant qu'une partie du stock : les programmes de la table `acces` et les lots de `acces_lot` (18 470 lignes pour 231 comptes). Une API par prescripteur doit respecter ces mêmes droits.
- Le cycle des lots (libre, option, réservé, acté) est centralisé dans le module workflow, ce qui facilite des notifications sortantes plus tard.

### Proposition

Une nouvelle API, à côté de l'actuelle, sans rien changer à celle-ci (COCOON et corim-promotion continuent de fonctionner tels quels).

#### Base : consultation du stock (3 jours)

L'API permet seulement de **consulter** des données. Le prescripteur (ou le prestataire qui gère son site ou son logiciel) récupère la liste des lots ; il ne peut rien créer ni modifier par l'API.

- **Clé d'accès par prescripteur** : l'admin la génère ou la révoque depuis la fiche du prescripteur. La clé est stockée chiffrée, jamais en clair.
- **Liste des lots** (`GET /api/v1/lots`) : uniquement les lots que ce prescripteur a le droit de voir sur la plateforme (mêmes droits qu'à l'écran). Pour chaque lot : programme (titre, ville, livraison, description, vignette), numéro, type, étage, surface, prix, options (terrasse, parking…), statut et lien du plan PDF. Filtres : programme, type, ville, budget.
- **Protection** : limite du nombre d'appels par clé, tests automatisés.
- **Documentation** : une page courte avec exemples d'appels, liste des champs et codes d'erreur, à transmettre au prestataire du prescripteur.

#### Complément : les dossiers du prescripteur (+1 jour)

En plus du stock, le prescripteur consulte ses propres dossiers : dénonciations, options et réservations, avec leur statut (en attente, validée, refusée, annulée) et la date de décision. Toujours en consultation seule.

#### Options, chiffrées à part si la cliente les veut

| Option | Ce que ça permet | Estimation |
|---|---|---|
| A. Notifications automatiques (webhooks) | la plateforme prévient le logiciel du prescripteur quand un lot change de statut ou qu'une de ses demandes est traitée, sans qu'il ait à interroger l'API | 2 jours |
| B. Dénoncer par l'API | le prescripteur déclare un acquéreur depuis son propre logiciel ; même circuit qu'à l'écran (validation par l'admin, notification, mails) | 2 jours |
| C. Demander une option ou une réservation par l'API | idem pour les lots, avec blocage du lot et gestion des demandes simultanées | 2 à 3 jours |
| D. Export au format d'un portail ou d'un multidiffuseur | flux XML ou CSV au format imposé par le diffuseur, mis à jour automatiquement | 2 à 3 jours par format, selon sa documentation |

Tout repose sur des briques Laravel déjà présentes (routes API, limite de débit, files d'attente). Pas de nouveau framework.

#### Ouvert à tous ou réservé aux prescripteurs connus ?

Techniquement, une API ouverte à tous (sans clé) est la plus simple : l'API actuelle des sites COCOON et corim-promotion en est déjà une. Une version propre et documentée du même flux prendrait environ 1 à 1,5 jour. La complexité vient de l'identification des prescripteurs (droits par lot), de l'écriture et des formats imposés par des tiers.

Les enjeux d'une API ouverte sont commerciaux :

- **Visibilité** : tout le monde voit le stock et les prix, concurrents compris, et peut les aspirer automatiquement.
- **Contrôle** : on ne sait pas qui utilise le flux ni ce qu'il en fait, et on ne peut couper l'accès à personne en particulier.
- **Contenu limité** : seul ce qui est public peut sortir, c'est-à-dire les lots libres. Pas de lots réservés à certains prescripteurs, pas de dossiers.

Voie intermédiaire (celle de la base à 3 jours) : **accès sur demande**. Un prescripteur ou un partenaire demande une clé, l'admin l'accorde en un clic et peut la couper à tout moment. Presque aussi simple pour eux, et Corim garde le contrôle.

### Questions à poser à la cliente

1. **Ouvert ou réservé ?** Le stock et les prix peuvent-ils être publics (accessibles à n'importe qui, concurrents compris), ou réservés aux prescripteurs et partenaires que Corim autorise ?
2. **Pour qui ?** Quels prescripteurs veulent ce flux, nommément ? Combien au départ ? On peut commencer par un seul pour valider le format.
3. **Pour quoi faire ?** Afficher les lots sur le site de l'agence, les intégrer dans un logiciel de transaction (lequel ?), les publier sur un portail d'annonces ?
4. **Qui branche le flux côté prescripteur ?** Ont-ils un prestataire technique capable d'appeler une API, ou attendent-ils un fichier prêt à l'emploi (XML, CSV) au format d'un logiciel ou d'un portail précis ?
5. **Quel contenu ?** Le stock disponible suffit-il, ou veulent-ils aussi suivre leurs dossiers (dénonciations, options, réservations) ?
6. **Uniquement les lots libres**, ou aussi ceux en option, réservés ou vendus (pour afficher « vendu » par exemple) ?
7. **Les prix** peuvent-ils être diffusés tels quels, ou certains prescripteurs ont-ils des conditions différentes ?
8. **Fraîcheur** : une mise à jour en temps réel est-elle nécessaire, ou une fois par heure ou par jour suffit-elle ?
9. **Écriture** : veulent-ils pouvoir dénoncer ou poser une option depuis leur propre outil (options B et C), ou la plateforme reste-t-elle le seul point d'entrée ?
10. **Notifications** : veulent-ils être prévenus automatiquement des changements (option A), ou consulter le flux leur suffit-il ?
11. **Documents** : faut-il aussi diffuser les visuels, la plaquette et les documents du programme, en plus du plan de chaque lot ?

---

## 2. Description des programmes : forme et mise en page

> « Texte description programme : améliorer forme et mise en page »

### Ce qui existe

- La description se saisit dans un simple champ texte (`ajouter_programme`, `modifier_programme`) et s'affiche en texte brut sur la fiche programme (`programme.blade.php`).
- Trois défauts visibles dans la base actuelle :
  - **retours à la ligne perdus** : tout s'affiche sur un seul paragraphe ;
  - **liens non cliquables** : les liens Google Maps et YouTube s'affichent en texte ;
  - **barres obliques et `\n` parasites** : l'ancienne application ajoutait des `\` à chaque enregistrement, ils s'accumulent (exemple NATURA : `Lien vers l\\\\\\\'emplacement`, ST HILAIRE : `\\\\\\\\n\\\\\\\\n`).

### Proposition

1. **Nettoyage des données** une fois, à la migration : retirer les `\` accumulés et transformer les `\n` littéraux en vrais retours à la ligne. La refonte n'ajoute plus de `\`, le problème ne revient pas.
2. **Affichage** : conserver les retours à la ligne, rendre les liens cliquables (ouverture dans un nouvel onglet), aérer le bloc (paragraphes, interligne).
Décision (30 octobre 2026) : **simple mise en page** (points 1 et 2). Pas d'éditeur riche : la saisie reste un champ texte.

---

## 3. Messagerie : messages automatiques et message au prescripteur

> « Messagerie : améliorer le paramétrage automatique (après avoir accepté une dénonce/option/réservation) + le message qu'on laisse au prescripteur après la demande de dénonce/option/réservation (bug sur la taille de la police et la signature) »

### Ce qui existe

- Quand l'admin valide ou refuse une demande, une fenêtre propose « Laisser un message au prescripteur » : une zone de texte **vide à chaque fois**. Ce texte part dans le mail au prescripteur et est collé à la suite du message de la demande, séparé par un trait.
- En pratique, ce message est le plus souvent vide ou très court (« T4 », « pour Eva », « VINCI » sur les dernières réservations).
- Le mail au prescripteur (`resources/views/workflow/mail/prescriber.blade.php`, copie de l'ancien) :
  - n'a **aucune police ni taille définie** : chaque logiciel de mail applique sa propre police par défaut, d'où des tailles différentes selon les messageries ;
  - **perd les retours à la ligne** du message saisi par l'admin ;
  - a une **signature écrite en dur** dans le code : adresse « Immeuble Le Cristal, 1475 av. Albert Einstein, 34000 Montpellier », téléphone fixe et portable, sans nom ni fonction. Si l'admin ajoute sa signature à la main, elle se retrouve en double.
- Les messages internes (`mp`, la « messagerie ») sont tous générés automatiquement à chaque demande, à destination de l'admin (1 996 sur douze mois, aucun message humain).

### Proposition

1. **Messages types modifiables par l'admin** : un écran « Messages automatiques » avec un texte par décision (dénonciation validée, refusée, option validée, refusée, réservation validée, refusée, et si utile annulation et passage à l'acte). Ce texte pré-remplit la fenêtre de validation ; l'admin peut le modifier au cas par cas avant d'envoyer. Variables possibles : nom du prescripteur, programme, lot, acquéreur.
2. **Mail propre** : police et taille fixées dans le mail (lisible partout), retours à la ligne conservés, mise en page légèrement aérée, mêmes informations qu'aujourd'hui.
3. **Signature paramétrable** : un seul texte de signature (nom, fonction, adresse, téléphones, éventuellement logo) modifiable par l'admin, ajouté automatiquement en bas de chaque mail. Plus besoin de la taper.

### À trancher avec la cliente

- **Un exemple du mail qui pose problème** (transfert ou capture d'écran) pour confirmer le bug de police : est-ce dans le mail reçu par le prescripteur, ou dans l'affichage du message sur le site ?
- Le texte de signature voulu (l'adresse « Le Cristal » et les numéros sont-ils encore bons ?).
- Le texte de chaque message type.
- « Paramétrage automatique » : s'agit-il bien des textes pré-remplis ci-dessus, ou aussi des notifications internes de la messagerie (que l'admin reçoit à chaque demande) ? Si ce sont les notifications internes, la piste « file à traiter sur le tableau de bord » de `plateforme.md` (section 8, point 3) est à rediscuter.

---

## 4. Suivi des refus

> « Prévoir un suivi pour les refus : n'existe que pour les validations et annulations »

### Ce qui existe

- **Dénonciations** : les refus sont déjà conservés (`r_prospects`, page « Dénonciations refusées »).
- **Options et réservations** : un refus **supprime la demande** (`d_options`, `d_reservations`) et libère le lot. Le message de l'admin n'est même pas enregistré. Il ne reste aucune trace, à part le mail envoyé.
- Les pages `options/refused` et `reservations/refused` existent mais montrent en réalité les **annulations** (titre « Options annulées »), d'où la confusion.

### Proposition

1. Au moment du refus, enregistrer la demande refusée (acquéreur, prescripteur, lot, programme, date de la demande, date du refus, message de l'admin) dans une table de refus, au lieu de la perdre.
2. Deux pages « Options refusées » et « Réservations refusées », sur le modèle de « Dénonciations refusées » : l'admin voit tout, le prescripteur ne voit que les siens. Les pages actuelles gardent le libellé « annulées ».
3. Enregistrer aussi la date de décision sur les refus de dénonciation (aujourd'hui seule la date de la demande est gardée).

Limite : **l'historique commence à la mise en service**. Les refus passés ont été supprimés de la base et ne sont pas récupérables.

### À trancher avec la cliente

- **Question à poser à la cliente** : faut-il un motif de refus à choisir dans une liste (lot déjà réservé, doublon, dossier incomplet…) en plus du message libre ? Utile pour des statistiques.
- Le prescripteur doit-il voir ses refus, ou seulement l'admin ?

---

## Après le rendez-vous

Rendez-vous du 3 novembre 2026 : décisions et plan d'action dans `plan-action.md`. L'API est réduite à la consultation par prescripteur (options A à D abandonnées).

- Reporter ici les réponses de la cliente et les décisions.
- Valider le chiffrage et l'ordre, puis découper en tâches avec tests (comme les modules de la refonte).
- Rappel : la phase 3 de la refonte (vérification, revue, répétition de migration, recette) reste à faire ; la recette client couvre la refonte et ces évolutions avant la mise en production.

## Questions à poser à la cliente (récapitulatif)

1. **API** : les questions de la section 1 (ouvert ou réservé, pour qui, pour quoi faire, qui branche le flux, contenu, statuts diffusés, prix, fraîcheur, écriture, notifications, documents).
2. **Messagerie** : un exemple du mail fautif (transfert ou capture). Le texte de signature voulu (adresse « Le Cristal » et téléphones à jour ?). Le texte de chaque message type. « Paramétrage automatique » : textes pré-remplis, ou aussi les notifications internes de la messagerie ?
3. **Refus** : un motif à choisir dans une liste (lot déjà réservé, doublon, dossier incomplet…) en plus du message libre ? Le prescripteur voit-il ses refus, ou seulement l'admin ?
