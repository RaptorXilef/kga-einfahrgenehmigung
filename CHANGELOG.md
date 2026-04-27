# Changelog



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
