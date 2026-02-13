# Bedienungsanleitung - Info-Hub

> Anleitung für Redakteure und Content-Ersteller

## Anmelden

1. Öffne /backend/login.php
2. Gib deine hinterlegte Email-Adresse ein
3. Du erhältst einen 6-stelligen Code per Email
4. Gib den Code ein  Du wirst zum Editor weitergeleitet

**Hinweis:** Der Code ist 15 Minuten gültig.

### Mehrere Admins

Falls du von einem anderen Admin eingeladen wurdest:
1. Du erhältst eine Email mit einem Einladungslink
2. Klicke den Link innerhalb von 1 Stunde
3. Melde dich mit deiner Email-Adresse an → dein Zugang wird aktiviert

### Security-Warnungen

Falls oben auf der Seite ein gelbes Banner erscheint, zeigt es Sicherheitshinweise:

- **Debug-Modus aktiv** - Login-Code wird ohne Email angezeigt (nur für Entwicklung!)
- **Keine HTTPS-Verbindung** - Seite sollte mit SSL aufgerufen werden

---

## Neue Kachel erstellen

1. Klicke auf **[+ Neue Kachel]**
2. Wähle den **Typ**:
   - **Infobox** - Für Texte und Ankündigungen
   - **Download** - Für PDF, Word, etc.
   - **Bild** - Für Fotos mit Lightbox
   - **Link** - Für externe Verlinkungen
   - **Iframe** - Für eingebettete Formulare/Widgets
   - **Zitat** - Für Zitate oder Bibelverse
   - **Kontakt** - Für Kontaktpersonen (mit Spam-Schutz)
   - **Countdown** - Countdown zu einem Datum
   - **Akkordeon** - Auf-/zuklappbare Bereiche
   - **Separator** - Optischer Trenner zwischen Bereichen
3. Fülle die Felder aus
4. Wähle **Position** (10, 20, 30... für Sortierung)
5. Wähle **Größe** (1x1, 1x2, 2x2...)
6. Wähle **Style**:
   - **Flat** - Ohne Rahmen, fügt sich in Hintergrund
   - **Card** - Mit Rahmen und Schatten
7. Wähle **Farbschema** (Default, Weiß, Akzent 1-3)
8. Klicke **[Speichern]**

### Titel anzeigen

Jede Kachel hat ein **Titel**-Feld (Pflicht für die Übersicht im Editor).
Mit der Checkbox **"Titel anzeigen"** kannst du steuern, ob der Titel
auf der veröffentlichten Seite sichtbar ist.

---

## Iframe-Kachel (für Formulare)

Die Iframe-Kachel eignet sich für:
- Anmeldeformulare (Google Forms, Typeform, etc.)
- Kalender-Widgets
- Karten (Google Maps)

**Einstellungen:**

- **URL** - Die Einbettungs-URL des Formulars
- **Anzeigemodus**:
  - *Inline* - Formular direkt in der Kachel
  - *Modal* - Formular öffnet sich in einem Popup
- **Seitenverhältnis** - 16:9, 4:3, 1:1, 9:16

---

## Kachel bearbeiten

1. Klicke auf **[]** bei der gewünschten Kachel
2. Ändere die Felder
3. Klicke **[Speichern]**

---

## Kachel duplizieren

1. Klicke auf **[]** bei der gewünschten Kachel
2. Eine Kopie wird erstellt (mit höherer Position)
3. Bearbeite die Kopie nach Bedarf

---

## Kachel löschen

1. Klicke auf **[]**
2. Bestätige die Löschung

---

## Sortierung ändern

Die Kacheln werden nach der **Position**-Nummer sortiert (aufsteigend).

**Tipp:** Verwende Abstände von 10 (10, 20, 30...), dann kannst du später leicht Kacheln dazwischen einfügen (z.B. Position 15).

---

## Seiten-Einstellungen

Klicke auf **[ Settings]** um zu ändern:

- **Seiten-Titel** - Erscheint im Header
- **Header-Bild** - Großes Bild oben (oder keins)
- **Header-Fokuspunkt** - Welcher Teil des Bildes sichtbar bleibt (z.B. "oben mitte")
- **Hintergrundfarbe** - Farbe der Seite
- **Akzentfarben** (4 Stück) - Für Buttons, Links und Farbschemata
- **Footer-Text** - Text am Seitenende (mehrzeilig möglich)

---

## Vorschau & Veröffentlichen

- **[ Preview]** - Zeigt die Seite wie sie aussehen wird
- **[ Publish]** - Generiert die finale index.html

Die Preview aktualisiert sich automatisch wenn du Änderungen speicherst.

---

## Session-Timeout

Nach 1 Stunde wirst du automatisch abgemeldet.
5 Minuten vorher erscheint ein Dialog:

- **[Session verlängern]** - Weitere Stunde arbeiten
- **[Abmelden]** - Sofort ausloggen

Bei Aktivität (Speichern, Klicken) verlängert sich die Session automatisch.

---

## Tipps

- Lade Bilder in passender Größe hoch (max. 5MB)
- Nutze aussagekräftige Dateinamen
- Speichere regelmäßig
- Prüfe die Vorschau vor dem Veröffentlichen
- Bei Inaktivität: Session verlängern nicht vergessen!

---

## Hilfe benötigt?

Bei technischen Problemen wende dich an den Administrator.
