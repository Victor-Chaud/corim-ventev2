# Phase 3 : consignes communes aux agents

Projet : `/home/etiseo/www/corim-vente`. `OLD/` = ancienne application Kohana (référence, lecture seule, ne jamais l'exécuter). `NEW/` = refonte Laravel 13 / PHP 8.5 à vérifier.

À lire avant de commencer : `/home/etiseo/www/corim-vente/CLAUDE.md` (règles de la refonte, contrat de l'API publique, checklist sécurité), `NEW/docs/conventions.md`, `NEW/docs/suivi.md`, puis le contrat de ton module dans `NEW/docs/contract/`.

## Règle de périmètre

Le même projet à l'identique : mêmes URL, mêmes textes, même HTML, mêmes e-mails, même API. Seules différences admises : les plantages de l'ancien code remplacés par le résultat visé, la sécurité apportée par Laravel sans changement visible, et les évolutions de novembre 2026 décrites dans `NEW/docs/plan-action.md` et `NEW/docs/ameliorations.md` (lot 5 non fait, c'est voulu).

## Ton rôle : vérificateur

- Tu ne modifies **aucun** fichier de l'application (`app/`, `routes/`, `resources/`, `config/`, `database/`, `public/`, `bootstrap/`, `composer.*`), ni les tests existants, ni `OLD/`.
- Tu peux écrire uniquement : ton rapport `NEW/docs/phase3/<nom>.md`, et de nouveaux tests Pest dans `NEW/tests/Feature/Phase3/<Nom>/` (un dossier par agent). Regarde `tests/Pest.php` et `tests/Helpers/` pour les helpers existants (`loadLegacy()`, connexion par rôle, etc.) et réutilise-les.
- Chaque écart trouvé qui peut se tester reçoit un test Pest qui échoue aujourd'hui et passera une fois corrigé. Chaque fonctionnalité du contrat sans test existant reçoit un test (qui passe si le comportement est bon).
- Aucun commentaire dans le code, y compris dans les tests (`//`, `/* */`, docblocks). `php8.5 vendor/bin/pint tests/Feature/Phase3/<Nom>` avant de rendre.

## Outils

- Toujours `php8.5` (le `php` par défaut est 8.1 et ne marche pas).
- Tests : `cd /home/etiseo/www/corim-vente/NEW && flock storage/framework/pest.lock php8.5 vendor/bin/pest tests/Feature/Phase3/<Nom>` (le verrou est obligatoire, la base de test est partagée entre agents). Ne lance pas la suite complète en boucle : ton dossier, plus les dossiers du module si besoin.
- Base de test `corim_new_test`. Base de dev `corim_new` et connexion `legacy` : lecture seule (SELECT). Jamais `migrate:fresh`, `legacy:import`, `artisan serve`, `queue:work`.
- Aucun accès réseau, aucune connexion à la prod, aucun envoi de mail réel, aucun `git commit`/`push`/branche.
- Recherche dans `OLD/` : l'outil codebase-memory-mcp (projet `home-etiseo-www-corim-vente-OLD`) si disponible, sinon grep ciblé dans `OLD/application/` (ne pas parcourir `OLD/medias/programmes`, 1,3 Go).

## Rapport attendu (`NEW/docs/phase3/<nom>.md`, en français, prose simple)

1. Verdict : PASS ou FAIL.
2. Ce qui a été vérifié (liste courte, avec la preuve : test, commande, fichier:ligne).
3. Écarts, classés bloquant / moyen / mineur. Pour chacun : fichier:ligne dans NEW, comportement attendu (avec la référence dans OLD ou dans le contrat), comportement constaté, test Pest qui le montre, correction proposée en une phrase.
4. Points non vérifiables en local (à faire à la main ou en préproduction).

Ne signale que ce que tu as vérifié dans le code ou par un test. Pas de supposition. Ta réponse finale au lead : verdict, nombre d'écarts par gravité, chemin du rapport, liste des tests ajoutés (passent / échouent).
