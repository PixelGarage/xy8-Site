## MODULE UPDATES

    // Update Drupal Core and modules
    composer update (--no-dev)
    
    // update db and reset cache
    drush updb
    drush cr
    
    // export new config (config-export)
    drush cex
    

## MODULE INSTALLATION

    // Install new modules with composer
    composer require drupal/module:<version> e.g. 3.0.0-beta1
    
    // Remove packages from installation
    composer remove vendor/package
    
    INSTALLATION ERROR SEARCH
    //Check what blocks the update, if update failed:
    composer prohibits drupal/core:8.6.0


