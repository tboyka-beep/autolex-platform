# Autolex Dev Pipeline

## GitHub Secrets

Repository Settings → Secrets and variables → Actions → New repository secret

Required secrets:

- CPANEL_HOST
- CPANEL_USER
- CPANEL_PORT
- CPANEL_SSH_KEY
- WP_PLUGIN_PATH

Example WP_PLUGIN_PATH:

/home/CPANELUSER/public_html/wp-content/plugins/autolex-platform/

## Folder structure

Put the WordPress plugin here:

plugin/autolex-platform/

The main plugin file should be:

plugin/autolex-platform/autolex-platform.php

## Deploy

Push to main, or run:

Actions → Build and Deploy Autolex Plugin → Run workflow
