<?php

namespace Modules\Installer\Services;

use Illuminate\Encryption\Encrypter;
use Log;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class EnvironmentService
{
    /**
     * Create the .env file
     * @return boolean
     */
    public function createEnvFile($driver, $host, $port, $name, $user, $pass)
    {
        $opts = [
            'APP_ENV' => 'dev',
            'APP_KEY' => $this->createAppKey(),
            'DB_CONN' => $driver,
            'DB_HOST' => $host,
            'DB_PORT' => $port,
            'DB_NAME' => $name,
            'DB_USER' => $user,
            'DB_PASS' => $pass,
        ];

        $opts = $this->getCacheDriver($opts);
        $opts = $this->getQueueDriver($opts);

        $this->writeEnvFile($opts);

        return true;
    }

    /**
     * Generate a fresh new APP_KEY
     * @return string
     */
    protected function createAppKey()
    {
        return base64_encode(Encrypter::generateKey(config('app.cipher')));
    }

    /**
     * Determine is APC is installed, if so, then use it as a cache driver
     * @param $opts
     * @return mixed
     */
    protected function getCacheDriver($opts)
    {
        if(\extension_loaded('apc')) {
            $opts['CACHE_DRIVER'] = 'apc';
        } else {
            $opts['CACHE_DRIVER'] = 'array';
        }

        return $opts;
    }

    /**
     * Setup a queue driver that's not the default "sync"
     * driver, if a database is being used
     * @param $opts
     * @return mixed
     */
    protected function getQueueDriver($opts)
    {
        # If we're setting up a database, then also setup
        # the default queue driver to use the database
        if ($opts['DB_CONN'] === 'mysql' || $opts['DB_CONN'] === 'postgres') {
            $opts['QUEUE_DRIVER'] = 'database';
        } else {
            $opts['QUEUE_DRIVER'] = 'sync';
        }

        return $opts;
    }

    /**
     * Get the template file name and write it out
     * @param $opts
     * @throws \Symfony\Component\HttpFoundation\File\Exception\FileException
     */
    protected function writeEnvFile($opts)
    {
        $env_file = \App::environmentFilePath();

        if(file_exists($env_file) && !is_writable($env_file)) {
            Log::error('Permissions on existing env.php is not writable');
            throw new FileException('Can\'t write to the env.php file! Check the permissions');
        }

        $fp = fopen($env_file, 'wb');
        if($fp === false) {
            throw new FileException('Couldn\'t write the env.php. (' . error_get_last() .')');
        }

        # render it within Blade and log the contents
        $env_contents = view('installer::stubs/env', $opts);
        Log::info($env_contents);

        fwrite($fp, $env_contents);
        fclose($fp);
    }
}
