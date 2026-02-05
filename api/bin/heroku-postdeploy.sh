#!/bin/bash
# Parse JAWSDB_URL into DATABASE_URL for Doctrine if present
if [ -n "$JAWSDB_URL" ]; then
    export DATABASE_URL=$(echo "$JAWSDB_URL" | sed 's/^mysql:\/\//mysql:\/\//' )
fi

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
