# Plan: OpenAI Vision (PrismPHP) + UI streaming

## Cel
Po wgraniu nowego pliku na stronie tworzenia dokumentu (`/documents/create`) od razu wysylamy obraz(y) do OpenAI Vision (PrismPHP + streaming). Model zwraca zarowno ekstrakcje tekstu, jak i klasyfikacje/uzupelnienie danych. UI ma na biezaco aktualizowac pola (tytul, notatki, kategoria, tagi, daty) i pokazywac po prawej stronie panel typu chat z pelnym logiem (tool calls, reasoning, itp.) oraz wyciagnietym tekstem.

## Zalozenia i ograniczenia
- Integracja LLM: PrismPHP z opcja stream.
- Frontend: uzyc zainstalowanego Vercel AI SDK (Vue).
- Etapy wdrozeniowe zgodnie z kolejnoscia ponizej.
- Brak zmian w zaleznosciach bez zgody.
- Dokument w bazie musi miec pole na wyszukiwalna tresc (JSON lub Markdown) tak, aby w przyszlosci dobrze wspolpracowalo to z Meilisearch. Ta tresc buduje OpenAI (podsumowanie + kluczowe informacje).

## Format pola wyszukiwalnego (rekomendacja)
Rekomendacja: JSON jako kanoniczny format + klucz `search_text` do prostego full-text.
Powod: JSON pozwala zachowac strukture (latwiejsze mapowanie na pola Document), a `search_text` daje proste wyszukiwanie full-text w Meilisearch bez dodatkowego przetwarzania.

Rekomendowane kolumny w `documents`:
- `extracted_content` (JSON) — pelna struktura (summary, key_points, entities, search_text, itd.)
- `ai_metadata` (JSON, nullable) — confidence, reasoning, usage, itp. (opcjonalne)
- Logi tool calls na start pomijamy (brak osobnej tabeli).

Proponowana struktura JSON:
```json
{
  "summary": "Krotkie streszczenie dokumentu",
  "key_points": ["Punkt 1", "Punkt 2"],
  "document_type": "umowa/faktura/zaswiadczenie/...",
  "entities": {
    "people": ["Jan Kowalski"],
    "organizations": ["Acme Sp. z o.o."],
    "addresses": ["ul. Przykladowa 1, Warszawa"],
    "reference_numbers": ["FV/12/2025"]
  },
  "dates": {
    "document_date": "2025-12-01",
    "received_at": "2025-12-03",
    "other": ["2025-12-15"]
  },
  "amounts": ["1234.56 PLN"],
  "keywords": ["vat", "energia", "abonament"],
  "search_text": "summary + key_points + entities + keywords (polaczony tekst)"
}
```

Uwaga: jesli chcemy tylko jedno pole bez JSON, alternatywa to Markdown, ale JSON + `search_text` jest bardziej elastyczny i prostszy do indeksacji w Meilisearch.

## PrismPHP - snippety z dokumentacji (do szybkiego wdrozenia streamu)

### Ostrzezenie (streaming + interceptory)
Kiedy Laravel Telescope lub inne pakiety przechwytuja zdarzenia HTTP klienta, moga skonsumowac stream zanim Prism go wyemituje. Streaming moze wygladac na zepsuty/niepelny - rozwiazanie to wylaczenie takich interceptorow albo pominiecie requestow Prism.

### Quick Start: SSE (EventSource)
```php
Route::get('/chat', function () {
    return Prism::text()
        ->using('anthropic', 'claude-3-7-sonnet')
        ->withPrompt(request('message'))
        ->asEventStreamResponse();
});
```
```javascript
const eventSource = new EventSource('/chat');

eventSource.addEventListener('text_delta', (event) => {
    const data = JSON.parse(event.data);
    document.getElementById('output').textContent += data.delta;
});

eventSource.addEventListener('stream_end', (event) => {
    const data = JSON.parse(event.data);
    console.log('Stream ended:', data.finish_reason);
    eventSource.close();
});
```

### Vercel AI SDK (Data Protocol)
```php
Route::post('/api/chat', function () {
    return Prism::text()
        ->using('openai', 'gpt-5-mini')
        ->withPrompt(request('message'))
        ->asDataStreamResponse();
});
```
```javascript
import { useChat } from '@ai-sdk/react';
import { useState } from 'react';

export default function Chat() {
    const [input, setInput] = useState('');

    const { messages, sendMessage, status } = useChat({
        transport: { api: '/api/chat' },
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        if (input.trim() && status === 'ready') {
            sendMessage(input);
            setInput('');
        }
    };

    return (
        <div>
            <div>
                {messages.map(m => (
                    <div key={m.id}>
                        <strong>{m.role}:</strong>{' '}
                        {m.parts
                            .filter(part => part.type === 'text')
                            .map(part => part.text)
                            .join('')}
                    </div>
                ))}
            </div>

            <form onSubmit={handleSubmit}>
                <input
                    value={input}
                    placeholder="Say something..."
                    onChange={(e) => setInput(e.target.value)}
                    disabled={status !== 'ready'}
                />
                <button type="submit" disabled={status !== 'ready'}>
                    {status === 'streaming' ? 'Sending...' : 'Send'}
                </button>
            </form>
        </div>
    );
}
```
NOTE: AI SDK 5.0 nie zarzadza juz stanem inputu wewnetrznie, trzeba uzywac `sendMessage`.

### Vision: wysylanie obrazow (OpenAI, gpt-5-mini)
Najprostszy wariant - prompt + obrazy:
```php
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Media\Image;

$response = Prism::text()
    ->using(Provider::OpenAI, 'gpt-5-mini')
    ->withPrompt(
        'Wyciagnij tekst i dane dokumentu:',
        [Image::fromStoragePath(path: $pathToImage, diskName: 'private')]
    )
    ->asText();
```

Wariant message-based (latwiej rozbudowywac o dodatkowe role/obrazy):
```php
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Messages\UserMessage;

$message = new UserMessage(
    'Wyciagnij tekst i dane dokumentu:',
    [Image::fromLocalPath(path: $pathToImage)]
);

$response = Prism::text()
    ->using(Provider::OpenAI, 'gpt-5-mini')
    ->withMessages([$message])
    ->asText();
```

Streaming + Vercel AI SDK (Data Protocol):
```php
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Media\Image;

Route::post('/api/document-intake', function () use ($pathToImage) {
    return Prism::text()
        ->using(Provider::OpenAI, 'gpt-5-mini')
        ->withPrompt(
            'Wyciagnij tekst i dane dokumentu:',
            [Image::fromStoragePath(path: $pathToImage, diskName: 'private')]
        )
        ->asDataStreamResponse();
});
```

### Structured Output (strict JSON schema) - PrismPHP + OpenAI
Schemat wymusza zwrot JSON zgodny z definicja. W trybie strict wszystkie pola musza byc w `requiredFields`, a opcjonalne pola musza byc `nullable: true`.
```php
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

$schema = new ObjectSchema(
    name: 'document_intake',
    description: 'Znormalizowane dane dokumentu',
    properties: [
        new StringSchema('title', 'Tytul dokumentu'),
        new StringSchema('notes', 'Krotkie notatki/podsumowanie', nullable: true),
        new StringSchema('category_id', 'ID dopasowanej kategorii (string lub null)', nullable: true),
        new StringSchema('category_name_new', 'Nowa nazwa kategorii, gdy brak dopasowania', nullable: true),
        new StringSchema('category_name', 'Dopasowana nazwa kategorii (opcjonalnie, do podgladu)', nullable: true),
        new StringSchema('document_date', 'Data dokumentu (YYYY-MM-DD)', nullable: true),
        new StringSchema('received_at', 'Data otrzymania (YYYY-MM-DD)', nullable: true),
        new ArraySchema('tags', 'Lista tagow', new StringSchema('tag', 'Pojedynczy tag')),
        new StringSchema('extracted_text', 'Pelny wyciagniety tekst', nullable: true),
        new StringSchema('search_text', 'Tekst do indeksacji (summary+key_points+entities)', nullable: true),
    ],
    requiredFields: [
        'title',
        'notes',
        'category_id',
        'category_name_new',
        'category_name',
        'document_date',
        'received_at',
        'tags',
        'extracted_text',
        'search_text',
    ]
);

$response = Prism::structured()
    ->using(Provider::OpenAI, 'gpt-5-mini')
    ->withSchema($schema)
    ->withProviderOptions([
        'schema' => ['strict' => true],
    ])
    ->withPrompt('Wyciagnij dane dokumentu i zwroc JSON zgodny ze schematem')
    ->asStructured();

$data = $response->structured;
```

Przyklad fragmentu promptu z kategoriami:
```
Dostepne kategorie: [{"id": 1, "name": "Faktury"}, {"id": 2, "name": "Umowy"}]
Jesli nic nie pasuje, zwroc category_id = null oraz category_name_new z proponowana nazwa.
```

### Event types (streaming)
- stream_start
- text_start
- text_delta
- text_complete
- thinking_start
- thinking_delta
- thinking_complete
- tool_call
- tool_result
- provider_tool_event
- error
- stream_end

## Etap 1 — OpenAI Vision (ekstrakcja + klasyfikacja)
Zakres: Vision end-to-end bez osobnego OCR.

1) Analiza
- Zidentyfikowac obecny flow uploadu na `/documents/create` (kontroler, request, media library).
- Sprawdzic, gdzie najlepiej podpiac wywolanie Vision (po zapisie pliku do media, ale przed finalnym zapisem metadanych).
- Ustalic strategia dla PDF: konwersja stron do obrazow i limitowanie liczby stron / rozmiaru requestu.

2) Implementacja Vision
- Dodac serwis/akcje (np. `app/Services/Vision/…`) ktory przyjmuje sciezke do pliku i wysyla obraz(y) do OpenAI przez PrismPHP.
- W odpowiedzi pobierac: wyciagniety tekst + metadane dokumentu (title, notes, category, tags, daty).
- Zapisac surowy tekst i logi do pol/tabel (do ustalenia po przegladzie modelu).

3) Testy
- Pest: test, ktory wgrywa plik i sprawdza, ze tekst + metadane sa zapisane.
- Dodatkowo manualne sprawdzenie w tinker na przykladowym obrazie.

## Etap 2 — Streaming + PrismPHP (bez frontendu)
Zakres: akcje backendowe, streaming, walidacja danych i testy, bez zmian UI.

1) Kontrakt danych
- Ustalic format odpowiedzi Vision (np. JSON schema: extracted_text, title, notes, category_id, category_name_new, tags[], document_date, received_at, confidence, tools_log, reasoning_log, search_summary).
- Zdefiniowac reguly mapowania Vision -> Document (np. w serwisie/akcji).
- Rekomendacja: przekazywac do LLM liste dostepnych kategorii (id, name, description). LLM zwraca `category_id`, a gdy nic nie pasuje: `category_id = null` oraz `category_name_new` do utworzenia.

2) Integracja PrismPHP
- Serwis/akcja: przyjmuje obraz(y) i zwraca strumien odpowiedzi Vision/LLM.
- Obszyc tryb streaming w PrismPHP (SSE / chunked response).
- Zapisac wynik do `extracted_content` oraz opcjonalne dane pomocnicze do `ai_metadata`. Tool calls tylko w streamie (bez persystencji).

3) Testy i weryfikacja
- Pest: testy akcji Vision z mockiem (bez realnego wywolania sieci).
- Tinker: manualne odpalanie akcji na przykladowym obrazie, weryfikacja streaming logiki.

## Etap 3 — Frontend (Inertia + Vue + Vercel AI SDK)
Zakres: UI i streaming po prawej stronie strony tworzenia dokumentu.

1) UI / UX
- Po prawej stronie panel "chat":
  - extracted text (raw text)
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

## Decyzje
- Przechowywanie: `documents.extracted_content` (JSON) + `documents.ai_metadata` (JSON, nullable), bez `document_ai_logs`.
- Kategorie: do LLM przekazujemy liste kategorii (id/name/description), LLM zwraca `category_id` lub `category_name_new` gdy brak dopasowania.
- PDF: uzywamy `spatie/pdf-to-image` (wymaga Imagick); limit max 10 stron.
