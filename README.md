# Tous en couple

Site du jeu annuel « Tous en couple » : déclarations, soirée de juillet-août, classement, historique, amendements.
PHP 8 + PDO, HTML/CSS/JS simples, sans étape de build. SQLite par défaut, MySQL possible.

## Installation
```
cp config.example.php config.php        # facultatif (SQLite par défaut)
php bin/install.php --admin=matt --nom=Matt --annee=2027
```
La commande affiche un mot de passe provisoire (à changer à la première connexion).

## Lancer en local
```
php -S localhost:8000 -t public public/index.php
```
Données de démonstration (base vide seulement) : `php bin/seed-demo.php` (mot de passe `demo-demo`).
Tests : `php tests/run.php`

## Déploiement (hébergement classique)
1. Le **dossier web racine doit être `public/`** : `src/`, `storage/` et `config.php` ne doivent jamais être accessibles depuis le web.
2. Créer `config.php` (MySQL : voir `config.example.php`), puis lancer `php bin/install.php`.
3. Activer HTTPS. Sauvegarder régulièrement la base (`storage/app.sqlite` ou dump MySQL).
4. Mise à jour : `php bin/migrate.php`.

## Organisation
- `src/Scoring/` : barème et calcul (pur, testé) · `src/YearService.php` : calcul et clôture d'une année
- `src/Repo/` : accès aux données · `src/Controller/` + `templates/` : pages
- Le barème est versionné en base (`rule_versions`) ; une année clôturée est figée (`year_results`).
