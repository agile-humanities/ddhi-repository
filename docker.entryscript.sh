#!/bin/bash

INSTALLLOCKFILE='/repository/docroot/sites/default/files/install.lock'

if [ ! -f $INSTALLLOCKFILE ]
then
    cp ./docroot/sites/default/default.settings.php ./docroot/sites/default/settings.php
    chown www-data:www-data ./docroot/sites/default/settings.php
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
    touch $INSTALLLOCKFILE
elif [ $DRUPAL_UPDATE = "yes" ]
then
    composer update
fi
