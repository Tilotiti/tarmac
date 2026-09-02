# Tarmac

## Déploiement

Heroku Scheduler, une fois par jour : `php bin/console app:users:purge-unverified`. L'add-on s'active depuis le dashboard Heroku ; ce n'est pas automatisable depuis le dépôt.

Une fois par jour également : `php bin/console app:imports:purge`.

Après chaque déploiement qui touche la table `aerodrome` (première mise en production incluse) : `heroku run php bin/console app:aerodromes:import`. La commande est idempotente.
