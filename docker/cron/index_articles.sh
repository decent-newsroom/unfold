#!/bin/bash
set -e

# Run Symfony commands sequentially
/usr/local/bin/php /var/www/html/bin/console articles:get
/usr/local/bin/php /var/www/html/bin/console articles:qa
/usr/local/bin/php /var/www/html/bin/console articles:index
/usr/local/bin/php /var/www/html/bin/console articles:indexed
