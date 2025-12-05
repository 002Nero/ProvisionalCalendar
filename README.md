- BOCQUET Lucas: lbocquet0
- CHASTENET Valentin: 002Nero
- DESCOUTURES Cathy: Cat-dcts
- DESMOND Romain: RomainDesmond
- GENDRY Marine: Wiiinterz

# Calendrier prévisionnel

Application de gestion de calendrier provisoire développée avec Laravel et Vue.js.

## Prérequis

- Docker Desktop
- Git

### Cloner le projet
```bash
git clone https://github.com/0wme/ProvisionalCalendar.git
cd provisionnal-calendar
```


### Lancer le projet
```bash
docker compose up -d --build
```

## Commandes utiles

- **Arrêter l'environnement**
```bash
docker compose down
```

- **Voir les logs**
```bash
docker compose logs -f
```

- **Lancer un shell dans le conteneur de l'application php**
```bash
docker compose exec app bash
```

## Résolution des problèmes courants

### Les modifications Vue.js ou composer ne sont pas prises en compte
# TODO: Permettre au cache de se rafraîchir automatiquement
Supprimer les dossiers vendor et node_modules ainsi que les volumes `provisionalcalendar_node_modules` et `provisionalcalendar_vendor`
```bash
docker compose down
docker volume rm provisionalcalendar_node_modules provisionalcalendar_vendor
rm -rf vendor node_modules
docker compose up -d --build
```


## Liens utiles

- [PHP Documentation](https://www.php.net/)
- [Composer Documentation](https://getcomposer.org/)
- [Laravel Documentation](https://laravel.com/)
- [PHPUnit Documentation](https://phpunit.de/)

- [Inertia.js Documentation](https://inertiajs.com/)
- [Ziggy Documentation](https://github.com/tighten/ziggy)

- [Node.js Documentation](https://nodejs.org/)
- [Vue.js Documentation](https://vuejs.org/)
- [Vue Test Utils Documentation](https://test-utils.vuejs.org/)
- [Tailwind CSS Documentation](https://tailwindcss.com/)

- [Docker Documentation](https://www.docker.com/)
