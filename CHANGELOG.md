# Changelog



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
