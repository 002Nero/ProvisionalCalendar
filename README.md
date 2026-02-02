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

## Services

L'application utilise plusieurs services Docker :

- **app** : Application Laravel (PHP)
- **nginx** : Serveur web
- **mariadb** : Base de données
- **node** : Build des assets Vue.js
- **phpmyadmin** : Interface de gestion de base de données (http://localhost:8080)
- **generator** : Serveur Python pour la génération d'emplois du temps (http://localhost:5000)

## Commandes utiles

- **Arrêter l'environnement**
```bash
docker compose down
```

- **Voir les logs**
```bash
docker compose logs -f
```

- **Voir les logs d'un service spécifique**
```bash
docker compose logs -f generator
```

- **Lancer un shell dans le conteneur de l'application php**
```bash
docker compose exec app bash
```

- **Lancer un shell dans le conteneur du générateur Python**
```bash
docker compose exec generator bash
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


## Générateur d'emplois du temps

Le serveur Python de génération d'emplois du temps est disponible sur le port 5000.

### API Endpoints

- **Health Check** : `GET http://localhost:5000/api/heartbeat`
- **Générer un emploi du temps** : `POST http://localhost:5000/api/generate`

### Configuration

Le générateur utilise les variables d'environnement suivantes :
- `DB_HOST` : Hôte de la base de données (mariadb)
- `DB_DATABASE` : Nom de la base de données
- `DB_USERNAME` : Utilisateur de la base de données
- `DB_PASSWORD` : Mot de passe de la base de données

Pour plus de détails sur le générateur, consultez [generator/README.md](generator/README.md)

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

- [Flask Documentation](https://flask.palletsprojects.com/)
- [OR-Tools Documentation](https://developers.google.com/optimization)

- [Docker Documentation](https://www.docker.com/)
