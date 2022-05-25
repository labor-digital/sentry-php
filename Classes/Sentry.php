<?php
declare(strict_types=1);
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

namespace LaborDigital\Sentry;

use Sentry\Breadcrumb;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\EventId;
use Sentry\Integration\EnvironmentIntegration;
use Sentry\Integration\FrameContextifierIntegration;
use Sentry\Integration\RequestIntegration;
use Sentry\Integration\TransactionIntegration;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use function Sentry\init;

class Sentry
{
    /**
     * True as soon as sentry is initialized
     *
     * @var bool
     */
    protected static $isInitialized = false;
    
    /**
     * As long as this is null we use the automatic activity detection,
     * if this is either true or false we will use this property to determine activity
     *
     * @var bool|null
     */
    protected static $manualActiveState;
    
    /**
     * Holds the determined, dynamic activity state
     *
     * @var bool|null
     */
    protected static $dynamicActivityState;
    
    /**
     * Holds the sentry configuration
     *
     * @var array
     */
    protected static $sentryConfig = [];
    
    /**
     * Contains the hub instance provided, before the SentrySdk was initialized
     *
     * @var HubInterface|null
     */
    protected static $sentryHub;
    
    /**
     * Captures a generic message event and sends it to Sentry.
     *
     * @param   string          $message  The message
     * @param   Severity|null   $level    The severity level of the message
     * @param   EventHint|null  $hint     Object that can contain additional information about the event
     *
     * @return \Sentry\EventId|null
     */
    public static function captureMessage(
        string $message,
        ?Severity $level = null,
        ?EventHint $hint = null
    ): ?EventId
    {
        if (! static::isActivated()) {
            return null;
        }
        
        return SentrySdk::getCurrentHub()->captureMessage($message, $level, $hint);
    }
    
    /**
     * Captures an exception event and sends it to Sentry.
     *
     * @param   \Throwable      $exception  The exception
     * @param   EventHint|null  $hint       Object that can contain additional information about the event
     *
     * @return \Sentry\EventId|null
     */
    public static function captureException(\Throwable $exception, ?EventHint $hint = null): ?EventId
    {
        if (! static::isActivated()) {
            return null;
        }
        
        return SentrySdk::getCurrentHub()->captureException($exception, $hint);
    }
    
    /**
     * Captures a new event using the provided data.
     *
     * @param   array|Event     $payload  The data of the event being captured, or a already
     *                                    prepared event object
     * @param   EventHint|null  $hint     May contain additional information about the event
     *
     * @return \Sentry\EventId|null
     */
    public static function captureEvent($payload, ?EventHint $hint = null): ?EventId
    {
        if (! static::isActivated()) {
            return null;
        }
        
        $event = $payload;
        if (! $event instanceof Event) {
            $event = Event::createEvent();
            
            if (! is_array($payload)) {
                $payload = [
                    'data' => json_encode($payload, defined('JSON_THROW_ON_ERROR') ? JSON_THROW_ON_ERROR : 0),
                ];
            }
            
            $event->setExtra($payload);
        }
        
        return SentrySdk::getCurrentHub()->captureEvent($event, $hint);
    }
    
    /**
     * Logs the most recent error (obtained with {@link error_get_last}).
     *
     * @param   EventHint|null  $hint  Object that can contain additional information about the event
     */
    public static function captureLastError(?EventHint $hint = null): ?EventId
    {
        if (! static::isActivated()) {
            return null;
        }
        
        return SentrySdk::getCurrentHub()->captureLastError($hint);
    }
    
    /**
     * Records a new breadcrumb which will be attached to future events. They
     * will be added to subsequent events to provide more context on user's
     * actions prior to an error or crash.
     *
     * @param   Breadcrumb  $breadcrumb  The breadcrumb to record
     *
     * @return bool Whether the breadcrumb was actually added to the current scope
     */
    public static function addBreadcrumb(Breadcrumb $breadcrumb): bool
    {
        if (! static::isActivated()) {
            return false;
        }
        
        return SentrySdk::getCurrentHub()->addBreadcrumb($breadcrumb);
    }
    
    /**
     * Calls the given callback passing to it the current scope so that any
     * operation can be run within its context.
     *
     * @param   callable  $callback  The callback to be executed
     */
    public static function configureScope(callable $callback): void
    {
        if (! static::isActivated()) {
            return;
        }
        
        SentrySdk::getCurrentHub()->configureScope($callback);
    }
    
    /**
     * Creates a new scope with and executes the given operation within. The scope
     * is automatically removed once the operation finishes or throws.
     *
     * @param   callable  $callback  The callback to be executed
     *
     * @return mixed|void
     */
    public static function withScope(callable $callback)
    {
        if (! static::isActivated()) {
            return $callback(new Scope());
        }
        
        return SentrySdk::getCurrentHub()->withScope($callback);
    }
    
    /**
     * We use either SENTRY_ACTIVE > 0 or PROJECT_ENV === "prod" to automatically detect
     * if sentry logging should be enabled. If you want to force the activity state you can always use this method to
     * set the activity manually.
     *
     * Keep in mind, that even if you set this to true but your configuration is incomplete nothing will be logged!
     *
     * @param   bool  $state
     */
    public static function manualActivation(bool $state = true): void
    {
        static::$manualActiveState = $state;
    }
    
    /**
     * By default, the class will try to load the basic configuration using the config.json file
     * that was generated by the sentry-release build step. But you may use this method either to
     * provide additional configuration for sentry or to provide the whole configuration without using the build step.
     *
     * @param   array  $config
     */
    public static function setSentryConfig(array $config): void
    {
        static::$sentryConfig = $config;
    }
    
    /**
     * Registers a global error and exception handler that is used to catch all not-cached exceptions
     */
    public static function registerGlobalHandler(): void
    {
        if (! static::isActivated()) {
            return;
        }
        
        set_error_handler(static function ($level, $message) {
            if ($level) {
                static::captureException(new \Exception($message, $level));
            }
        });
        
        set_exception_handler(static function ($e) {
            static::captureException($e);
        });
    }
    
    /**
     * Restores the global error and exception handlers
     */
    public static function restoreGlobalHandler(): void
    {
        if (! static::isActivated()) {
            return;
        }
        
        restore_error_handler();
        restore_exception_handler();
    }
    
    /**
     * Checks if the sentry logging is activated, by validating if the class was correctly initialized
     *
     * @return bool
     */
    public static function isActivated(): bool
    {
        if (static::$manualActiveState === false) {
            return false;
        }
        
        if (static::$dynamicActivityState === null) {
            static::$dynamicActivityState
                = getenv('PROJECT_ENV') === 'prod' || ! empty(getenv('SENTRY_ACTIVE'));
        }
        
        if (! static::$manualActiveState && ! static::$dynamicActivityState) {
            return false;
        }
        
        if (static::$isInitialized) {
            return true;
        }
        
        static::initialize();
        
        return static::$isInitialized;
    }
    
    /**
     * Sets the sentry hub, used to transport the messages
     *
     * @param   \Sentry\State\HubInterface  $hub
     *
     * @return \Sentry\State\HubInterface
     */
    public static function setHub(HubInterface $hub): HubInterface
    {
        if (! static::isActivated()) {
            static::$sentryHub = $hub;
            
            return $hub;
        }
        
        return SentrySdk::setCurrentHub($hub);
    }
    
    /**
     * Returns the sentry hub, used to transport messages or null if logging is not activated
     *
     * @return \Sentry\State\HubInterface|null
     */
    public static function getHub(): ?HubInterface
    {
        if (! static::isActivated()) {
            return static::$sentryHub;
        }
        
        return SentrySdk::getCurrentHub();
    }
    
    /**
     * Internal helper that is used to initialize the sentry client based on the configuration
     * we got from setSentryConfig() and the possible auto config file at config.json
     *
     * @return void
     * @throws \JsonException
     */
    protected static function initialize(): void
    {
        if (static::$isInitialized) {
            return;
        }
        
        // Try to load the config file
        $autoConfig = [];
        $configFilePath = __DIR__ . DIRECTORY_SEPARATOR . 'config.json';
        if (is_readable($configFilePath)) {
            $autoConfig = file_get_contents($configFilePath);
            
            if (is_string($autoConfig)) {
                $autoConfig = json_decode($autoConfig, true, 512,
                    defined('JSON_THROW_ON_ERROR') ? JSON_THROW_ON_ERROR : 0);
            }
            
            if (! is_array($autoConfig)) {
                $autoConfig = [];
            }
        }
        
        // Merge with possibly given config
        $config = static::$sentryConfig;
        if (isset($autoConfig['sdk']) && is_array($autoConfig['sdk'])) {
            foreach ($autoConfig['sdk'] as $k => $v) {
                if (! isset($config[$k])) {
                    $config[$k] = $v;
                }
            }
        }
        
        // Check if we got a dsn
        if (! isset($config['dsn'])) {
            return;
        }
        
        // Disable default integrations
        // Otherwise the global error handler will automatically be added to sentry
        if (! isset($config['default_integrations'])) {
            $config['default_integrations'] = false;
        }
        
        if (! isset($config['integrations'])) {
            $config['integrations'] = [
                new RequestIntegration(),
                new TransactionIntegration(),
                new FrameContextifierIntegration(),
                new EnvironmentIntegration(),
            ];
        }
        
        // Initialize sentry
        init($config);
        static::$isInitialized = true;
        
        if (static::$sentryHub) {
            SentrySdk::setCurrentHub(static::$sentryHub);
            static::$sentryHub = null;
        }
    }
}