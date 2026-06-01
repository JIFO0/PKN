# Opis plików projektu PKN Backend

Ten dokument opisuje prostym językiem, do czego służy każdy plik w repozytorium. Projekt jest wtyczką WordPress do katalogowania i obsługi kół naukowych / społeczności akademickich. Najważniejsze są pliki PHP w katalogu głównym oraz w `includes/`, bo tam znajduje się logika aplikacji. Pliki w `templates/` są opisane krócej, ponieważ głównie składają HTML formularzy i widoków.

## Jak czytać ten projekt

- `PKN-backend.php` jest głównym plikiem wtyczki. WordPress zaczyna działanie pluginu właśnie od niego.
- `includes/` zawiera funkcje pomocnicze i główną logikę: pobieranie danych, uprawnienia, import, statystyki, forum, aktualizacje.
- `templates/` zawiera widoki, czyli pliki generujące HTML widoczny dla użytkowników i administratorów.
- `assets/css/` zawiera style wyglądu.
- `assets/js/` zawiera zachowania w przeglądarce, np. AJAX, przełączanie formularzy, obsługę forum.
- `lang/` zawiera tłumaczenia PL/EN.
- `builds/`, `Package-build.sh` i `build.ps1` służą do pakowania wtyczki do ZIP-a.

---

## Pliki główne repozytorium


### `.vscode/launch.json`

Lokalna konfiguracja debugowania dla Visual Studio Code. Definiuje trzy sposoby uruchamiania/debugowania PHP:

- nasłuchiwanie na Xdebug,
- uruchomienie aktualnie otwartego skryptu PHP,
- uruchomienie wbudowanego serwera PHP dla workspace'u.

To plik pomocniczy dla programisty. Nie jest częścią działania wtyczki na WordPressie.

### `.gitignore`

Plik mówi Gitowi, których plików i katalogów nie dodawać do repozytorium. Dzięki temu do commita nie trafiają rzeczy tymczasowe, lokalne ustawienia IDE, folder `.dist`, paczki ZIP albo pliki systemowe.

### `PKN-backend.php`

To najważniejszy plik wtyczki. Pełni rolę centrum sterowania całym pluginem.

Co robi na początku:

- Zawiera nagłówek wtyczki WordPress, czyli nazwę, wersję, opis i dane autora.
- Definiuje stałe takie jak:
  - `SC_PLUGIN_PATH` — ścieżka do katalogu wtyczki na serwerze,
  - `SC_PLUGIN_URL` — adres URL do zasobów wtyczki,
  - `SC_PLUGIN_VERSION` i `SC_VERSION` — aktualna wersja pluginu,
  - `SC_DEBUG_MODE` — flaga trybu debugowania.
- Wczytuje pliki z katalogu `includes/`, czyli rozdzielone części logiki.
- Rejestruje hook aktywacji i deaktywacji wtyczki.

Najważniejsze obszary działania:

1. **Aktywacja i deaktywacja wtyczki**
   - Przy aktywacji uruchamia tworzenie tabel w bazie danych.
   - Dodaje domyślne wydziały.
   - Tworzy lub naprawia wymagane strony WordPress z shortcode'ami.
   - Odświeża reguły linków WordPressa.
   - Przy deaktywacji tylko odświeża reguły linków.

2. **Tworzenie wymaganych stron**
   - Funkcja tworzy strony takie jak:
     - wyszukiwarka kół,
     - wyniki wyszukiwania,
     - szczegóły koła,
     - panel administracyjny,
     - lista kół,
     - statystyki,
     - forum.
   - Jeśli strona już istnieje, ale nie ma potrzebnego shortcode'u, plugin dopisuje go do treści.

3. **Ukrywanie chronionych pozycji menu**
   - Dla niezalogowanych użytkowników ukrywa linki do forum, statystyk i panelu administracyjnego.
   - Robi to zarówno dla klasycznych menu WordPressa, jak i dla list stron.

4. **Tworzenie tabel bazy danych**
   - Tworzy wszystkie własne tabele pluginu, m.in.:
     - `science_communities` — główne dane kół,
     - `science_faculties` — wydziały,
     - `science_tags` — tagi,
     - `science_community_tags` — powiązanie kół z tagami,
     - `science_statistics` — statystyki,
     - `science_import_logs` — log importu,
     - `science_update_history` — historia zmian/importów,
     - `science_forum_threads` i `science_forum_messages` — forum,
     - `science_community_images` — dodatkowe zdjęcia kół,
     - `science_contact_requests` — prośby od administratorów kół,
     - `science_community_applications` — zgłoszenia kandydatów do kół.
   - Używa WordPressowego `dbDelta`, więc może tworzyć tabele i aktualizować ich strukturę.

5. **Aktualizacja schematu bazy**
   - Sprawdza zapisaną wersję schematu w opcji WordPressa.
   - Jeśli wersja jest nieaktualna, ponownie uruchamia tworzenie/aktualizację tabel.

6. **Shortcode'y**
   - Rejestruje shortcode'y, które można wstawić na stronach WordPressa.
   - Każdy shortcode najczęściej ładuje odpowiedni plik z `templates/`.
   - Przykłady:
     - `[science_communities_search]` pokazuje formularz wyszukiwania,
     - `[science_communities_results]` pokazuje wyniki,
     - `[science_community_detail]` pokazuje szczegóły jednego koła,
     - `[science_communities_admin]` pokazuje panel administracyjny,
     - `[science_communities_forum]` pokazuje forum.

7. **Ładowanie CSS i JS**
   - Na froncie ładuje style i skrypty potrzebne do wyszukiwarki, listy, szczegółów, panelu i forum.
   - Przekazuje do JavaScriptu dane takie jak `ajaxurl`, nonce bezpieczeństwa i teksty tłumaczeń.
   - W panelu WordPress ładuje osobne style/skrypty administracyjne.

8. **Obsługa AJAX**
   - Rejestruje akcje AJAX do:
     - aktualizacji danych koła,
     - pobierania tagów,
     - uploadu logo,
     - pobierania danych z Facebooka,
     - funkcji forum.
   - Każda akcja sprawdza nonce i uprawnienia użytkownika.

9. **Śledzenie kliknięć w linki społecznościowe**
   - Gdy link prowadzi przez specjalny adres z parametrem `sc_track_social`, plugin zapisuje kliknięcie w statystykach.
   - Potem przekierowuje użytkownika do docelowego linku, ale tylko jeśli platforma i adres są zgodne z zapisanymi danymi.

10. **Integracja z Facebookiem**
    - Potrafi wyciągnąć identyfikator strony Facebooka z URL-a.
    - Próbuje pobrać nazwę, opis, zdjęcie i tło strony przez Graph API.
    - Jeśli nie ma pełnego dostępu API, próbuje chociaż pobrać zdjęcie profilowe.
    - Dane są cache'owane w transientach WordPressa, aby nie pytać API przy każdym odświeżeniu.

11. **Dodawanie i edycja kół**
    - Obsługuje formularze `admin-post` dodawania i edycji koła.
    - Waliduje nonce, uprawnienia i dane wejściowe.
    - Dla dodawania tworzy nowy rekord oraz przypisuje tagi.
    - Dla edycji aktualizuje rekord, tagi, zdjęcia i przekierowuje użytkownika z komunikatem.

12. **Kontakt i zgłoszenia**
    - Obsługuje prośby administratorów kół do superadminów.
    - Obsługuje formularz dołączenia do koła od zwykłego użytkownika.
    - Zapisuje zgłoszenie do bazy oraz wysyła maila do kontaktowego adresu koła.
    - Ma prosty limit antyspamowy oparty o transient.

13. **Użytkownicy i role**
    - Obsługuje przypisanie użytkownika jako administratora konkretnego koła.
    - Pozwala adminowi koła zmienić swój `display_name`.
    - Obsługuje prośby o usunięcie przypisania lub inne zgłoszenia do superadminów.

14. **Menu administratora WordPress**
    - Dodaje menu PKN w kokpicie WordPressa.
    - Podłącza strony do listy kół, importu, tagów/wydziałów, dashboardu, statystyk, użytkowników, zgłoszeń i ustawień Facebooka.

15. **Akcje masowe i import/eksport**
    - Obsługuje masowe usuwanie, zmianę statusu, zmianę wydziału, tagi i archiwizowanie kół.
    - Obsługuje import CSV/Excel przez formularz.
    - Obsługuje eksport listy kół do CSV.
    - Obsługuje eksport kont administratorów kół do CSV.

16. **Renderowanie stron admina**
    - Na końcu znajdują się funkcje, które po prostu dołączają odpowiednie template'y z katalogu `templates/`.

### `Doc.md`

To rozbudowana dokumentacja techniczno-funkcjonalna projektu. Opisuje ogólny cel platformy, architekturę, model bazy danych, panel administracyjny, importy, forum, statystyki i system aktualizacji. Jest bardziej formalna i szersza niż ten plik.

### `PROJECT_FILES_PL.md`

To bieżący plik dokumentacji. Ma prostym językiem wyjaśnić rolę każdego pliku w repozytorium, z większym naciskiem na pliki logiczne niż na template'y.

### `Package-build.sh`

Skrypt budujący paczkę ZIP w systemach Linux/macOS lub w środowisku bash.

Co robi:

- Odczytuje wersję wtyczki z nagłówka `PKN-backend.php`.
- Tworzy katalogi `builds/` i `.dist/`.
- Kopiuje do tymczasowego katalogu tylko pliki potrzebne wtyczce: główny plik, `templates/`, `lang/`, `includes/`, `assets/`.
- Usuwa niepotrzebne pliki IDE z paczki.
- Tworzy ZIP `pkn-backend-{wersja}.zip` w katalogu `builds/`.
- Aktualizuje `builds/latest.json`, czyli manifest używany przez updater.
- Opcjonalnie, jeśli zmienna `SC_CREATE_GITHUB_RELEASE=1`, próbuje utworzyć release na GitHubie przez `gh`.

Uwaga: w skrypcie znajduje się literówka `rDEST_DIR=...`, a później używana jest zmienna `DEST_DIR`. To może powodować błąd działania skryptu bash.

### `build.ps1`

PowerShellowy odpowiednik skryptu budującego, przeznaczony głównie dla Windowsa.

Co robi:

- Sprawdza, czy istnieje `PKN-backend.php`.
- Odczytuje wersję pluginu.
- Tworzy katalog `.dist/pkn-backend`.
- Kopiuje potrzebne pliki wtyczki.
- Pakuje je do ZIP-a.
- Aktualizuje `builds/latest.json`.
- Obsługuje archiwizację poprzednich buildów.
- Potrafi pracować z GitHub CLI (`gh`), sprawdzać logowanie i publikować release.
- Ma funkcję `Exit-Script`, która pauzuje okno przed zamknięciem, co jest wygodne przy uruchamianiu przez dwuklik.

### `builds/latest.json`

Manifest najnowszej wersji pluginu. Updater może z niego odczytać:

- nazwę pluginu,
- slug,
- wersję,
- datę builda,
- URL paczki ZIP,
- URL szczegółów,
- wymagania WordPress/PHP,
- opis i changelog.

W praktyce to mały plik informujący WordPressa lub własny updater, skąd pobrać aktualizację.

### `overview v2.txt`

Luźny plik planistyczny z opisem wymagań, pomysłów i notatek. Zawiera listę funkcji do zrobienia lub już zaplanowanych, np. statystyki, bulk edit, dashboard, galerie zdjęć, eksport CSV, historię statusów, wersję PL/EN i poprawki wyglądu.

### `todo.txt`

Lista zadań i pomysłów do dalszego rozwoju. Jest mniej formalna niż dokumentacja. Służy jako roboczy backlog.

### `new.patch`

Plik z patchem/diffem. Zawiera propozycje zmian w kodzie, np. utwardzenie przekierowań społecznościowych i poprawki bezpieczeństwa. Nie jest wykonywany automatycznie; to zapis zmian do ręcznego przejrzenia lub nałożenia przez `git apply`.

---

## Katalog `includes/` — logika projektu

### `includes/functions.php`

To główny zestaw funkcji do pracy z danymi kół naukowych. Można go traktować jako warstwę "modelu" albo "repozytorium" danych.

Najważniejsze funkcje:

1. **`sc_search_communities(...)`**
   - Wyszukuje koła w bazie.
   - Przyjmuje tekst wyszukiwania, tagi, wydziały, tryb fuzzy i filtr "otwarte na zgłoszenia".
   - Szuka po nazwie, opisie, krótkim opisie, linkach, mailu, tagach i wydziale.
   - Uwzględnia statusy kół i zwykle pomija archiwalne/nieaktywne wyniki.
   - Tryb fuzzy pomaga znaleźć wyniki nawet przy literówkach, używając porównywania podobieństwa tekstu.
   - Zwraca listę kół pasujących do filtrów.

2. **`sc_find_page_id_by_shortcode($shortcode)`**
   - Szuka w WordPressie strony, która zawiera dany shortcode.
   - Dzięki temu plugin nie musi znać na sztywno ID strony.
   - Przydaje się przy tworzeniu linków do wyszukiwarki, wyników, detali lub admina.

3. **`sc_get_page_url_by_shortcode($shortcode, $fallback)`**
   - Najpierw znajduje stronę po shortcode.
   - Jeśli ją znajdzie, zwraca jej permalink.
   - Jeśli nie, zwraca adres awaryjny przekazany w `fallback`.

4. **`sc_get_admin_page_url()`**
   - Zwraca adres frontendowego panelu administracyjnego PKN.
   - Używane przy przekierowaniach po dodaniu/edycji koła.

5. **`sc_levenshtein_distance(...)`**
   - Liczy odległość Levenshteina, czyli jak bardzo dwa napisy różnią się od siebie.
   - Jest używane do wyszukiwania przybliżonego.
   - Ma limit maksymalnej odległości, aby nie robić zbyt ciężkich obliczeń.

6. **`sc_get_community($community_id)` i `sc_get_community_by_id(...)` z innych plików**
   - Pobiera dane jednego koła po jego publicznym identyfikatorze.
   - Dane są potem pokazywane w template'ach lub używane przy edycji.

7. **`sc_get_community_tags($community_id)`**
   - Pobiera tagi przypisane do konkretnego koła.
   - Łączy tabelę powiązań z tabelą tagów.

8. **`sc_get_all_tags()`**
   - Pobiera wszystkie tagi dostępne w systemie.
   - Używane w formularzach wyszukiwania, edycji i importu.

9. **`sc_cleanup_orphan_tags()`**
   - Usuwa tagi, których nie używa żadne koło.
   - Pomaga utrzymać porządek po edycjach/importach.

10. **`sc_generate_community_id()`**
    - Generuje krótki identyfikator koła.
    - Sprawdza, czy taki identyfikator nie istnieje już w bazie.
    - Dzięki temu nowe koło dostaje unikalny `community_id`.

11. **`sc_create_community($community_data)`**
    - Tworzy nowe koło w tabeli `science_communities`.
    - Czyści i normalizuje dane wejściowe.
    - Ustawia domyślne wartości tam, gdzie brakuje danych.
    - Po zapisie może zaktualizować tagi.

12. **`sc_update_community($community_data)`**
    - Aktualizuje istniejące koło.
    - Pilnuje, żeby dane były poprawnie oczyszczone.
    - Aktualizuje pola takie jak nazwa, opis, wydział, linki, status, logo, mail kontaktowy, otwarcie rekrutacji itd.

13. **`sc_normalize_tags_input($tags)`**
    - Przyjmuje tagi z formularza/importu w różnych formach.
    - Zamienia je na spójną tablicę nazw.
    - Usuwa puste wartości, duplikaty i zbędne spacje.

14. **`sc_update_community_tags($community_id, $tags)`**
    - Aktualizuje komplet tagów dla danego koła.
    - Najpierw normalizuje tagi.
    - Tworzy brakujące tagi w tabeli `science_tags`.
    - Czyści stare powiązania i zapisuje nowe w `science_community_tags`.

15. **`sc_delete_community($community_id)`**
    - Usuwa koło z bazy.
    - Czyści też powiązania tagów, aby nie zostały osierocone rekordy.

16. **`sc_format_community_for_display($community)`**
    - Przygotowuje dane koła do bezpiecznego wyświetlania.
    - Może dodawać czytelne etykiety statusów, wydziału i inne pola pomocnicze.

17. **`sc_get_all_faculties()`**
    - Pobiera listę wydziałów z bazy.
    - Używane w filtrach i formularzach.

18. **`sc_get_status_display($status, $is_archived)`**
    - Zamienia status techniczny na czytelny tekst.
    - Uwzględnia specjalny przypadek archiwum.

19. **`sc_get_all_statuses($include_archived)`**
    - Zwraca listę możliwych statusów kół.
    - Przydatne do selectów w panelu administratora.

20. **`sc_get_faculty_name($faculty_id)`**
    - Zamienia ID wydziału na nazwę wydziału.

21. **AJAX importu i pomocniczy skrypt w stopce admina**
    - Plik zawiera też rejestrację jednej akcji AJAX związanej z importem społeczności.
    - W stopce admina dodaje JavaScript wspierający import.

### `includes/admin-functions.php`

Ten plik zawiera funkcje administracyjne: uprawnienia edycji, zapis danych, uploady, import CSV, historia zmian, zdjęcia i zgłoszenia.

Najważniejsze części:

1. **Uprawnienia edycji**
   - `sc_user_can_edit_community($community_id)` sprawdza, czy aktualny użytkownik może edytować konkretne koło.
   - Superadmin może edytować wszystko.
   - Administrator koła może edytować tylko koła, do których jest przypisany.
   - `sc_user_can_edit_any_community()` sprawdza, czy użytkownik może edytować przynajmniej jedno koło.
   - `sc_get_editable_communities()` zwraca listę kół widocznych do edycji dla aktualnego użytkownika.

2. **Zapisywanie koła**
   - `sc_save_community($data)` przygotowuje dane z formularza i decyduje, czy tworzyć nowe koło, czy aktualizować istniejące.
   - Czyści teksty, linki i pola logiczne.
   - Obsługuje statusy, wydział, tagi, opis, logo, mail kontaktowy i linki społecznościowe.

3. **Role administratorów kół**
   - `sc_register_community_admin_role($community_id)` tworzy rolę WordPressa dla administratora konkretnego koła.
   - Role mają schemat zależny od `community_id`, np. rola kończąca się `-admin`.
   - Dzięki temu można przypisać użytkownika tylko do konkretnego koła.

4. **Pobieranie koła po ID**
   - `sc_get_community_by_id($community_id)` pobiera jeden rekord z bazy.
   - To funkcja często używana przy edycji, kontaktach, zgłoszeniach i śledzeniu linków.

5. **Ograniczenia edycji/uploadu**
   - `sc_can_user_edit_now($user_id, $community_id)` może sprawdzać limity czasowe lub zasady, czy dany użytkownik może teraz edytować.
   - `sc_can_user_upload($user_id)` pilnuje limitów uploadu.

6. **Upload logo**
   - `sc_handle_logo_upload($file, $user_id)` obsługuje przesyłanie obrazka.
   - Sprawdza typ pliku, rozmiar i uprawnienia.
   - Korzysta z mechanizmów uploadu WordPressa.
   - Zwraca URL logo albo błąd.

7. **Import CSV/Excel**
   - `sc_normalize_import_header($header)` ujednolica nazwy kolumn, żeby import działał mimo różnych wariantów nagłówków.
   - `sc_detect_csv_delimiter($file_path)` próbuje wykryć separator CSV, np. `|`, `;`, `,` albo tabulator.
   - `sc_is_empty_import_row($row)` rozpoznaje puste wiersze, żeby nie tworzyć pustych kół.
   - `sc_sanitize_links_list($links_raw)` czyści listy linków z importu.
   - `sc_get_links_list($links_raw)` zamienia surowy tekst z linkami na listę.
   - `sc_parse_import_status($status_raw)` zamienia status z pliku na status systemowy.
   - `sc_parse_import_tags($tags_raw)` rozbija tagi z importu na listę.
   - `sc_import_from_excel($file_path, $args)` jest główną funkcją importu:
     - otwiera plik,
     - rozpoznaje nagłówki,
     - czyta wiersze,
     - waliduje wymagane dane,
     - tworzy nowe koła lub aktualizuje istniejące,
     - zapisuje tagi,
     - liczy utworzone, zaktualizowane i pominięte rekordy,
     - zapisuje logi i historię.

8. **Logi importu i historia zmian**
   - `sc_import_log($message)` zapisuje komunikat importu do logów.
   - `sc_record_update_history($args)` zapisuje informację o zmianie/importcie do tabeli historii.
   - `sc_cleanup_broken_semicolon_tags()` sprząta tagi, które mogły powstać błędnie przez złe rozdzielenie średnikami.

9. **Tabela administratorów kół**
   - `sc_display_community_admins_table()` generuje listę użytkowników z rolami administratorów kół.
   - Używane w panelu zarządzania użytkownikami.

10. **Zdjęcia kół**
    - `sc_get_community_images($community_id, $category)` pobiera zdjęcia dla koła, opcjonalnie z konkretnej kategorii.
    - `sc_save_community_images($community_id, $category, $image_urls)` zapisuje listę zdjęć dla kategorii.
    - Kategorie mogą oznaczać np. zdjęcia wydarzeń, zespołu albo osiągnięć.

11. **Zgłoszenia kontaktowe**
    - `sc_create_contact_request($community_id, $message, $requester_id)` zapisuje wiadomość od administratora koła do superadminów.
    - Używane przy prośbach o usunięcie koła lub innych sprawach organizacyjnych.

12. **Czyszczenie historii aktualizacji**
    - `sc_handle_clear_update_history()` obsługuje akcję admin-post do usunięcia historii aktualizacji.
    - Sprawdza nonce i uprawnienia.

### `includes/auth.php`

Ten plik odpowiada za uprawnienia użytkowników i role.

Najważniejsze funkcje:

1. **`sc_is_superadmin()`**
   - Sprawdza, czy aktualny użytkownik ma rolę superadmina albo odpowiednie uprawnienia.
   - Superadmin ma pełną kontrolę nad pluginem.

2. **`sc_is_community_admin($community_id)`**
   - Sprawdza, czy użytkownik jest administratorem konkretnego koła.
   - Działa na podstawie ról użytkownika powiązanych z `community_id`.

3. **`sc_get_user_admin_communities()`**
   - Zwraca listę kół, którymi może zarządzać aktualny użytkownik.
   - Dla superadmina może zwrócić szeroki dostęp, a dla admina koła tylko przypisane koła.

4. **`sc_verify_community_edit_request(...)`**
   - Sprawdza, czy request edycji jest bezpieczny.
   - Weryfikuje ID koła, nonce oraz uprawnienia.
   - Chroni formularze przed przypadkową lub złośliwą edycją.

5. **`sc_assign_community_admin($user_id, $community_id)`**
   - Przypisuje użytkownika do roli administratora konkretnego koła.
   - Jeśli rola jeszcze nie istnieje, może ją utworzyć.

6. **`sc_remove_community_admin($user_id, $community_id)`**
   - Odbiera użytkownikowi rolę administratora konkretnego koła.

7. **`sc_can_access_admin_panel()`**
   - Sprawdza, czy użytkownik może wejść do panelu administracyjnego pluginu.
   - Zwykle pozwala superadminom i administratorom przypisanych kół.

8. **`sc_get_login_form()`**
   - Zwraca formularz logowania WordPressa albo link do logowania.
   - Używane, gdy ktoś niezalogowany próbuje wejść do części admina.

9. **`sc_get_current_user_name()`**
   - Zwraca czytelną nazwę aktualnego użytkownika.
   - Przydatne w nagłówkach panelu i forum.

### `includes/error-logger.php`

Plik do obsługi błędów i diagnostyki.

Co robi:

1. **`sc_table_exists($table_name)`**
   - Sprawdza, czy dana tabela istnieje w bazie.

2. **`sc_log_error($message, $context)`**
   - Zapisuje błąd do logu.
   - Może dopisać kontekst, np. nazwę funkcji, dane wejściowe albo informacje o użytkowniku.

3. **`sc_error_handler(...)`**
   - Niestandardowy handler błędów PHP.
   - Może przechwytywać warningi/notices i zapisywać je w kontrolowany sposób.

4. **`sc_exception_handler($exception)`**
   - Obsługuje wyjątki PHP i zapisuje je w logu.

5. **`sc_add_error_log_menu()`**
   - Dodaje w kokpicie WordPressa stronę do podglądu logów błędów.

6. **`sc_render_error_log_page()`**
   - Renderuje stronę logów błędów.
   - Pokazuje informacje diagnostyczne i może pozwalać na czyszczenie logu.

7. **`sc_debug($message, $data)`**
   - Pomocnicza funkcja debugowania.
   - Zapisuje komunikaty tylko wtedy, gdy debug jest włączony.

8. **`sc_check_system_requirements()`**
   - Sprawdza wymagania środowiska, np. wersję PHP, WordPressa, wymagane funkcje lub tabele.

### `includes/forum.php`

To pełna logika wewnętrznego forum dla superadminów i administratorów kół.

Najważniejsze części:

1. **Dostęp do forum**
   - `sc_forum_user_can_access()` sprawdza, czy użytkownik może korzystać z forum.
   - Forum jest dla zalogowanych osób z uprawnieniami administracyjnymi w PKN.

2. **Etykiety użytkowników**
   - `sc_forum_get_user_role_label($user_id)` zwraca czytelną rolę użytkownika, np. superadmin lub admin koła.
   - `sc_forum_get_user_communities_label($user_id)` zwraca nazwy/ID kół powiązanych z użytkownikiem.
   - Te informacje są pokazywane przy wiadomościach.

3. **Wątek ogólny**
   - `sc_forum_get_general_thread_id()` zwraca ID domyślnego wątku ogólnego.
   - `sc_forum_ensure_general_thread()` tworzy taki wątek, jeśli go nie ma.
   - Dzięki temu forum zawsze ma miejsce na ogólne rozmowy.

4. **Tworzenie tabel forum**
   - `sc_forum_create_tables()` tworzy tabele wątków i wiadomości.
   - `sc_forum_maybe_install()` sprawdza, czy forum jest zainstalowane i ewentualnie uruchamia tworzenie tabel.

5. **Pobieranie wątków**
   - `sc_forum_get_threads()` pobiera listę wątków.
   - `sc_forum_get_threads_paginated($page, $per_page)` pobiera wątki stronicowane, czyli po kilka/kilkanaście na stronę.
   - Funkcje dodają informacje o autorach, liczbie wiadomości i ostatniej aktywności.

6. **Pobieranie jednego wątku i wiadomości**
   - `sc_forum_get_thread($thread_id)` pobiera konkretny wątek.
   - `sc_forum_get_messages($thread_id)` pobiera wiadomości z wątku.
   - `sc_forum_format_message_row($row)` przygotowuje wiadomość do wysłania jako JSON do frontendu.

7. **Wspólna kontrola AJAX**
   - `sc_forum_ajax_require_access()` sprawdza nonce, logowanie i dostęp.
   - Większość akcji AJAX forum używa tej funkcji na starcie.

8. **AJAX: lista wątków i wiadomości**
   - `sc_forum_ajax_get_threads()` zwraca listę wątków.
   - `sc_forum_ajax_get_messages()` zwraca wiadomości z wybranego wątku.

9. **Tworzenie wątków**
   - `sc_forum_user_can_create_thread($user_id)` sprawdza, czy użytkownik może utworzyć nowy wątek.
   - `sc_forum_ajax_create_thread()` tworzy wątek i pierwszą wiadomość.
   - Może ograniczać spam przez limity czasowe.

10. **Dodawanie wiadomości**
    - `sc_forum_user_can_post_message_now($user_id)` sprawdza limit wysyłania wiadomości.
    - `sc_forum_ajax_post_message()` zapisuje nową wiadomość w wątku.
    - Waliduje treść i uprawnienia.

11. **Edycja i usuwanie**
    - `sc_forum_ajax_edit_message()` pozwala edytować wiadomość.
    - `sc_forum_ajax_delete_thread()` usuwa wątek.
    - `sc_forum_ajax_delete_message()` usuwa wiadomość.
    - Uprawnienia są różne dla autora i superadmina.

12. **Zamykanie i zgłaszanie**
    - `sc_forum_ajax_close_thread()` zamyka lub otwiera wątek.
    - `sc_forum_ajax_report_message()` zapisuje zgłoszenie problematycznej wiadomości.

13. **Upload obrazów**
    - `sc_forum_ajax_upload_image()` obsługuje upload obrazka do wiadomości.
    - Używa uploadu WordPressa i sprawdza dostęp.

### `includes/lang.php`

Plik odpowiada za prosty system językowy PL/EN.

Co robi:

1. **`sc_init_language()`**
   - Uruchamia się wcześnie na hooku `init`.
   - Ustala aktualny język.
   - Może brać język z parametru URL, ciasteczka albo ustawienia domyślnego.

2. **`sc_get_lang()`**
   - Zwraca aktualny kod języka, np. `pl` albo `en`.

3. **`sc_load_translations()`**
   - Wczytuje odpowiedni plik z `lang/`.
   - Jeśli język to PL, ładuje `lang/pl.php`; jeśli EN, ładuje `lang/en.php`.

4. **`sc_t($key)`**
   - Najważniejsza funkcja tłumaczeń.
   - Przyjmuje klucz, np. `search`, i zwraca tekst w aktualnym języku.
   - Jeśli klucza brakuje, zwraca sam klucz, żeby było widać problem.

5. **`sc_render_lang_toggle()`**
   - Generuje przełącznik języka.
   - Linki ustawiają język i wracają na bieżącą stronę.

6. **Shortcode `sc_lang_header_toggle`**
   - Pozwala wstawić przełącznik języka w nagłówku lub treści strony WordPressa.

### `includes/statistics.php`

Plik obsługuje statystyki użycia katalogu.

Najważniejsze funkcje:

1. **`sc_create_statistics_table()`**
   - Tworzy tabelę statystyk.
   - Przechowuje ID koła, typ zdarzenia, wartość zdarzenia i datę.

2. **`sc_track_stat_event($community_id, $event_type, $event_value)`**
   - Uniwersalna funkcja zapisująca zdarzenie.
   - Przykładowe typy zdarzeń: widok koła, kliknięcie social media, wyszukiwane hasło.

3. **`sc_track_community_view($community_id)`**
   - Zapisuje wejście na stronę szczegółów koła.

4. **`sc_track_social_click($community_id, $platform)`**
   - Zapisuje kliknięcie w link społecznościowy danego koła.

5. **`sc_track_search_term_for_results($search_term, $communities)`**
   - Po wyszukiwaniu zapisuje hasło wyszukiwania dla znalezionych kół.
   - Dzięki temu można potem zobaczyć, jakie frazy prowadzą do konkretnych kół.

6. **`sc_get_tag_usage_statistics($community_ids)`**
   - Liczy popularność tagów.
   - Może działać dla wszystkich kół albo tylko dla wybranych.

7. **`sc_get_dashboard_data()`**
   - Zbiera dane do dashboardu administratora:
     - ostatnie edycje,
     - najczęściej oglądane koła,
     - koła bez logo,
     - koła bez opisów,
     - statystyki tagów.

8. **`sc_get_statistics_data($community_ids)`**
   - Zbiera dane do strony statystyk.
   - Może ograniczyć wyniki do kół, którymi zarządza dany admin.

### `includes/updater.php`

Plik odpowiada za sprawdzanie i instalowanie aktualizacji wtyczki z GitHuba.

Najważniejsze elementy:

1. **Stałe updatera**
   - Określają właściciela repozytorium GitHub, nazwę repo, slug pluginu, nazwę ZIP-a i URL API najnowszego release'u.

2. **`sc_register_plugin_updater()`**
   - Podłącza plugin do filtrów WordPressa związanych z aktualizacjami.
   - Dzięki temu WordPress może pokazać, że dostępna jest nowa wersja.

3. **`sc_get_plugin_basename()` i `sc_get_current_plugin_version()`**
   - Zwracają techniczne informacje o aktualnie zainstalowanej wtyczce.

4. **`sc_is_newer_version($candidate, $current)`**
   - Porównuje wersje.
   - Decyduje, czy wersja z GitHuba jest nowsza od lokalnej.

5. **`sc_check_for_plugin_updates($transient)`**
   - Wpina się w mechanizm aktualizacji WordPressa.
   - Pobiera manifest/release.
   - Jeśli jest nowsza wersja, dopisuje ją do listy aktualizacji.

6. **`sc_plugin_info_popup($result, $action, $args)`**
   - Dostarcza dane do popupu „szczegóły wtyczki” w panelu WordPress.

7. **`sc_normalize_release_version($release)`**
   - Ujednolica wersję z danych release'u, np. usuwa `v` z tagu `v0.965`.

8. **`sc_find_installed_plugin_dir($directory)` i `sc_after_plugin_install(...)`**
   - Pomagają po instalacji ZIP-a znaleźć prawidłowy katalog pluginu.
   - Naprawiają sytuacje, gdy ZIP rozpakował się pod inną nazwą katalogu.

9. **`sc_fetch_update_manifest($force_refresh)`**
   - Pobiera dane o aktualizacji.
   - Może używać cache, żeby nie pytać GitHuba za często.
   - Przy wymuszonym odświeżeniu pobiera świeże dane.

10. **`sc_get_update_status($force_refresh)`**
    - Zwraca gotową informację dla UI: aktualna wersja, najnowsza wersja, czy jest update, link do paczki itd.

11. **`sc_handle_manual_plugin_update()`**
    - Obsługuje ręczne uruchomienie aktualizacji z panelu.
    - Sprawdza uprawnienia i nonce, a potem uruchamia proces aktualizacji.

---

## Katalog `lang/` — tłumaczenia

### `lang/pl.php`

Tablica tekstów po polsku. Klucze są używane przez funkcję `sc_t()`. Dzięki temu template'y i logika nie muszą mieć tekstów wpisanych na sztywno w każdym miejscu. Plik zawiera napisy do wyszukiwarki, wyników, detali koła, panelu admina, zgłoszeń, błędów i komunikatów.

### `lang/en.php`

Angielska wersja tych samych tłumaczeń. Powinna mieć możliwie te same klucze co `pl.php`, żeby przełączanie języka działało bez brakujących napisów.

---

## Katalog `templates/` — widoki HTML/PHP

Template'y głównie pobierają dane przygotowane przez funkcje z `includes/` i składają z nich HTML. Nie powinny zawierać zbyt dużo ciężkiej logiki biznesowej, chociaż część walidacji i drobnych obliczeń jest w nich obecna.

### `templates/add-community.php`

Formularz dodawania nowego koła. Pokazuje pola nazwy, opisu, wydziału, linków, maila, statusu, tagów i logo. Formularz wysyła dane do akcji obsługiwanej w `PKN-backend.php`.

### `templates/admin-communities-list.php`

Lista kół w panelu WordPress. Pozwala superadminowi przeglądać koła, zaznaczać je checkboxami i wykonywać akcje masowe, np. zmianę statusu, wydziału, tagów, archiwizację albo usuwanie. Zawiera też fragment JavaScriptu do obsługi zaznaczania i bulk akcji.

### `templates/admin-import.php`

Widok importu/eksportu. Zawiera formularz uploadu pliku CSV/Excel, przyciski eksportu, informacje o aktualizacji pluginu, historię update'ów i logi importu.

### `templates/admin-panel.php`

Frontendowy panel administracyjny PKN. To panel dostępny poza klasycznym kokpitem WordPressa. Pokazuje różne opcje zależnie od roli użytkownika: superadmin widzi więcej, admin koła widzi swoje koła, formularze profilu, zgłoszenia i linki do edycji.

### `templates/admin-tags-faculties.php`

Widok zarządzania tagami i wydziałami. Pozwala zobaczyć istniejące wartości i prawdopodobnie dodawać/edytować elementy słownikowe używane przez koła.

### `templates/community-detail.php`

Publiczna strona szczegółów jednego koła. Pokazuje nazwę, logo, opis, wydział, tagi, linki społecznościowe, dodatkowe zdjęcia i formularz zgłoszenia do koła, jeśli rekrutacja jest otwarta. Zawiera też pomocnicze funkcje do budowania linków społecznościowych i osadzania Facebooka.

### `templates/community-list.php`

Publiczna lista wszystkich kół. Umożliwia filtrowanie, sortowanie i stronicowanie. Pokazuje karty kół z podstawowymi informacjami oraz linkami do szczegółów. Zawiera JavaScript do interakcji na liście.

### `templates/community-statistics.php`

Widok statystyk. Pobiera dane ze `sc_get_statistics_data()` i pokazuje metryki dla wszystkich kół lub tylko dla tych, którymi zarządza użytkownik.

### `templates/contact-requests.php`

Widok zgłoszeń kontaktowych od administratorów kół. Superadmin może zobaczyć, kto i w sprawie jakiego koła wysłał prośbę.

### `templates/dashboard.php`

Dashboard aktywności i jakości danych. Pokazuje podsumowania typu ostatnie edycje, najpopularniejsze koła, braki w danych i popularność tagów.

### `templates/debug-info.php`

Widok diagnostyczny. Pokazuje informacje pomocne przy debugowaniu, np. wersję pluginu, ścieżki, środowisko lub status wymaganych elementów.

### `templates/edit-community.php`

Duży formularz edycji koła. Pozwala zmieniać dane podstawowe, opisy, wydział, status, tagi, linki, logo, zdjęcia, mail kontaktowy i ustawienia rekrutacji. Może też pokazywać zgłoszenia kandydatów i narzędzia administracyjne powiązane z danym kołem.

### `templates/forum.php`

Widok forum. Tworzy kontener HTML dla listy wątków, wiadomości, formularza nowego wątku i formularza odpowiedzi. Większość danych ładuje JavaScript przez AJAX z `includes/forum.php`.

### `templates/manage-users.php`

Widok zarządzania użytkownikami. Pozwala superadminowi tworzyć/wybierać użytkowników, przypisywać ich do kół, usuwać przypisania i eksportować konta. Zawiera JavaScript ułatwiający obsługę formularzy.

### `templates/search-form.php`

Publiczny formularz wyszukiwarki. Pozwala wpisać frazę, wybrać tagi, wydziały i opcję otwartej rekrutacji. Po wysłaniu kieruje użytkownika do strony wyników.

### `templates/search-results.php`

Widok wyników wyszukiwania. Odczytuje parametry wyszukiwania, pobiera pasujące koła i pokazuje ich karty. Obsługuje sytuację braku wyników oraz linki do szczegółów.

---

## Katalog `assets/js/` — JavaScript

### `assets/js/admin-script.js`

Skrypt dla panelu administracyjnego. Obsługuje interakcje formularzy admina, m.in. upload logo przez AJAX, dynamiczne elementy formularzy, przyciski związane z Facebook pull i komunikaty po operacjach. Działa razem z danymi przekazanymi przez `wp_localize_script`, np. nonce i `ajaxurl`.

### `assets/js/forum.js`

Główna logika frontendu forum. Robi zapytania AJAX do WordPressa, pobiera listę wątków, pobiera wiadomości, wysyła nowe wiadomości, tworzy wątki, edytuje/usuwa elementy, obsługuje upload obrazków i odświeża widok bez przeładowania strony.

### `assets/js/layout-fixes.js`

Mały skrypt naprawiający problemy layoutu po stronie przeglądarki. Dodaje klasy albo poprawki do elementów strony, żeby plugin lepiej działał z motywem WordPressa.

### `assets/js/script.js`

Ogólny skrypt publiczny. Jest miejscem na proste zachowania frontendu wspólne dla publicznych stron pluginu. Aktualnie jest krótki i pełni rolę bazowego pliku JS.

---

## Katalog `assets/css/` — style

### `assets/css/admin-panel.css`

Główne style frontendowego panelu admina PKN. Definiuje wygląd kart, formularzy, przycisków, sekcji, komunikatów, układu profilu admina i elementów zarządzania kołami.

### `assets/css/admin.css`

Style dla klasycznego panelu WordPress/kokpitu. Uzupełnia wygląd stron administracyjnych, tabel i formularzy po stronie `wp-admin`.

### `assets/css/community-detail.css`

Style publicznej strony szczegółów koła. Odpowiada za układ nagłówka, logo, opisów, linków społecznościowych, tagów, galerii, formularza zgłoszeniowego i elementów dekoracyjnych.

### `assets/css/community-list.css`

Style publicznej listy kół. Definiuje karty kół, filtry, przyciski, siatkę/listę, paginację, responsywność i elementy stanu pustego.

### `assets/css/forum.css`

Style forum. Odpowiada za listę wątków, wiadomości, formularze, przyciski akcji, etykiety autorów, layout rozmowy i responsywność.

### `assets/css/globals.css`

Globalne poprawki stylów dla stron pluginu. Pomaga nadpisać konflikty z motywem WordPressa, ustawia szerokości, tła, typografię, dekoracje i ogólną spójność layoutu.

### `assets/css/results.css`

Style strony wyników wyszukiwania. Definiuje wygląd kart wyników, podsumowania wyszukiwania, komunikatu braku wyników i linków do szczegółów.

### `assets/css/search.css`

Style formularza wyszukiwania. Odpowiada za pola tekstowe, checkboxy/tagi, wybór wydziałów, przyciski, układ filtrów i responsywność.

### `assets/css/style.css`

Ogólny publiczny arkusz stylów pluginu. Zawiera bazowe style wspólne dla publicznych widoków, np. przyciski, kontenery, typografię lub drobne elementy UI.

---

## Katalog `assets/images/`

### `assets/images/underline.svg`

Dekoracyjny obrazek SVG z podkreśleniem. Jest używany pod tytułami, żeby strony pluginu bardziej przypominały stylistykę UG.

---

## Katalog `builds/`


### `builds/PKN.zip`

Gotowa paczka ZIP wtyczki. Jest to plik wynikowy, który można zainstalować w WordPressie albo udostępnić jako build. Nie zawiera logiki źródłowej do edycji bezpośrednio w repozytorium — jest efektem pakowania kodu.

### `builds/old/pkn-backend-Alpha-0.945.zip`

Archiwalna paczka ZIP starszej wersji pluginu `Alpha 0.945`. Służy jako kopia zapasowa poprzedniego builda.

### `builds/old/pkn-backend-Alpha-0.948.zip`

Archiwalna paczka ZIP starszej wersji pluginu `Alpha 0.948`. Służy jako kopia zapasowa poprzedniego builda.

### `builds/old/pkn-backend-Alpha-0.951.zip`

Archiwalna paczka ZIP starszej wersji pluginu `Alpha 0.951`. Służy jako kopia zapasowa poprzedniego builda.

### `builds/latest.json`

Ten plik jest opisany też wyżej, ale fizycznie znajduje się w `builds/`. To manifest aktualnej paczki builda. Updater może go czytać, żeby wiedzieć, jaka wersja jest najnowsza i skąd pobrać ZIP.

---

## Podsumowanie najważniejszych zależności

- `PKN-backend.php` ładuje moduły z `includes/` i template'y z `templates/`.
- `includes/functions.php` zarządza podstawowymi danymi kół.
- `includes/admin-functions.php` zarządza operacjami administracyjnymi, importem i uploadami.
- `includes/auth.php` decyduje, kto ma dostęp do czego.
- `includes/forum.php` obsługuje forum i jego AJAX.
- `includes/statistics.php` zapisuje i odczytuje statystyki.
- `includes/lang.php` dostarcza tłumaczenia dla template'ów i komunikatów.
- `includes/updater.php` łączy plugin z systemem aktualizacji.
- `templates/` pokazują HTML, ale cięższa logika powinna być w `includes/`.
- `assets/` odpowiada za wygląd i zachowanie w przeglądarce.
