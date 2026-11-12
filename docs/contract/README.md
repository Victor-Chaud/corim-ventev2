# Phase 0 — Contrats de la refonte

Statut : brouillons rédigés le 2026-10-27, **à valider par Victor** avant la phase 1.

| Module | Fichier | URL / actions | Critères |
|---|---|---|---|
| Utilisateurs, rôles, privilèges | [auth-users-privileges.md](auth-users-privileges.md) | 17 | 29 |
| Programmes et lots | [programmes-lots.md](programmes-lots.md) | 32 | 32 |
| Workflow commercial | [workflow.md](workflow.md) | 34 | 34 |
| Clients et prospects | [clients-prospects.md](clients-prospects.md) | 11 | 22 |
| Promoteurs | [promoteurs.md](promoteurs.md) | 8 | 32 |
| Communication | [communication.md](communication.md) | 23 | 30 |
| Partie publique et gabarit | [public.md](public.md) | 22 | 31 |
| API publique | [public-api.md](public-api.md) | 13 | 38 |

Références : [schema-legacy.sql](schema-legacy.sql) (structure des 23 tables, sans données), `../../tests/Fixtures/legacy-api/` (réponses prod enregistrées).

## Constats transverses

- **Aucune action `json/*` n'exige de connexion** (`OLD/application/controllers/__json.php` ne vérifie jamais la session). Touche tous les modules : création d'admin, prise de compte, lecture de la base clients, validation/annulation de ventes, suppression de fichiers, envoi d'emails HTML depuis l'adresse officielle.
- **`isAdmin()` / `isConnected()` redirigent sans `exit`** : la page continue de s'exécuter, un prescripteur connecté exécute les actions admin.
- Injections SQL, XSS stockées et réfléchies (`?callback=`), aucun CSRF, suppressions en GET.
- Uploads sans contrôle d'extension dans `modifier_programme.php` vers `medias/` qui exécute le PHP.
- Mots de passe en clair (`pwdshow`) et envoyés par email ; identifiants MySQL en dur dans `model.php:8` et `refused_ask.php:20` (présents dans l'historique git).
- Pages d'erreur Kohana affichant chemins et SQL.
- Données orphelines après suppressions (acces_lot, prospects, mp, ventes) à nettoyer avant les clés étrangères InnoDB.
- La copie locale de `medias/programmes` est incomplète (10 projets sans dossier) : un inventaire des fichiers de prod sera nécessaire pour la reprise.

Règle commune proposée pour toute la refonte : **tout ce qui est cassé en prod n'est pas reproduit** ; le comportement visiblement voulu est implémenté, avec le même rendu.

## Règle de la refonte (décidée par Victor le 2026-10-27)

**Le même projet, en Laravel et PHP à jour, pas plus.** Aucune décision fonctionnelle à prendre :

- Toutes les pages, actions, écrans, textes, emails (sujets et corps) et l'API sont reproduits à l'identique, y compris les fonctions peu utilisées.
- Rien n'est ajouté, rien n'est supprimé, aucun changement de design.
- La sécurité vient de Laravel lui-même, sans changer le comportement visible : connexion exigée là où l'interface la suppose, contrôles de rôle effectifs, requêtes paramétrées, échappement Blade, CSRF, mots de passe hachés (les emails qui contiennent le mot de passe l'envoient toujours au moment de la saisie, mais il n'est plus stocké en clair).
- Ce qui plante en prod (erreur 500, `var_dump`) produit le résultat que le code visait, avec le même rendu, au lieu de l'erreur.
- Données migrées telles quelles ; seules les lignes orphelines qui empêchent les clés étrangères sont écartées, avec un rapport.

Les « Questions ouvertes » des contrats ne bloquent pas la refonte : chaque builder applique cette règle.
