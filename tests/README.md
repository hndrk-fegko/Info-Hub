# Test Suite

Der Einstieg fuer automatisierte und manuell registrierte Tests ist `tests/run.php`.

Struktur:

- `tests/integration/`: aktuell ausführbare PHP-Regressionen und Contract-Tests
- `tests/e2e/`: manuelle Browser-/End-to-End-Szenarien, die zentral ueber den Runner gelistet werden
- `tests/unit/`: isolierte Unit-Tests fuer reine Fach- und Helper-Logik
- `tests/manifest.php`: zentrale Registrierung aller Runner-Eintraege
- `tests/api_endpoint_test_helper.php`: gemeinsamer Helper fuer API-Tests

Beispiele:

```bash
php tests/run.php --list
php tests/run.php --suite=contracts
php tests/run.php --suite=unit
php tests/run.php --suite=render,api --format=json
php tests/run.php --test=section-render-contract
php tests/run.php --suite=e2e
```

Wrapper:

```powershell
./scripts/test.ps1 --suite=contracts
```

```bash
./scripts/test.sh --suite=contracts
```

Aktuelle Suite-Tags:

- `unit`: isolierte Tests ohne Storage-, Session-, HTTP- oder Browser-Kontext
- `smoke`: schnelle Render-/Editor-Grundchecks
- `contracts`: explizite Contract-Tests zwischen Generator, API und V2-Editor
- `integration`: mehrteilige Service-/Workflow-Tests
- `render`: HTML-/CSS-/Canvas-Renderpfade
- `api`: API-Endpunkte und deren Verträge
- `editor`: Editor-spezifische Regressionen
- `backup`: Backup-/Restore-Workflows
- `frontend`: JS-/CSS-Asset-Struktur
- `layout`: Layout-Varianten wie Narrow
- `auth`: Login-/Code-/Session-Vertraege des AuthService
- `upload`: Upload-Validierung sowie Media-List/Delete-Vertraege
- `settings`: SettingsService- und Settings-Endpoint-Vertraege
- `crud`: Tile-CRUD-Endpunkte gegen TileService
- `service`: Service-zentrierte Vertraege ohne UI/Browser
- `manual`: nicht automatisierte, aber zentral registrierte Browser-/E2E-Checks
- `e2e`: manuelle End-to-End-Szenarien aus der Browser- und Publish-Perspektive

Die Suite-Gruppierung wird in `tests/manifest.php` gepflegt.

Manuelle E2E-Szenarien werden ueber denselben Runner mit `--suite=e2e` ausgegeben. Der Runner fuehrt diese nicht automatisch aus, sondern listet die zu pruefenden Schritte aus `tests/e2e/*.md`.