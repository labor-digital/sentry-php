<?php
declare(strict_types=1);


namespace LaborDigital\Sentry\Composer;


use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Util\Filesystem;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    
    /**
     * @var Composer
     */
    protected $composer;
    
    /**
     * @var IOInterface
     */
    protected $io;
    
    public static function getSubscribedEvents(): array
    {
        return [
            'post-autoload-dump' => ['run', -500],
        ];
    }
    
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }
    
    public function deactivate(Composer $composer, IOInterface $io)
    {
    }
    
    public function uninstall(Composer $composer, IOInterface $io)
    {
    }
    
    public function run()
    {
        // Check if we already have a sentry config file
        $configTargetPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.json';
        if (file_exists($configTargetPath) && is_readable($configTargetPath)) {
            $this->io->write('<info>Sentry.io config file is already in place!');
            
            return;
        }
        
        // Try to find the config file
        $sentryConfigFilePath = getenv('SENTRY_CONFIG_FILE_LOCATION');
        if (empty($sentryConfigFilePath)) {
            if (empty(getenv('BITBUCKET_CLONE_DIR'))) {
                $this->io->write(
                    '<warning>Did not activate Sentry.io config file, as neither SENTRY_CONFIG_FILE_LOCATION ' .
                    'nor BITBUCKET_CLONE_DIR are set as environment variables!</warning>');
                
                return;
            }
            $sentryConfigFilePath = rtrim(getenv('BITBUCKET_CLONE_DIR'), "\\/") . DIRECTORY_SEPARATOR . 'sentry-configuration-file.json';
        }
        if (! file_exists($sentryConfigFilePath)) {
            $this->io->write(
                '<error>Failed to get contents of Sentry.io config file, as it does not exist at: ' .
                $sentryConfigFilePath . '</error>');
            
            return;
        }
        
        // Copy the config file into the local directory
        $fs = new Filesystem();
        $fs->copyThenRemove($sentryConfigFilePath, $configTargetPath);
        $this->io->write(
            '<info>Moved the Sentry.io config file into the composer source directory at: ' .
            $configTargetPath . '</info>');
    }
    
    
}
