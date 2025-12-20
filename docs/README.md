# Dokumentacja projektu "Dokumenty"

## Cel projektu
Aplikacja sluzy do porzadkowania domowych dokumentow. Kazdy dokument jest przypisany do segregatora (ok. 10), ma dane wyszukiwalne oraz skan(y) zapisane jako media.

## Stos technologiczny
- Laravel 12 + Fortify (auth)
- Inertia.js v2 + Vue 3
- Tailwind CSS v4
- SQLite (domyslnie)
- Spatie Media Library (skany dokumentow, dysk prywatny)
- Spatie Login Link (logowanie linkiem)
- Prism PHP (zainstalowane, konfiguracja opublikowana)
- AI SDK UI (Vue) + ai + zod (zainstalowane, jeszcze nie zintegrowane w UI)

## Funkcje
- Segregatory (CRUD): nazwa, lokalizacja, opis, kolejnosc.
- Kategorie (CRUD): zamkniety zbior kategorii dla dokumentow.
- Dokumenty (CRUD): tytul, numer referencyjny, wystawca, kategoria (select), tagi, daty, notatki.
- Wyszukiwanie dokumentow: tekst, segregator, kategoria (id), zakres dat.
- Skany dokumentow: upload wielu plikow, prywatny dysk, pobieranie przez kontroler.
- Logowanie linkiem (lokalnie) jako pawel@cieplinski.pl.

## Model danych (skrot)
- binders
  - id, name, location, description, sort_order, timestamps
- categories
  - id, name, description, timestamps
- documents
  - id, binder_id, category_id, title, reference_number, issuer, tags, document_date, received_at, notes, timestamps
- media (Spatie)
  - powiazane z documents przez model Media Library

Relacje:
- Binder hasMany Document
- Document belongsTo Binder
- Category hasMany Document
- Document belongsTo Category
- Document hasMany Media (kolekcja "scans")

## Media library (prywatny dysk)
Skany sa zapisywane na dysku `private`.
- Konfiguracja w `config/media-library.php` (domyslnie `disk_name = private`).
- Dysk prywatny w `config/filesystems.php`.

Przyklad konfiguracji (snippet):
```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
MEDIA_DISK=private
```

Pobieranie plikow:
- `/documents/{document}/media/{media}` (GET) - pobierz skan
- `/documents/{document}/media/{media}` (DELETE) - usun skan

## Logowanie linkiem (Spatie Login Link)
Na stronie logowania jest link "Log in as pawel@cieplinski.pl" widoczny tylko w `APP_ENV=local`.
Wymaga istnienia uzytkownika o tym emailu w bazie.

Szybka konfiguracja uzytkownika (przyklad, do wykonania recznie):
- dodaj uzytkownika w seedzie lub przez tinker.

## Wyszukiwanie dokumentow
Parametry query:
- `q` - fraza tekstowa (title, reference_number, issuer, category name, notes, tags)
- `binder` - id segregatora
- `category` - id kategorii
- `from` / `to` - zakres dat dla `document_date`

## Strony Inertia (Vue)
- `resources/js/pages/binders/*`
- `resources/js/pages/categories/*`
- `resources/js/pages/documents/*`
- `resources/js/pages/auth/*`
- `resources/js/pages/settings/*`

## Seedery
`DatabaseSeeder` tworzy:
- uzytkownika: test@example.com
- 10 segregatorow
- po 5 dokumentow na segregator

Uruchomienie:
```bash
php artisan db:seed
```

## Uruchomienie projektu
1) Instalacja zaleznosci
```bash
composer install
npm install
```

2) Konfiguracja i migracje
```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
```

3) Dev server
```bash
composer run dev
```

Alternatywnie:
```bash
php artisan serve
npm run dev
```

## Testy
```bash
php artisan test
```

## Zainstalowane pakiety AI
- Prism PHP: konfiguracja w `config/prism.php` (jeszcze bez integracji runtime)
- AI SDK UI (Vue): paczki `ai`, `@ai-sdk/vue`, `zod` (jeszcze bez komponentow w UI)

## Struktura kluczowych plikow
- `app/Models/Binder.php`
- `app/Models/Document.php`
- `app/Http/Controllers/BinderController.php`
- `app/Http/Controllers/DocumentController.php`
- `app/Http/Controllers/DocumentMediaController.php`
- `app/Http/Requests/*`
- `resources/js/pages/binders/*`
- `resources/js/pages/documents/*`
- `config/media-library.php`
- `config/prism.php`

## Troubleshooting
- "no such table" (SQLite): uruchom `php artisan migrate`.
- Brak zmian w UI: uruchom `npm run dev`.
