1. `/backend/login.php` im Browser oeffnen.
2. Security-Banner bei `DEBUG_MODE` oder fehlendem HTTPS pruefen.
3. Eine nicht freigegebene Email eingeben und die generische Rueckmeldung pruefen.
4. Eine freigegebene Email eingeben und den Login-Code beziehen.
5. Gueltigen Code eingeben und den Redirect nach `/backend/v2/editor.php` pruefen.
6. Im V2-Editor Header, Session-Timer und sichtbaren Canvas-Startzustand pruefen.