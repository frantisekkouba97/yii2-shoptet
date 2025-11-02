# Yii2 shoptet — správa produktů ze Shoptetu

Jednoduchá aplikace ve frameworku Yii2, která načítá produkty ze Shoptetu do lokální DB a zobrazuje jejich přehled na homepage. U každého produktu lze zobrazit detail (vč. aktuální ceny a kategorií z API) a jedním tlačítkem upravit popis (prefix "testFrantisek").

## Lokální běh přes Docker (PHP 8.4 + MariaDB + Nginx)

- Předpoklady: Docker a Docker Compose v2
- Spuštění kontejnerů:
  - `docker compose up -d`
- Instalace závislostí (poprvé):
  - `docker compose exec php composer install`
- Nastavení přístupu k DB (již přednastaveno):
  - Host: `database` (viz docker-compose)
  - DB: `app` | Uživatel: `app` | Heslo: `password`

## Migrace DB

- Vytvoření schématu (tabulky `product`, `category`, `product_category`):
  - `docker compose exec php php yii migrate` (potvrďte „Yes“)

## Naplnění dat (asynchronní job)

Pro ruční synchronizaci produktů ze Shoptetu je připraven CLI command:

- Spuštění synchronizace (včetně párování kategorií):
  - `docker compose exec php php yii shoptet/sync-products`

Poznámky:
- Respektuje rate-limit (mezi dotazy je krátký sleep + jednoduchý retry na 429).
- Nové produkty i kategorie se ukládají/aktualizují dle `guid`/`shoptet_id`.

## Webová část

- Aplikace běží na: http://localhost:8000/
- Homepage zobrazuje tabulku produktů (Bootstrap): název, kód, URL, obrázek, sklad, kategorie a akce.
- Tlačítko „Detail“ načte dynamicky z API aktuální cenu a kategorie a zobrazí je v modálním okně.
- Tlačítko „Upravit popis“ přidá prefix `testFrantisek` k existujícímu popisu produktu (REST volání na Shoptet) a uloží změnu i do lokální DB.


## Užitečné příkazy

- Spuštění migrací: `docker compose exec php php yii migrate`
- Spuštění synchronizace: `docker compose exec php php yii shoptet/sync-products`
