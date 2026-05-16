# Konzept: `flat`, CSS-Organisation und neue `SectionTile`

## Ziel

Dieses Dokument beschreibt den neuen Zielzustand für drei zusammenhängende Themen:

- die Semantik von `flat`
- die saubere Organisation von Shared- und Tile-CSS
- die Architektur einer echten `SectionTile`

Der Fokus liegt auf einer Lösung, die sowohl mit dem Classic-Editor als auch mit dem WYSIWYG-Editor kompatibel bleibt.

## Stand nach der CSS-Bereinigung

### `flat`

`flat` ist weiterhin ein Tile-Stil und keine Abschnittslogik.

Neu geschärfte Semantik:

- kein Shadow
- kein Hover-Lift
- keine Rundung
- kein Innenabstand
- `color-default` bleibt transparent

Damit verhält sich `flat` klar als flächiger, neutraler Tile-Wrapper ohne Kartencharakter.

### CSS-Organisation

Die CSS-Trennung folgt jetzt konsequenter dem Projektprinzip:

- Shared-CSS in `assets/css/shared/` nur für globale, typübergreifende Regeln
- Tile-spezifisches CSS in `backend/tiles/XyzTile.css`
- Generator nur noch für dynamische CSS-Werte, nicht für allgemeine Layout-Regeln

Konkrete Bereinigung:

- Download-spezifische Styles gehören nicht in Shared-CSS und wurden nach `DownloadTile.css` ausgelagert.
- Allgemeines Narrow-Layout-CSS lässt sich als Shared-CSS modellieren; der Generator muss nur noch Variablen mit Laufzeitwerten setzen.

## Architektur-Analyse: echte `SectionTile`

## Zielbild

Eine `SectionTile` ist ein Marker in der Tile-Liste mit folgenden Eigenschaften:

- immer volle Breite
- gilt bis zur nächsten `SectionTile`
- definiert Abschnittshintergrund
- Abschnittshintergrund kann eine Akzentfarbe oder ein Bild sein
- Bild kann relativ zum Inhalt oder relativ zum Viewport positioniert sein
- hat eigene Sichtbarkeitssteuerung
- diese Sichtbarkeitssteuerung wirkt auf alle Tiles des Abschnitts

Dieses Modell passt fachlich sehr gut zu eurer bestehenden Sortierlogik, weil der Abschnitt über die Reihenfolge definiert wird und nicht über Verschachtelung im Editor.

## Warum das nicht rein modular im aktuellen Tile-System lösbar ist

Das aktuelle modulare Tile-System kann einzelne Tiles sehr gut kapseln:

- jede Tile rendert nur ihr eigenes HTML
- `renderSingleTile()` erzeugt immer genau einen Tile-Wrapper
- `renderTiles()` rendert die Liste streng Tile für Tile
- `renderAllTilesHtml()` liefert im WYSIWYG genau ein HTML-Fragment pro Tile-ID

Das reicht für normale Tiles, aber nicht für eine `SectionTile`, die nachfolgende Geschwister beeinflussen soll.

Die Kernbeschränkung ist:

- eine Tile kann im aktuellen Modell nicht den Wrapper für spätere Tiles öffnen oder schließen
- eine Tile kann nicht bestimmen, welche nachfolgenden Tiles zu ihrem Abschnitt gehören
- Sichtbarkeitssteuerung greift heute pro Tile, nicht pro Tile-Gruppe

## Ergebnis der Prüfung

Eine echte `SectionTile` ist **im bestehenden Datenmodell gut darstellbar**, aber **nicht allein durch eine neue Tile-Klasse umsetzbar**.

Es sind Eingriffe in den Generator und in den WYSIWYG-Renderpfad nötig.

## Was modular bleibt

Weiterhin modular lösbar:

- die neue Tile-Klasse `SectionTile.php`
- section-spezifische Felder, Validierung und Editor-Metadaten
- section-spezifisches CSS für die sichtbare Marker-Darstellung im Editor
- section-spezifisches JS nur dann, wenn der Editor Zusatzverhalten braucht

## Was den Generator ändern muss

Der Generator muss künftig nicht mehr nur einzelne Tiles rendern, sondern erst Abschnitte bilden.

Benötigte Verantwortung im Generator:

1. Die sortierte Tile-Liste in Abschnitte gruppieren.
2. Für jede `SectionTile` einen neuen Abschnitt beginnen.
3. Abschnittseinstellungen bis zur nächsten `SectionTile` anwenden.
4. Abschnitts-Sichtbarkeit vor dem Rendern der Kind-Tiles auswerten.
5. Für Export und WYSIWYG konsistente Abschnitts-Wrapper erzeugen.

Ohne diese Gruppierung würde eine `SectionTile` nur als normales Einzel-Element erscheinen und keine Wirkung auf Folge-Tiles entfalten.

## Empfohlenes Datenmodell für `SectionTile`

## Basisfelder

- `title` optional, nur für Editor-Orientierung
- `backgroundMode`: `default`, `accent1`, `accent2`, `accent3`, `image`
- `backgroundImage`: Pfad zum Bild
- `backgroundAttachment`: `content`, `viewport`
- `backgroundDisplay`: `cover`, `tile`
- `overlayEnabled`: ja/nein
- `overlayColor`
- `overlayOpacity`

## Sichtbarkeit

Die `SectionTile` sollte dieselbe Sichtbarkeitsstruktur wie normale Tiles verwenden:

- `visible`
- `visibilitySchedule.showFrom`
- `visibilitySchedule.showUntil`

Aber semantisch anders ausgewertet:

- die Sichtbarkeit wird auf den gesamten Abschnitt angewendet
- die Kind-Tiles brauchen keine eigene Spiegelung dieser Werte

## Abschnittsregeln

- ein impliziter Startabschnitt existiert immer vor der ersten `SectionTile`
- jede `SectionTile` startet einen neuen Abschnitt
- ein Abschnitt gilt bis zur nächsten `SectionTile`
- eine `SectionTile` rendert auf der veröffentlichten Seite standardmäßig keine sichtbare Kachel, sondern nur Abschnitts-Metadaten

## Ziel-Renderstruktur

Statt einer einzigen globalen `tile-grid` mit allen Tiles braucht die Seite mehrere Abschnitts-Wrapper.

Ziel-Markup:

```html
<main class="page-sections">
  <section class="tile-section section-default">
    <div class="tile-grid">
      ... tiles dieses Abschnitts ...
    </div>
  </section>

  <section class="tile-section section-accent1">
    <div class="tile-section__background"></div>
    <div class="tile-grid">
      ... tiles dieses Abschnitts ...
    </div>
  </section>
</main>
```

Für Bild-Hintergründe kann der Abschnitt zusätzliche Klassen oder CSS-Variablen tragen.

## Attachment-Logik für Bilder

Die Anforderung "relativ zu Inhalt oder relativ zu Viewport" ist umsetzbar.

Empfohlene Semantik:

- `content`: Hintergrund hängt am Abschnitts-Wrapper, scrollt normal mit
- `viewport`: Hintergrund liegt auf eigener Abschnittsebene und kann per `background-attachment: fixed` oder Parallax-Ansatz umgesetzt werden

Pragmatischer Hinweis:

- `background-attachment: fixed` ist auf Mobile inkonsistent
- für `viewport` sollte langfristig dieselbe Strategie wie beim Narrow-Backdrop gelten: Desktop `fixed`, Mobile `scroll`

## Sichtbarkeit auf Abschnittsebene

Das Ziel ist technisch sauber umsetzbar, aber nicht über die aktuelle per-Tile-Auswertung allein.

Empfohlene Regel:

- ist eine `SectionTile` manuell versteckt, wird der gesamte Abschnitt nicht exportiert
- hat eine `SectionTile` eine Zeitsteuerung, wird der Abschnitt im Export mit Section-Datenattributen erzeugt und clientseitig gemeinsam ein-/ausgeblendet

Dafür reicht die aktuelle Funktion für Tile-Schedule-Attribute nicht aus; sie muss auf Abschnitts-Wrapper anwendbar werden.

## Auswirkungen auf Classic-Editor

Das Modell ist für den Classic-Editor gut geeignet.

Warum:

- die Tile-Liste bleibt linear und sortierbar
- Drag-and-drop oder Positionslogik bleiben verständlich
- eine `SectionTile` lässt sich ähnlich wie heute ein `Separator` als Marker-Karte darstellen

Empfohlene Editor-Darstellung:

- deutlich als Abschnittsmarker kennzeichnen
- Hintergrundmodus und Sichtbarkeitsstatus direkt in der Karten-Meta anzeigen
- Folgeabschnitt muss nicht im Classic visuell verschachtelt werden; die Reihenfolge reicht als mentale Modellierung

## Auswirkungen auf WYSIWYG / V2

Hier ist zusätzlicher Umbau nötig.

Der WYSIWYG-Editor geht aktuell von folgendem Modell aus:

- `renderAllTilesHtml()` liefert ein Array mit einem HTML-Block pro Tile
- der Canvas hängt jeden Tile-Block in einen eigenen Editor-Wrapper
- Overlays, Auswahl und Toolbar arbeiten auf Tile-ID-Basis

Für echte Abschnitte reicht das nicht mehr aus.

Die zwei naheliegenden Extreme wären ein reiner Export-Renderer oder ein eigener Canvas-Renderer. Für dieses Projekt ist aber ein stärker shared-orientierter Zwischenweg sinnvoller.

## Bevorzugter Shared-Economy-Ansatz

Die größte Wartungsgefahr entsteht, wenn Export/Preview und Canvas jeweils ihre eigene Abschnittslogik bekommen.

Die bevorzugte Architektur sollte deshalb drei Ebenen sauber trennen:

### 1. Gemeinsame Kompositions-Ebene

Aus der linearen Tile-Liste wird genau einmal eine Abschnittsstruktur gebaut.

Empfehlung:

- neue gemeinsame Funktion oder eigener Service, z.B. `buildSectionLayout()`
- Input: sortierte Raw-Tiles
- Output: strukturierte Sections mit Marker-Metadaten, Sichtbarkeit, Hintergrund-Konfiguration und Kind-Tiles

Diese Ebene muss die Single Source of Truth sein für:

- Abschnittsgrenzen
- impliziten Startabschnitt
- Sichtbarkeit auf Abschnittsebene
- Hintergrund- und Overlay-Defaults
- Zuordnung der Tiles zu einem Abschnitt

### 2. Gemeinsame Content-DOM-Ebene

Preview/Export und Canvas sollten möglichst dieselbe inhaltliche DOM-Struktur verwenden.

Empfehlung:

- derselbe `tile-section`-Wrapper in beiden Modi
- dieselbe `tile-grid`-Struktur innerhalb der Section
- dieselbe Tile-HTML-Erzeugung über `renderSingleTile()` für die Kind-Tiles
- dieselben Shared-CSS-Dateien für Section- und Tile-Grundlayout

Die `SectionTile` selbst sollte nicht zwei verschiedene Inhaltsrepräsentationen bekommen.

Stattdessen:

- im Export ist sie ein Struktur-Marker, kein sichtbarer Inhaltsblock
- im Canvas bleibt die Section sichtbar, aber als Editor-Chrome auf Basis derselben Section-DOM

### 3. Editor-Chrome-Ebene

Was im Canvas zusätzlich sichtbar sein muss, sollte möglichst **nicht** als zweiter Inhaltsrenderer gebaut werden.

Empfehlung:

- sichtbarer Abschnittsrahmen
- Abschnitts-Label
- Quick Actions / Selection
- Insert-Hilfen

werden als Editor-Chrome ergänzt:

- per zusätzlichem Overlay-Element im Canvas
- oder per editor-spezifischem Marker innerhalb des Section-Wrappers
- aber nicht als eigenständige zweite Business-HTML-Struktur

Das reduziert Drift massiv: Der Inhalt bleibt derselbe, nur die Editor-Bedienelemente kommen hinzu.

## Praktische Konsequenz

Die beste Balance aus Shared Economy und Umsetzbarkeit ist damit:

1. dieselbe Abschnitts-Komposition für alle Modi
2. dieselbe Section- und Tile-DOM für Export, Preview und Canvas
3. editor-only Chrome für Sichtbarkeit der `SectionTile` als Rahmen/Marker

So bleibt die zentrale Differenz nicht im Content-Markup, sondern nur im Editor-Overlay.

## Warum das besser ist als zwei Renderer

Wenn Export und Canvas je einen eigenen Renderer für Sections hätten, müssten künftig doppelt gepflegt werden:

- Abschnittsgrenzen
- implizite Default-Sections
- Sichtbarkeitslogik
- Hintergrundbild-Logik
- Datenattribute für Zeitsteuerung
- leere oder aufeinanderfolgende Sections

Genau diese Regeln sind die teure Logik. Sie dürfen nicht an zwei Orten liegen.

## Minimal unvermeidbare Divergenz

Vollständig vermeiden lässt sich eine Differenz nicht, weil der Canvas Interaktions- und Orientierungsfunktionen braucht, die die veröffentlichte Seite nicht haben darf.

Unvermeidbar editor-spezifisch bleiben daher:

- Selection-Overlay
- sichtbarer Section-Marker
- Toolbar / Context Menu
- Drag-and-drop-Indikatoren
- Insert-Zonen

Diese Divergenz ist aber akzeptabel, solange sie nur Editor-Chrome betrifft und nicht die Abschnittslogik selbst.

## Maximale Shared-Economy-Variante

Eine noch konsequentere Variante wäre ein Canvas, der die echte Seitenstruktur in einem isolierten Preview-Dokument oder Iframe nutzt und nur Editor-Chrome darüberlegt.

Vorteil:

- Export/Preview und Canvas wären fast identisch

Nachteil:

- deutlich höhere technische Komplexität bei Selection, Drag-and-drop, Events und Synchronisation

Für die aktuelle Codebasis ist das eher eine spätere Evolutionsstufe als der erste Umsetzungsschritt.

### Referenz-Variante A: Abschnitte nur im finalen Export, WYSIWYG bleibt tile-basiert

Vorteile:

- kleinster Umbau im Editor
- SectionTile bleibt im WYSIWYG nur ein Marker

Nachteile:

- Vorschau im Editor stimmt nicht exakt mit der finalen Seite überein
- Abschnittshintergründe und Sichtbarkeitswirkung sind im Canvas nur eingeschränkt sichtbar

### Referenz-Variante B: WYSIWYG rendert echte Abschnitts-Wrapper

Vorteile:

- Vorschau entspricht der echten Seite
- Section-Sichtbarkeit und Hintergründe werden korrekt sichtbar

Nachteile:

- `renderAllTilesHtml()` muss zu einer Abschnittsstruktur erweitert werden
- Canvas und State müssen neben Tiles auch Section-Wrapper kennen
- Selection- und Overlay-Logik wird komplexer

## Empfehlung für V2

Für eine erste umsetzbare Version ist kein freier Hybrid mit zwei separaten Renderlogiken sinnvoll, sondern ein kontrollierter Shared-Economy-Hybrid:

1. Generator baut eine gemeinsame Abschnittsstruktur.
2. Export/Preview rendern daraus die echte Section-DOM.
3. Canvas verwendet dieselbe Section-DOM und ergänzt nur Editor-Chrome.
4. Nur wo das technisch nicht reicht, darf editor-spezifische Zusatzstruktur entstehen.

So driftet die teure Logik nicht auseinander.

## Kommentar-Regel für unvermeidbare Parallelbereiche

Wenn zwei parallele Bereiche nicht vermieden werden können, müssen sie im Code wechselseitig markiert werden.

Regel:

- jeder parallele Bereich bekommt einen Kommentar mit Pfad auf den korrespondierenden Bereich
- der Kommentar beschreibt kurz den gemeinsamen Vertrag
- Strukturänderungen dürfen nur mit Prüfung beider Stellen erfolgen

Empfohlene Form:

```php
// PARALLEL RENDER CONTRACT:
// Keep structure in sync with backend/v2/editor.php and assets/js/v2/canvas.js.
// Changes to section wrappers or data attributes must be mirrored there.
```

Typische Stellen für solche Verweise:

- Abschnitts-Komposition im Generator ↔ Canvas-Interpretation in V2
- Section-Datenattribute im Export ↔ editor-spezifische Overlay-Logik
- Sichtbarkeitsattribute am Wrapper ↔ clientseitige Schedule-Auswertung
- gemeinsame DOM-Verträge ↔ API-Responses für den WYSIWYG-Canvas

## Edge Cases

## 1. Tiles vor der ersten `SectionTile`

Empfehlung:

- impliziter `default`-Abschnitt

## 2. Zwei `SectionTile`s direkt hintereinander

Empfehlung:

- erlauben
- leerer Zwischenabschnitt wird nicht gerendert
- die zweite `SectionTile` überschreibt die erste für den nächsten Inhalt

## 3. Leerer Abschnitt am Ende

Empfehlung:

- nicht rendern
- Marker darf im Editor existieren, erzeugt aber ohne Folge-Tiles keine Frontend-Sektion

## 4. Versteckte `SectionTile`

Empfehlung:

- der Abschnitt wird vollständig ausgeblendet oder nicht exportiert
- die nächste sichtbare `SectionTile` startet dann den nächsten sichtbaren Abschnitt

## 5. Tiles mit eigener Sichtbarkeit innerhalb eines sichtbaren Abschnitts

Empfehlung:

- Abschnittssichtbarkeit ist die äußere Klammer
- Tile-Sichtbarkeit bleibt zusätzlich gültig
- effektiv sichtbar ist nur, was beide Bedingungen erfüllt

## 6. `card` in farbigem Abschnitt

Empfehlung:

- erlaubt und gewollt
- Card bleibt eigenständige Box auf Abschnittsfläche

## 7. `flat + default` in farbigem Abschnitt

Empfehlung:

- transparent belassen
- damit zeigt die Tile den Abschnittshintergrund

## CSS-Organisation: Zielbild

## Shared-CSS bleibt global

In Shared-CSS gehören nur Regeln, die nicht an einen einzelnen Tile-Typ gebunden sind:

- Variablen
- Reset / Basis
- Grid
- allgemeine Tile-Wrapper-Regeln
- Header / Footer
- allgemeine Komponenten
- allgemeine Narrow-Layout-Struktur
- künftig: allgemeine `tile-section`-Regeln

## Tile-CSS bleibt beim Modul

In `backend/tiles/XyzTile.css` gehört alles, was semantisch an einen Typ gebunden ist:

- `.tile-download ...`
- `.tile-quote ...`
- `.tile-link ...`
- `.tile-section-marker ...` für Editor-spezifische Marker-Darstellung

## Generator-CSS nur für Laufzeitwerte

Im Generator sollte künftig nur noch Inline-CSS verbleiben, das echte Runtime-Daten enthält:

- Theme-Farben
- dynamische Hintergrundbilder
- Overlay-Werte
- per-Section gesetzte CSS-Variablen

Allgemeine CSS-Regeln gehören nicht in den Generator.

## Umsetzungsplan

## Phase 1: vorbereitende Bereinigung

- `flat` visuell schärfen
- Tile-spezifisches CSS aus Shared entfernen
- allgemeine Narrow-Regeln in Shared-CSS überführen

## Phase 2: `SectionTile` einführen

- neue `SectionTile.php`
- Felder, Validierung und Editor-Metadaten
- Marker-Darstellung im Classic-Editor
- SectionTile in V2 als neuer Typ ohne normale Appearance-Controls

## Phase 3: Generator section-fähig machen

- Tiles zu Abschnitten gruppieren
- Abschnitts-Wrapper mit CSS-Variablen rendern
- Abschnittssichtbarkeit auswerten
- Abschnitts-Hintergründe rendern

## Phase 4: WYSIWYG angleichen

- zunächst Marker + approximierte Vorschau
- optional später echte Section-Wrapper im Canvas

## Empfehlung

Die Zielvorstellung ist mit dem bestehenden Projekt gut vereinbar, aber nicht als reine neue Tile-Datei isoliert lösbar.

Die richtige Architektur ist:

1. `SectionTile` als neues Marker-Tile im Datenmodell.
2. eine gemeinsame Abschnitts-Komposition als Single Source of Truth.
3. Generator rendert daraus echte Abschnitte.
4. Canvas nutzt dieselbe DOM-Struktur und ergänzt nur Editor-Chrome.
5. unvermeidbare Parallelbereiche werden per Kommentar gegenseitig referenziert.

Damit bleibt die Bedienlogik linear und editorfreundlich, während Rendering, Hintergründe und Zeitsteuerung endlich die richtige semantische Ebene bekommen.
