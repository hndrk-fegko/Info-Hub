# Unit Tests

Diese Struktur enthaelt isolierte Tests fuer reine Fach- und Helper-Logik ohne Dateisystem-, Session-, HTTP- oder Browser-Kontext.

Aktuell abgedeckt:

- `test_media_path_helper.php`: Normalisierung und Validierung von Backend-Medienpfaden
- `test_render_contract.php`: kanonische Render-Keys und Fehlerfaelle von `RenderContract`

Neue Tests gehoeren hierhin, wenn sie gegen klar abgegrenzte Fachlogik laufen koennen, ohne `StorageService`, Session, HTTP-Request oder HTML-Ende-zu-Ende-Pfade mitzubenutzen.