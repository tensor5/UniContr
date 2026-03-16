# Sviluppo locale con Docker

## Prerequisiti

- Docker Desktop installato e avviato
- Docker Compose disponibile
- repository clonato con queste cartelle sorelle:
  - `unicontract-backend`
  - `unicontract-frontend`
  - `unicontract-mock-idp`
  - `unicontract-docker`

## Avviare i container
Entrare nella cartella:

```powershell
cd .\unicontract-docker\
```

Eseguire:

```powershell
docker compose build
docker compose up -d
```

I servizi esposti in locale sono:

- frontend Angular: `http://localhost:4200`
- nginx: `http://localhost`
- mock IdP: `http://localhost:7000`
- MariaDB dalla macchina host: `127.0.0.1:3308`

## Configurare il backend Laravel

Entrare nella cartella:

```powershell
cd ..\unicontract-backend\
```

Creare `.env` partendo da `.env.example` e verificare almeno questi parametri.

Per usare il database MariaDB del container Docker:

```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=unicontr
DB_USERNAME=unicontr
DB_PASSWORD=secret
```

Se invece vuoi collegarti dal backend a un database MySQL presente sulla macchina host:

```env
DB_CONNECTION=mysql
DB_HOST=host.docker.internal
```

Per la connessione Oracle esterna configurare:

```env
DB_USERNAME_ORACLE=...
DB_PASSWORD_ORACLE=...
DB_TNS=...
```

Per il mock IdP locale verificare anche:

```env
SAML2_IDP_HOST=http://localhost:7000/
SAML2_IDP_ENTITYID=http://localhost:7000/idp
IDP_ENV_ID=local
```

Nota: se esegui frontend e backend fuori Docker puoi usare `http://localhost:7000/`; se li esegui nei container e devono parlarsi tra loro, usa il nome servizio `idp`.

## Inizializzare il backend nel container

Dalla cartella `unicontract-docker` eseguire:

```powershell
docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan config:cache
docker compose exec backend php artisan migrate:fresh --seed
```

Se Oracle non e' disponibile in locale, il seed usa descrizioni fittizie nei punti dove non riesce a recuperarle.

## Verifica finale

Aprire l'applicazione su:

- `http://localhost:4200/` per il frontend Angular in sviluppo

