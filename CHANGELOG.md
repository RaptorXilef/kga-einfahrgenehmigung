# Changelog



## [0.55.3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.55.2...v0.55.3) (2026-07-06)

### 🐛 Bug Fixes

* **bootstrap:** resolve fatal error on static call to non-static JsonHelper ([e5a8ecc](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e5a8ecc3c33c9fb4976e8d484de704400243903c))

### ⚙️ Refactoring

* **architecture:** enforce clean architecture by decoupling application from infrastructure ([d6fd8b7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d6fd8b7b41d656cbb2f8640077c3e483bfedcab7))
* **bootstrap:** sort infrastructure provider and update architecture ruleset ([659d3ab](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/659d3ab4e15eb9db5e40ebd6df0e55674f7c3a6b))
* **storage:** enforce dependency injection for JsonHelper across all repositories ([dd2ef41](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/dd2ef411d782b83cb78e9c3537c363f2b288adb1))

## [0.55.2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.55.1...v0.55.2) (2026-07-04)

### 🐛 Bug Fixes

* **dashboard:** resolve execution order bug preventing archives from appearing in tabs ([2bee4e0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2bee4e01923a3a1e96c2ff965f106127c8160a22))

## [0.55.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.55.0...v0.55.1) (2026-07-04)

### 🐛 Bug Fixes

* **templates:** add missing PHPDoc annotations for template variables ([78bfd60](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/78bfd6079173ac726f3a841f85232b402cc29cfc))

## [0.55.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.54.2...v0.55.0) (2026-07-04)

### 🚀 Features

* **dashboard:** integrate lazy-loaded archive stats and implement semantic sorting ([5dd9baa](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5dd9baa776ea6b0da51e14e4500d5b28fcf269f6))

## [0.54.2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.54.1...v0.54.2) (2026-07-04)

### 🐛 Bug Fixes

* **config:** implement auto-heal for missing storage configurations and add audit_logs to backups ([9a644dc](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9a644dcdddf92dd9161e1a279086cfd04b1b8167))
* **maintenance:** patch missing tables in backup loop and migration UI ([21b1c8e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/21b1c8ea31251ed7afe6595321ba1237705eabbc))

## [0.54.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.54.0...v0.54.1) (2026-07-03)

### 🐛 Bug Fixes

* **audit:** add logging for session extension and plot owner logouts ([5d73aec](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5d73aecfd6ff91aad0032a897ea3c521976f7a19))
* **audit:** allow logging of public user actions and remove restrictive admin-only condition ([a1933fe](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a1933feba917b8c2e1476c809cd42ed4f7b692cc))

## [0.54.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.53.0...v0.54.0) (2026-07-03)

### 🚀 Features

* **security:** implement comprehensive Audit Log for administrative actions ([57b834e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/57b834e6bf5cc350517b103262fe6fda3975c2e8))

## [0.53.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.52.0...v0.53.0) (2026-07-03)

### 🚀 Features

* **security:** implement comprehensive Audit Log for administrative actions ([651b4b0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/651b4b0aaeb742a873e5210164e9c7ce696e614f))

## [0.52.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.51.5...v0.52.0) (2026-07-03)

### 🚀 Features

* **finance:** add bulk payment approval and fix UI/UX issues ([984db8f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/984db8f3a196942d3a0a34a983ec69ea27c4aeb4))

### ⚙️ Refactoring

* **routing:** implement automated action routing via PHP Attributes and Universal Factory ([d30a7a3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d30a7a3659b54dea2842a51555c4c8e0b50b26ce))

## [0.51.5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.51.4...v0.51.5) (2026-07-03)

### 🐛 Bug Fixes

* **api,core:** bypass API CSRF for GET requests and resolve Enum type safety issues ([e6a64fb](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e6a64fbb236a5d9007866ad1debd8e7765766658))

### ⚙️ Refactoring

* **core, ui:** complete migration to Post-Redirect-Get PRG pattern, enforce strict type safety ([5b3e4ad](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5b3e4ad1418c4479274c5393825f125fec6b2e12))
* **core, ui:** complete PRG migration, enforce strict type safety, and patch dynamic SQL bugs ([cdf2cd5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/cdf2cd5df1c3fd0a725885c77f58421a24b31c58))

## [0.51.4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.51.3...v0.51.4) (2026-06-29)

### 🐛 Bug Fixes

* **api:** bypass CSRF validation for GET requests to unblock external cron jobs ([27c32f8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/27c32f85b25a64c43174568c5fe4d836e1b0eb80))

### 📚 Dokumentation

* **repo:** overhaul project documentation, architecture guidelines, & define proprietary licensing ([955633a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/955633a386b23f71f95d87eb912887f88c3bca58))

## [0.51.3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.51.2...v0.51.3) (2026-06-28)

### ⚙️ Refactoring

* **core:** deploy DynamicSqlTrait across all MySQL repositories and purge concrete DI bindings ([8038b9b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8038b9b01526951dc192631620558739d10812f8))

## [0.51.2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.51.1...v0.51.2) (2026-06-28)

### 🐛 Bug Fixes

* **storage:** resolve PDO HY093 exception during permit creation and persistence ([7e7329e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7e7329e3113db2355ef5a1a1d378ef4549a1c679))

### ⚡ Performance

* **mail:** implement 10-tier priority queue and split batch limits for web and cli processing ([d8c5533](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d8c5533686fc77e5c8d50775324ed1bfd3768b82))

### ⚙️ Refactoring

* **core:** implement dynamic SQL generation and background session heartbeat ([e6d4c09](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e6d4c09bb0979753c56bac3ace197333a133e2f9))
* **mail:** externalize hardcoded mail queue processing limits to JSON configuration ([98b0489](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/98b04899dd1b9d3facc7b1c38d033b9e04bc045d))

## [0.51.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.51.0...v0.51.1) (2026-06-28)

### 🐛 Bug Fixes

* **core:** resolve PHP 8+ parameter deprecation in UpdateMigrationService ([fa8c6ca](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/fa8c6ca7145f8eacd54c301bef03167c7fca8db7))

## [0.51.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.50.1...v0.51.0) (2026-06-28)

### 🚀 Features

* **core:** implement permit cancellation logic, anonymization and archiving ([c9352cd](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c9352cd37dc6b93f49229eb713cd3fb616c24838))
* **core:** introduce dedicated storage and dashboard tab for cancelled permits ([28d2da4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/28d2da4fc0d7dbe5fde0696c2ea42374c8cb04f5))
* **core:** prepare domain, database and mailing infrastructure for payment reminders ([c9ac252](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c9ac252c561b79f7b8e0836df8d9b1d590bc89a8))
* **cron:** dispatch automated payment reminders via pseudo-cron scheduler ([a133045](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a13304593bc837438b83725a059adf01778a7a06))
* **frontend:** implement user-facing permit cancellation in history tab ([5113cca](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5113cca461c75a912988230353c9874f627d1f53))
* **payment:** implement dynamic due date calculation for permits ([d14ed8b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d14ed8bb301187126c1253867369a931145d2426))

### 🐛 Bug Fixes

* **schema:** apply safe array access to prevent migration crashes and optimize reminder index ([da25088](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/da25088e8d096a4807a60d01b1d97443709b7ab6))

### ⚙️ Refactoring

* **storage:** migrate configuration to JSON and centralize schema definitions ([340f0a3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/340f0a32b7fd6d9dbae420bea57529ba18a4feac))

### 📚 Dokumentation

* **config:** update system manual and enhance JSON metadata descriptions ([60e6067](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/60e60678b4a959ac0275e2b0f5ac4d31e4530125))

## [0.50.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.50.0...v0.50.1) (2026-06-26)

### ⚙️ Refactoring

* **dashboard:** dashboard sorted ([d22bb6b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d22bb6b58c4d3e58cf44d4a565025d7b969d36b0))

## [0.50.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.14...v0.50.0) (2026-06-24)

### 🚀 Features

* **finance:** add dynamic data row preview to banking import layout wizard ([3a0c43b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3a0c43b8f8c4a340ec578c3f0f535945476571df))
* **finance:** implement multi-payment bank statement import ([4dbb0f0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4dbb0f082abcf7c392a8062d60190d95a7fd0614))
* **finance:** introduce automated banking statement import engine... ([000cb24](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/000cb248745362511aa04daf26c9ec8c20f3050b))

## [0.49.14](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.13...v0.49.14) (2026-06-24)

### 🐛 Bug Fixes

* **pricing:** add missing sharing vehicle pricing to prevent 0 euro calculation ([6635ead](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6635ead7f98e8aa0f87efe386a24c20dcba6ec2a))

## [0.49.13](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.12...v0.49.13) (2026-06-24)

### 🐛 Bug Fixes

* **architecture:** resolve ADR leaks, enforce DTOs, decouple infrastructure from Domain ([66d6763](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/66d6763dee4551238ba946b60f1bf1ce64ff0ae9))

## [0.49.12](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.11...v0.49.12) (2026-06-24)

### 🐛 Bug Fixes

* **auth:** prevent 500 error on profile password change and gracefully handle token mismatches ([ccfe6f9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ccfe6f94cc9840804b34fc338a17a671c2c811cb))

### ⚙️ Refactoring

* **architecture:** enforce strict DTO boundaries and eliminate dead dependencies ([f74bdb0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f74bdb00804e2bb956eece71075f5b3602bb52bb))
* **core:** encapsulate session state and delegate business rules to domain services ([2321851](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2321851b319cb547e432339339f9a3c78031f2a6))

## [0.49.11](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.10...v0.49.11) (2026-06-23)

### 🐛 Bug Fixes

* **auth:** prevent idle timeout from resetting guest CSRF tokens and expose swallowed auth errors ([248780e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/248780e708804b43ec55f57aac54b495671d486d))

## [0.49.10](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.9...v0.49.10) (2026-06-23)

### 🧹 Chore / Maintenance

* **maintenance:** add migration script 007 to clean up obsolete core configuration files ([c5256bc](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c5256bcca7f9b00207ae7a32b4e3ac82b6ced681))

## [0.49.9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.8...v0.49.9) (2026-06-23)

### 🐛 Bug Fixes

* **json:** implement bulletproof JSONC comment parser to prevent string corruption ([616566b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/616566bb4cfc982cc7ad35738c905d8955a77a06))

## [0.49.8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.7...v0.49.8) (2026-06-23)

### ⚙️ Refactoring

* **core:** decouple infrastructure logic, implement JSON state settings, secure bootstrap ... ([924e3c8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/924e3c8348582745f37b39a40aec733b85d90013))

## [0.49.7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.6...v0.49.7) (2026-06-23)

### ⚙️ Refactoring

* **bootstrap:** decouple HTTP logic from app.php and extract into ADR middlewares ([72d860a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/72d860a8240ee043648c954889a9970fc8eae8a1))

## [0.49.6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.5...v0.49.6) (2026-06-23)

### ⚙️ Refactoring

* **storage:** final hybrid repositories and map MailQueue/LoginAttempts to Domain Entities ([06fbac7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/06fbac7a4b5d1b858fd25d9b087577055af659bf))

## [0.49.5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.4...v0.49.5) (2026-06-23)

### 🐛 Bug Fixes

* **maintenance:** resolve undefined property error and complete missing LoginAttempt entity ([8ca184a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8ca184afdc0cb352f9fcc8bb49a88aa798c21136))

### ⚙️ Refactoring

* **architecture:** execute final SRP audit, clear dead code, & externalize infrastructure URLs ([0a61b73](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0a61b7334230649cb97a54ed2e92dce22adcd419))

## [0.49.4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.3...v0.49.4) (2026-06-23)

### ⚙️ Refactoring

* **routing:** apply RequiresPermissionInterface to remaining admin actions ([ab95182](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ab951820765ae53ac9bec6347cc6dde838f0e217))
* **routing:** harmonize API and Changelog controllers with interface-based permission mapping ([69ff820](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/69ff820bc881836ca7582d7d20be32d6e006ec9e))
* **routing:** replace procedural permission mapping with Action-Level interfaces ([84b4058](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/84b4058740c70d46817e504d46b5ca355b482b07))

## [0.49.3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.2...v0.49.3) (2026-06-22)

### 🐛 Bug Fixes

* **core:** cast array keys to string in PermitService to resolve type warnings ([79de918](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/79de91848c6ad1dae630a0d1bdb6b749f30f12dc))
* **core:** ensure Value Objects are passed to entity constructors in PermitService ([03182b4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/03182b449160625a80a0b0cd955a3104f60101ad))
* **storage,maintenance:** resolve Intelephense type warnings and ensure safe FQCN instantiations ([fcadc34](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/fcadc3454ea8f6673c7e6d473350bf50bf44e676))

### ⚙️ Refactoring

* **actions:** extract procedural array logic into strictly typed DTOs ([f765938](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f765938f9ec331371088e71eb26a44ffda641618))
* **core:** introduce Value Objects to mitigate primitive obsession ([242bbe8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/242bbe8367b4486246b4693c6d7c548aa7938f2e))
* **core:** replace raw arrays with Domain Entities for MagicLink and MailLog ([e0849db](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e0849db6e08af4b38358b77598991981635e3e59))
* **core:** replace raw arrays with Domain Entities for Vouchers and resolve Linter warnings ([0ba2b12](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0ba2b12aa98d789622d7e69ab5cf13e54905b49c))
* **core:** replace raw arrays with Domain Entity for Pending Verifications ([31c631f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/31c631faec1d8775f877de49a70adbced3806454))
* **maintenance:** depower MigrationService and delegate data import to repositories ([b4a4bc9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b4a4bc967666167842bd9d47301cff9abb2b414b))
* **storage:** split GroupRepository into MySQL and JSON implementations ([97638e0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/97638e08618b41c1fd1f60eb311500df49c08373))
* **storage:** split remaining repositories into MySQL and JSON implementations ([29b7b33](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/29b7b33545d830c737a0e5d4f4caad4811bc9db7))
* **storage:** split UserRepository into MySQL and JSON specific implementations ([5759408](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5759408f72821f363b352877e11a17fe05f1a4f4))

## [0.49.2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.1...v0.49.2) (2026-06-21)

### 🐛 Bug Fixes

* **analytics,config:** enforce GDPR consent, fix GA4 sessions and add sharing vehicle type ([acd8c7b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/acd8c7ba01f49e0220c987a99bc4c262d087953a))

## [0.49.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.49.0...v0.49.1) (2026-06-21)

### 🐛 Bug Fixes

* **updater,core:** patch destructive ZIP root resolution and prevent autoloader crashes ([8908605](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8908605bc88a4728b26849cbbe2ea13666af470c))

### ⚙️ Refactoring

* **ui:** open print views in new tabs to preserve scroll state ([29ebb56](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/29ebb563f272b272e5b3f98962aafb0756f51357))

## [0.49.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.48.0...v0.49.0) (2026-06-21)

### 🚀 Features

* **updates,security:** bind manual update polling and secure tenant histories ([3a6c607](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3a6c607187f25035126414564f79bca5baa3211e))

### 🐛 Bug Fixes

* **ui:** refine update banner ([cc82a73](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/cc82a73d515feee7d423084be9ba3e009164f9c4))

## [0.48.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.47.10...v0.48.0) (2026-06-21)

### 🚀 Features

* **infrastructure,ui:** introduce mass-migration operations, manual backups, expand session secure ([20833d7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/20833d7319a1fb55ccfa9b23e1a71707582a3722))

### 🐛 Bug Fixes

* **core,ui:** prevent interval underflow, catch migration exceptions, and patch modal buttons ([cf55e39](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/cf55e39f236d969440b58e1581654e1ac08e9b72))
* **core:** prevent swallowed exceptions by explicitly routing action errors to the system logger ([9fc8392](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9fc8392d7d27bc9b4c898fa64835eb7ec77d2733))
* **cron,ui:** compensate for 24h cron drift, enforce daily DSGVO purge, enhance tenant session UX ([67a7821](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/67a7821afd8200a7975547ab7598038c4dce0c90))
* **db:** resolve migration PK collision and append missing GDPR column ([c148b4b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c148b4b176780da8ce07fb7de389262b64884f0d))
* **infrastructure:** resolve migration path errors and type-safety in permit archiving ([5e153c2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5e153c2438cd3a41f1a80f9f57703acb99c3f160))
* **migration,ui:** patch missing SQL mappers, repair marker inserts and fix button layout ([b041e8e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b041e8ed422eba364677d13079be289698ec5a9f))
* **ui,migrations:** correct flex layout rendering and harden directory resolution for updates ([22d03ca](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/22d03caf7c37b386c38a26143aaccfd96c6ad0a0))
* **ui,migrations:** use dynamic config for 00x scripts and patch button grids ([31ef6a1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/31ef6a1749ee218458d7003a05a7690141cc27aa))

## [0.47.10](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.47.9...v0.47.10) (2026-06-21)

### 🐛 Bug Fixes

* **core,ui:** resolve print-view crashes, enforce session expiration, and patch container cloning ([93081a7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/93081a7e5430e91da003a5fc3c1dfa47c5d37997))
* **database,storage:** resolve schema drift by adding missing 'agreements' column to MySQL ([e543c6f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e543c6fed10c8bccdb5f8ce3fd549a648e425719))
* **security,ui:** implement interactive session timeout and refine updater garbage collection ([42c8999](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/42c899960107baafdbc5c03b7475ff0f96b8bc5f))
* **updater,arch:** implement dynamic manifest parsing and GitHub API rate limit caching ([c849714](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c849714add3e21fd69123b42dc4bc29d51acd4f0))

### ⚙️ Refactoring

* **sec,ui:** optimize idle tracking events and synchronize backend timeout ([160168d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/160168d9e875b8bdb752dfcbcd018f553089567f))

## [0.47.9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.47.8...v0.47.9) (2026-06-21)

### ⚙️ Refactoring

* **di,core:** implement reflection-based autowiring container and eradicate boilerplate ([7440d29](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7440d29b954eb2e4a3b775744ae92cb8e3618ae3))

## [0.47.8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.47.7...v0.47.8) (2026-06-20)

### ⚙️ Refactoring

* **api,routing:** eradicate API hard-exits & extract routing intelligence from action factories ([81b670c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/81b670cbd1310ff425ac726883dedc30412dda10))
* **core,arch:** implement PermitFilterService, inject ClockInterface, secure template rendering ([296fedd](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/296fedd9ce9c37f6fac5ee4cbda669e48da9dd68))
* **http,core:** introduce PSR-7 inspired ServerRequest, purge superglobal leaks, and ... ([b2794c3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b2794c3cdc464c0b4f0f5f51208bcf12c936e892))
* **security,core:** harden architecture, rotate CSRF tokens, & extract image processing service ([fca36b1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/fca36b1d6c0629d62b281a8447d9f069ed929d1e))

## [0.47.7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.47.6...v0.47.7) (2026-06-19)

### ⚙️ Refactoring

* **core,arch:** eradicate persistent leaky abstractions and solidify domain decoupling ([6545e0b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6545e0b92f0834b7f031e2880ca828227506e2e4))
* **core,middlewares:** introduce robust HTTP response abstraction & dynamic permission routing ([7e5d692](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7e5d69299e1c6512cf1033df9455be52e59af1bb))

## [0.47.6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.47.5...v0.47.6) (2026-06-19)

### 🐛 Bug Fixes

* simplify license headers ([857c6a2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/857c6a2a2d0640652885a7799dcfe016f353a102))

### 🧹 Chore / Maintenance

* add license cleanup commits to .git-blame-ignore-revs ([5826268](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/58262689e7977ae9491c4951712d091e0e1c6171))
* simplify license headers ([736858b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/736858b7f7dfe2a6bacd17b6a71222aaa4e6c0a8))

## [0.47.5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.47.4...v0.47.5) (2026-06-19)

### ⚙️ Refactoring

* **actions,middlewares:** eradicate procedural security logic from business actions ([d4fadec](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d4fadeca7d1d8a1d92cca7dbc515fd9983fcb3e8))
* **bootstrap,middleware:** eradicate infrastructure leak and encapsulate server-side tracking ([bc346e6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/bc346e6cc68cfa91b64520f3506798a7084f33ab))
* **core,arch:** introduce domain entities and psr-7 inspired response abstraction ([4c86251](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4c862514d9ae0102b3e5ea66a30a6879b59d28de))
* **core,dto:** eradicate global array access in read-only View Actions ([54e04e7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/54e04e754298c81f2ab8fc343e2989e02f9b830c))

## [0.47.4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.47.3...v0.47.4) (2026-06-19)

### 🐛 Bug Fixes

* **actions:** correct request payload access in frontend view actions ([8845db7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8845db7455c63cf6dd5a59e33895347ab558b477))

### ⚙️ Refactoring

* **core,arch:** encapsulate global state mutations and eliminate god-closures ([dc10e4f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/dc10e4fbf73b496e273c04c6abb7cd740a323d3c))
* **core,arch:** resolve DIP violations and isolate domain logic from infrastructure ([86e38b6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/86e38b6d3ac2e0236a855d85dfec3c41e7eea19e))
* **core,types:** enforce strict return types on closures for static analysis ([64e737a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/64e737ace5cec787b9ff7bbea4bf80fac1614383))
* **public,adr:** obliterate entry-script duplication and strictly route via controllers ([0038b52](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0038b52e4b8594daff3f6136c3d9c5e7fda4d650))

## [0.47.3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.47.2...v0.47.3) (2026-06-18)

### ⚙️ Refactoring

* **api,dto:** decouple HTTP methods from actions and enforce strict maintenance DTOs ([42ebe6a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/42ebe6ae98d8c93c048ef9c4acf94322a496d93b))
* **controllers,adr:** enforce pure middleware pipelines and extract procedural checks ([9494729](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/94947290045081495dc985d172849cec64ea2c8a))

## [0.47.2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.47.1...v0.47.2) (2026-06-18)

### ⚙️ Refactoring

* **controllers,adr:** purge procedural logic from entrypoints and enforce middleware pipelines ([214bf74](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/214bf7441c1f4f8e2857c7e91e45c81f2347d361))
* **core,dto:** secure file uploads and client IPs via strict DTO encapsulation ([684ca84](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/684ca84d873796b78f459a66d398ab03fd71e58e))
* **core,types:** eradicate dead code dependencies and enforce strict typing rules ([060658c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/060658c27f596b74872e5b802f6744c750f99bbf))

## [0.47.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.47.0...v0.47.1) (2026-06-18)

### 🐛 Bug Fixes

* **api,actions:** resolve JSON decoding errors and Linter warnings in API endpoints ([6b590e2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6b590e2ccf26c6a86da9142e2e7e9c75ef7e4f80))
* **core,provider:** remove invalid argument from EventServiceProvider binding ([ae10053](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ae10053d2a79cf709ad92dc0b46cc8d9ddccc378))

### ⚙️ Refactoring

* **api,adr:** migrate all raw API scripts to central ApiController and middleware pipeline ([adde28e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/adde28e1efd1538b93c7201ac9bc69b3bbd7889c))
* **core,arch:** eradicate dead code and resolve critical SRP violation in PermitSubmitAction ([b947be7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b947be74741bd252eeea2849bd0da479df7ae78f))
* **core,di:** remove ghost dependencies of MailServiceInterface after EDA migration ([0be2ae9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0be2ae97ed4ac4c546d9bd6a0f239209b6082bcf))
* **core,dto:** resolve leaky abstractions and encapsulate global state accesses ([2d084fa](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2d084faa00dccf39a8c9e484cc643a58896d2296))
* **core,events:** decouple PermitService from notification logic via Domain Events ([5421ee3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5421ee3578b656282b32694e466294e4e2d84ed8))
* **core,events:** fully complete event-driven decoupling across all domains ([fca7c1b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/fca7c1b2077308cc9565bda62a78c5607720b05f))
* **core,views:** decouple infrastructure orchestration and eliminate view leaks ([872dbda](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/872dbdab77fe4c679da5c62b2f2b7fda9bc6b88a))

## [0.47.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.15...v0.47.0) (2026-06-18)

### 🚀 Features

* **core,middleware:** implement explicit middleware pipeline for route protection ([4cf87e2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4cf87e2353c11362ece86dccf48534a78d90ba25))

### 🐛 Bug Fixes

* **core,dto:** resolve leaky DTO abstractions in status toggle actions ([9ba8772](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9ba8772dc085d0711c1c8b8082c33fbe563589cc))

### ⚙️ Refactoring

* **admin,actions:** rename admin action classes to enforce domain-specific prefixes ([3673359](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3673359eee4c143818eb4820dbe5f64416c436a6))
* **core,dto:** conclude DTO migration for all remaining mutation actions ([ff98b94](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ff98b9434a219bd9c3bde524e7c3f66572e5dfbc))
* **core,dto:** finalize DTO integration for all remaining mutation actions ([d58bacd](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d58bacd15f6d9d3f6bc6ca09f606e0d4d2c372ee))
* **core,dto:** implement Data Transfer Objects for major data mutation actions ([abf2272](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/abf227216f87a7351fc2aecfcc540530f63ef856))
* **core,dto:** implement DTOs for 6 additional mutation actions ([6d917b0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6d917b0ddb80f7e547f1b2102daab988cff2f17a))
* **core,middleware:** apply middleware pipelines to all front controllers and harden login ([b248b2d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b248b2db5724c70ccc1eeca4e4937e5d2aa083af))
* **profile,dto:** implement DTOs for profile update actions ([a2b6c74](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a2b6c74ea9a2737edf0a3685869f48bbf79e00c8))
* **users,dto:** introduce DTO for UserSaveAction to enforce strict request validation ([4b733c2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4b733c2f521c043a22b4a350ed6ceac98cae3cf6))

## [0.46.15](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.14...v0.46.15) (2026-06-17)

### ⚙️ Refactoring

* **check,adr:** convert CheckController to dedicated CheckPermitAction ([8a1892f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8a1892f5913d0e0c2b1410868abd4e1285daa1ba))
* **checkout,adr:** convert CheckoutController to dedicated CheckoutAction ([ee803ec](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ee803ecc4687d4a227a848dcac686d6377dc373c))
* **checkout,adr:** convert SuccessController to dedicated SuccessAction ([900724f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/900724f3b394e1cf804b14c0c94ba5fa18b42376))
* **history,adr:** dismantle God Controller into isolated ADR actions via factory ([9ed6d70](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9ed6d70be3505b5ad50d1e50f1359ecc8332fac1))
* **legal,adr:** split LegalController into dedicated ADR actions ([72c5e31](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/72c5e3188d6113795d46e32e5625b239922d835f))
* **payment,adr:** convert PaymentController to dedicated CapturePaymentAction ([4ae7d63](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4ae7d63960e65dacf794f04dfd50781a26cffab8))
* **permits,adr:** dismantle PermitController into dedicated ADR actions and enforce PRG pattern ([a44fd44](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a44fd449d0681151609255d9e118c6f1774ded6f))
* **users,adr:** completely dismantle UserController into 15 isolated ADR actions ([997ae64](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/997ae64af2e9ac1e5de5738cfd6da2f240bd2939))
* **verification,adr:** dismantle VerificationController into ADR actions via factory ([09ff0c9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/09ff0c94ec233eb876b7ec827c7043676549ee13))

## [0.46.14](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.13...v0.46.14) (2026-06-17)

### ⚙️ Refactoring

* **admin,actions:** finalize ADR extraction for data actions and remove legacy routing ([3ae5805](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3ae5805c69d256cde646a6f03e32c94e6f6c6e68))
* **admin,auth:** extract login and logout logic to ADR actions ([8995ad2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8995ad24d747d52de7d852650f7d442bf9a1785b))
* **admin,auth:** unify login and logout under standard action field ([b81255d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b81255d336ea3f035a4113a708b60db75f4ecf6c))
* **admin,maintenance:** extract system maintenance logic to ADR actions ([bc79760](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/bc79760dc6b7cf426fd9d95447cee0931def80c6))
* **admin,permits:** extract permit lifecycle logic to ADR actions ([9d62a13](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9d62a13b90ec5dd3cc3cd159c8af30a507256968))
* **admin,routing:** implement explicit ADR pattern with lazy-loading factory ([988cee7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/988cee7fcb59fd9d91105caa371d6fd242a03d80))
* **admin,vouchers:** extract complete voucher logic to ADR actions ([6b13185](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6b131851438ef6238f14def513a5eca6dbb2e47b))
* **admin,vouchers:** extract delete voucher logic to dedicated action class ([6066708](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/606670845ef1ddf3a5df87247538eca5505107db))

## [0.46.13](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.12...v0.46.13) (2026-06-16)

### ⚙️ Refactoring

* **core,di:** implement service provider pattern for DI container ([c35d47b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c35d47b96ffea72f0fe5e957d759476277ee25e5))

## [0.46.12](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.11...v0.46.12) (2026-06-14)

### ⚙️ Refactoring

* **core,infra:** enforce domain encapsulation, fix Deptrac violations, and abstract I/O locking ([26f6963](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/26f696315d031bffaa54de9d3807fff37b4a1b03))
* **core,ui:** rename ambiguous $p variables to domain-specific identifiers ([6b387a5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6b387a5f9564c32fe4b17fb0ff0447d9bd2faaf0))
* **core,views:** extract DateRangeHelper and enforce Tell-Don't-Ask entity encapsulation ([279e2bb](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/279e2bb78cdbf33d59d19f82ee57ae032796b3f8))
* **core,views:** extract DateRangeHelper and enforce Tell-Don't-Ask entity encapsulation ([424b728](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/424b7289c4650e0807fe6df2c00420bf17b0209a))

## [0.46.11](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.10...v0.46.11) (2026-06-14)

### 🐛 Bug Fixes

* **mail:** resolve lingering path boilerplate and race condition in mail logger ([e1685a8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e1685a88f3bfbb6b3077e8e6ee0a758b3048ca25))

### ⚙️ Refactoring

* **core:** eradicate boilerplate via DRY extraction (JSON locking, paths, CSRF) ([c2ab758](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c2ab758fec9297d2d455e96b6ece1ce11fe47237))
* **core:** eradicate boilerplate via DRY extraction (JSON locking, paths, CSRF) ([cf5608f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/cf5608fdb4aa4811071a56bcd31d0e2bd456d93a))
* **storage:** apply centralized path resolution in StorageFactory ([52d9ee3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/52d9ee36fbdb162ac1abeaa8ceca3530fe69b89a))
* **storage:** strictly centralize storage path resolutions ([9624d56](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9624d56170e24afdd2a31e493e8e3697c114f183))

## [0.46.10](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.9...v0.46.10) (2026-06-14)

### ⚙️ Refactoring

* **arch:** realign maintenance services to domain core and enforce strict deptrac boundaries ([d93189e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d93189e12cb51041f89feb18053bad64c17b2365))
* **di:** extract complex instantiation logic from Container to dedicated Factories ([3e4d397](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3e4d39741b8bdf8c12c08d93fe8021fc4b14994a))

### 🧹 Chore / Maintenance

* **di:** finalize container categorization and purge dead legacy code ([8c27988](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8c279882472f76583c5b694bf3651153108fb7d4))
* **di:** restructure and document dependency injection container ([f4232c9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f4232c992369aebbdcf5323541a78b26b99ec46e))

## [0.46.9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.8...v0.46.9) (2026-06-14)

### 🐛 Bug Fixes

* **stats:** resolve undefined method crash and harden optional template dependencies ([6daf278](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6daf278cef1d651eb70a19305b6895f5a29f7b09))

### ⚙️ Refactoring

* **core:** extract CSV/JSON data formatting logic from AdminController to ExportService ([0167e92](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0167e925f3d1b5d02c19232abfb69f1df834faf3))
* **view:** consolidate template rendering and extract HTML presenters (DRY/SoC) ([aa1e3c6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/aa1e3c6e55f5514e045b5f64287b966d463d53e4))

## [0.46.8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.7...v0.46.8) (2026-06-13)

### 🐛 Bug Fixes

* **di:** resolve residual proxy references and inject missing repositories ([ba20a81](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ba20a81b281613eb759e4af5c3523edd6f74852b))

### ⚙️ Refactoring

* **core:** decouple identity proxies and enforce strict repository injection ([c0efe06](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c0efe062d96b4f919c5b406b459ac6b4e8a415ef))
* **di:** decouple Law of Demeter violations from PermitService ([7d43fb0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7d43fb071d2a8735c649d102e4682c85bec031f4))
* **mail:** apply interface segregation to purge final proxy methods ([65b7c50](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/65b7c501f51a013a8021ae985a6f0fd5349d58cb))
* **permits:** purge data proxies from PermitService and route via direct repositories ([11a8820](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/11a882098811d2627af34b00114b2f4a317579b5))
* **storage:** seal abstraction leaks and enforce persistence ignorance in PermitService ([a9c656b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a9c656bccfc70edce74feb6392962478bc0f86da))

## [0.46.7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.6...v0.46.7) (2026-06-12)

### ⚙️ Refactoring

* **mail,print:** modernize a4 permit layout and resolve rendering scope collision ([1f7ef18](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/1f7ef187b09ec85a106dcf30eab18ff9ed77cfbb))

## [0.46.6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.5...v0.46.6) (2026-06-12)

### 🐛 Bug Fixes

* **ui,auth:** restrict changelog and bug report visibility to authorized administrators ([c5bb404](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c5bb404aedac26cf1bccf6fc6c0c569df85842ed))

## [0.46.5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.4...v0.46.5) (2026-06-12)

### 💎 Styling

* **ui,mail:** unify email template layouts and improve contextual subject lines ([de1e1f9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/de1e1f947c0361a7d1257190d0e418629fa05f32))

## [0.46.4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.3...v0.46.4) (2026-06-12)

### 🐛 Bug Fixes

* **schema:** resolve mysql syntax exception on boot by stripping invalid auto_increment ([bb3789e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/bb3789e90205241ed9bd6178439ce4ba0dddd2b5))

## [0.46.3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.2...v0.46.3) (2026-06-12)

### 🐛 Bug Fixes

* **bootstrap,logging:** redirect php error logs to storage directory ([acac5bb](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/acac5bb9f587ecc7bdf8b142af0ab392919baee4))

## [0.46.2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.1...v0.46.2) (2026-06-12)

### 🐛 Bug Fixes

* **ui,core:** repair paypal checkout integration and unblock mail queue rendering ([b44c39b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b44c39b64a3889e2d65169fc98a69be399a12fa4))

## [0.46.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.46.0...v0.46.1) (2026-06-12)

### ⚙️ Refactoring

* **core,updater:** decouple core config update logic via dynamic manifest injection ([461a49a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/461a49a0144291c17a883c6d1a137a45b55e631b))

## [0.46.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.45.1...v0.46.0) (2026-06-12)

### 🚀 Features

* **sec,core:** deploy CSP, halt json bloat, and formally enforce magic link single-use ([82d9008](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/82d9008c2b8c87ff41a4375e9ac2f9b39f8f3d8f))
* **sec,core:** mitigate XSS, path traversal, user enumeration, and stat cache race conditions ([fc8c647](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/fc8c6473995263f8390e94f6811d19a7e217374a))

### 🐛 Bug Fixes

* **api,sec:** neutralize critical IDOR data extraction vectors and secure OTA APIs ([f857f35](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f857f35b8dfedb96417e8da7b08e4954df9ce606))
* **auth,core:** eradicate IDOR vectors, enforce RBAC execution locks, and restore voucher deletion ([3cda72b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3cda72b3800b310aade1ab98d72248df97779bba))
* **auth,sec:** harden session lifecycle, eliminate duplicate accounts, and enforce api csrf ([68a4957](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/68a4957afddf9fdbc095652875c5f3613f15b9f0))
* **core,api:** resolve 100% voucher crash, fix mysql auto-backups, and patch update telemetry ([1df49be](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/1df49be41d4facb95d6598402b63cb0ae42fc85a))
* **core,sec:** prevent CSV injection, fix migration schema types, and harden session destruction ([5a25b1f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5a25b1f9a93ad0eefd5055b27f10d7dab5bfd292))
* **core,sec:** resolve TOCTOU race conditions, atomic cron locks, and timezone desync ([eb2dd9c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/eb2dd9c972ad8bba6338bb8cdac6c37dc47629b9))
* **core,ui:** case-insensitize voucher validation, fix mail error mapping, and ensure cron dir ([cee506b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/cee506bdd503d823a8dbc3c79fc190323a1f0d6f))
* **core:** implement robust collision prevention for auto-generated user and group IDs ([29f719b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/29f719b464ad79478e36b0220229dc3c22718036))
* **core:** mitigate orphaned permissions, time divergence, fail-safe writes, and secure configs ([8a6d30c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8a6d30c3eb45edb18cbeab4b2678435cced96857))
* **queue,cron:** resolve concurrent file contention and tighten bootstrapping latency ([cf7881c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/cf7881c38f2e6c638c6c8e1068f2d5ba1e571ffc))
* **sec,api:** implement HMAC signatures, halt brute-forcing, and sanitize development artifacts ([f51783c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f51783c88a704ae4371d7df04885d6d6c5e40bf9))
* **sec,core:** implement state-aware RBAC, patch secret bypass, and halt mail bomber vector ([881e374](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/881e374562cd5c171d2f58c46fea872a846de373))
* **sec,core:** neutralize clickjacking, halt CRLF injection, and block JSON parsing corruption ([061022f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/061022f18627ad4299c625a67d1701342042ee3a))
* **sec,core:** neutralize json storage bloat during asymmetric brute-force attacks ([8148751](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8148751452d3fbcfa4864e3d48401512d406bd25))
* **sec,core:** neutralize timing attacks, host poisoning, and cryptographic entropy vulnerabilities ([7b83044](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7b8304406fb9c649f9a98e43b0185588780befad))
* **sec,core:** patch IDOR vulnerabilities, secure cookie parameters, and mitigate timing attacks ([7165480](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/71654805af3588c5e29372ce8e784674db5a3112))
* **sec,logic:** prevent SSRF updates, patch currency spoofing, and halt OTP brute-forcing ([f9b3561](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f9b356123358b5c3b3c0d2820425784ae0b9a139))
* **sec,net:** restricted curl dispatchers to HTTPS-only protocols to prevent SSRF file exfiltration ([b277d70](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b277d707175e57c430a75f13f03e679aea4a0a8b))
* **sec,storage:** mitigate proxy DoS, content sniffing, and enforce filesystem thread safety ([26b2c6c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/26b2c6cfb7db48aec3b0abb510c55d2f9c99ca4b))
* **sec,storage:** patch critical LFI vector, enforce strict json deletes, and block rename collision ([6441297](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/64412974b1e4bfea14aae8185fddd9743c0f077c))
* **sec,ui:** whitelist legitimate external resources in Content Security Policy ([ad6f0f8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ad6f0f8532d0bffdb59deb8210ba28b40143c6d5))
* **sec,updater:** neutralize zip bomb denial of service vectors during release extraction ([7148ea7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7148ea756cfd96f681008f566915b57c3bdff28c))
* **storage,api:** prevent silent data destruction, centralize parsing, and secure API payloads ([c32212f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c32212fc534f673af5e0d0efeb7bc70a216995e8))
* **storage:** implement acid transactions across volatile database repositories ([2d99661](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2d99661c5a4703a7375425d4bf38fb4c815da48f))

## [0.45.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.45.0...v0.45.1) (2026-06-11)

### ⚙️ Refactoring

* **application:** structurally reorder methods within controllers and global exception handler ([97c90df](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/97c90df3dcbf50ea4db054109fa8d9ff4310a917))
* **core:** structurally reorder domain services for consistency ([853f258](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/853f2585d02951b4a488485877d0daa2bd30a99e))
* **infrastructure:** structurally reorder components and repository layout ([e4e7c53](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e4e7c539f745b765b9374f917c235050529fdb49))

## [0.45.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.44.0...v0.45.0) (2026-06-10)

### 🚀 Features

* **config:** clean dependencies and formalize system requirements ([97324b7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/97324b774649506356b06e987b2c79a621d3a17d))
* **environment:** implement central local environment detection ([7ff78e3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7ff78e3c7bfbc81221ef187a0c6f8bb595ba9a3a))
* **privacy:** migrate sensitive endpoints to POST and implement server-side analytics ([9585135](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/958513507cb27d6ede3289f1e5a9fff38f63be87))
* **security:** refactor real-time search and layout live-checks to strict POST parameters ([ec9b300](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ec9b30030493c5c0571123de429d392eae4655de))

### 🐛 Bug Fixes

* **core,perf:** optimize auto-archiving I/O performance and align dashboard status logic ([5edacd3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5edacd3890e205f868a3b91468809d6948813048))
* **core,sec:** resolve PDO parameter mismatch, enforce API CSRF, and sanitize UI components ([5d667df](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5d667dfea6d6d769c5caa60f0294dd84303bcb23))
* **dashboard:** resolve error by replacing deprecated service call with direct repository access ([fb8bdea](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/fb8bdea2390076f554152adbdf9c20b316b9d6a5))

### ⚙️ Refactoring

* **vouchers:** decouple repository reads from voucher service and remove proxy methods ([1b1fedf](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/1b1fedf062c7e859a59b1b6b8c347223b4103ada))

### 📚 Dokumentation

* **core:** finalize comprehensive docblock and type-hint audit across entire codebase ([b675828](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b675828b357810d9830018846dd3be4ae67b77af))

## [0.44.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.43.1...v0.44.0) (2026-06-08)

### 🚀 Features

* **security:** finalize comprehensive XSS protection and input sanitization ([c0b1f81](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c0b1f815fd4e5a888a5fcf9431c817c9968ac1be))

## [0.43.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.43.0...v0.43.1) (2026-06-08)

### 🧹 Chore / Maintenance

* **branding/naming:** rename application to KGA-Einfahrts-Manager ([78d6ba7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/78d6ba7fd1c646b9dfeabcf24fa02cc209514cec))

## [0.43.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.42.2...v0.43.0) (2026-06-08)

### 🚀 Features

* **mail/board:** introduce feature toggle for board notification summaries ([045b4ce](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/045b4ced525700963cfdec6260cb2a5a2532193b))

## [0.42.2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.42.1...v0.42.2) (2026-06-08)

### ⚙️ Refactoring

* **admin/migration:** implement update_migrations database mapping and permission keys ([0da8d08](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0da8d0859849e4fdb5c8ce365323f57cd1321d1f))

## [0.42.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.42.0...v0.42.1) (2026-06-07)

### ⚙️ Refactoring

* **core/updates:** rename migrations storage key to update_migrations for semantic clarity ([e0207db](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e0207dbd3b99708b0acef279694dac6f5efee50c))

## [0.42.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.41.0...v0.42.0) (2026-06-07)

### 🚀 Features

* **core/updates:** implement two-phase self-updating architecture with manifest parsing... ([894e106](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/894e1066f53799ecca1573c1a9cf18793fad8575))

## [0.41.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.40.1...v0.41.0) (2026-06-07)

### 🚀 Features

* **legal/consent:** implement dynamic data-driven cookie consent banner with lazy-load tracking ([46ac619](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/46ac6197103294e641b12ec72dfedc3e2ddb52fb))
* **legal/routing:** modularize imprint and privacy policies into data-driven config-backed routes ([40688be](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/40688be73734e961aee23bd5072575bdb3e54c8a))

## [0.40.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.40.0...v0.40.1) (2026-06-07)

### ⚙️ Refactoring

* **permits/agreements:** extract link parsing and markup generation from template to controller ([6a8c7ed](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6a8c7edc357a60b50e6e9b8bef87b970ac03f1e4))

### 📚 Dokumentation

* **legal:** add comprehensive gdpr privacy policy and terms of service templates ([77beeb3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/77beeb3c0d9acc90feeab2cb9b74b1038db4ba87))

## [0.40.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.39.0...v0.40.0) (2026-06-07)

### 🚀 Features

* **permits/agreements:** implement multi-option agreements and tracking system ([8fcfd72](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8fcfd72dd371173029065ca9f75bbcff8fd17e0a))

## [0.39.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.38.2...v0.39.0) (2026-06-07)

### 🚀 Features

* **calendar/seasons:** implement dynamic seasonal opening hours system across all interfaces ([fdb011a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/fdb011a6ce14478b7d35b9aef50f8f5d8dcdcbd6))

### 🐛 Bug Fixes

* **config/ui:** restructure maintenance settings and harden permission management ([cc5620d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/cc5620d727495e11b3b36b6842504100f0a065f4))

## [0.38.2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.38.1...v0.38.2) (2026-06-06)

### 🐛 Bug Fixes

* **infrastructure:** add optional port support to MySQL connection configuration ([5248f50](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5248f50331a9c4889118243a3c55e9fcf9234834))

## [0.38.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.38.0...v0.38.1) (2026-06-06)

### 🧹 Chore / Maintenance

* **security:** implement granular update permissions in configuration and dashboard ([dc28679](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/dc2867920e1f2bba2cc0e49f693aada70776406d))

## [0.38.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.37.2...v0.38.0) (2026-06-06)

### 🚀 Features

* **build:** implement CSS minification with source maps and refactor build process ([706b44b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/706b44bb9a8c765a325ef557f216c880eb9d36f6))

### ⚙️ Refactoring

* **build:** remove unminified CSS source files after minification ([e4c1017](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e4c10170754cbe6d72a31acfb69861e8e4fc21ee))

## [0.37.2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.37.1...v0.37.2) (2026-06-06)

### 🧹 Chore / Maintenance

* **infrastructure:** automate asset compilation in GitHub Actions release workflow ([f5d6820](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f5d682059eacc80e91d0c5b17f0aaaa77c9ad5e4))

## [0.37.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.37.0...v0.37.1) (2026-06-06)

### 🐛 Bug Fixes

* **infrastructure:** add missing backticks to dynamic table identifiers to prevent SQL syntax errors ([b362ff3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b362ff3b2c43b8c5f0fa26d1c2688e3189ae447e))

## [0.37.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.36.8...v0.37.0) (2026-06-06)

### 🚀 Features

* **ci/core:** automate config cascading via CI and allow strict schema updates ([2bc0bcc](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2bc0bccf5e2100669547e62e88b1a7af34d68dd0))

### 🐛 Bug Fixes

* **admin:** enhance update banner with version diff and external release links ([a76b537](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a76b537884aed34e774808cafdbffbe531769dbe))
* **core:** exclude permissions config from cascading and enforce strict overwrite ([19b270f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/19b270fd2a8b22b6735e2d832960db8ff16a249a))
* **core:** implement robust config cascading architecture for updates ([5727c46](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5727c4616e6bbcfc7589ce94170e9ecf05d23504))

### 🧹 Chore / Maintenance

* **ci:** adjust release zip creation for default config filtering ([b320440](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b3204408ff4fb629ce54a9d0d30729bb0fabbe69))

## [0.36.8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.36.7...v0.36.8) (2026-06-06)

### 🐛 Bug Fixes

* **updater:** enable config directory overwrite and aggregate all custom config overrides ([37c9d7f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/37c9d7f933032d20685cb407f8c466ebf750df5d))

## [0.36.7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.36.6...v0.36.7) (2026-06-06)

### 🐛 Bug Fixes

* **updater:** implement automated configuration snapshotting before system update ([b20fc44](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b20fc442aec1b0faadc21bc69e4f80a7b789c222))

## [0.36.6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.36.5...v0.36.6) (2026-06-06)

### 🐛 Bug Fixes

* **ci:** refactor CLA check to show as success for bot PRs ([e08e861](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e08e861165a2c4401c10043a76e7875a1e5f0584))
* **ci:** update CLA check with explicit token and environment configuration ([f323177](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f323177b15f2c9c65360deb54a542cf41035ba08))

## [0.36.5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.36.4...v0.36.5) (2026-06-06)

### 🐛 Bug Fixes

* **core:** update GitHubUpdaterService to support versioned release assets ([139f031](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/139f031f64acfc617635e41363dec555edd4928c))

## [0.36.4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.36.3...v0.36.4) (2026-06-06)

### 🐛 Bug Fixes

* **ci:** exclude dependabot from CLA check to reduce noise and API usage ([0e85b75](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0e85b758a6eddd0104ec0e2ae8799cabc25f9592))
* **ci:** resolve CLA check 403 error by providing explicit token and fixing event trigger ([4a1bee1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4a1bee18301865b2a40de1c9845a3af783567e50))

## [0.36.3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.36.2...v0.36.3) (2026-06-06)

### 🐛 Bug Fixes

* **ci:** correct pull\_request\_target event type and ensure required permissions ([1679f93](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/1679f937fc6defef78e9c2a535f6215fb1e506b5))

## [0.36.2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.36.1...v0.36.2) (2026-06-06)

### 🐛 Bug Fixes

* **ci:** optimize release workflow with versioned assets and modern Node.js runtime ([b01d24e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b01d24eba8e4099be561fefcf89199cb6a96c253))
* **ci:** update action versions and standardize workflow syntax ([b8b140e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b8b140eecb5f5e0cd76e8f121c42498a1d5ae882))

## [0.36.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.36.0...v0.36.1) (2026-06-06)

### 🐛 Bug Fixes

* **ci:** grant write permissions to GitHub Actions to allow release asset uploads ([79c93d8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/79c93d8f90b2bb101619deb7ded976c6647804e0))

## [0.36.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.35.1...v0.36.0) (2026-06-06)

### 🚀 Features

* **system:** implement robust 1-click GitHub ZIP updater with whitelist protection ([586066b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/586066b20e8a1c92867f7b30a8933eabb5e7e50a))

## [0.35.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.35.0...v0.35.1) (2026-06-05)

### 🐛 Bug Fixes

* **history:** resolve undefined variable `$config` in print view ([e3151ee](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e3151ee68cc0136b735d1b8dd1b41eb31d0dbce2))

## [0.35.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.34.0...v0.35.0) (2026-06-05)

### 🚀 Features

* **admin/stats:** integrate monthly chart toggle and restore historic KPI cards ([b70ee9d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b70ee9db3c11a0dfb792a5b8f329db63bc8aecc4))
* **checkout:** implement dedicated checkout flow, sticky forms and correction loop ([5006b31](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5006b310f68c9476a97bc18dbf4e5dab20428fe5))
* **public/changelog:** render markdown changelog securely via marked.js and DOMPurify ([ccb8fa0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ccb8fa0c11d434069eb3212bc4f6f3372dd5c5d9))

### ⚙️ Refactoring

* **admin/vouchers:** implement lazy-loading modal for QR codes ([c18de4f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c18de4f4c05e7d02320753d038401c5ca15ec382))

### 💎 Styling

* **public/changelog:** add responsive padding to markdown body for better readability ([7abf14a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7abf14aeb1ef19bd2dcd79c0c61ace2503ab285d))

## [0.34.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.33.0...v0.34.0) (2026-06-04)

### 🚀 Features

* **admin/dashboard:** implement configurable smart pagination across all data tabs ([f14bce3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f14bce330ab109bf047011166a4383b99d3a6ab9))
* **core/exception:** implement global error handling and centralized logging ([78cae60](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/78cae600c5b28af7f95816de8e3945cb851db7fd))
* **maintenance/cron:** implement hybrid cron scheduler for automated archiving and backups ([94e56f5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/94e56f56966d0fd6f9c1c083bff32ceb3748efe1))
* **security/auth:** implement brute-force protection and IP rate limiting for admin login ([d56f4c3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d56f4c3527830afe4662041b52364d00d39bb490))
* **security/csrf:** enforce global CSRF protection across all controllers and templates ([1e98c5a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/1e98c5a7f5bbdebfe4bdced67339991a49dd6d6b))
* **storage/archive:** implement single-archive strategy and GDPR-compliant data anonymization ([dd5444a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/dd5444a2fc07edaab214ec0206518dd121e3f2ff))

### 🐛 Bug Fixes

* **maintenance/backup:** resolve parameter mismatch and restore functional auto-backup rotation ([139f4be](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/139f4be463fc9c430616920cfb82c1064537a884))

### ⚙️ Refactoring

* **admin/dashboard:** implement server-side search and HTML-over-the-Wire pagination ([823a91e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/823a91e1dc91185b6b16b401189af472b5fd5013))
* **core/service:** decouple formatting logic to adhere to Single Responsibility Principle ([4867629](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/486762976317e37e2815de61a7eee0b0fd314a27))
* **maintenance/backup:** integrate auto-backup with hybrid cron scheduler and migrate logs ([cd073dc](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/cd073dca74d019061009cc655b0e64d44ad4dc64))

## [0.33.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.32.1...v0.33.0) (2026-06-02)

### 🚀 Features

* **admin/migration:** complete dashboard targets, add dedicated mappers and update permissions ([70f280d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/70f280d9fefdfa6fd132b3a426bd46d1e6a4dbaa))

### ⚙️ Refactoring

* **api/controllers:** implement JsonResponse standardization and purge residual I/O from ... ([6e7acda](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6e7acda82db23c465cf2dea207dbe5f5dc51d346))
* **core/architecture:** implement repository pattern, relocate maintenance services ([090757b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/090757b1a5e2381e5bee02d9460296e65ab156e8))
* **core/auth:** apply repository pattern to AuthService and relocate to Core layer ([fb52661](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/fb52661addb4c2d3c2f39c842624d735a99f4a7e))
* **storage/schema:** upgrade to native JSON and DATETIME types with on-the-fly data healing ([b282a9c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b282a9cc3e5fb5dcc5b8192fe46750f6eacbf32b))

### 📚 Dokumentation

* **core/infrastructure:** implement missing DocBlocks ([1bf1c11](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/1bf1c115ef161818edc2404d7b1be00a715b51b0))

### 🧹 Chore / Maintenance

* update composer.lock and vendor packages ([c18da0f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c18da0f86fe2a2550670eb598a6b4a4989cee6fd))

## [0.32.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.32.0...v0.32.1) (2026-05-28)

### 🐛 Bug Fixes

* **migration/backup:** correct backup directory listing and implement targeted engine truncation ([29cadfe](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/29cadfeb4a248dbcc4a6a590683bb1beb8d79833))

### ⚙️ Refactoring

* **architecture/bootstrap:** decouple initial storage bootstrapping from migration service ([a6c5a18](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a6c5a1806b3420fb2d4e3b220f4ad2ea1828e83f))
* **core/admin:** finalize JSON decoding, extract reporting logic, and introduce system tools ([fdaae9c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/fdaae9c790567d6a991f4e84c44d8497c7da8ecd))
* **core/application:** enforce global snake_case properties and purge dead code ([a772483](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a7724837cfc9330a89fdf5813241143b7e206ca5))
* **storage/migration:** harmonize schema mapping and enforce rigid cross-engine data migration ([fa1f835](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/fa1f8355bbc7811386627a12946f7f34673f34b8))
* **ui/migration:** redesign backup history list into a responsive card grid ([e32f7e0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e32f7e0c258192e18582f1c626ef8ead17733224))

## [0.32.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.31.1...v0.32.0) (2026-05-27)

### 🚀 Features

* **core:** dynamic holiday calculation with custom overrides and state support ([077db34](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/077db34f83e673b21aba434ddd1d95668fc55b1e))
* **db:** implement automated MySQL database and schema installation ([67feb2d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/67feb2da4954d1a9a06aa21fec815221ed20dd35))
* **holiday/ui:** implement smart grouping for opening hours ([110746d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/110746d8067a64ba002b8d50af1464b6dbe0484c))
* **holiday/ui:** optimize opening hours grouping and template rendering ([b80d890](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b80d890641a7caa529098d574f5967c197149c92))
* **holiday:** implement date range grouping and conditional prefixing ([5f8088b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5f8088be86ad0ebf99d07665405b295966b3ce2c))
* **mail/admin:** fix recipient tracking and implement email resend functionality ([ab85b85](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ab85b853cac53207a36c58585d5a60176a51f922))
* **migration:** implement robust bidirectional storage bootstrap and resource management ([550a2f4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/550a2f4c89f513656e7644e22358bca6f5951df6))
* **permit/config:** fix configuration loading and introduce optional short permit codes ([786d721](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/786d72197db24a7fc0da751708520699d8b96482))
* **permit/status:** rename 'wartend' to 'offen' and implement strict payment validation ([6f70642](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6f70642af7fd115a6e37d4ddb5a259e012e644e4))
* **permit/validity:** introduce strict payment-based validation and confirm-dialogs ([75dadc6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/75dadc694ce6cf149f70acc6d13b11bbd58af608))
* **scripts:** implement token-optimized file collector for LLM context injection ([09850d1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/09850d16f290939b635fe3c954243d3413e33005))
* **security:** remove API secret from frontend and implement CSRF protection ([4e7b549](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4e7b5490f921d673a7b1fdded0667f5a6555bffd))
* **ui/admin:** introduce drag-and-drop file uploads for profile and group avatars ([be8bd39](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/be8bd39251b769b84aa592b869d3b4ab02acad39))
* **ui/check:** reveal suspension reasons within administrative and public detail views ([c553cc7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c553cc722d30c687afe25d0411661c73eac3a7db))
* **ui/dashboard:** introduce permit type filtering and expand suspension actions ([1035f49](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/1035f4952ea29cf767012899808f1a8dc4fb6474))
* **ui/history:** elevate tenant history list to dashboard standards ([9842894](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/98428945da383aa347bb3ef1752bcec2c7ba4a80))
* **ui/layout:** implement global dynamic footer with software branding and version tracking ([04a0b98](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/04a0b988d25ffe944c7c1f05eb6c1d9104dd2092))
* **ui/navigation:** implement global public navigation bar and unify routing access ([2237b06](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2237b06e72b9a2586ffad818ee3ba204dcc55646))
* **ui/tables:** implement interactive 3-state column sorting for all data grids ([7af793d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7af793d93cde849d66172a7975261b199887bca9))
* **ui:** conditional display of voucher activation link ([148f131](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/148f13195766bf51c89f69f30e99608fb490620d))
* **ui:** display dynamic holiday warnings directly in issue forms and summary ([3877d62](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3877d62e348b696d4ffdfdac057bc28501630339))

### 🐛 Bug Fixes

* **api/vouchers:** synchronize frontend price calculation with manual voucher suspension ([ecf8bff](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ecf8bffe8feb5cdef9109aae26b1f435413a2685))
* **auth:** implement post-login redirect to check view and resolve auto-backup offset crash ([7e7b0b5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7e7b0b55ad3d8ad03c7dbfbe26c38964af37c1fb))
* **auth:** implement post-redirect-get (PRG) pattern for user profile updates ([f839a98](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f839a98df140f89fb866082d69d8a548ae7a3eb4))
* **config/sql:** complete schema synchronization and harden archiving logic ([37776f4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/37776f46bf0b42959b5831b6476400c726ed5122))
* **controller/paths:** unify template rendering and modernize session data access ([6a9957f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6a9957fba395ff2b76003aa412c8e95b370fc05d))
* **controller/ux:** align admin identity strings and normalize template pathing ([4b7e589](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4b7e589abd473707d61f412eb9518813122f5e9d))
* **core/architecture:** resolve variable collisions, path errors, permission tree anomalies ([9895a82](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9895a82cc8fba8be10abf4e30edc0f74bc8efe06))
* **core/path:** resolve path concatenation errors and enhance archiving robustness ([aebb7c8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/aebb7c86e7cf025f1cf3231ac1883ef9080d7ee5))
* **core/paths:** resolve template inclusion errors and harden archiving logic ([9a1d800](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9a1d8002c50f1ad5b587e7f60245f10a3387f805))
* **core/services:** implement resilient vehicle-type mapping and windows-safe path normalization ([4b980d1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4b980d1fd820232a1332ee57cb207b3e2325b149))
* **core:** move system initialization and data seeding before authentication gate ([e364a04](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e364a0493f0af969289f3e7d17d6667c9c23d990))
* **core:** prevent hash_equals length warnings and apply chronological sorting to history ([d2d9922](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d2d9922d0773a0709847b53434aa6b2060971f01))
* **dashboard/ui:** harmonize asset pathing and activate administrative action triggers ([ba79974](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ba79974746069e553b8ee61a72ea8f04bc654822))
* **database/seeding:** synchronize core database seeds with granular system-ID schemas ([a255ac7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a255ac7cf53a54b55237315e8c65898beb99cbd6))
* **db:** resolve seeding crash on empty database and refactor default payloads ([7d820cb](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7d820cb7aad0b61f056b0d79fd22b1e41709d7a7))
* **emails:** unify variable mapping for dual-use templates and harden A4 logic ([16356c0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/16356c062c5e619ae866305dcb14099f587aba12))
* **history:** resolve undefined variable error in permit list view ([6bcd5de](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6bcd5debfdacaec5b0d7e9cc13550620a89f9df4))
* **infrastructure/mail:** achieve full path consistency across all logging operations ([397fd4c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/397fd4c33998aceaf63d6716d550c60b13b40d11))
* **infrastructure:** standardize path normalization and harden storage operations ([ef8799f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ef8799f4537a58c2db6465e5421f54e3f66221f0))
* **js/ui:** synchronize license plate formatting and voucher validation states ([be4bf74](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/be4bf7485aeec058551d456c89de3d14a1756061))
* **logic/database:** refine temporal validation and complete SQL archive schema ([31a7cd6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/31a7cd63604007bf309519fc82eb5e80e74d607e))
* **public/entry:** synchronize mail queue thresholds and enhance maintenance resilience ([ed579a4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ed579a4ab2ba41b7b92c087770e733b78f6af6d5))
* **security/auth:** remove deprecated legacy level checks from migration endpoints ([adf134e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/adf134ec348a86c86bb4fc4b162bdb03b30e5177))
* **templates/api:** harden asset detection and synchronize pricing UI ([9563af5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9563af5fac525a7b763ac821a8c71f821f9b4a21))
* **templates/history:** modernize magic-link login and unify print document mapping ([d627781](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d62778190271bfe619a1376ccf9ed56e577c87f8))
* **templates/ui:** unify sub-inclusion paths and encapsulate session access ([d8a50cf](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d8a50cfbdf108ad2dfdb80c7d70da3ec57e43ec6))
* **ui/history:** recover and modernize history list layout and enhance form navigation ([1c04ce7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/1c04ce7ca38ef8ca6bcbcf17db4a45087c701e62))
* **ui/ux:** resolve group label inconsistencies and finalize media-ID synchronization ([0c9cb2d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0c9cb2df686f3dadaeafdd02d6268d4461384f6c))

### ⚡ Performance

* **scripts:** append minimized suffixes to output files and mirror directories ([59c0b17](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/59c0b177ee1f6d96558eddcd193e1971cb1e805f))

### ⚙️ Refactoring

* **admin:** fix dashboard filter, export handling, migration lifecycle and email notification ([6411e92](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6411e9275ab62b09784c590acade2aec2950be00))
* **auth/media:** implement failsafe native image pipeline and consolidate ID-based management ([86a16d2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/86a16d2d309230f389c83b394e95b062d75dc085))
* automate codebase modernization via Rector ([cc081bf](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/cc081bf048c5fd2b749bb8eaf668ab806a46ea8f))
* **config:** add project-wide code formatting and style configuration ([6d84976](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6d849767e138a463e74ec47b8b52f19bd839577b))
* **config:** decouple domain-specific settings from core technical configuration ([af13646](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/af136463e2ee4a9b8a1756b7495f1a8bf8671f10))
* **config:** decouple internal reasons into dedicated config file and enforce history sorting ([b54e9a2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b54e9a24b91a8e290946fe806d548760b5825962))
* **entities:** enforce global immutability and strict typing for domain models ([3a18336](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3a183363aaf4a54302a368060ccb6c80970ae5b0))
* **js/admin:** implement event delegation for administrative actions ([a7edf63](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a7edf6322ec5da32b0000ee49278da5c80660345))
* **js:** resolve linting warnings in form-handler and table-sorter ([dfb2494](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/dfb24944545aa003fc387b50e6eac0f4491595da))
* **mail/print:** unify data keys and standardize temporal formatting ([e4e530b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e4e530bc5cabb2c6be57c6c37d6f7c53789c48f8))
* **profile/media:** remove deprecated GD functions and resolve template diagnostic errors ([a30c510](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a30c510576ffe9b84a927966ce7c9a794e126a49))
* resolve coding standard violations and improve code quality ([4ac580b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4ac580bcbe4800ad0f8e4727a5d596f235669950))
* **security/ux:** persistent ID architecture and hierarchical recursive permission engine ([a04419f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a04419f784fa1f86456d0138213d2f2e3d55e5d6))
* **storage/media:** align MySQL schema with ID-architecture and stabilize image pipeline ([675d69e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/675d69ef81a06c9f81b9c8e2ec23517f57df6fd9))
* **storage:** global SQL schema capitalization and defensive architecture refactoring ([6b6b7a4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6b6b7a495074eefacbaec106aa560f6bd6208131))
* **style:** address PSR12 and coding standard violations ([4f0f2df](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4f0f2dfc876f26f1eb50c5db677574b8718914f9))
* **templates/emails:** optimize a4 permit document and integrate dynamic opening hours ([d933c38](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d933c386838df04128b35337c7049741bd124838))
* **ui/admin:** modernize the administrative login interface ([a58624a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a58624a2b457d8cddeac86be3a8ac877837dcf86))
* **vouchers:** centralize validation logic into VoucherService ([b753e6c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b753e6c9969f15dd2e13cdba9ce42ceb95699b2f))

### 💎 Styling

* **ui:** optimize print layouts and update global CSS styles ([6815ac2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6815ac2e764dd3b0087b9f7347bc30dcde735403))

### 🧪 Tests

* **core/services:** validation of auxiliary services and final path integrity check ([003f998](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/003f99883d08adb6ce7637189b941a58fe6ca08a))

### 🧹 Chore / Maintenance

* **templates:** add standardized file headers and licensing information to all phtml templates ([9384f80](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9384f8022acf6de038e416cc2886d36569c547d4))

## [0.31.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.31.0...v0.31.1) (2026-05-16)

### 🐛 Bug Fixes

* **auth:** ensure logout works globally by routing to central admin controller ([c12f786](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c12f786da07154527f7cf3016421f38bcc035956))

## [0.31.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.30.0...v0.31.0) (2026-05-16)

### 🚀 Features

* **mail/ux:** consolidate queue stability and enhance user data summary ([ca136b2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ca136b2ea530b3be1773307fd17eeda65cb75561))
* **mail:** implement asynchronous mail queue and optimized success UI ([1d6740a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/1d6740ae776d0af692667a72efa59a9e41bbf7c7))

## [0.30.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.29.0...v0.30.0) (2026-05-15)

### 🚀 Features

* **core:** implement intelligent auto-setup and cross-storage data seeding ([8466837](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8466837f9393565a152eaa7bbe5ff9b1279f9cc8))
* **migration:** implement granular backup management and restore engine ([f448d0c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f448d0c46e469e9325eab0aec2cfa51e9cb09b37))

### 🐛 Bug Fixes

* **auth:** implement deny-first priority and live storage permission checks ([4fa1033](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4fa10335b4d76da7da4505994f49d129c6f8b7d0))

## [0.29.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.28.0...v0.29.0) (2026-05-14)

### 🚀 Features

* **profile:** add personal profile management and self-service password updates ([be018c8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/be018c8ca363509b56d59418a4811e753c90449e))

## [0.28.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.27.1...v0.28.0) (2026-05-14)

### 🚀 Features

* **auth:** complete overhaul and migration to granular permission matrix system v0.28.0 ([44651e2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/44651e254b45d8fdf644ab05d8ca54a37d8b17f4))

## [0.27.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.27.0...v0.27.1) (2026-05-12)

### 🐛 Bug Fixes

* **ui:** resolve array conversion error and filter inactive vehicles ([edafa55](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/edafa5589dc8093c3bc971bf3caef2810e71f9d0))

### ⚙️ Refactoring

* **core:** implement agnostic vehicle metadata system ([b53fc9b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b53fc9bb54c8efccecf4b98daccc7e4372f8a6af))
* **stats:** implement legacy vehicle fallback and active filtering ([fd0b7a9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/fd0b7a997fb14913ec5dab2b4262afeb5573d118))

### 🧹 Chore / Maintenance

* **env:** upgrade dev-env-blueprint to v0.26.0 ([a26278b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a26278b464b1e60cd06cbe3d633d5c9f4b3ab365))

## [0.27.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.26.0...v0.27.0) (2026-05-08)

### 🚀 Features

* **logs:** redesign mail log and implement detailed error reporting ([c064113](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c064113aa9e0265b251ed3df81bfe61bf39f185d))

## [0.26.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.25.0...v0.26.0) (2026-05-08)

### 🚀 Features

* **stats:** add last used name to ranking and fix table alignment ([51d9d0d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/51d9d0da95cb59615462043b84aafad1187f4cba))
* **stats:** enhance plot ranking with revenue and email tracking ([4e2b920](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4e2b9204ebbd4c0a4040a7cdf0760f54f2ffa653))

### ⚙️ Refactoring

* **admin-ui:** global dashboard overhaul and functional unification ([bf2e197](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/bf2e197dfec05431601f7da2d401477531f03c79))

### 💎 Styling

* **admin-ui:** display entry counts for future permits tab ([678fae2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/678fae259fbab657b87d33a31f469b19d9218644))

## [0.25.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.24.2...v0.25.0) (2026-05-08)

### 🚀 Features

* **stats:** implement comprehensive financial reporting and yearly archives ([9b8b6fe](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9b8b6fe6c8cb931aa7a5f5f221cfb135446cfab7))

## [0.24.2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.24.0...v0.24.2) (2026-05-08)

### 🐛 Bug Fixes

* **core:** resolve PHP syntax errors and JS bridge synchronization ([704809a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/704809a2e2b825d84bc6f487be0b2ebcf06d76fa))
* **form-ui:** restore missing alert styles and add navigation footer ([3e8168f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3e8168f4656dab1c2a4487f5e5e0f34d1eec0719))
* **mail:** implement SMTP response code validation and debugging ([aa8b2d6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/aa8b2d67770f25439150fd1e59ffe3349ac45398))

### 💎 Styling

* **public-ui:** enhance form feedback and implement auto-redirect ([9967f67](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9967f673963e1fadffc5cd8cf7f65333b84a0810))

### 🧹 Chore / Maintenance

* **release:** v0.24.1 ([ec47e7c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ec47e7cff152e91cd9434888a1dcd5b481cc00fb))

## [0.24.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.24.0...v0.24.1) (2026-05-08)

### 🐛 Bug Fixes

* **core:** resolve PHP syntax errors and JS bridge synchronization ([704809a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/704809a2e2b825d84bc6f487be0b2ebcf06d76fa))
* **form-ui:** restore missing alert styles and add navigation footer ([3e8168f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3e8168f4656dab1c2a4487f5e5e0f34d1eec0719))
* **mail:** implement SMTP response code validation and debugging ([aa8b2d6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/aa8b2d67770f25439150fd1e59ffe3349ac45398))

### 💎 Styling

* **public-ui:** enhance form feedback and implement auto-redirect ([9967f67](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9967f673963e1fadffc5cd8cf7f65333b84a0810))

## [0.24.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.23.0...v0.24.0) (2026-05-07)

### 🚀 Features

* **config:** rename permit storage files for semantic consistency ([f3cd62b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f3cd62bce14df4a99a8ee811ec4da31d2b5b586b))

### 🐛 Bug Fixes

* **admin:** dynamicize export extensions and cleanup mail log retrieval ([94c528c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/94c528c3d135f338a9cee7288d8c75ab5e12faa9))
* **admin:** resolve hardcoded opening hours in admin print view ([10d558e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/10d558eedf249624f5af0d717c6547b584472c05))
* **api:** clean up legacy code and unused variables in endpoints ([5fff9c6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5fff9c6b6be74d3e6e6160ae530695339da922bf))
* **api:** replace manual storage access with service delegation ([bbf3c77](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/bbf3c77ab26912127c2f1c1ef53a41d37c51ce69))
* **arch:** eliminate remaining hardcoded paths and fix dependency injection ([3ea9c22](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3ea9c22d49a98af20f36c2ea6a2ef160717675b3))
* **arch:** move opening hours formatting to HolidayService ([ddf40ad](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ddf40adc06d16511cdf0ee7373c4c1afd9467bb9))
* **auth:** unify access level detection and username retrieval ([946f263](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/946f2636678b6338576b7f87c860ca9948273749))
* **check:** dynamicize API and asset paths in public status view ([db13ae3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/db13ae385f75f4bf9fe9b6909f9245697464e155))
* **config:** dynamicize archive lookup and clean up redundant variables ([aa9f0ac](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/aa9f0ac2680ec3f4d2ff7bf4d14f85cc960fbbd6))
* **config:** eliminate last hardcoded storage paths in container and service ([deffc85](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/deffc8568d13de2a8655700d27313e0430ba25f4))
* **controller:** provide base_url to User and Verification views ([0443195](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/04431955b689b3e415593dbd7f963854d86a8855))
* **core:** dynamicize migration backups and globalize config bridge ([482b8d2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/482b8d2205308afe5343bdcdd8b82650c7a2dcd4))
* **core:** resolve critical price snapshot ([3b997b3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3b997b357e0b1137986b051c321541b1f44588f4))
* **core:** resolve verification redirect bug ([5a235c1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5a235c18428e33a5dd104e87a1e9494ec5eda820))
* **frontend:** restore form stability and fix asset path resolution ([574262b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/574262b26af594a74fbb78358e737c529267a11a))
* **icon:** upload missing icon ([29970c7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/29970c7e72f9391519f4845dd40fdb39218f6875))
* **maintenance:** resolve base_url detection and asset path errors ([23dfcb1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/23dfcb19f4fddc5b40165e6b190cc834b2ae7d2e))
* **view:** align variable names for opening hours in admin print preview ([32ce2dc](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/32ce2dc38958fefdfd4129ccdb6b9aeb31150325))
* **view:** resolve remaining magic strings and hardcoded paths in templates ([9e86d46](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9e86d465be267730d09aa1e763782d2591c2dcb4))

## [0.23.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.22.0...v0.23.0) (2026-05-06)

### 🚀 Features

* hybrid MySQL/JSON storage architecture, migration engine & sync v0.23.0 ([29c2a54](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/29c2a541fd929f1c69239cc44e9438a12d159d3e))

### ⚙️ Refactoring

* finalize hybrid storage and fix strict typing errors ([f9143bf](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f9143bf50efb23784cb656ac5976a108a42f49ed))

## [0.22.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.21.0...v0.22.0) (2026-05-05)

### 🚀 Features

* **core:** add dedicated admin maintenance mode ([c74fc09](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c74fc090336cc03bee8ab33ea74acb5a1a836e71))
* **core:** implement maintenance mode via central bootstrap ([e9653c8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e9653c834a9a7c36473f1deba2f068612fca1592))

### 🐛 Bug Fixes

* resolve P1014 and P1037 diagnostic errors ([df1ee08](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/df1ee08e4029d52c08b1ec1406eef284ac620052))
* restore corrupted core files after license header update ([3a429d8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3a429d8faa2b0bfef7d6650cae2d4ae38cae28af))

### ⚙️ Refactoring

* **core:** use internal include for maintenance mode instead of redirect ([d63c853](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d63c853b813557183884a413a2e536ce74d65543))

## [0.21.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.20.0...v0.21.0) (2026-05-05)

### 🚀 Features

* **history:** implement dual-auth login and PRG pattern v0.21.0 ([66081ec](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/66081ecfc12a40eb6054f608709b1421904e478d))
* **verify:** implement 6-digit short code for double opt-in v0.22.0 ([5201e5e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5201e5eac0eb4f87de0316c8a5b41742c3b098b3))

### ⚙️ Refactoring

* **api:** migrate all API endpoints to central bootstrapper ([04bd341](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/04bd3412f38182577596be83f260841a164499e8))
* **bootstrap:** centralize app initialization and config merging ([c648931](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c648931a47ea49a01c05e7fcbb4a60dafe7056d1))

### 📚 Dokumentation

* add proprietary license headers to all source files ([15ebb61](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/15ebb61ceeb7036a3279e55ccb031cebc497c73d))
* add proprietary license headers to all source files ([362a0d7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/362a0d72d089b9a7a1632ca74a09e9ef57b1f1f2))

# Changelog



## [0.20.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.19.0...v0.20.0) (2026-05-05)

### 🚀 Features

* **admin:** advanced voucher engine and issuance workflow v0.20.0 ([0542357](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/054235722675770c91c78dd1e4d5a224b4645f3e))

## [0.19.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.18.0...v0.19.0) (2026-05-04)

### 🚀 Features

* **admin:** enhance universal generator with dynamic pricing and form restructuring ([8d51a4e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8d51a4efd9ded79c6c0ab48bee7c07582bb8387a))
* **admin:** finalize universal generator with 4-step layout and dynamic pricing ([919c643](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/919c643ea2fac6b50e12fea6a64b77b8519ba7b6))

### 💎 Styling

* **assets:** add WebP system icons to public/assets/img/icons ([3c83be9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3c83be9af18d33be333aff732b2183acadc068a9))

## [0.18.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.17.0...v0.18.0) (2026-05-04)

### 🚀 Features

* **admin/core:** finance monitoring, status fallback & form submission fix ([94093a1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/94093a1839f9df0f53088b324306b391a079b1bd))
* **config:** make mail log storage and display limits configurable ([1238589](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/12385894482d84dd0841fcce95e43aaa57784279))

### 🐛 Bug Fixes

* **admin:** implement status fallback in detailed check view ([3a3563d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3a3563d1cc29d365f6276666cf56d5bc5c021002))
* **core:** enforce global uniqueness for permit IDs across all archives ([0497f5a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0497f5a790182696dc675d0001ee0c5402d50aed))
* **voucher:** ensure absolute uniqueness for generated voucher codes ([9b4c9f6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9b4c9f680f26bb6ef16d69236068d5194fc64838))

# Changelog



## [0.17.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.16.0...v0.17.0) (2026-05-03)

### 🚀 Features

* **admin:** add direct action buttons to manual permit success message ([bedd7b6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/bedd7b6e7ba02734e55ac63dd760321143794b64))
* **admin:** finalize universal generator with optional email and advanced validation ([672cef4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/672cef41a8dc93eb7c28821b3a6d1ae2e8a2a27b))
* **admin:** implement finance monitoring and unify unpaid status display ([c7edbd9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c7edbd909d67f7c1850831d98bbf81f5c1245903))
* **admin:** make email optional in manual permit generation ([7387841](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7387841766cbc3631bb0b2360b835af9816bb98e))
* **check:** add ability to verify permits by license plate number ([4306cbc](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4306cbcc47577d09c268ec9331aacbf5cdca15c1))

### 🐛 Bug Fixes

* **admin:** align detailed check view logic with public rest period behavior ([5d206b3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5d206b34c49dc3bb031ddd36da824ffba8f6c95f))
* **check:** implement smart next-available-slot detection for rest periods ([17a5738](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/17a57386dee6a06c3e308c6e506a05abf0e13d6f))
* **check:** resolve conflicting "today/tomorrow" labels in rest period view ([bb64c62](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/bb64c6295799e72e73e7881ca9890714059fca28))
* **core:** support electric (E) and historical (H) license plate suffixes ([f2dde07](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f2dde07d8d5ca121b1afb148152bdcc6096e3bae))
* **ui:** synchronize public rest period banner with enhanced controller logic ([4eb0a00](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4eb0a0093241c8e9154403b63a6406a36d2639d7))

### 💎 Styling

* **admin:** replace text emojis with WebP icons in admin tools tab ([88ae211](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/88ae211e870528c8ff5f82fa12c10a3c14a973fe))
* **admin:** synchronize table columns for future and expired tabs ([9d53554](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9d53554af868f97b56b04dd7e3c0ae595097e330))
* **ui:** implement full-page status backgrounds and admin status banner ([b7a9bcd](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b7a9bcdadd256599e83e067e760665d0e95a85b1))

## [0.16.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.15.3...v0.16.0) (2026-05-03)

### 🚀 Features

* **public:** synchronize bidirectional date logic and auto-formatting to public form ([feae62e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/feae62efa33b37cfc71dac668782163c475918ca))

### 🐛 Bug Fixes

* **admin:** implement bidirectional date synchronization and minimum duration lock ([f72d024](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f72d024277d000009496b547fd14d716c16a2195))
* **admin:** improve dashboard button symmetry and tool input validation ([e45fd7a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e45fd7a2187668e745c19271884787190f33ec41))
* **admin:** resolve mail rendering errors and prevent duplicate form submissions ([75b9b3e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/75b9b3e74d66fb66befdaafc7a4af7f68cf9d9ad))
* **core:** enhance license plate formatting with manual hyphen override ([64e69ea](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/64e69eabaca66e67b1f022db7e49a32ea09bc079))

### ⚙️ Refactoring

* **check:** transform legacy check pages into structured premium UI ([50969d2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/50969d2f73d7832322d0a3e6a81e9823f9370a8b))

### 💎 Styling

* **admin:** enhance success alert design and center notifications ([d2a2227](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d2a2227e7b86d88f37557cdce09a3d5eedce8cc4))
* **admin:** relax date filter constraints and implement soft-sync logic ([e441b73](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e441b7338ff21f437a1e36cf4b7d6cb10a7fd1fb))
* **ui:** replace emojis with custom webp icons and fix button rendering ([42193dd](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/42193dd36f81bdbdcc455bfe5de9e83a816d0c2f))

## [0.15.3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.15.2...v0.15.3) (2026-05-03)

### ⚙️ Refactoring

* **admin:** standardize session variables and unify user management UI ([a350f7f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a350f7feb448b7fa97ae9076a845050333c43e76))

### 💎 Styling

* **admin:** modernize user management UI and implement role badging ([31aa72e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/31aa72ee3dbd97ab102734444d881827ba229cbb))

## [0.15.2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.15.1...v0.15.2) (2026-05-03)

### 💎 Styling

* **admin:** make dashboard navigation menu responsive ([7e280ed](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7e280edd1123b9e78aac9514d1d99837aecaccbe))
* **ui:** rename "Gutscheincode" to "Aktivierungscode" ([63b36bc](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/63b36bc0f6a2f3d470683ee88f494b04231394d9))

## [0.15.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.15.0...v0.15.1) (2026-05-03)

### ⚙️ Refactoring

* **public:** finalize premium application form with dynamic vehicle logic ([130bf1d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/130bf1dd0731d116ff3ddc6d4a7149df677e0bc0)), closes [#price-display](https://github.com/RaptorXilef/kga-einfahrgenehmigung/issues/price-display)

## [0.15.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.14.0...v0.15.0) (2026-05-03)

### 🚀 Features

* **dashboard:** centralize filters and exports into dedicated tab with iconography ([c07accd](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c07accd13a3066b7bd0a5b0e5fcac19bb87dd2e0))
* **dashboard:** migrate statistics and ranking to dedicated tabs ([bd705fe](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/bd705febbd9b708b0a16eb2b5cca9412dd10fd14))
* **public:** enhance form UX by hiding redundant fields and highlighting fees ([e9f04d6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e9f04d699e1b0694356e4a004fc8263d8df0fc3a)), closes [#price-display](https://github.com/RaptorXilef/kga-einfahrgenehmigung/issues/price-display)
* **public:** improve voucher UX and dynamic pricing display ([a60f36d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a60f36d88d6c128e3b882d5969db8ebfe6bf2ec4))
* **ui:** implement bi-directional date binding and safety constraints in admin tools ([dec6703](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/dec6703d2d1191d76bb15c0149ace53527baac22))
* **ui:** unify admin tools into a smart universal permit generator ([687a18b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/687a18b75099aef7102445249d5ba29b81e0f27b))

### 🐛 Bug Fixes

* **ui:** restore global navigation and filter aesthetics while maintaining premium tools ([f44a171](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f44a171d60162717575abcc743171f66aa44bdb2))

### ⚙️ Refactoring

* **admin:** unify permit generator with bi-directional dates and conditional fields ([2cce7e8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2cce7e81b46194c948e75cbdc9113d1bcf194871))
* **backend:** consolidate payload naming convention for admin tools ([6f3eea6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6f3eea622ab96e3f18284cb98fd7c22e17b0c002))
* **dashboard:** elevate global filters to dedicated control bar ([3d5c887](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3d5c8877bf101a170038b6396cc2075d0cbc0f3c))
* **public:** align application form with premium design system ([2d08bf8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2d08bf87438edd8a0a8f12ce39b127c7b291c7f9))
* **view:** complete dashboard modularization and component migration ([bded9d0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/bded9d0de10ce430fa13e265953f53599252bb1f))
* **view:** extract active permits tab to standalone partial ([91abe6a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/91abe6aec9a5dcd844ee5b067fec01e40bdc165c))
* **view:** modularize remaining dashboard tabs for future, expired and vouchers ([06c86a2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/06c86a2ee99c6dd0ae47b00ff3970b66afd4cf1c))

### 💎 Styling

* **admin:** achieve pixel-perfect symmetry and isolated layout logic ([e6a3cea](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e6a3cea40bb8763a095766e72bf1ddf367de8cd7))
* **admin:** finalize dashboard UI with pixel-perfect symmetry and adaptive containers ([2df7472](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2df747246ebfb40802ff08d59b16e816e1f5f8f5))
* **admin:** fix heading alignment, restore badge colors and unify export UI ([bc8c03a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/bc8c03a75d0bd7a03de7970cbd28644b4c06a579))
* **admin:** overhaul universal generator with premium UI and custom purpose logic ([f878d4d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f878d4dbcdeffbec3dbcc3f83a48ae57bd5589eb))
* **admin:** synchronize export tab with premium action card design ([dd2cf15](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/dd2cf154346f0f3c2015426cf40a7bad39c1ec05))
* **admin:** upgrade navigation to modern segmented control and unify export UI ([e694615](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e694615d13b4af83bec09019dc9e197f6044fec8))
* **dashboard:** center navigation and fix absolute icon positioning ([4ac40ff](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4ac40ff60cd5b5b1de867dd2ba077bbc5704bcee))
* **dashboard:** finalize polishing of stats, vouchers, and log tabs ([fda3915](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/fda3915b3f4e0608d4fad24c6c98794e6cab928d))
* **dashboard:** finalize styling for active, future, expired, tools, and export tabs ([d6d3fda](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d6d3fda92e7e16a347ea889cf441159dd6d53777))
* **dashboard:** implement responsive wrapping grid for export actions ([0327673](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/032767378e63dd94453d1a945edca505231c3424))
* **dashboard:** implement unified design system and content centering ([095fdfa](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/095fdfa6688d99d4b7c86519a4ae359bc74fbadb))
* **dashboard:** upgrade export UI and fix search input display ([3a0533e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3a0533e5d3ec60d0cc7979d23f1164d53c74ec34))
* **public:** final centering and typography polish for application form ([0ff33d4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0ff33d4ff8a3166f6f2c908efa9fefed76601b70))
* **public:** relocate price display to final summary position ([841e38d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/841e38da7df0e007118876da3b0f23a3cdbb2d42)), closes [#price-display](https://github.com/RaptorXilef/kga-einfahrgenehmigung/issues/price-display)
* **ui:** align global header and test indicator with new design system ([1eacaa0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/1eacaa0b1b7a27062505fd46e031d1cf4367c2b3))
* **ui:** animate test mode indicator with marching stripes ([6c4388a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6c4388a83e7d0781e209fd164266df1c1fb979b0))

### 🧹 Chore / Maintenance

* **arch:** design new template partial architecture ([d41e19e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d41e19e7809161c064851bb2b5b743c8be649992))

## [0.14.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.13.0...v0.14.0) (2026-05-02)

### 🚀 Features

* **admin:** implement voucher management and permit suspension ([3cabc2c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3cabc2c542ac3f2cab785cec42015037ca35cbd2))
* **permits:** implement template engine for flexible durations and types ([21cbe2b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/21cbe2bb5659b2e1fefa5e130ff0fa818f980469))
* **storage:** implement yearly archiving and incremental history loading ([148af21](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/148af21bfaf7ec5a92d9f7460fc6313ab6d48fdd))
* **vouchers:** implement advanced voucher types with pre-filled data and QR codes ([5b783e1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5b783e177562b59fe49f29d664311d2ceebc8530))
* **vouchers:** implement multi-template support for permanent and custom permits ([dd0366c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/dd0366c661a4c88623b4cb8bca79e1222b9aeee1))
* **vouchers:** implement pre-filled vouchers with QR and field locking ([591bce3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/591bce380f603b0da04b3a136cb9a461ac94e8ee))
* **vouchers:** implement pre-filled vouchers with QR and self-deletion ([8bea118](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8bea118b5b879414a6c68af6f8d114923720947c))

### 🐛 Bug Fixes

* **arch:** resolve static analysis findings and stabilize view rendering ([211a2ee](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/211a2ee5146d84de56068a46fcc03001b7b3906b))
* **core:** finalize controller-service integration and resolve rendering issues ([4730877](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/47308770e2e1797a16609c397d972177a6d06faa))

### ⚙️ Refactoring

* **core:** finalize Value Object integration and restore payment mail logic ([85b4232](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/85b4232745fc332ed81a526320c70e337e96b1b4))
* **core:** migrate to controller-service architecture and implement features ([44cba7d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/44cba7da5ba864cccc272e4caeb0f39dc044646e))
* **domain:** finalize Value Object architecture and cross-layer integration ([c73ce29](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c73ce29517078fd23d000d27c71047e6e10e1bde))
* **storage:** resolve code duplication via StorageMapperTrait ([ba34a51](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ba34a51c35924107ddc118a65c7ec6581de8d0a4))

## [0.13.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.12.0...v0.13.0) (2026-05-01)

### 🚀 Features

* **history:** implement document re-print for verified tenants ([3bcc65b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3bcc65b89c522f662aede6949337c7ce0a14e4d8))
* **history:** implement Magic Link service for tenant access ([08d4cfb](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/08d4cfb7ca23bb9c87fbc9f2ddef7596a1eff303))
* **history:** implement tenant portal with passwordless magic links ([f3b76c2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f3b76c2b4fb4943b4938d4897a6502652765d22c))

### 🐛 Bug Fixes

* **qa:** resolve complexity and type-hinting issues in history portal ([4aa553c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4aa553c80786322727829319af5de15171a13497))
* **qa:** resolve final PHPMD ElseExpression in HistoryController ([ea9609f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ea9609fcba7ff85a885f3824b5ab9d663d169bab))

## [0.12.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.11.0...v0.12.0) (2026-05-01)

### 🚀 Features

* **admin:** implement tools tab for manual bookings and vouchers ([2b740b6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2b740b68773a409d08419eb1b9417f21ec4b2002))
* **api:** complete 2-stage verification and payment finalization ([e30ff96](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e30ff962c1b7e81d9fffad2eabe6292435490877))
* **api:** implement paypal order creation for verified permits ([58c9971](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/58c99715db0cb92e49883c0e5a076795e758924b))
* **core:** implement voucher service and internal comment persistence ([11bc186](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/11bc186fdb940f9d272929050659f2006a5fd3d5))
* **logic:** implement public voucher redemption and fix service access ([c81e99a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c81e99a571f97f629cc3575cf36ff0ba79447062))
* **workflow:** enforce email verification before payment or voucher usage ([f54cfb8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f54cfb8f352c745f084da0210e03bf083d8dcb70))
* **workflow:** implement 2-stage pending system for improved data quality ([7e12daa](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7e12daa20804c03e172d6eeb1ea68731c4e0e1b3))

### 🐛 Bug Fixes

* **controller:** resolve dependency errors and refactor verified request access ([bc4a534](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/bc4a5342cb727cd027e32e76af3f3f710dbf6674))
* **qa:** ensure identifier uniqueness and resolve PHPMD/PHPStan violations ([8976bea](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8976bea7d2008b9b581fcaf62ea3eb7057cc97da))
* **qa:** resolve phpcs violations and scale identifier for 1400 plots ([339a826](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/339a82642d5720732e67e21eddb994d466ac782f))

### 🧹 Chore / Maintenance

* **cleanup:** remove legacy paypal logic and unused dependencies ([66f37ec](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/66f37ecb2ee350788004907dc7e577c25326e9ca))

## [0.11.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.10.0...v0.11.0) (2026-04-29)

### 🚀 Features

* **admin:** implement email logging and dashboard history tab ([b4aff46](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b4aff46ba425b6305e815e99e90745566fc80f62))
* **check:** add granular validity states for rest periods ([df10c15](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/df10c15d85dc2e78ad6a2532be0537af873c9cd6))
* **logic:** implement overdue tracking and granular opening hours matrix ([cf7fb59](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/cf7fb5920efcf1fc6cc46afeae3f82ba3131e578))
* **logic:** implement temporal overlap validation for parcel permits ([e492366](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e492366204ad0a5a5ae722062f4a42addbfbbe12))
* **logic:** implement two-stage overdue escalation and matrix-based holiday check ([5f0670d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5f0670d5dda82bcef9be6c56c7f431bbc31b0353))

### 🐛 Bug Fixes

* **container:** resolve dependency mismatches for HolidayService and CheckController ([566207e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/566207e35068c8acea1167ae07a72652d78a0b61))
* **qa:** resolve static analysis violations and stabilize template context ([1fed181](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/1fed18150bd681e3ad5ba53de72a61500d1ddc68))

### ⚙️ Refactoring

* **storage:** enforce immutable request timestamp across all layers ([6d4d943](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6d4d9432286c8c307885b63280c18456c31b6b2b))

## [0.10.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.9.6...v0.10.0) (2026-04-29)

### 🚀 Features

* **auth:** enable user creation for admins and fix template variable scope ([060a5c0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/060a5c05bd1ffee42fb54d6c5279872e24875662))
* **auth:** implement hierarchical user management and permission matrix ([d8fb652](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d8fb652fd8816e092633177c801e0fd49126d591))

### 🐛 Bug Fixes

* **ui:** finalize v0.9.7 identity system and eliminate template warnings ([c164e68](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c164e68a12a99a7c80c6c3d5c9ca614c418e594e))

### ⚙️ Refactoring

* **user:** reduce cyclomatic complexity and eliminate else expressions ([86ababc](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/86ababcebb01f22624b1ce665ce20e3be17838f4))

### 🧹 Chore / Maintenance

* **release:** v0.9.7 - implement user management system and 4-tier RBAC ([37519e7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/37519e73549de28579b68357aebd7024865da9d1))
* **update:** update php-js-dev-env-blueprint to version 0.25.1 ([d690b7f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d690b7f6c58db6dc645e052c8d46256ca6bed309))

## [0.9.6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.9.5...v0.9.6) (2026-04-28)

### 🧹 Chore / Maintenance

* **release:** v0.9.6 - achieve zero PHPStan violations on level 6 ([521a5c0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/521a5c0e744b8e7cc769468dc4008b6ae83041ca))
* **release:** v0.9.6 - complete generic type coverage and fix container parsing ([666958b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/666958b7463a98d21d6758f9b801d7abad7f0442))
* **release:** v0.9.6 - implement PHPDoc generics for array types ([18863e8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/18863e888abaa9cb35762283b8da8683ca5f185c))

## [0.9.5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.9.4...v0.9.5) (2026-04-28)

### 🐛 Bug Fixes

* **arch:** register AuthService in container and restore full admin logic ([efa35b1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/efa35b1d963a3023d25bf2b205de00b305f359bd))
* **release:** v0.9.5 - fix template scope and unused controller parameters ([e8a4eff](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e8a4eff78caca526525fae0434e9878691019512))

### 🧹 Chore / Maintenance

* **arch:** harmonize view rendering and eliminate unused variable warnings ([4afb686](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4afb686ea9c0d0bcec19ace37a8cdfacde7ca50d))
* **arch:** implement view-render pattern to eliminate linter noise ([ecfb009](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ecfb0092cd568e8a3fb6a7605712504051c0f00e))
* **release:** v0.9.5 - complete application migration to controller-based architecture ([fd13bfc](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/fd13bfccb11bde9b0c97d9967f0a49e3740af798))
* **release:** v0.9.5 - complete public directory logic migration ([a84e30a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a84e30a4bb941d9f594d2d490af7a619dda15e28))
* **release:** v0.9.5 - eliminate exit expressions and reduce complexity ([11ce95a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/11ce95acb0fefeb64866e3a19f75fc905f84d9eb))
* **release:** v0.9.5 - migrate admin logic to namespaced controller ([2f9d30a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2f9d30ab43a5a9cd0dbd66e1d2072592e523a012))
* **release:** v0.9.5 - migrate email verification to VerificationController ([dd0bcfd](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/dd0bcfd1efbc7949a14253228cc1e845164c62d0))
* **release:** v0.9.5 - migrate payment api to PaymentController ([9e69043](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9e690433739ec795fda64a1ccc20e93708ad7309))
* **release:** v0.9.5 - migrate permit validation to CheckController ([d39ff60](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d39ff60cb4c1a83f6b9be64151d0b2e25f490b26))

## [0.9.4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.9.3...v0.9.4) (2026-04-27)

### 🧹 Chore / Maintenance

* **release:** v0.9.4 - enforce clean architecture and decouple configuration ([216f55c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/216f55cc68d35f79747695531d30186d0534aa6e))
* **release:** v0.9.4 - finalize dependency inversion in service container ([c9ca4fb](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c9ca4fba4ebd0faed653533cb3c44ac01412ded0))

## [0.9.3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.9.2...v0.9.3) (2026-04-27)

### 🧹 Chore / Maintenance

* **qa:** adjust PHPMD rules for architectural requirements ([04fc6bb](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/04fc6bb4932ba164c7227f66827b02a416977c03))
* **release:** v0.9.3 - global FQCN support and file-based authentication ([a10bfdd](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a10bfdda4e41047d9b55e043e1da3bd06dd8e99c))

## [0.9.2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.9.1...v0.9.2) (2026-04-27)

### 🧹 Chore / Maintenance

* **release:** v0.9.2 - resolve linter issues and prepare paypal testing ([7677efa](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7677efa2ac5037650174e1712c67fcb800108dfe))
* **release:** v0.9.2 - satisfy phpcs requirements and modernize payment service ([0a45822](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0a458229ea6513e9fdf569566c97aff94be1484a))

## [0.9.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.9.0...v0.9.1) (2026-04-27)

### 🧹 Chore / Maintenance

* **release:** v0.9.1 - externalize user auth and fix critical API paths ([1e4ee42](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/1e4ee42a106948b2566eb29befb6f305ecd9b8e6))

## [0.9.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.8.0...v0.9.0) (2026-04-27)

### 🚀 Features

* **api:** implement create_pending endpoint and PayPal order generation ([99ecb37](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/99ecb3754be0fac8aed1fe31f2cb76e7fce7ce61))
* **security:** implement double opt-in for bank transfers and paypal flow ([0906c90](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0906c9002dd24afadb759fe52f35297ea7aa259d))

### 🐛 Bug Fixes

* **core:** resolve variable scope issues and modernize curl usage ([1a21067](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/1a2106750bb3b23ce793d55e809cc2d4f0aeb947))

### 🧹 Chore / Maintenance

* **release:** v0.9.0 - implement double opt-in and live paypal flow ([703dd41](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/703dd413deb486342bd6ce85d4700e9f99a994eb))

# Changelog



## [0.8.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.7.1...v0.8.0) (2026-04-27)

### 🚀 Features

* **core:** implement environment-aware architecture and paypal integration ([e7883ca](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e7883ca55d2b1d332767800dd22210a96e592ab4))

### 🧹 Chore / Maintenance

* **release:** v0.8.0 - finalize payment architecture and fix template scope ([b966713](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b9667136c0b217d1dcf5c3963a2e91c3e8ed20b1))

## [0.7.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.7.0...v0.7.1) (2026-04-27)

### 🧹 Chore / Maintenance

* **release:** v0.8.0 - architecture refactoring, dev-mode and paypal integration ([e34d90c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/e34d90ca1573e84fb1435c909ca8a6b135f003d1))

## [0.7.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.6.0...v0.7.0) (2026-04-27)

### 🚀 Features

* **admin/check:** implement dev-mode, manual search, and partial ID matching ([dc5f38c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/dc5f38cd6ed817b1e9677dadd51d551757749e20))
* **admin:** add real-time dashboard search and refactor JS handler ([0c75ccb](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0c75ccb15eb4a07102667cef19b5a5ef7102b46f))
* **admin:** complete association dashboard with tabs and print preview ([cde7513](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/cde75133298827a38fa30da652d82a6ddd82c65d))
* **admin:** implement login template, dashboard search and manual activation ([18dfa00](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/18dfa005e3708c18c18dfb92077cadcf236e5cfc))
* **admin:** implement tabbed dashboard view for permit grouping ([67855b9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/67855b9d345653bf4850fda6a9feb5c094b3b598))

### 🐛 Bug Fixes

* **storage:** update JsonStorage mapping for expanded Permit entity ([7bc0651](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7bc0651fab053ae519524b257b902eec5e5470d2))

### ⚙️ Refactoring

* **admin:** optimize CSV export for German Excel compatibility ([dc64d46](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/dc64d4648716e69cd16c7836469cd285c64299e1))

### 💎 Styling

* enforce global namespace backslashes for classes ([34be12d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/34be12dd9cb7f62431819d93728012b4e98d782d))

### 🧹 Chore / Maintenance

* finalize IDE environment and rector modernization ([6cbad2b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6cbad2bfc9a1d6cac281ff7afa86791006afcbd1))
* **release:** v0.7.0 - administrative dashboard with search and tabs ([dde15c7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/dde15c7129b351602bc81f7ef46cfe2ed2f40912))
* **style:** synchronize php-cs-fixer rules with rector and optimize performance ([13bc68c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/13bc68c5fb8939820492e289dd3ccab6a34361c6))

# Changelog



## [0.6.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.5.0...v0.6.0) (2026-04-26)

### 🚀 Features

* **admin:** implement secure login and multi-level dashboard ([8131e7d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8131e7d26ee1f8aeb29e2efe642f2f65129a8d14))
* **admin:** implement statistics, date filtering, and data export ([0f0d64d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0f0d64db66047edb1f8bed1350b22f89951ace89))

### 🧹 Chore / Maintenance

* **release:** v0.6.0 ([dd93e34](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/dd93e347bd9068cbdf35ebcf131ac0294522d293))

## [0.5.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.4.1...v0.5.0) (2026-04-26)

### 🚀 Features

* **config:** implement v0.4.0 configuration and session-aware check ([701f2c0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/701f2c0398105f1bcf1c4d510dc8b14fbe321e33))
* **core:** implement unified identifier and EPC-QR payment integration ([b2b63b9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b2b63b9780edb198482b42e7cfdb6b528da2a94a))
* **frontend:** implement v0.4.0 form with dynamic fields and JS logic ([d04f469](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d04f46975e583ce21e32a26d8b3b86386a66804c))
* **mail:** implement professional 3-mail system with v0.5.0 standards ([597f622](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/597f622e82b9318760ed9e7b9146634421af39d5))
* **view:** implement A4 print template and privacy-aware check logic ([8c94f95](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8c94f950dea1e1996da295bba9f7deb88022750f))

### 🐛 Bug Fixes

* **js:** correct license plate regex and resolve biome linting ([d640524](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d64052482b6f7ba8ffa0def6ffa22c8b1a594cfa))
* **js:** prioritize Berlin city code in license plate formatting ([9e079c1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9e079c1b7770af28be739810f632b111eef6f140))
* **view:** align check views with privacy and admin requirements ([a7e2cd3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a7e2cd3a80c6957359e6d572ba0a993533f82ca2))

### ⚙️ Refactoring

* **core:** migrate to v0.4.0 root-architecture and extended permit logic ([d43f83a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d43f83aed276dba5e65bc815561c8b3905b27bbc))
* **js:** migrate form-handler to OOP and implement Berlin holiday logic ([947d742](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/947d742e3125dbcf2faff1bbb98a611ac84aaad4))

### 🧪 Tests

* **js:** implement comprehensive Vitest suite for PermitFormHandler ([9a2fb25](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9a2fb25bcf666aea0828bd1873f3abcf9c3800ca))

## [0.4.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.4.0...v0.4.1) (2026-04-26)

### ⚙️ Refactoring

* **arch:** flatten directory structure and optimize path resolution ([ee40888](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ee408881bbeca8b206b8281b9dd80bf97882ee4e))
* **arch:** flatten directory structure and optimize path resolution ([9954a46](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/9954a46aa04b70d58bf51262446cf33157e9a8fb))

### 🧹 Chore / Maintenance

* **env:** initialize high-end dev-ecosystem via blueprint ([651eb8d](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/651eb8db6f39386af3968e096a1cee977da3cb9d))

## [0.4.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.3.6...v0.4.0) (2026-04-24)

### 🚀 Features

* **workflow:** implement conditional bank transfer and v0.3.0 security enhancements ([4ee9ec1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4ee9ec15e54615fcaeba4d8818d2a15152d52c43))
## [0.3.6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.3.5...v0.3.6) (2026-04-24)

### 🧹 Chore / Maintenance

* **release:** implement hook-based changelog generation and staging ([7e1479f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/7e1479f648967c4e84e808b0068f5dce61167acc))
* **release:** v0.3.6 ([b98ed1e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b98ed1e007786364d8faf2d85d0857fc8636a3be))
## [0.3.5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.3.4...v0.3.5) (2026-04-24)

### 🚀 Features

* **test:** check if changelog formatting works ([ccd8765](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ccd8765b2a47fce92630945d46312925e4df22ff))
* **test:** check if changelog formatting works 2 ([2501e81](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2501e8186d91dd13dbb1c741a3496c83884d7497))
* **test:** check if changelog formatting works 3 ([459d985](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/459d98545b692066e44fb349bb0d5e4ce7c31133))

### 🐛 Bug Fixes

* **changelog:** implement hybrid sync/async config and inline release-it types ([933ad05](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/933ad05225d86aed1752361c0235fe059eba2402))
* package-lock ([b823749](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/b8237491d7148f5a656f85b21725b7d7bb66b4a6))
* **release:** disable internal git-log and force conventional-changelog plugin ([655d4dd](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/655d4dd31f54225611ed1fe6d7b7c24d4ddc8227))
* **release:** inline changelog configuration ([5b791d8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5b791d8290f6d598bf5140ffb73fba37e1cb6004))

### 🧪 Tests

* ahhhhh ([458bca8](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/458bca834d7159baa9a63b1e853323e4e5567165))
* i hate it ([93b2f08](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/93b2f08aa1a4c23cf77647c5468ca284cd13250c))
* ich will nicht mehr ([68f118a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/68f118a25d2bc396a2db5bd4b86bb35aabd41000))
* other releaseit config ([4ece35e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4ece35e481c33e925557a679e67f7c681b3dfba3))
* please work ([d4756d5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d4756d59551f8b2658904bb18582d8685d719960))
* reset release-it ([ce72bca](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ce72bca8314b333779d6724135678fe404105685))
* test with script ([2923338](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2923338b32b63eb7a0726a709e49d0e4954dd637))
* will this work? ([635ddb5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/635ddb50d05cb16904dacc23ab1003067c691a95))

### 🧹 Chore / Maintenance

* **release:** v0.3.5 ([aa8757e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/aa8757e042b52ab93dc590b2cb9f9b771ab3e640))
## [0.3.4](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.3.3...v0.3.4) (2026-04-24)

### 🐛 Bug Fixes

* **changelog:** migrate to conventionalcommits preset and fix async config ([625ffe6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/625ffe6e25a4948806de75a9a0ee1f47a59b8505))

### 🧹 Chore / Maintenance

* **release:** v0.3.4 ([4058d71](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4058d716bf8ccf14d3ea3dafa09020e166cad137))
## [0.3.3](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.3.2...v0.3.3) (2026-04-23)

### 🐛 Bug Fixes

* **changelog:** rem old changelog ([79a258a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/79a258ac2a5a18c377860459718c6fc2ff2b8745))

### 🧹 Chore / Maintenance

* **changelog:** rebuild history and fix configuration immutability ([6c59f6f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6c59f6f07151023cef519a028d9baa0e6c60ecf7))
* **release:** v0.3.3 ([ee2643f](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ee2643fbe808b7d4c0e0845ae4ddf7b2b4a4705e))
## [0.3.2](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.3.1...v0.3.2) (2026-04-23)

### 🐛 Bug Fixes

* **changelog:** rem old changelog ([90a1f06](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/90a1f063022532a4e1b721b5c44aaeb2c9ad2324))

### 🧹 Chore / Maintenance

* **release:** v0.3.2 ([2e65282](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2e652821cb7f4616d03ba42d9badf74e1917479c))
## [0.3.1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.3.0...v0.3.1) (2026-04-23)

### 🧹 Chore / Maintenance

* **release:** v0.3.1 ([a86a970](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a86a9706da7dcbd98cd9bca3a029a37a2ef4b2e9))
## [0.3.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.2.0...v0.3.0) (2026-04-23)

### 🚀 Features

* **check:** upgrade to v0.2.0 and optimize check logic ([f0dbe43](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/f0dbe43a53405a2f802f230ea533088adb56d5f5))
* **mail:** implement v0.3.0 permit design with security features ([224271b](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/224271bda067fc5a36f25731c6d2f5d890704f84))
* **security:** implement server-side price matching and dual check views ([8295df9](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8295df9ddedf360bd6298924a1405cd43d5dcc27))
* **security:** implement strict server-side amount validation for paypal ([0429ed5](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/0429ed5a6dca00270f43f50781c65f775d6eb423))
* **view:** finalize dual-check templates in bem style ([c84ff08](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/c84ff0863c86fed7837b5b228763e0539cfd55b5))
## [0.2.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.1.0...v0.2.0) (2026-04-23)

### 🚀 Features

* **admin:** implement admin view and idempotent service logic ([5f3adf0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/5f3adf0b850b861092b028a6b80fceb789589318))
* **api:** implement secure paypal capture endpoint ([2de5be7](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/2de5be7e99f14094ef3f988186e0a76342c83b97))
* **arch:** unify anchor-system across all entry points ([4f54c21](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/4f54c214324f7363a2f5e6ef88a3f63b6dc1795b))
* **core:** implement permitservice for workflow orchestration ([d7a771a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d7a771a29de8d4148ec3923b4557df39d1b102fc))
* **infra:** finalize directory anchoring and tool configurations ([ed60d12](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ed60d129275a44fe2966aba7c6471580066d8aea))
* **logic:** implement multi-pricing and dual-view check system ([a1b7cb1](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a1b7cb124fe84ab3f9ff4adda19884a4b7a58a75))
* **mail:** finalize smtpmailservice and container registration ([d894a42](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/d894a429efda03cbb6eb0869f237f5df987fd32f))
* **mail:** implement template-based mail service ([3caadb6](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3caadb669f989afa19c9230a7ddc05883a5599e5))
* **payment:** implement secure server-side paypal capture ([46fa51c](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/46fa51c61705bd15a8f6660aed2d2232dcc763b4))
* **storage:** implement mysqlstorage and admin migration logic ([1bda1eb](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/1bda1eb88bd728323407b0fa9eb14083b1eda804))

### ⚙️ Refactoring

* **app:** transition to container-based entry point ([8d3d437](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8d3d43761c92f79cc7b77ce59a255d917e39bdff))
* **arch:** implement path anchoring for deployment flexibility ([fbe2777](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/fbe2777ee2dda9eabdc34d556e7e8052ec336210))
* **infra:** establish independent core composer configuration ([3a160ab](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/3a160abd26cfba11b07d3828954d70f58babd46f))
## [0.1.0](https://github.com/RaptorXilef/kga-einfahrgenehmigung/compare/v0.0.1...v0.1.0) (2026-04-23)

### 🚀 Features

* **core:** implement permit entity and json storage provider ([ae9e23a](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/ae9e23a7a88fec23d08187e118a69a38766681a8))
* **upload:** upload project-base ([6af8f8e](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/6af8f8e893e9f5632670b30b7eb229c71c49685d))

### 🐛 Bug Fixes

* resolve phpcs standards registration and enable fixer ([a960442](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/a960442e37f18ff070cd31e5abc5ddc582441e29))

### 🧹 Chore / Maintenance

* automate phpcs standards path registration ([8e8c5fa](https://github.com/RaptorXilef/kga-einfahrgenehmigung/commit/8e8c5fa2d63b0eda66203cfa8c5545826325e65a))
## 0.0.1 (2026-04-23)
