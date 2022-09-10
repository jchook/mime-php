php_port := "3030"

build:
  docker build -t php-mime .

down:
  docker stop php-mime-1

root:
  docker exec -u root -it php-mime-1 sh

run:
  docker run --name php-mime-1 --rm --init -dt \
    --name php-mime-1 \
    --env XDEBUG_MODE=coverage \
    --env COMPOSER_HOME=/composer \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd)":/app:rw \
    -p {{php_port}}:{{php_port}} \
    php-mime php -S 0.0.0.0:{{php_port}}

sh:
  docker exec -it \
    -u "$(id -u):$(id -g)" \
    php-mime-1 sh

up: build run

