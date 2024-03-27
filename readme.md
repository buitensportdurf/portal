# Getting started
## Installation (generic)
Install the following tools:
- Git
- PHP version 8.3+ (php-fpm and php-cli)
- MySQL or MariaDB
- [Composer](https://www.digitalocean.com/community/tutorials/how-to-install-and-use-composer-on-ubuntu-22-04)
- [Symfony CLI](https://symfony.com/download)

## Installation MacOS
```console fish
brew install php
brew install mariadb
brew services start mariadb
brew install composer
brew install symfony-cli/tap/symfony-cli
sudo mariadb-secure-installation
# Decline switch to unix sockets, answer Yes to rest to complete setup
```

## Setup
Clone the repository
```bash
git clone git@github.com:buitensportdurf/portal.git
cd portal
```

Create a database (and user) in MySQL/MariaDB
```sql
sudo mysql
CREATE DATABASE `durf`;
CREATE USER 'durf'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON `durf`.* TO 'durf'@'localhost';
```

Copy the `.env` file to `.env.local` and edit all the required fields:
```bash
cp .env .env.local
```

Install the composer dependencies:
```bash
composer install
```

Run the migrations:
```bash
php bin/console doctrine:migrations:migrate
```

Start the server:
```bash
symfony server:start
```

Open your browser and go to `http://localhost:8000`
