# laravelhome

https://github.com/FriendsOfPHP/Goutte
php artisan make:controller HtmlAnalysisController
composer require fabpot/goutte

#after clone
composer install
cp .env.example .env

vi .env
APP_ENV=local
APP_DEBUG=true
APP_KEY=


## How to Setup a Laravel Project You Cloned from Github.com ##
1. Clone GitHub repo for this project locally
git clone linktogithubrepo.com/ projectName

2. cd into your project

3. Install Composer Dependencies
composer install

4.Install NPM Dependencies
npm install

5.Create a copy of your .env file
cp .env.example .env

6.Generate an app encryption key
php artisan key:generate

7.Create an empty database for our application

8.In the .env file, add database information to allow Laravel to connect to the database

9.Migrate the database
php artisan migrate

10.[Optional]: Seed the database
php artisan db:seed

