# Testing-Checkliste - Info-Hub

> Manuelle Testschritte für alle Features

## 🔐 Authentifizierung

### Login-Flow
- [ ] Login-Seite öffnen (`/backend/login.php`)
- [ ] Security-Banner wird angezeigt (bei DEBUG_MODE oder fehlendem HTTPS)
- [ ] Security-Banner kann geschlossen werden (×-Button)
- [ ] Ungültige Email → Fehlermeldung
- [ ] Falsche Email (nicht hinterlegte) → "Email-Adresse nicht berechtigt"
- [ ] Richtige Email → Code wird gesendet / angezeigt (DEBUG_MODE)
- [ ] Code-Eingabe-Feld erscheint
- [ ] Falscher Code 3x → 10 Minuten Sperre
- [ ] Richtiger Code → Redirect zu editor.php

### Session-Management
- [ ] Session-Timer im Header sichtbar
- [ ] Timer zählt runter
- [ ] Bei < 5 Minuten: Session-Dialog erscheint
- [ ] "Session verlängern" → Timer reset auf 60min
- [ ] "Abmelden" → Redirect zu login.php
- [ ] Aktivität (Speichern) → Session automatisch verlängert
- [ ] Nach Timeout → Redirect zu login.php mit "expired" Meldung

### Logout
- [ ] Logout-Button funktioniert
- [ ] Session wird beendet
- [ ] Redirect zu login.php

---

## 📝 Editor - Grundfunktionen

### Tile-Übersicht
- [ ] Alle Tiles werden geladen
- [ ] Tiles sind nach Position sortiert
- [ ] Tile-Karten zeigen: Typ, Titel, Position, Größe
- [ ] Drag-Handle sichtbar (Vorbereitung für D&D)

### Tile erstellen
- [ ] "+ Neue Kachel" Button funktioniert
- [ ] Modal öffnet sich
- [ ] Typ-Auswahl vorhanden (5 Typen)
- [ ] Felder ändern sich je nach Typ
- [ ] Pflichtfelder werden validiert
- [ ] "Speichern" erstellt neue Tile
- [ ] Tile erscheint in der Liste

### Tile bearbeiten
- [ ] Edit-Button (✏️) öffnet Modal
- [ ] Bestehende Daten werden geladen
- [ ] Änderungen speichern funktioniert
- [ ] Modal schließt nach Speichern

### Tile duplizieren
- [ ] Duplicate-Button (📋) funktioniert
- [ ] Neue Tile mit Kopie der Daten
- [ ] Position ist höher als Original

### Tile löschen
- [ ] Delete-Button (🗑️) zeigt Bestätigung
- [ ] "OK" löscht Tile
- [ ] "Abbrechen" behält Tile

---

## 🧱 Tile-Typen

### Infobox-Tile
- [ ] Erstellen mit Titel und Beschreibung
- [ ] "Titel anzeigen" Checkbox funktioniert
- [ ] Mit showTitle=true → Titel im Output
- [ ] Mit showTitle=false → Kein Titel im Output
- [ ] Markdown in Beschreibung wird interpretiert

### Download-Tile
- [ ] Datei-Upload funktioniert (PDF, DOCX, XLSX, ZIP)
- [ ] Maximalgröße 50MB wird geprüft
- [ ] Falscher Dateityp → Fehler
- [ ] Download-Button erscheint
- [ ] Button-Text anpassbar
- [ ] Download funktioniert

### Bild-Tile
- [ ] Bild-Upload funktioniert (JPG, PNG, GIF, WebP)
- [ ] Maximalgröße 5MB wird geprüft
- [ ] Vorschau im Editor
- [ ] Lightbox-Option ein/aus
- [ ] Bei Lightbox=true → Klick öffnet Lightbox
- [ ] Link-Option statt Lightbox
- [ ] Caption wird angezeigt

### Link-Tile
- [ ] URL-Feld validiert URLs
- [ ] Link-Text anpassbar
- [ ] "Externer Link" Checkbox
- [ ] Bei external=true → target="_blank"
- [ ] Beschreibung optional

### Iframe-Tile
- [ ] URL-Feld für Embed-URL
- [ ] HTTP-Warnung bei nicht-HTTPS URLs
- [ ] **Inline-Modus:**
  - [ ] Iframe wird direkt angezeigt
  - [ ] Seitenverhältnis wählbar (16:9, 4:3, 1:1, custom)
  - [ ] Custom-Höhe nur bei "custom" aktiv
- [ ] **Modal-Modus:**
  - [ ] Button "Formular öffnen" erscheint
  - [ ] Klick öffnet Modal mit Iframe
  - [ ] Mit showTitle=true → Titel im Modal-Header
  - [ ] Mit showTitle=false → Kein Titel im Modal
  - [ ] Modal schließen (×, ESC, außerhalb klicken)

---

## 🎨 Tile-Styling

### Größe
- [ ] 1x1, 1x2, 2x1, 2x2 wählbar
- [ ] Größe wirkt sich auf Grid aus

### Style
- [ ] "Flat" → Transparenter Hintergrund
- [ ] "Card" → Weißer Hintergrund mit Schatten

### Farbschema
- [ ] "Default" → Standard-Styling
- [ ] "Weiß" → Weißer Hintergrund
- [ ] "Akzent 1/2/3" → Farbiger Hintergrund
- [ ] Text-Kontrast wird automatisch angepasst (WCAG)

---

## ⚙️ Einstellungen

### Site-Einstellungen
- [ ] Settings-Button (⚙️) öffnet Modal
- [ ] Seiten-Titel änderbar
- [ ] Header-Bild upload
- [ ] Header-Bild entfernen
- [ ] Speichern aktualisiert Daten

### Theme-Einstellungen
- [ ] Hintergrundfarbe (Color-Picker)
- [ ] Primär-Akzentfarbe
- [ ] Akzentfarbe 2, 3, 4
- [ ] Farben werden gespeichert

### Footer
- [ ] Footer-Text editierbar
- [ ] Mehrzeilig möglich (Textarea)
- [ ] HTML wird escaped

---

## 👁️ Preview & Publish

### Preview
- [ ] Preview-Button öffnet Vorschau
- [ ] Vorschau zeigt aktuelle Tiles
- [ ] Vorschau aktualisiert sich bei Speichern
- [ ] Vorschau in neuem Tab/Fenster

### Publish
- [ ] Publish-Button (🚀) funktioniert
- [ ] Bestätigungsdialog erscheint
- [ ] index.html wird generiert
- [ ] "Veröffentlicht"-Badge im Header
- [ ] Link zur veröffentlichten Seite

---

## 🌐 Frontend (Generierte Seite)

### Basis-Darstellung
- [ ] index.html lädt korrekt
- [ ] Alle Tiles werden angezeigt
- [ ] Sortierung nach Position
- [ ] Header mit Bild (falls konfiguriert)
- [ ] Footer-Text korrekt
- [ ] Responsive auf Mobile

### Interaktionen
- [ ] Lightbox für Bilder funktioniert
- [ ] Lightbox schließen (×, ESC, außerhalb)
- [ ] Download-Buttons laden Dateien
- [ ] Links öffnen (extern in neuem Tab)
- [ ] Iframe-Modal öffnet/schließt

### Embedding-Parameter
- [ ] `?embedded=true` → Header/Footer versteckt
- [ ] `?style=clean` → Transparenter Hintergrund
- [ ] `?style=minimalbox` → Alle Tiles weiß
- [ ] Kombination funktioniert

---

## 🔒 Security

### CSRF-Schutz
- [ ] Token wird bei Login generiert
- [ ] API-Calls enthalten Token
- [ ] Fehler bei fehlendem/falschem Token
- [ ] Token im JavaScript verfügbar

### Security-Warnungen
- [ ] **Login-Seite:**
  - [ ] Banner bei DEBUG_MODE aktiv
  - [ ] Banner bei fehlendem HTTPS (Production)
  - [ ] Banner bei display_errors aktiv
  - [ ] Banner kann geschlossen werden
- [ ] **Editor:**
  - [ ] Badge im Header bei Warnungen
  - [ ] Tooltip zeigt Details
- [ ] **Login-Email:**
  - [ ] Sicherheitsinfos am Ende der Email

### .htaccess
- [ ] /backend/data/ nicht direkt aufrufbar
- [ ] /backend/logs/ nicht direkt aufrufbar
- [ ] JSON-Dateien nicht aufrufbar
- [ ] /backend/core/*.php nicht aufrufbar
- [ ] /backend/tiles/*.php nicht aufrufbar

---

## 📱 Responsive Design

### Desktop (> 1024px)
- [ ] 4-Spalten Grid
- [ ] Sidebar/Modal gut platziert

### Tablet (768px - 1024px)
- [ ] 2-Spalten Grid
- [ ] Touch-freundliche Buttons

### Mobile (< 768px)
- [ ] 1-Spalte Grid
- [ ] Modal nimmt volle Breite
- [ ] Hamburger-Menü (falls vorhanden)

---

## 🐛 Edge Cases

### Leere Zustände
- [ ] Keine Tiles → "Noch keine Kacheln" Hinweis
- [ ] Kein Header-Bild → Kein Header-Bereich

### Fehlerbehandlung
- [ ] Server offline → Fehlermeldung
- [ ] Upload zu groß → Fehlermeldung
- [ ] Ungültiger Dateityp → Fehlermeldung
- [ ] Session abgelaufen → Redirect mit Meldung

### Browser-Kompatibilität
- [ ] Chrome (aktuell)
- [ ] Firefox (aktuell)
- [ ] Safari (aktuell)
- [ ] Edge (aktuell)

---

## ✅ Checkliste für Release

- [ ] DEBUG_MODE = false in `backend/config.php`
- [ ] HTTPS aktiviert
- [ ] setup.php gelöscht
- [ ] Alle Ordner-Berechtigungen korrekt
- [ ] Backup erstellt
- [ ] Finale Tests auf Production-Server

---

*Checkliste Version: 1.0 | Januar 2026*
