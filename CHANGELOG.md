# Changelog

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

### [2.0.1](https://github.com/labor-digital/sentry-php/compare/v2.0.0...v2.0.1) (2022-05-03)


### Bug Fixes

* allow usage with composer v1 ([109b998](https://github.com/labor-digital/sentry-php/commit/109b998d220e915cc9086b0909924b52ffdbd4af))

## 2.0.0 (2022-05-03)

### ⚠ BREAKING CHANGES

* updates dependencies to new major versions

### Features

* add automatic releasing ([7c759ad](https://github.com/labor-digital/sentry-php/commit/7c759ad406f20c7e97d895452f4b2329046a123a))
* add support for new, shared config file format ([602dc49](https://github.com/labor-digital/sentry-php/commit/602dc49b304b8af0daa3d7e5c78d185cf94ad5c7))
* disable default integrations by default + update
  dependencies ([2f28a37](https://github.com/labor-digital/sentry-php/commit/2f28a379c073f3230be5e9b44ba746d9c413b0fd))
* disable global handler registration when sentry is not
  enabled ([4d3e406](https://github.com/labor-digital/sentry-php/commit/4d3e40687603bdb054484969ba46f0294af51462))
* initial commit ([629200b](https://github.com/labor-digital/sentry-php/commit/629200b07417248b99adb1739ab21b5fad678f26))
* prepare for releasing as open-source ([a177850](https://github.com/labor-digital/sentry-php/commit/a1778503e5e6da514ef283dd3d6ec011550e37d0))

### Bug Fixes

* fix the invalid configuration array that broke the error
  handling  ([be0e3bd](https://github.com/labor-digital/sentry-php/commit/be0e3bdf5fec75bf944904a476698ba6a04897bc))
* make sure that the request and transaction default integrations are
  enabled ([2d7f8ee](https://github.com/labor-digital/sentry-php/commit/2d7f8ee7f502c5a3374ba3388bc7e68353e6c8d1))
* remove vendor directory from git ([894870e](https://github.com/labor-digital/sentry-php/commit/894870e98746a67be0c786a7598c586b065c1eb4))

## 1.3.3 (2020-02-10)

### Bug Fixes

* remove vendor directory from git (894870e)

## 1.3.2 (2020-02-05)

### Bug Fixes

* fix the unvalid configuration array that broke the error handling  (be0e3bd)

## 1.3.1 (2020-01-10)

### Bug Fixes

* make sure that the request and transaction default integrations are enabled (2d7f8ee)

# 1.3.0 (2020-01-10)

### Features

* disable default integrations by default + update dependencies (2f28a37)

# 1.2.0 (2019-11-16)

### Features

* disable global handler registration when sentry is not enabled (4d3e406)

# 1.1.0 (2019-11-16)

### Features

* add automatic releasing (7c759ad)
* add support for new, shared config file format (602dc49)
* initial commit (629200b)
