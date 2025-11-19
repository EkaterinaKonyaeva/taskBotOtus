#!/bin/sh
set -e

echo "Starting cron..."
crond

# wait for db to be ready
echo "Waiting for database..."
host="$DB_HOST"
user="$DB_USER"
pass="$DB_PASS"
db="$DB_NAME"
i=0
until mysqladmin ping -h"$host" -u"$user" -p"$pass" --silent; do
  i=$((i+1))
  if [ $i -gt 30 ]; then
    echo "Database did not become available"
    break
  fi
  sleep 1
done

echo "Running migrations..."
if [ -f /var/www/html/src/Database/Migrations/001_init.sql ]; then
  mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"     < /var/www/html/src/Database/Migrations/001_init.sql || true
  echo "Migrations applied."
else
  echo "No migration file found."
fi

echo "Starting PHP-FPM..."
php-fpm
