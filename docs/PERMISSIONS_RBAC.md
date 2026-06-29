# Rollenbasiertes Berechtigungssystem (RBAC)

Das System nutzt ein modernes **Role-Based Access Control (RBAC)** Modell. Es gibt keine starren "User-Level" (wie 1, 2 oder 3) mehr. Stattdessen basiert alles auf granularen Berechtigungen (Permissions).

## 1. Das Konzept: Berechtigungen statt Level

Eine Berechtigung (Permission) ist ein eindeutiger String, der eine exakte Aktion oder Ansicht erlaubt.
Beispiele:

* `dashboard.finance.view`: Erlaubt das Sehen des Finanz-Tabs.
* `privacy.finance.reveal`: Erlaubt das Einsehen von genauen Geldbeträgen.
* `dashboard.finance.suspend`: Erlaubt das Sperren von Genehmigungen im Finanz-Tab.

**Gruppen (Rollen):**
Eine Gruppe (z. B. "Prüfer", "Buchhaltung", "Vorstand") ist lediglich ein Container, dem diese Permissions zugewiesen werden. Benutzer werden einer Gruppe zugeordnet und erben deren Rechte.

## 2. Hierarchische Rechte & Wildcards (`*`)

Das System unterstützt Hierarchien und Wildcards, ähnlich wie komplexe Enterprise-Systeme, um den Administrationsaufwand gering zu halten.

* Wer das Recht `template.*` besitzt, darf automatisch **alle** bestehenden und künftig hinzugefügten Vorlagen (z. B. `template.std_7`, `template.perm_3`) nutzen.
* Wer in seiner Gruppe das nackte `*` (Sternchen) besitzt, hat den absoluten **Gott-Modus** und darf alles im System tun.
* Auf diese Wildcards solte aber verzichtet werden und statdessen aktiv das inApp Interface zur Rechteverteilung genutzt werden. `public/user.php`

## 3. Der SuperAdmin-Bypass (Notfall-Zugang)

In der `config/config.php` kann ein `superadmin` definiert werden. Loggt sich dieser Benutzer ein, hat er systemweit *immer* alle Rechte, unabhängig davon, in welcher Gruppe er sich befindet oder ob die Datenbankverbindung zu den Gruppen intakt ist. Dies dient als sichere "Hintertür" (Backdoor) für den Entwickler oder Haupt-Administrator.

## 4. Permissions im Code abfragen

Im Code (PHP oder in den Templates) wird die Berechtigung über den injizierten `AuthService` geprüft.

**Beispiel:** Einen Tab im Dashboard ausblenden

```php
<?php if ($auth->hasPermission('dashboard.finance.view')): ?>
    <button data-tab-target="tab-finance">Finanzen</button>
<?php endif; ?>
```

**Beispiel:** Die Geld-Bremse (Sichtbarkeit von Umsätzen drosseln)

```php
<td>
    <?php if ($auth->hasPermission('privacy.finance.reveal')): ?>
        <strong><?php echo \number_format($permit->getPrice(), 2); ?> €</strong>
    <?php else: ?>
        <span class="u-text-muted">***</span>
    <?php endif; ?>
</td>
```

## 5. Neue Berechtigungen hinzufügen

1. Trage die neue Permission in die Konfigurationsdatei (Struktur-Baum) ein.
2. Schütze die gewünschte Route (Middleware) oder den HTML-Button mit `$auth->hasPermission('dein.neues.recht')`.
3. Gehe im Dashboard in die Gruppenverwaltung und hake das neue Recht für die gewünschten Gruppen an.

## 6. Neue Berechtigungen anlegen

* Siehe `src/Core/Security/PermissionRegistry.php`
