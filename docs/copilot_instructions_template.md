# COPILOT_INSTRUCTIONS_TEMPLATE

**Zweck:** 🤖 KI-Assistenten Leitfaden - Projektunabhängige Best Practices  
**Target:** GitHub Copilot / Cursor AI / Claude  
**Version:** 1.0  

---

## 📋 Projekt-Kontext

<!-- Projektbeschreibung hier einfügen -->
**Projektname:** [NAME]  
**Beschreibung:** [KURZBESCHREIBUNG]  
**Tech-Stack:** [z.B. Node.js, React, Python, etc.]  

**Wichtig:** Dokumentation im `/docs` Ordner ist die **Single Source of Truth**.

---

## 🎯 Kernprinzipien

### 1. **Contract-First Development**
- API-Dokumentation ist verbindlich → Frontend/Backend unabhängig entwickelbar
- Jede Änderung am Contract muss dokumentiert werden
- TypeScript Interfaces als Referenz (auch bei JavaScript-Projekten)

### 2. **Event-Driven Architecture** (wenn zutreffend)
- ❌ **KEINE** Polling-Intervalle (`setInterval` vermeiden!)
- ✅ **JA** Event-basierte Updates (WebSockets, SSE, etc.)
- ✅ **JA** Database-Triggers für Aggregationen

### 3. **Dokumentation First**
- README.md immer aktuell halten
- Code-Änderungen mit entsprechenden Doc-Updates
- JSDoc/TSDoc für Public Functions

---

## 📝 Naming Conventions

**Konsequent durch gesamte Codebase.**

| Bereich | Konvention | Beispiel |
|---------|-----------|----------|
| **Dateien** | `kebab-case` | `user-service.js` |
| **Database** | `snake_case` | `user_accounts`, `created_at` |
| **JavaScript/TypeScript** | `camelCase` | `getUserById()`, `isActive` |
| **Klassen/Interfaces** | `PascalCase` | `UserService`, `IUserRepository` |
| **Konstanten** | `UPPER_SNAKE_CASE` | `MAX_RETRY_COUNT` |
| **CSS/HTML** | `kebab-case` | `.user-card`, `#main-container` |
| **Environment Variables** | `UPPER_SNAKE_CASE` | `DATABASE_URL` |
| **REST Endpoints** | `kebab-case` | `/api/user-accounts` |
| **Events** | `namespace:action` | `user:created`, `order:updated` |

---

## 🏗️ Code-Struktur Best Practices

### Allgemein

#### 1. Async/Await + Error Handling

```javascript
// ✅ GOOD: Async/Await + Error Handling
async function fetchData(id) {
  try {
    const result = await database.query(id);
    return { success: true, data: result };
  } catch (error) {
    logger.error('Fetch failed', { error, id });
    throw new Error('Data fetch failed');
  }
}

// ❌ BAD: Keine Error Handling
async function fetchData(id) {
  const result = await database.query(id); // kann crashen!
  return result;
}
```

#### 2. Input Validation

```javascript
// ✅ GOOD: Vollständige Validation
function processRequest(data) {
  if (!data) throw new Error('Data required');
  if (!data.id) throw new Error('ID required');
  if (typeof data.id !== 'string') throw new Error('ID must be string');
  // Weiter mit sicheren Daten
}

// ❌ BAD: Keine Validation
function processRequest(data) {
  return database.update(data.id, data.value); // data kann null sein!
}
```

#### 3. Database-Queries (SQL)

```javascript
// ✅ GOOD: Prepared Statements
function getUser(userId) {
  const stmt = db.prepare('SELECT * FROM users WHERE id = ?');
  return stmt.get(userId);
}

// ❌ BAD: String Concatenation (SQL Injection!)
function getUser(userId) {
  const sql = `SELECT * FROM users WHERE id = ${userId}`;
  return db.exec(sql);
}
```

#### 4. Atomic Database Operations

```javascript
// ❌ BAD: Race Condition
async function incrementCounter(id) {
  const item = await db.get(id);
  const newValue = item.counter + 1;
  await db.update(id, { counter: newValue });
  // Problem: Zwischen GET und UPDATE kann anderer Request laufen!
}

// ✅ GOOD: Atomic Update
function incrementCounter(id) {
  db.prepare('UPDATE items SET counter = counter + 1 WHERE id = ?').run(id);
}
```

---

### Backend (Node.js/Express)

#### Route-Handler

```javascript
// ✅ GOOD: Strukturierter Handler
router.get('/api/resource/:id', async (req, res) => {
  try {
    // 1. Input Validation
    const { id } = req.params;
    if (!id) return res.status(400).json({ error: 'ID required' });
    
    // 2. Business Logic
    const result = await service.getById(id);
    if (!result) return res.status(404).json({ error: 'Not found' });
    
    // 3. Response
    res.json({ success: true, data: result });
  } catch (error) {
    logger.error('Request failed', { error, path: req.path });
    res.status(500).json({ error: 'Internal server error' });
  }
});
```

#### WebSocket Event Handler (wenn zutreffend)

```javascript
// ✅ GOOD: Validation + Callback
socket.on('action:name', async (data, callback) => {
  try {
    // 1. Validate
    if (!validateInput(data)) {
      return callback({ success: false, error: 'Invalid input' });
    }
    
    // 2. Process
    const result = await processAction(data);
    
    // 3. Broadcast (optional)
    io.emit('action:completed', result);
    
    // 4. Callback
    callback({ success: true, data: result });
  } catch (error) {
    logger.error('Action failed', { error, data });
    callback({ success: false, error: 'Internal error' });
  }
});
```

---

### Frontend (JavaScript/TypeScript)

#### Event Listener Management

```javascript
// ✅ GOOD: Cleanup vor neuen Listenern
function setupListeners() {
  element.removeEventListener('click', handleClick);
  element.addEventListener('click', handleClick);
}

// ❌ BAD: Listener stacked (Memory Leak!)
function setupListeners() {
  element.addEventListener('click', handleClick);
  element.addEventListener('click', handleClick); // Doppelt!
}
```

#### API Calls mit Error Handling

```javascript
// ✅ GOOD: Vollständiges Error Handling
async function fetchResource(id) {
  try {
    const response = await fetch(`/api/resource/${id}`);
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    const data = await response.json();
    return data;
  } catch (error) {
    showError('Laden fehlgeschlagen');
    logger.error('Fetch failed', { error, id });
    return null;
  }
}
```

---

## 🐛 Debugging Best Practices

### 1. Strukturiertes Logging

```javascript
// ✅ GOOD: Kategorisiertes Logging mit Kontext
logger.info('[API] Request received', { path: '/users', method: 'GET' });
logger.error('[DB] Query failed', { error: err.message, query: 'SELECT...' });
logger.debug('[AUTH] Token validated', { userId: '123' });

// ❌ BAD: Generisches Logging ohne Kontext
console.log('Something happened');
logger.info('Error', error);
```

### 2. Log-Level Richtlinien

| Level | Verwendung |
|-------|------------|
| `error` | Fehler die Aktion verhindern |
| `warn` | Potentielle Probleme, Aktion fortgesetzt |
| `info` | Wichtige Business-Events |
| `debug` | Entwickler-Details (nur Dev) |

### 3. Performance-Monitoring

```javascript
// Execution Time messen
const start = performance.now();
await expensiveOperation();
const duration = performance.now() - start;
logger.info('Operation completed', { duration: `${duration.toFixed(2)}ms` });
```

---

## ⚠️ Common Pitfalls

### 1. Memory Leaks

```javascript
// ❌ BAD: Event Listener nicht entfernt
class Component {
  init() {
    window.addEventListener('resize', this.handleResize);
  }
  // Kein cleanup! → Memory Leak
}

// ✅ GOOD: Cleanup in destroy
class Component {
  init() {
    this.boundHandler = this.handleResize.bind(this);
    window.addEventListener('resize', this.boundHandler);
  }
  destroy() {
    window.removeEventListener('resize', this.boundHandler);
  }
}
```

### 2. Blocking Operations

```javascript
// ❌ BAD: Synchrone I/O blockiert Event Loop
const data = fs.readFileSync('large-file.json');

// ✅ GOOD: Async I/O
const data = await fs.promises.readFile('large-file.json');
```

### 3. Unhandled Promise Rejections

```javascript
// ❌ BAD: Promise ohne catch
fetchData().then(process);

// ✅ GOOD: Error handling
fetchData()
  .then(process)
  .catch(error => logger.error('Failed', { error }));

// Oder mit async/await
try {
  const data = await fetchData();
  process(data);
} catch (error) {
  logger.error('Failed', { error });
}
```

### 4. Graceful Shutdown (Server)

```javascript
// ✅ GOOD: Cleanup bei Shutdown
const shutdown = async (signal) => {
  logger.info(`${signal} received, shutting down...`);
  
  // Force exit nach Timeout
  const forceExit = setTimeout(() => process.exit(1), 5000);
  
  try {
    await server.close();
    await database.close();
    clearTimeout(forceExit);
    process.exit(0);
  } catch (error) {
    logger.error('Shutdown error', { error });
    process.exit(1);
  }
};

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
```

### 5. Environment-spezifische Konfiguration

```javascript
// ✅ GOOD: Environment Variables nutzen
const config = {
  port: process.env.PORT || 3000,
  dbPath: process.env.DATABASE_PATH || './data/app.db',
  logLevel: process.env.LOG_LEVEL || 'info',
  isDev: process.env.NODE_ENV === 'development'
};

// ❌ BAD: Hardcoded Values
const PORT = 3000;
const DB_PATH = '/var/data/production.db';
```

---

## ✅ Code Quality Checklisten

### Pre-Commit Checklist

- [ ] Keine `console.log()` (nutze Logger)
- [ ] Keine TODO-Kommentare ohne Issue-Referenz
- [ ] Alle Promises haben Error-Handling
- [ ] Input Validation für alle Public Functions
- [ ] SQL-Queries nutzen Prepared Statements
- [ ] Naming Conventions eingehalten
- [ ] JSDoc/TSDoc für Public Functions
- [ ] Keine auskommentierten Code-Blöcke

### Pre-Deployment Checklist

- [ ] Alle Tests grün
- [ ] `npm audit` / Dependency Check
- [ ] Environment Variables dokumentiert
- [ ] Error-Handling getestet
- [ ] Performance-Test bei kritischen Pfaden
- [ ] Logging funktioniert
- [ ] Graceful Shutdown implementiert
- [ ] Backup-Strategie vorhanden

---

## 🚀 Getting Started (für KI-Agent)

### Vor dem Coding

1. **README.md lesen** → Projekt-Übersicht verstehen
2. **Dokumentation prüfen** → API-Contracts, Schemas
3. **Bestehende Patterns erkennen** → Coding-Style übernehmen
4. **Dependencies kennen** → package.json / requirements.txt

### Während des Codings

1. **Kleine Commits** → Eine logische Änderung pro Commit
2. **Tests schreiben** → Mindestens für kritische Pfade
3. **Dokumentieren** → Code-Kommentare + README-Updates
4. **Validieren** → Linter + Type-Check + Tests ausführen

### Bei Problemen

1. **Logs prüfen** → Strukturierte Logs analysieren
2. **Stack Trace lesen** → Fehlerursache identifizieren
3. **Dokumentation referenzieren** → API/Schema-Docs
4. **Isolieren** → Minimales Reproduktions-Beispiel

---

## 💡 KI-Agent Best Practices

### Do's ✅

- **Dokumentation zuerst lesen** → Verstehe die Schnittstellen
- **Bestehende Patterns übernehmen** → Konsistenz wahren
- **Kleine, fokussierte Änderungen** → Leichter zu reviewen
- **Error Handling immer** → Robuster Code
- **Tests mitliefern** → Vertrauen in Änderungen
- **Logging nutzen** → Debugging ermöglichen

### Don'ts ❌

- **Keine Breaking Changes** ohne Absprache
- **Keine Magic Numbers** → Konstanten definieren
- **Keine tiefen Verschachtelungen** → Early Returns nutzen
- **Keine God-Objects** → Single Responsibility
- **Keine Copy-Paste-Programmierung** → DRY-Prinzip

---

## 🎯 Erfolgs-Kriterien

Code ist **production-ready** wenn:

- ✅ Alle Tests grün
- ✅ Keine Linter-Errors/Warnings
- ✅ Error-Handling vollständig
- ✅ Logging implementiert
- ✅ Dokumentation aktuell
- ✅ Keine `console.log()` (nur Logger)
- ✅ Keine offenen TODOs
- ✅ Code-Review bestanden

---

## 📚 Projekt-spezifische Ergänzungen

<!-- Hier projektspezifische Anweisungen einfügen -->

### Wichtige Dateien
- `README.md` - Projekt-Übersicht
- `docs/` - Detaillierte Dokumentation

### Lokale Entwicklung
```bash
# Setup
npm install

# Development
npm run dev

# Tests
npm test
```

---

**Viel Erfolg beim Implementieren! 🚀**
