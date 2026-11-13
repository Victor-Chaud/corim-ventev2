# Réunion client du 3 novembre 2026 : points à aborder

Détail complet : `NEW/docs/ameliorations.md` et `NEW/docs/plateforme.md`.

## Cadre

- Les 4 demandes sont des évolutions, hors refonte. Tout est soumis à l'acceptation du devis.
- Si le devis est accepté : livraison sur la nouvelle version, avant la mise en ligne, recettée avec la refonte.
- Ordre conseillé : 2 (description), 4 (refus), 3 (messagerie), puis 1 (API) une fois le besoin précisé.

## 1. Passerelle API pour les prescripteurs

- Question clé : API ouverte à tous ou réservée ?
  - Ouverte : le plus simple , mais le stock et les prix sont visibles par tous, concurrents compris, sans contrôle des utilisateurs, et seuls les lots libres peuvent être diffusés.
  - Recommandé : une clé par prescripteur, accordée ou coupée par l'admin en un clic.
- Base : consultation seule des lots que le prescripteur a le droit de voir.(dénonciations, options, réservations).
- Options chiffrées à part :
  - A. notifications automatiques (webhooks) :
  - B. dénoncer par l'API ;
  - C. poser une option ou une réservation par l'API :
  - D. export au format d'un portail :
- Questions :
  - Quels prescripteurs, nommément ? Commencer avec un seul ?
  - Pour quoi faire : site d'agence, logiciel de transaction (lequel ?), portail d'annonces ?
  - Qui branche le flux chez eux : un prestataire technique, ou faut-il un fichier XML ou CSV prêt à l'emploi ?
  - Quel contenu : seulement les lots libres, ou aussi les statuts (option, réservé, vendu) ?
  - Les prix sont-ils les mêmes pour tous les prescripteurs ?
  - Fraîcheur : temps réel, une fois par heure ou une fois par jour ?
  - Veulent-ils écrire dans la plateforme (options B et C) ou être notifiés (option A) ?
  - Faut-il diffuser aussi les visuels, la plaquette et les documents du programme ?

Questions absolu !

Quels infos ? Et sous quelle forme.

Infos refraichis en temps réel ou une fois toutes les heures suffit ?

L'acces a l'api juste pour obtenir les infos ou aussi pour pouvoir interagie sur la plateforme ?

## 2. Description des programmes : décidé

- Simple mise en page : retours à la ligne conservés, liens cliquables, nettoyage des `\` et `\n` parasites. Pas d'éditeur riche.
- À faire valider par la cliente.

## 3. Messagerie et mail au prescripteur

- Proposition : messages types modifiables qui pré-remplissent la fenêtre de validation ou de refus, mail avec police fixée et retours à la ligne conservés, signature paramétrable ajoutée automatiquement.
- Questions :
  - Un exemple du mail fautif (transfert ou capture) : le problème est-il dans le mail reçu, ou à l'affichage sur le site ?
  - Le texte de signature : l'adresse « Le Cristal » et les téléphones sont-ils à jour ?
  - Le texte de chaque message type.
  - « Paramétrage automatique » : seulement les textes pré-remplis, ou aussi les notifications internes de la messagerie ?

## 4. Suivi des refus

- Aujourd'hui, un refus d'option ou de réservation supprime la demande sans laisser de trace. Les pages « refusées » actuelles montrent en réalité les annulations.
- Proposition : enregistrer les refus et créer deux pages, « Options refusées » et « Réservations refusées ». L'historique commence à la mise en service : les refus passés ne sont pas récupérables.
- Questions :
  - Faut-il un motif à choisir dans une liste, en plus du message libre ?
  - Le prescripteur voit-il ses refus, ou seulement l'admin ?

## Autres sujets si le temps le permet

- Page Dénonciations très lente (6 668 lignes, 4,8 Mo) : pagination ou filtre par période, avec les 12 derniers mois par défaut ? -> ca sera réglé
- Expiration des options : 34 options ont plus de 3 mois. Y a-t-il une règle de durée appliquée à la main ?
- Doublons de dénonciation (53 cas) : comment sont-ils arbitrés ?
- Modules peu ou pas utilisés (emailing, agenda, fiches clients, flash info) : lesquels peut-on retirer ?
- Ventes : où sont suivis les actes ? La table `ventes` n'est presque plus alimentée.
- Équipe : combien de personnes utilisent le compte admin ? Faut-il plusieurs comptes Corim ?
- Droits : ouvrir un programme entier à un prescripteur en un clic, plutôt que lot par lot.
- RGPD : durée de conservation et purge des prospects anciens (plus de 8 600 personnes en base).
- Statistiques : accord pour récupérer les logs d'accès du serveur (trafic réel de l'API, pages consultées) ?
- Calendrier : dates visées pour la recette et la mise en ligne.


~/www/corim-old-local/lancer.sh
Puis ouvre http://127.0.0.1:8056/connexion.

### « Créer une passerelle API pour pouvoir partager les flux avec nos prescripteurs »

C'est faisable. On crée une nouvelle API à côté de l'actuelle, sans rien changer à celle qui alimente les sites COCOON et corim-promotion.

- Proposition de base : chaque prescripteur reçoit une clé d'accès, que l'admin génère ou coupe depuis sa fiche. Avec cette clé, il récupère la liste des lots qu'il a le droit de voir sur la plateforme (mêmes droits qu'à l'écran) : programme, numéro, type, étage, surface, prix, statut, plan PDF. Il peut seulement consulter, rien modifier.
- En complément, il peut aussi suivre ses propres dossiers : dénonciations, options, réservations et leur statut.
- Si besoin, en options : être prévenu automatiquement des changements, dénoncer ou poser une option depuis son propre logiciel, ou un export au format d'un portail d'annonces.
- Une API ouverte à tout le monde serait plus simple, mais tout le monde verrait le stock et les prix, concurrents compris, sans possibilité de couper l'accès à quelqu'un. Je recommande l'accès par clé.
- Pour chiffrer précisément, j'ai besoin des réponses aux questions de la section 1 : quels prescripteurs, pour quel usage, qui branche le flux chez eux, quel contenu.

A Quels infos le prescripteur peut il acceder et surtout auxquels il ne doit surtout pas ?

### « Texte description programme : améliorer forme et mise en page »

Le problème vient de l'affichage et des données enregistrées.

Problème d'affichage sera réglé et amélioré un bonne fois pour tout.

### « Messagerie : améliorer le paramétrage automatique (après avoir accepté une dénonce/option/réservation) »

- Aujourd'hui, quand l'admin valide ou refuse une demande, la fenêtre « Laisser un message au prescripteur » est vide à chaque fois : il faut tout retaper.
- Ce qu'on propose : un écran « Messages automatiques » avec un texte type par décision (dénonciation, option ou réservation validée ou refusée). Ce texte pré-remplit la fenêtre et l'admin peut le modifier au cas par cas avant d'envoyer. Il peut contenir le nom du prescripteur, le programme, le lot et l'acquéreur.
- À confirmer avec elle : s'agit-il bien de ces textes pré-remplis, ou aussi des notifications internes que l'admin reçoit dans la messagerie à chaque demande ?

Nous dire le texte et on l'implante.

### « Le message qu'on laisse au prescripteur (bug sur la taille de la police et la signature) »

- Police : le mail n'a aucune police ni taille définie, donc chaque logiciel de mail applique la sienne, d'où des tailles différentes selon les destinataires. Les retours à la ligne du message sont aussi perdus.
- Signature : elle est écrite en dur dans le code (adresse « Le Cristal », téléphones, sans nom ni fonction). Si l'admin ajoute la sienne, elle apparaît en double.
- Ce qu'on fait : police et taille fixées dans le mail, retours à la ligne conservés, et une signature unique modifiable par l'admin, ajoutée automatiquement en bas de chaque mail.
- À lui demander : un exemple du mail qui pose problème (transfert ou capture), et le texte de signature voulu (l'adresse et les téléphones sont-ils encore bons ?).

Nous fournir une capture
On refait le check du mail.. Et ensuite

### « Prévoir un suivi pour les refus : n'existe que pour les validations et annulations »

- Dénonciations : les refus sont déjà conservés (page « Dénonciations refusées »).
- Options et réservations : aujourd'hui un refus supprime la demande et il n'en reste aucune trace. Les pages « refusées » actuelles affichent en réalité les annulations.

- Ajout de 2 nouvelles pages « Options refusées » et « Réservations refusées » a coté des .
Ce qu'on fait : chaque refus est enregistré (acquéreur, prescripteur, lot, programme, date de la demande, date du refus, message de l'admin), avec deux nouvelles pages « Options refusées » et « Réservations refusées ».
- Limite : l'historique commence à la mise en service, les refus passés ne sont pas récupérables.

- À lui demander : faut-il un motif à choisir dans une liste (lot déjà réservé, doublon, dossier incomplet…) en plus du message libre ?

Le prescripteur voit-il ses refus, ou seulement l'admin ?

Connexion au flux pour avoir l'actualisation en direct donc !!
Api en lecture simple pas plus pas moins
Actualisation toues les heures
Enlever ce qui n'est pas utilisé
Double click valider deonciation qui envois 2fois l'info a l'api.
Suivi perf tel
Le prescritpeur doit avoir que ses decnoncitaitosn et lots qui le concerne lui.
Grille de prix qui s'incremente ,Ajouter en masse et plans => en excel selon les colonnes. A fix pour incrementer CVS sur le 1er index du tableau de données
Nom des plans comme le nom du fichier sur serveur. Pour le faire remonter.