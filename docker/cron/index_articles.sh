#!/bin/bash
set -e
export PATH="/usr/local/bin:/usr/bin:/bin"

php /var/www/html/bin/console articles:get -- '-6 min' 'now'
