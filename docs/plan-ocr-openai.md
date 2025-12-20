# Plan: OCR + OpenAI (PrismPHP) + UI streaming

## Cel
Po wgraniu nowego pliku na stronie tworzenia dokumentu (`/documents/create`) najpierw wykonac OCR do tekstu, a nastepnie przekazac wynik do LLM (PrismPHP + OpenAI, streaming). UI ma na biezaco aktualizowac pola (tytul, notatki, kategoria, tagi, daty) i pokazywac po prawej stronie panel typu chat z pelnym logiem (OCR, tool calls, reasoning, itp.).

## Zalozenia i ograniczenia
- OCR: lokalnie (np. Tesseract) lub inny engine kompatybilny z PHP/Laravel.
- Integracja LLM: PrismPHP z opcja stream.
- Frontend: uzyc zainstalowanego Vercel AI SDK (Vue).
- Etapy wdrozeniowe zgodnie z kolejnoscia ponizej.
- Brak zmian w zaleznosciach bez zgody.

## Etap 1 — OCR ("OCP" w opisie uzytkownika; zakladam OCR)
Zakres: tylko OCR i zapis tekstu, bez LLM.

1) Analiza
- Zidentyfikowac obecny flow uploadu na `/documents/create` (kontroler, request, media library).
- Sprawdzic, gdzie najlepiej podpiac OCR (po zapisie pliku do media, ale przed finalnym zapisem metadanych).
- Potwierdzic, czy OCR ma byc synchroniczny czy w kolejce (preferencja: kolejka dla duzych plikow).

2) Implementacja OCR
- Dodac serwis OCR (np. `app/Services/Ocr/…`), ktory przyjmuje sciezke do pliku i zwraca tekst.
- Dla obrazow: bezposrednio OCR.
- Dla PDF: ekstrakcja stron do obrazow + OCR per strona (lub engine, ktory obsluguje PDF bezposrednio).
- Zapis OCR do pola tekstowego (np. `documents.ocr_text`) lub osobnej relacji/tabeli (do ustalenia po przegladzie modelu).

3) Testy
- Pest: test, ktory wgrywa plik i sprawdza, ze OCR tekst jest zapisany.
- Dodatkowo manualne sprawdzenie (opcjonalnie) w tinker.

## Etap 2 — OpenAI + PrismPHP (bez frontendu)
Zakres: akcje backendowe, streaming, walidacja danych i testy, bez zmian UI.

1) Kontrakt danych
- Ustalic format odpowiedzi LLM (np. JSON schema: title, notes, category_id, tags[], document_date, received_at, confidence, raw_ocr_excerpt, tools_log, reasoning_log).
- Zdefiniowac reguly mapowania LLM -> Document (np. w serwisie/akcji).

2) Integracja PrismPHP
- Serwis/akcja: przyjmuje OCR text i zwraca strumien odpowiedzi LLM.
- Obszyc tryb streaming w PrismPHP (SSE / chunked response).
- Zapisac wynik i logi (OCR + tool calls + reasoning) do osobnych pol (lub tabeli logow) dla pozniejszego UI.

3) Testy i weryfikacja
- Pest: testy akcji LLM z mockiem (bez realnego wywolania sieci).
- Tinker: manualne odpalanie akcji na przykladowym OCR, weryfikacja streaming logiki.

## Etap 3 — Frontend (Inertia + Vue + Vercel AI SDK)
Zakres: UI i streaming po prawej stronie strony tworzenia dokumentu.

1) UI / UX
- Po prawej stronie panel "chat":
  - OCR output (raw text)
  - streaming odpowiedzi LLM
  - log tool calls
  - reasoning (jesli dostepny w API)
- Po lewej formularz dokumentu z dynamicznym uzuplenianiem pol.

2) Integracja strumienia
- Uzyc Vercel AI SDK (Vue) do odbioru streamu.
- Aktualizowac pola formularza w czasie rzeczywistym (tytul, notatki, kategoria, tagi, daty).
- Dodac widoczne stany: loading, partial, error, retry.

3) Testy
- Pest (feature): sprawdzenie endpointu stream.
- (Opcjonalnie) Pest v4 Browser test dla UI streamu.

## Otwarte pytania
- Czy OCR ma byc synchroniczny czy w kolejce?
- Gdzie przechowywac OCR text i logi (kolumny vs. osobne tabele)?
- Jak detaliczny powinien byc log reasoning i tool calls?
- Czy LLM ma automatycznie wybierac kategorie (po nazwie) czy po id? (wymaga mapowania)

