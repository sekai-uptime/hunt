FROM php:fpm-alpine
LABEL Name=hunt Version=0.0.1


WORKDIR /app
COPY hunt.php index.php

EXPOSE 8000

CMD [ "php" , "-S" , "0.0.0.0:8000" ]