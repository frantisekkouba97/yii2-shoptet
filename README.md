# Yii2 shoptet — správa produktů ze Shoptetu

## Lokální běh přes Docker (PHP 8.4 + MariaDB + Nginx)

- Předpoklady: Docker a Docker Compose v2
- Spuštění:
  docker compose up -d
  Instalace závislostí:
     - docker compose exec php composer install
  Aplikace poběží na: http://localhost:8000/
