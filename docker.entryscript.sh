#!/bin/bash

INSTALLLOCKFILE='/repository/docroot/sites/default/files/install.lock'

if [ ! -f $INSTALLLOCKFILE ]
then
    echo "Drupal instance not found. Beginning installation."
    # Prep the install process
    cp ./docroot/sites/default/default.settings.php ./docroot/sites/default/settings.php
    chown www-data:www-data ./docroot/sites/default/settings.php
    # Run Drupal console Install
    drupal site:install -n standard  \
      --langcode=$DRUPAL_LANGCODE  \
      --db-type=$DRUPAL_DB_TYPE  \
      --db-host=$DRUPAL_DATABASE_HOST  \
      --db-name=$DRUPAL_DATABASE_NAME \
      --db-user=$DRUPAL_DATABASE_USERNAME \
      --db-pass=$DRUPAL_DATABASE_PASSWORD \
      --db-port=$DRUPAL_DATABASE_PORT \
      --site-name="$DRUPAL_SITE_NAME" \
      --site-mail=$DRUPAL_SITE_MAIL \
      --account-name="$DRUPAL_SITE_ADMIN_ACCOUNT_NAME" \
      --account-mail=$DRUPAL_SITE_ADMIN_ACCOUNT_MAIL \
      --account-pass=$DRUPAL_SITE_ADMIN_ACCOUNT_PASSWORD
    # Use Drush to install module suite. @todo: may require a more sophisticated profile
    # system here
    drush pm-enable \
            ctools \
            token \
            devel \
            pathauto \
            auto_entitylabel \
            rabbit_hole \
            svg_image \
            migrate_tools \
            migrate_source_csv \
            migrate_plus \
            paragraphs
    # Install a lockfile in persistent storage to indicate installation
    # @todo: This should run a test (perhaps on drupal console output) to
    # ensure installation was successful before writing
    touch $INSTALLLOCKFILE
else
    echo "Drupal instance found. No installation required."
fi
