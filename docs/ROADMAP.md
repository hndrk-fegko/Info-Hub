# Roadmap - Info-Hub

> Geplante Features und Entwicklungs-Ideen

## 🎯 Aktuelle Version: v1.0 MVP

Basis-Funktionalität abgeschlossen:
- ✅ 6 Tile-Typen (Infobox, Download, Bild, Link, Iframe, Countdown)
- ✅ Contact-Tile mit Crawler-Schutz (XOR-Verschleierung)
- ✅ Visibility-Toggle & Zeitsteuerung für Tiles
- ✅ Email-Code-Authentifizierung
- ✅ CSRF-Schutz & Session-Management
- ✅ Visueller Editor mit Live-Preview
- ✅ Embedding-Optionen (URL-Parameter)
- ✅ Security-Warnungen (Debug-Mode, HTTPS)
- ✅ Responsive Design mit CSS Grid

---

## ✅ Implementierte Features

### v1.1 - Visibility & Countdown

#### ✅ Visibility-Toggle für Tiles

Ein Button zum schnellen Ein-/Ausblenden von Tiles ohne Löschung.

**Features:**
- Manuell versteckte Tiles werden **NICHT** exportiert (Badge "⛔ Nicht im Export" im Editor)
- Zeitgesteuerte Sichtbarkeit mit `showFrom` / `showUntil` (wird exportiert, clientseitig gesteuert)
- Security-Warnung im Editor für zeitgesteuerte Inhalte

#### ✅ Countdown-Tile

Zählt Tage/Stunden bis zu einem Datum herunter.

**Features:**
- 4 Anzeigemodi: Dynamisch, nur Tage, nur Stunden, Timer (DD:HH:MM:SS)
- Ablauftext konfigurierbar
- Option: nach Ablauf automatisch ausblenden

---

### v1.2 - Scheduled Visibility & Contact-Tile

#### ✅ Scheduled Visibility

Tiles können zeitgesteuert ein-/ausgeblendet werden.

**Implementation:**
```json
{
  "visibilitySchedule": {
    "showFrom": "2026-01-20T00:00:00",
    "showUntil": "2026-02-28T23:59:59"
  }
}
```

**Use-Case: Countdown + Anmeldung**
1. **Countdown-Tile**: Zeigt "Anmeldung startet in X Tagen" → `showUntil: "2026-01-20"`
2. **Iframe-Tile**: Anmeldeformular → `showFrom: "2026-01-20"`

→ Countdown zählt runter, wird am Stichtag unsichtbar, Formular erscheint automatisch.

#### ✅ Contact-Tile

Kontaktdaten mit Crawler-Schutz anzeigen.

**Felder:**
- `name` - Name der Person
- `role` - Funktion/Rolle
- `image` - Profilbild (optional, rund mit Akzent-Rahmen)
- `email` - Email (XOR+Base64 verschleiert)
- `phone` - Telefon (XOR+Base64 verschleiert)
- `showEmailButton` - "Email anzeigen" Button
- `showPhoneButton` - "Telefon anzeigen" Button

**Anti-Spam:** Kontaktdaten erst bei Klick clientseitig entschlüsselt. Ohne JavaScript keine lesbaren Daten im HTML.

---

## 💡 Weitere Ideen

> Die folgenden Ideen sind Vorschläge für zukünftige Entwicklung.
> Priorisierung und Umsetzung nach Bedarf.

### Tile-Typen

- [ ] **Video-Tile** - YouTube/Vimeo Einbettung mit Preview-Bild
- [ ] **Event-Tile** - Termine mit Datum, Uhrzeit, Ort (Schema.org kompatibel)
- [ ] **Gallery-Tile** - Mehrere Bilder mit Slideshow
- [ ] **Accordion-Tile** - FAQ-Style aufklappbare Bereiche
- [ ] **Quote-Tile** - Bibelverse/Zitate mit stilisierter Darstellung
- [ ] **Map-Tile** - Standort-Karte (OpenStreetMap oder Google Maps)
- [ ] **Weather-Tile** - Wetter-Widget für Outdoor-Events
- [ ] **RSS-Tile** - Automatisch aktualisierte News-Feeds

### Editor-Features

- [ ] **Drag & Drop Sortierung** - Tiles per Drag verschieben
- [ ] **Bulk-Actions** - Mehrere Tiles gleichzeitig bearbeiten
- [ ] **Undo/Redo** - Änderungen rückgängig machen
- [ ] **Keyboard Shortcuts** - Schnellere Bedienung
- [ ] **Tile-Templates** - Vorgefertigte Tile-Kombinationen
- [ ] **Copy/Paste zwischen Instanzen** - Tiles zwischen Info-Hubs kopieren

### Design & Theming

- [ ] **Dark Mode** - Dunkles Farbschema
- [ ] **Theme-Presets** - Vorgefertigte Farbpaletten
- [ ] **Custom CSS** - Eigene CSS-Regeln hinzufügen
- [ ] **Header-Varianten** - Verschiedene Header-Layouts
- [ ] **Font-Auswahl** - Google Fonts Integration

### Technik & Sicherheit

- [ ] **Versionierung** - Frühere Versionen wiederherstellen
- [ ] **Import/Export** - Tiles als JSON exportieren/importieren
- [ ] **Multi-User** - Mehrere Redakteure mit verschiedenen Rechten
- [ ] **Audit-Log** - Wer hat wann was geändert
- [ ] **API-Keys** - Für externe Integrationen
- [ ] **Webhook-Support** - Bei Änderungen benachrichtigen

### Performance & SEO

- [ ] **Lazy Loading** - Bilder erst bei Scroll laden
- [ ] **Image Optimization** - Automatische Komprimierung
- [ ] **PWA-Support** - Progressive Web App Features
- [ ] **Sitemap** - Automatische Sitemap-Generierung
- [ ] **OpenGraph** - Social Media Preview-Bilder

### Integration

- [ ] **ChurchTools-Integration** - z.B. Termine automatisch importieren
- [ ] **Newsletter-Anbindung** - Mailchimp, CleverReach
- [ ] **Analytics** - Einfache Statistiken (DSGVO-konform)
- [ ] **Download-Counter** - Zählt PDF-Downloads

---

## 🤝 Contributing

Wir freuen uns über Beiträge! 

### So kannst du beitragen:

1. **Fork** das Repository
2. **Branch** erstellen (`git checkout -b feature/mein-feature`)
3. **Commit** deine Änderungen (`git commit -am 'Neues Feature: XYZ'`)
4. **Push** zum Branch (`git push origin feature/mein-feature`)
5. **Pull Request** erstellen

### Richtlinien:

- Halte dich an die bestehende Code-Struktur
- Neue Tile-Typen als separate Datei in `/backend/tiles/`
- Dokumentiere neue Features
- Teste vor dem PR

### Ideen einreichen:

Öffne ein [Issue](../../issues) mit dem Label `enhancement` für neue Feature-Ideen.

---

## 📋 Versionierung

Wir verwenden [Semantic Versioning](https://semver.org/):

- **MAJOR** (1.x.x): Breaking Changes
- **MINOR** (x.1.x): Neue Features (rückwärtskompatibel)
- **PATCH** (x.x.1): Bugfixes

---

*Letzte Aktualisierung: Januar 2026*
