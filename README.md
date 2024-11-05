# ePapier PHP (Symfony)

Repozytorium odpowiedzialne za część projektu wykorzystującą język PHP w frameworku Symfony.

> **Uwaga**: Projekt znajduje się w fazie rozwoju i nie stanowi wersji finalnej. W bieżącej wersji mogą występować błędy oraz niedociągnięcia logiczne, które będą poprawiane w przyszłych aktualizacjach.
>
> Dokumentacja również jest w trakcie tworzenia i może być uzupełniana wraz z postępem prac nad projektem.

## Cel projektu

Aby stworzyć interfejs graficzny wyświetlający zawartość na ekranie ePapier, zdecydowałem się na budowę aplikacji webowej w PHP przy użyciu frameworka Symfony. Projekt pełni również rolę edukacyjną, umożliwiając naukę pracy w Symfony.

## Zmienne środowiskowe

Przed uruchomieniem projektu należy utworzyć plik `.env` na podstawie pliku `.env.example` i uzupełnić go odpowiednimi wartościami:

    MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
    DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"

    ## Google API
    GOOGLE_CLIENT_ID=
    GOOGLE_PROJECT_ID=
    GOOGLE_AUTH_URI=
    GOOGLE_TOKEN_URI=
    GOOGLE_AUTH_PROVIDER_CERT_URL=
    GOOGLE_CLIENT_SECRET=

    ## Spotify API
    SPOTIFY_CLIENT_ID=
    SPOTIFY_CLIENT_SECRET=

    ## Adres do przekierowywania spoza localhost (ustaw nazwę domeny zgodną z ustawieniami w Cloudflare w projekcie Python, np. local.example.com)
    REDIRECT_URL=

    # Klucz szyfrowania, generowany przez base64_encode(openssl_random_pseudo_bytes(32)) - w wersji deweloperskiej należy wygenerować ręcznie
    # ENCRYPTION_KEY=

## Instalacja

Projekt można uruchomić na dwa sposoby:

### 1) Wersja produkcyjna (Docker)

- Uruchom za pomocą komendy:

  ```bash
  docker compose -f "compose.yaml" up -d --build production-server
  ```

- Alternatywnie, utwórz obraz, a następnie kontener, udostępniając trzy porty:
  - 80
  - 443
  - 443/udp

### 2) Wersja deweloperska

a) Wymagane jest zainstalowanie PHP oraz zależności poprzez Composer. W pliku `.env` należy wygenerować `ENCRYPTION_KEY` zgodnie z instrukcjami w `.env.example`.

b) Uruchom serwer Symfony przy użyciu Docker Compose:

```bash
docker compose -f "compose.yaml" up -d --build development-server
```

Zmienne środowiskowe dla wersji deweloperskiej powinny znajdować się w pliku `.env.dev`.

W obu przypadkach należy pamiętać o wygenerowaniu schematu bazy danych komendą:

```bash
php bin/console doctrine:schema:update --force
```
