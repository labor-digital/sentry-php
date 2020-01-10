# Labor Sentry.io wrapper
This package is a wrapper for the sentry.io.

It will also provide support for our sentry-release pipeline script to automatically
inherit all required information, like the dsn or the release from a shared config file.

## Global errors
The default PHP implementation of sentry will automatically listen to global exceptions and errors.
This package **disables** the automatic global exception handling. You can activate it using the Sentry::registerGlobalHandler() method.