# LABOR - Sentry.io wrapper

**Warning! This is the legacy version for PHP 7.0!**

This package is a wrapper for the sentry.io.

It will also provide support for our [sentry-release pipeline](https://github.com/labor-digital/bitbucket-pipeline-images#sentry-release) script to
automatically. It will inherit all required information, like the dsn or the release from a shared config file.

## Requirements

- PHP 7.0
- Composer v1

## Installation

Install this package using composer:

```
composer require labor-digital/sentry-php
```

## Usage

You can use this library, like you would use the sentry PHP sdk, however it will check if it is actually needed by your environment. It uses either the
SENTRY_ACTIVE > 0 or PROJECT_ENV === "prod" environment variables to detect if sentry logging should be enabled. If you want to force the state, you can always
use the `Sentry::manualActivation()` method to set it manually.

Before you start your logging, you need to provide the configuration through `Sentry::setSentryConfig()`. Additionally, the sentry configuration can be provided
as "config.json" inside the package's vendor directory. (e.g /project/vendor/labor-digital/sentry-php/config.json)
Note: You need to wrap the config in the "sdk" key in the JSON object. Note2: Keep in mind, this is the internal bridge used by
the [sentry-release pipeline](https://github.com/labor-digital/bitbucket-pipeline-images#sentry-release).

### Available methods

- Sentry::captureMessage() | Captures a generic message and sends it to Sentry.
- Sentry::captureException() | Captures an exception event and sends it to Sentry.
- Sentry::captureLastError() | Logs the most recent error (obtained with error_get_last).
- Sentry::manualActivation() | Manually activates the sentry logging, if it was not auto-enabled through the environment variables
- Sentry::setSentryConfig() | Either for manual configuration, or to enhance the auto-config provided by the pipeline integration
- Sentry::registerGlobalHandler() | Registers a global error and exception handler that is used to catch all not-cached exceptions
- Sentry::restoreGlobalHandler() | Restores the global error and exception handlers
- Sentry::isActivated() | Checks if the sentry logging is activated, by validating if the class was correctly initialized

### Global errors

The default PHP implementation of sentry will automatically listen to global exceptions and errors. This package **disables** the automatic global exception
handling. You can activate it using the `Sentry::registerGlobalHandler()` method.

### Pipeline integration

As mentioned multiple times above, the wrapper integrates seamlessly with
our [sentry-release pipeline](https://github.com/labor-digital/bitbucket-pipeline-images#sentry-release). This means, the provided composer plugin will
automatically detect the `sentry-configuration-file.json` in your build artifacts and create a `config.json` for the wrapper to read at runtime. The plugin can
be configured through environment variables:

- BITBUCKET_CLONE_DIR (This is normally set by bitbucket, but you can adjust it for other pipelines as well (gitlab: $CI_PROJECT_DIR | github:
  $GITHUB_WORKSPACE))
- SENTRY_CONFIG_FILE_LOCATION (_DEFAULT: "${BITBUCKET_CLONE_DIR}/sentry-configuration-file.json"_, holds the compiled information for other build steps to use)

## Postcardware

You're free to use this package, but if it makes it to your production environment we highly appreciate you sending us a postcard from your hometown, mentioning
which of our package(s) you are using.

Our address is: LABOR.digital - Fischtorplatz 21 - 55116 Mainz, Germany

We publish all received postcards on our [company website](https://labor.digital).
