# Simple CRM (PHP + SQLite)

MVP: logowanie użytkowników, brak rejestracji przez WWW, każdy użytkownik ma osobny plik SQLite. Routing bez frameworka, proste widoki.

## Struktura

- `public/` — punkt wejścia (`index.php`)
- `app/` — pomocnicze klasy (`Auth`, `DB`, helpery)
- `views/` — czysty HTML/PHP (login, dashboard)
- `bootstrap/` — konfiguracja
- `data/` — pliki SQLite (per użytkownik)
- `data/users/` — metadane użytkowników (JSON z hashami haseł)
- `scripts/` — skrypty CLI (tworzenie użytkownika)

## Szybki start

1. Utwórz użytkownika (CLI):

```bash
php scripts/create_user.php twoj@email.pl "TwojeHaslo!123"
```

2. Uruchom serwer developerski PHP (wskazując katalog `public/`):

```bash
php -S 127.0.0.1:8000 -t public
```

3. Wejdź na `http://127.0.0.1:8000/login` i zaloguj się.

## Założenia

- Brak rejestracji w interfejsie WWW (tylko `scripts/create_user.php`).
- Po zalogowaniu przekierowanie na `/` (prosty dashboard placeholder).
- CSRF dla POST, sesje HttpOnly, SameSite=Lax.

## Następne kroki

- Dodać migracje per-użytkownik (tabele: projects, time_entries) po zalogowaniu.
- Moduł projektów i wpisów czasu.
- Raporty i eksport CSV.

## Deploy i bezpieczeństwo plików bazy

Najbezpieczniej trzymać pliki SQLite poza katalogiem publicznym (DocumentRoot). Masz 3 opcje:

1) Zmień DocumentRoot na katalog `public/` (rekomendowane)
	 - Apache: VirtualHost z `DocumentRoot /sciezka/projektu/public`
	 - Nginx: `root /sciezka/projektu/public;` i `try_files $uri /index.php?$query_string;`
	 - W repo dołączono `.htaccess` w `public/` oraz w root (fallback 302 do /public), ale najlepiej ustawić to w konfiguracji serwera.

2) Ustaw katalog danych poza webrootem
	 - Użyj zmiennych środowiskowych:
		 - `CRM_DATA_DIR=/var/app/crm-data`
		 - `CRM_USERS_DIR=/var/app/crm-data/users`
	 - lub pliku `bootstrap/config.local.php` (gitignored), np.:
		 ```php
		 <?php
		 return [
			 'data_dir' => '/var/app/crm-data',
			 'users_dir' => '/var/app/crm-data/users',
		 ];
		 ```
	 - Skrypt `scripts/create_user.php` i backend użyją tych ścieżek.

3) Gdy nie możesz zmienić DocumentRoot (np. shared hosting z public_html)
	 - Umieść cały katalog `data/` i `bootstrap/config.local.php` poza `public_html` (np. `~/crm-data`) i ustaw `CRM_DATA_DIR`/`CRM_USERS_DIR`.
	 - Alternatywnie użyj symlinków: `data -> /home/user/crm-data` (upewnij się, że webserver nie serwuje tych plików; najlepiej trzymaj je poza public_html).

Ważne: katalogi `data/` i `data/users/` są w `.gitignore` — nie commituj ich do repo.
