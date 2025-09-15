# Calendrier prévisionnel

Application de gestion de calendrier provisoire développée avec Laravel et Vue.js.

## Prérequis

- Docker Desktop
- Composer
- Node.js (v18+)
- Git

## Installation
Dans le cas où vous utilisez une VM linux il faut ajouter les ports suivant:

<img width="640" height="392" alt="image" src="https://github.com/user-attachments/assets/03d69105-b469-4ac2-b0ec-4ed1d7317b25" />

### Cloner le projet
```bash
git clone https://github.com/0wme/ProvisionalCalendar.git
cd provisionnal-calendar
```

### Configuration de l'environnement
Créez le fichier d'environnement :
```bash
cp .env.example .env
```

### Installation de Laravel Sail

#### Windows

1. Installez PHP à l'aide la commande Powershell suivante : 
```powershell
powershell -c "& ([ScriptBlock]::Create((irm 'https://www.php.net/include/download-instructions/windows.ps1'))) -Version 8.4"
```

2. Installez composer
Téléchargez et exécutez le programme d'installation de Composer depuis [getcomposer.org](https://getcomposer.org/).

3. Activation des extensions php.
Rendez-vous dans le fichier `C:/php/php.ini` et décommentez les lignes suivantes (en supprimant le point-virgule `;` au début) :
```ini
extension=bz2
extension=fileinfo
extension=zip
extension=pdo_mysql
extension=sqlite3
```

4. Installation des dépendances
```bash
composer install
```

1. Utilisation de sail
Pour permettre l'exécution de `sail` sous Windows sans passer par `WSL 2`, nous allons devoir ruser un peu.
Modifiez le fichier `vendor/laravel/sail/bin/sail` en supprimant les lignes suivantes : 
```bash
if [ "$MACHINE" == "UNKNOWN" ]; then
    echo "Unsupported operating system [$(uname -s)]. Laravel Sail supports macOS, Linux, and Windows (WSL2)." >&2

    exit 1
fi
```

*Note: Il est possible qu'il soit de-nouveau nécessaire de modifier ce fichier lors d'une mise à jour de Sail.*

Ouvrez ensuite un `Bash` (`Git Bash` par exemple) et exécutez les commandes suivantes :
```bash
echo "alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'" >> ~/.bashrc
source ~/.bashrc
```

#### Linux / macOS
Installer php grâce aux commandes suivante :
```bash
sudo apt-get update
sudo apt-get install -y lsb-release ca-certificates apt-transport-https curl
sudo curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
sudo dpkg -i /tmp/debsuryorg-archive-keyring.deb
sudo sh -c 'echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'
sudo apt-get update

sudo apt-get install -y php8.4
```

Installation de Composer :
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

php composer-setup.php --install-dir=/usr/local/bin --filename=composer

# Permet de supprimer le programme d'installation de composer
php -r "unlink('composer-setup.php');"

# Augmenter la rapidité de composer
sudo apt install -y php-curl && unzip

# Activer l'extension DOM
sudo apt update
sudo apt install php-xml
```

Configurez l'alias Sail (facultatif mais recommandé) :
```bash
# Pour macOS (dans ~/.zshrc)
# Pour Linux (dans ~/.bashrc)
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```

#### **Installation des dépendances**
```bash
composer install
```

### Démarrage

#### **Lancer l'environnement Docker**
```bash
sail up -d
```

#### **Configuration de la base de données**
```bash
sail artisan key:generate
sail artisan migrate
sail php artisan db:seed
```

#### **Installation et compilation des assets**
```bash
sail npm install
```

L'application est maintenant accessible à l'adresse : http://localhost

## Première connection au service
utilisateur : admin
mot de passe : admin123


## Développement

- **Lancer le serveur de développement**
```bash
sail npm run dev
```

- **Lancer les tests**
```bash
sail artisan test
```

- **Accéder à la base de données**
```bash
sail mariadb
```

## Commandes utiles

- **Arrêter l'environnement**
```bash
sail down
```

- **Voir les logs**
```bash
sail logs
```

- **Lancer un shell dans le conteneur**
```bash
sail shell
```

## Résolution des problèmes courants

### Erreur : Access denied for user 'sail'@'<ip>' (using password: YES)

Cette erreur survient lorsqu'un volume Docker existant entre en conflit.

Solution :
```bash
# Supprimer le volume existant
docker volume rm sail-mariadb

# Redémarrer l'environnement
sail down
sail up -d
```

### Les modifications Vue.js ne sont pas prises en compte

1. Vérifiez que le serveur Vite est en cours d'exécution :
```bash
sail npm run dev
```

2. Si le problème persiste, essayez de :
```bash
sail npm cache clean --force
sail npm install
sail npm run dev
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
