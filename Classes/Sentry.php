<?php
/**
 * Copyright 2019 LABOR.digital
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Last modified: 2019.11.15 at 20:51
 */

namespace Labor\Sentry;

use Sentry\Breadcrumb;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\Scope;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use function Sentry\init;

class Sentry {
	/**
	 * True as soon as sentry is initialized
	 * @var bool
	 */
	protected static $isInitialized = FALSE;
	
	/**
	 * As long as this is null we use the automatic activity detection,
	 * if this is either true or false we will use this property to determine activity
	 * @var bool|null
	 */
	protected static $manualActiveState;
	
	/**
	 * Holds the determined, dynamic activity state
	 * @var bool|null
	 */
	protected static $dynamicActivityState;
	
	/**
	 * Holds the sentry configuration
	 * @var array
	 */
	protected static $sentryConfig = [];
	
	/**
	 * Captures a message event and sends it to Sentry.
	 *
	 * @param string   $message The message
	 * @param Severity $level   The severity level of the message
	 *
	 * @return string|null
	 */
	public static function captureMessage(string $message, ?Severity $level = NULL): ?string {
		if (!static::isActivated()) return NULL;
		return SentrySdk::getCurrentHub()->captureMessage($message, $level);
	}
	
	/**
	 * Captures an exception event and sends it to Sentry.
	 *
	 * @param \Throwable $exception The exception
	 *
	 * @return string|null
	 */
	public static function captureException(\Throwable $exception): ?string {
		if (!static::isActivated()) return NULL;
		return SentrySdk::getCurrentHub()->captureException($exception);
	}
	
	/**
	 * Captures a new event using the provided data.
	 *
	 * @param array $payload The data of the event being captured
	 *
	 * @return string|null
	 */
	public static function captureEvent(array $payload): ?string {
		if (!static::isActivated()) return NULL;
		return SentrySdk::getCurrentHub()->captureEvent($payload);
	}
	
	/**
	 * Logs the most recent error (obtained with {@link error_get_last}).
	 */
	public static function captureLastError(): ?string {
		if (!static::isActivated()) return NULL;
		return SentrySdk::getCurrentHub()->captureLastError();
	}
	
	/**
	 * Records a new breadcrumb which will be attached to future events. They
	 * will be added to subsequent events to provide more context on user's
	 * actions prior to an error or crash.
	 *
	 * @param Breadcrumb $breadcrumb The breadcrumb to record
	 */
	public static function addBreadcrumb(Breadcrumb $breadcrumb): void {
		if (!static::isActivated()) return;
		SentrySdk::getCurrentHub()->addBreadcrumb($breadcrumb);
	}
	
	/**
	 * Calls the given callback passing to it the current scope so that any
	 * operation can be run within its context.
	 *
	 * @param callable $callback The callback to be executed
	 */
	public static function configureScope(callable $callback): void {
		if (!static::isActivated()) return;
		SentrySdk::getCurrentHub()->configureScope($callback);
	}
	
	/**
	 * Creates a new scope with and executes the given operation within. The scope
	 * is automatically removed once the operation finishes or throws.
	 *
	 * @param callable $callback The callback to be executed
	 */
	public static function withScope(callable $callback): void {
		if (!static::isActivated()) {
			// Run with dummy scope
			$callback(new Scope());
			return;
		}
		SentrySdk::getCurrentHub()->withScope($callback);
	}
	
	/**
	 * We use either SENTRY_ACTIVE > 0 or PROJECT_ENV === "prod" to automatically detect
	 * if sentry logging should be enabled. If you want to force the activity state you can always use this method to
	 * set the activity manually.
	 *
	 * Keep in mind, that even if you set this to true but your configuration is incomplete nothing will be logged!
	 *
	 * @param bool $state
	 */
	public static function manualActivation(bool $state): void {
		static::$manualActiveState = $state;
	}
	
	/**
	 * By default the class will try to load the basic configuration using the config.json file
	 * that was generated by the sentry-release build step. But you may use this method either to
	 * provide additional configuration for sentry or to provide the whole configuration without using the build step.
	 *
	 * @param array $config
	 */
	public static function setSentryConfig(array $config): void {
		static::$sentryConfig = $config;
	}
	
	/**
	 * Registers a global error and exception handler that is used to catch all not-cached exceptions
	 */
	public static function registerGlobalHandler() {
		set_error_handler(function ($level, $message) {
			if ($level) static::captureException(new \Exception($message, $level));
		});
		set_exception_handler(function ($e) {
			static::captureException($e);
		});
	}
	
	/**
	 * Restores the global error and exception handlers
	 */
	public static function restoreGlobalHandler() {
		restore_error_handler();
		restore_exception_handler();
	}
	
	/**
	 * Checks if the sentry logging is activated, by validating if the class was correctly initialized
	 * @return bool
	 */
	public static function isActivated(): bool {
		// Check if the class is activated
		if (static::$manualActiveState === FALSE) return FALSE;
		if (static::$dynamicActivityState === NULL) {
			if (getenv("PROJECT_ENV") === "prod") static::$dynamicActivityState = TRUE;
			else if (!empty(getenv("SENTRY_ACTIVE"))) static::$dynamicActivityState = TRUE;
			else static::$dynamicActivityState = FALSE;
		}
		if (static::$dynamicActivityState === FALSE && static::$manualActiveState !== TRUE) return FALSE;
		
		// Check if the class is initialized
		if (static::$isInitialized) return TRUE;
		static::initialize();
		if (!static::$isInitialized) return FALSE;
		return TRUE;
	}
	
	/**
	 * Internal helper that is used to initialize the sentry client based on the configuration
	 * we got from setSentryConfig() and the possible auto config file at config.json
	 * @return bool
	 */
	protected static function initialize() {
		if (static::$isInitialized) return TRUE;
		
		// Try to load the config file
		$autoConfig = [];
		$configFilePath = __DIR__ . DIRECTORY_SEPARATOR . "config.json";
		if (is_readable($configFilePath)) {
			$autoConfig = file_get_contents($configFilePath);
			if (is_string($autoConfig)) $autoConfig = json_decode($autoConfig, TRUE);
			if (!is_array($autoConfig)) $autoConfig = [];
		}
		
		// Merge with possibly given config
		$config = static::$sentryConfig;
		if (is_array($autoConfig["sdk"]))
			foreach ($autoConfig["sdk"] as $k => $v)
				if (!isset($config[$k])) $config[$k] = $v;
		
		// Check if we got a dsn
		if (!isset($config["dsn"])) return FALSE;
		
		// Initialize sentry
		init($config);
		static::$isInitialized = TRUE;
		return TRUE;
	}
}