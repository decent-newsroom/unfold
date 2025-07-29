#!/bin/bash
set -e
export PATH="/usr/local/bin:/usr/bin:/bin"

php /var/www/html/bin/console articles:get -- '-1 week' 'now'
php /var/www/html/bin/console articles:qa
