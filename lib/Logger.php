<?php
namespace UnitessB24;

class Logger
{
    const FILE_PATH = '/var/www/html/Unitess-B24/log.log';

    public static function writeToLog($message, $type = '', $operation = '')
    {
        $date = new \DateTime('now', new \DateTimeZone("Europe/Moscow"));
        $str  = "-------------------------------\n";
        $str .= $date->format('d.m.Y H:i:s');
        $str .= "\nType: " . print_r($type, true);
        $str .= "\nOperation: " . print_r($operation, true) ."\n";
        $str .= print_r($message,true);
        if (!is_array($message) && !is_object($message))
            $str .= "\n";
        $str .= "-------------------------------\n";

        file_put_contents(self::FILE_PATH, print_r($str, true), FILE_APPEND);
    }

    public static function writeToScreen($data)
    {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }
}