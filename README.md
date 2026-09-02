# Tarmac

## Déploiement

Heroku Scheduler, une fois par jour : `php bin/console app:users:purge-unverified`. L'add-on s'active depuis le dashboard Heroku ; ce n'est pas automatisable depuis le dépôt.
