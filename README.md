# DomainGate – Restrict Registration by Email Domain

WordPress-Plugin, das die Benutzerregistrierung auf E-Mail-Adressen aus freigegebenen Domains beschränkt (z. B. nur `*@domain.de`).

## Funktionen

- Whitelist für E-Mail-Domains unter **Einstellungen → DomainGate** (Oberfläche mit [WP-Backend UI](https://github.com/mrclksr2409/WP-Backend))
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

## Updates

Updates kommen über den [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) direkt aus diesem Repository. Releases und Tags werden ignoriert, maßgeblich ist die Version im Plugin-Header des jeweiligen Branches:

- `main`: stabile Versionen (Standard)
- `beta`: Vorabversionen, aktivierbar unter **Einstellungen → DomainGate → Beta-Updates**

Für ein Update die Version in `domaingate.php` (Header und `DOMAINGATE_VERSION`) erhöhen und auf den Branch pushen.

## Mitgelieferte Bibliotheken

- `lib/wp-backend-ui/`: WP-Backend UI 1.1.0 (unveränderte Kopie, Änderungen nur im Repository WP-Backend)
- `vendor/plugin-update-checker/`: Plugin Update Checker 5.x

## Entwickler-Hook

```php
add_filter( 'domaingate_is_allowed', function ( $allowed, $email, $domain, $list ) {
    return $allowed || $email === 'partner@extern.com';
}, 10, 4 );
```

## Lizenz

GPL-2.0-or-later
