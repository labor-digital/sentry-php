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

use Raven_Client;

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
     * @var \Raven_Client
     */
    protected static $client;
    
    /**
     * Captures a generic message event and sends it to Sentry.
     *
     * @param   string      $message  The message (primary description) for the event.
     * @param   array       $params   params to use when formatting the message.
     * @param   array       $data     Additional attributes to pass with this event (see Sentry docs).
     * @param   bool|array  $stack
     * @param   mixed       $vars
     *
     * @return string|null
     */
    public static function captureMessage(
        string $message,
        $params = [],
        $data = [],
        $stack = false,
        $vars = null
    )
    {
        if (! static::isActivated()) {
            return null;
        }
        
        return static::$client->captureMessage($message, $params, $data, $stack, $vars);
    }
    
    /**
     * Captures an exception event and sends it to Sentry.
     *
     * @param   \Throwable|\Exception  $exception  The Throwable/Exception object.
     * @param   array                  $data       Additional attributes to pass with this event (see Sentry docs).
     * @param   mixed                  $logger
     * @param   mixed                  $vars
     *
     * @return string|null
     */
    public static function captureException($exception, $data = null, $logger = null, $vars = null)
    {
        if (! static::isActivated()) {
            return null;
        }
        
        return static::$client->captureException($exception, $data, $logger, $vars);
    }
    
    /**
     * Logs the most recent error (obtained with {@link error_get_last}).
     *
     * @return string|null
     */
    public static function captureLastError()
    {
        if (! static::isActivated()) {
            return null;
        }
        
        return static::$client->captureLastError();
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
    public static function manualActivation(bool $state = true)
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
    public static function setSentryConfig(array $config)
    {
        static::$sentryConfig = $config;
    }
    
    /**
     * Registers a global error and exception handler that is used to catch all not-cached exceptions
     */
    public static function registerGlobalHandler()
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
    public static function restoreGlobalHandler()
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
     * Internal helper that is used to initialize the sentry client based on the configuration
     * we got from setSentryConfig() and the possible auto config file at config.json
     *
     * @return void
     * @throws \JsonException
     */
    protected static function initialize()
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
        
        // The old ssl certificate provided by sentry is no longer valid, so we must disable the validation here
        $config['verify_ssl'] = false;
        
        static::$client = new Raven_Client($config);
        static::$isInitialized = true;
    }
}