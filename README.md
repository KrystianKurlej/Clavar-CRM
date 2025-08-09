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
