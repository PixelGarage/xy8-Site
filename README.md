##Drupal starter kit
This Drupal starter kit has been created by following the instructions in the file "README-Install Drupal.md".

To create a new Drupal project with the starter kit, perform the following steps:

1) Copy this folder an rename it with the project name
2) Delete **_.git folder_** in the top directory of this folder
3) Create a local database and import the database backup in the private_files folder
4) Duplicate the .env.example file and rename it to .env
5) Add the database credentials to the .env file
6) Adapt the deploy.php file to the specific needs (repository etc.)
7) Update the modules and dependencies with composer and update the database and clear caches 
```bash
composer update
drush updb
drush cr
```
You're ready to go.

###Backend login
The initial Drupal Administrator login has the following credentials:

name: dpAdmin
pwd: dpAdmin!

PLEASE change the password as soon as possible!
