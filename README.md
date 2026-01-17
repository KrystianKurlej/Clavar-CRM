# Clavar CRM

Prosty, samodzielny system CRM (Customer Relationship Management) do zarządzania projektami i śledzenia czasu pracy. System został zaprojektowany z myślą o prostocie, łatwości instalacji i minimalnych wymaganiach technicznych.


## Funkcjonalności

### Zarządzanie projektami
- Tworzenie, edycja i usuwanie projektów
- Archiwizacja projektów
- Śledzenie czasu pracy w czasie rzeczywistym (timer start/stop)
- Ręczne ustawianie czasu bazowego dla projektów
- Automatyczne sumowanie czasu pracy

### Raporty
- Generowanie raportów czasowych dla wybranych projektów
- Kalkulacja kosztów na podstawie stawki godzinowej
- Eksport danych w formacie JSON
- Historia wszystkich raportów

### System użytkowników
- Wielousługowość - każdy użytkownik ma własną bazę danych SQLite
- Bezpieczna autentykacja z hashowaniem haseł (bcrypt)
- Ochrona CSRF dla wszystkich formularzy
- Sesje z konfigurowalnymi parametrami bezpieczeństwa

## Techniczne
- Lekka, bezserwerowa architektura (SQLite)
- Responsywny interfejs użytkownika (Bootstrap 5)
- System szablonów Latte
- AJAX do dynamicznych operacji
- REST API dla integracji zewnętrznych
- Gotowy do uruchomienia w kontenerze

## Struktura kodu

- **PSR-4 lite**: Klasy są ładowane ręcznie w [public/index.php](public/index.php#L16-L21)
- **MVC pattern**: Kontrolery, repozytoria, widoki
- **Repository pattern**: Dostęp do danych przez dedykowane klasy
- **Template engine**: Latte 3.x dla widoków