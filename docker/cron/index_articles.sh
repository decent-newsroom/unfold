#!/bin/bash
set -e
export PATH="/usr/local/bin:/usr/bin:/bin"

# Run Symfony commands sequentially
php /var/www/html/bin/console articles:get -- '-1 week' 'now'
php /var/www/html/bin/console articles:qa
php /var/www/html/bin/console articles:index
php /var/www/html/bin/console articles:indexed
