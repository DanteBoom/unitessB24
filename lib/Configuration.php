<?php
namespace UnitessB24;

class Configuration
{
    private static $instance = null;

    private $FILE_PATH = '/var/www/html/Unitess-B24/config.json';

    public $CONFIGURATION;

    private function __construct()
    {
        self::readConfig();
    }

    public static function getInstance()
    {
        if(is_null(self::$instance)){
            self::$instance = new Configuration();
            return self::$instance;
        } else{
            return self::$instance;
        }
    }

    private function readConfig()
    {
        $this->CONFIGURATION = json_decode(file_get_contents($this->FILE_PATH), true);
    }

    private function writeConfig()
    {
        file_put_contents($this->FILE_PATH, json_encode($this->CONFIGURATION, JSON_PRETTY_PRINT));
    }

    public function saveConfig()
    {
        self::writeConfig();
    }
}