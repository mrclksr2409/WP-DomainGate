# DomainGate – Restrict Registration by Email Domain

WordPress-Plugin, das die Benutzerregistrierung auf E-Mail-Adressen aus freigegebenen Domains beschränkt (z. B. nur `*@domain.de`).

## Funktionen

- Whitelist für E-Mail-Domains unter **Einstellungen → DomainGate**
- Unterstützte Schreibweisen (eine pro Zeile):
  - `domain.de` oder `*@domain.de`: nur genau diese Domain
  - `*.domain.de`: alle Subdomains (z. B. `mail.domain.de`)
- Funktioniert mit der Standard-Registrierung (`wp-login.php?action=register`) und mit Multisite (`wp-signup.php`)
- Ist die Liste leer, wird niemand blockiert
- Filter `domaingate_is_allowed` für eigene Regeln

## Installation

1. Ordner nach `wp-content/plugins/domaingate` kopieren
2. Plugin im WordPress-Backend aktivieren
3. Unter **Einstellungen → DomainGate** die erlaubten Domains eintragen

## Entwickler-Hook

```php
add_filter( 'domaingate_is_allowed', function ( $allowed, $email, $domain, $list ) {
    return $allowed || $email === 'partner@extern.com';
}, 10, 4 );
```

## Lizenz

GPL-2.0-or-later
