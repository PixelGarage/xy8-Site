##Installing Drupal

###Project creation
Create a minimal drupal project and add some tools for optimisation.

```bash
composer create-project drupal/recommended-project <project-folder>
cd <project-folder>

composer remove drupal/core-project-message
composer require drush/drush
composer require zaporylie/composer-drupal-optimizations
```
###Create top directories
Create directories for private and temporary files outside of the website root.
```bash
mkdir config/sync
mkdir private_files
mkdir tmp
```


### Site installation
Install site with drush.
```bash
drush site:install --account-name=dpAdmin --account-pass=dpAdmin!
```

###Create an .env file
Add the PHP dot env library.
```bash 
composer require vlucas/phpdotenv
```

Keep sensitive and personal environment parameters in a .env file. Add the files .env, .env.example and load.environment.php to top folder.

Add the following to composer.json file.
```composer
  "autoload": {
    "files": [
      "load.environment.php"
    ]
  }
```

### Adapt settings file
Adapt the following settings in the web/sites/default/settings.php file.
```
$databases['default']['default'] = array (
  'database' => getenv('MYSQL_DATABASE'),
  'driver' => 'mysql',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'host' => getenv('MYSQL_HOSTNAME'),
  'port' => getenv('MYSQL_PORT'),
  'prefix' => '',
  'username' => getenv('MYSQL_USER'),
  'password' => getenv('MYSQL_PASSWORD'),
);
$settings['config_sync_directory'] = '../config/sync';
$settings['file_private_path'] = '../private_files';
$settings['file_temp_path'] = '../tmp';
$settings['trusted_host_patterns'] = array(
  '^.+\.localtunnel\.me$',
  '^localhost$',
);
```

###Enable local development override configuration
See https://drupalize.me/tutorial/configure-your-environment-theme-development?p=2512 for explanation.

Skip the overridden development services file in scaffolding by adding the following to the "drupal-scaffold" section in composer.json file.
```composer
"file-mapping": {
    "[web-root]/sites/development.services.yml": false
}
```


###Enable services and CORS
Copy the file web/sites/default/default.services.yml to web/sites/default/services.yml

Enable CORS with the following settings:
```
  cors.config:
    enabled: true
    # Specify allowed headers, like 'x-allowed-header'.
    allowedHeaders: ['*']
    # Specify allowed request methods, specify ['*'] to allow all possible ones.
    allowedMethods: ['*']
    # Configure requests allowed from specific origins.
    allowedOrigins: ['localhost:3000']
    allowedOriginsPatterns:
      - '/localhost:\d+/'
    # Sets the Access-Control-Expose-Headers header.
    exposedHeaders: false
    # Sets the Access-Control-Max-Age header.
    maxAge: false
    # Sets the Access-Control-Allow-Credentials header.
    supportsCredentials: false
```

###Download third-party libraries with composer
Enable the loading of npm or bower libraries with composer.
```bash
mkdir web/libraries
composer require oomphinc/composer-installers-extender
composer config repositories.npm composer https://asset-packagist.org
```

Update "installer-types" and "installer-paths" in the "extra" section of composer.json
```composer
"extra": {
    "installer-types": [
        "npm-asset",
        "bower-asset"
    ],
    "installer-paths": {
        "web/libraries/{$name}": [
            "type:drupal-library",
            "type:npm-asset",
            "type:bower-asset"
        ]
    }
}
```

###Patching projects
Require the library. This is a simple patches plugin for Composer. Applies a patch from a local or remote file to any package required with composer.
```bash
composer require cweagans/composer-patches
```

Add the following to the "extra" section in composer.json:
```composer
  "extra": {
    "enable-patching": true,
    "composer-exit-on-patch-failure": true,
    "patchLevel": {
      "drupal/core": "-p2"
    },
  }
```

A patch is added as follows to the "extra" section:
```composer
  "patches": {
    "vendor/project": {
      "Patch title": "http://example.com/url/to/patch.patch"
    }
  }
```

###Setup deployment
Setup the deployer library (see https://deployer.org/).
```bash
composer require deployer/deployer
```

Add the deploy command to the scripts section in composer.json.
```composer
  "scripts": {
    "deploy": [
      "@php vendor/bin/dep deploy"
    ]
  },
```

Create the deploy.php file in the top directory.


###Manage dependencies for custom modules and themes
To let composer manage the dependencies for any custom module or theme, perform the following commands.
```bash
composer config repositories.custom-module path web/modules/custom/*
composer config repositories.custom-theme path web/themes/custom/*
```
Now all custom modules can be required like any other module in a repository:
```bash
composer require org/custom-module
```


###Git the project
Copy .gitignore and initialize project repository.
```bash
git init
git checkout -b master
```

###Module installation
The following modules are essential tools and therefore have been installed and enabled.

####Custom Pixelgarage Theme
drupal/bootstrap_barrio
npm-asset/bootstrap
pixelgarage/pixelgarage

```bash
composer require pixelgarage/pixelgarage
```

####Administration tools
drupal/admin_toolbar
drupal/backup_migrate
drupal/coffee
drupal/entity_clone
drupal/diff
```bash
composer require drupal/admin_toolbar drupal/backup_migrate drupal/coffee drupal/entity_clone drupal/diff
drush en admin_toolbar, backup_migrate, coffee, diff, entity_clone
```

####Development tools
drupal/core-dev
drupal/twig_xdebug

_Remark_: The drupal/core-dev modules fails to be installed (Feb.2020)

```bash
composer require --dev drupal/core-dev drupal/twig_xdebug
drush en core-dev, twig_xdebug

composer require drupal/bamboo_twig
drush en bamboo_twig
```

####Utility tools
drupal/token

```bash
composer require drupal/token
drush en token
```

####Additional fields
drupal/address
drupal/video_embed_field

```bash
composer require drupal/address drupal/video_embed_field
drush en address, video_embed_field
```

####Editors
drupal/gutenberg

drupal/linkit
drupal/editor_advanced_link
drupal/ckeditor_loremipsum
drupal/focal_point

```bash
composer require drupal/gutenberg
drush en gutenberg
composer require drupal/linkit drupal/editor_advanced_link drupal/focal_point drupal/ckeditor_loremipsum
drush en linkit, editor_advanced_link, focal_point, ckeditor_loremipsum
```

####SEO tools
drupal/pathauto
drupal/metatag
drupal/redirect
drupal/google_analytics
drupal/simple_sitemap
drupal/rabbit_hole

```bash
composer require drupal/pathauto drupal/metatag drupal/redirect drupal/google_analytics drupal/simple_sitemap drupal/rabbit_hole
drush en pathauto, metatag, redirect, google_analytics, simple_sitemap, rabbit_hole
```

####Webforms
drupal/webform

```bash
composer require drupal/webform
drush en webform
```

####Spam prevention
drupal/antibot

```bash
composer require drupal/antibot
drush en antibot
```



The following modules list shows an excerpt of useful modules thematically sorted.

####Menu
drupal/responsive_menu

####Layout tools
drupal/ds
drupal/paragraphs
drupal/bootstrap_layouts
drupal/views_bootstrap

####Twig tools
drupal/bamboo_twig

####Decoupled Drupal modules
drupal/consumers
drupal/consumer_image_styles
drupal/decoupled_router
drupal/jsonapi_extras
drupal/jsonrpc
drupal/openapi
drupal/openapi_ui
drupal/openapi_ui_redoc
drupal/restui
drupal/simple_oauth
drupal/subrequests

####Mail system
drupal/mailsystem
drupal/swiftmailer

####Browser tools
drupal/entity_browser
drupal/media_entity_browser

####GDPR compliance
drupal/eu_cookie_compliance
