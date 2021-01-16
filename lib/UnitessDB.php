<?php
namespace UnitessB24;

class UnitessDB
{
    private $DB_CONNECTION;
    private $QUERY_RESULT;
    private $QUERY_ERROR;

    public  $CONFIG;
    public  $ENTITIES = [
        'organization' => false
    ];

    public function __construct()
    {
        $this->CONFIG = Configuration::getInstance();
    }

    public function connect()
    {
        try {
            $this->DB_CONNECTION = new \PDO(
                $this->CONFIG->CONFIGURATION['DB_AUTH']['TYPE'].':dbname='
                .$this->CONFIG->CONFIGURATION['DB_AUTH']['HOST'].':'
                .$this->CONFIG->CONFIGURATION['DB_AUTH']['NAME'] . ';charset=utf8;',
                $this->CONFIG->CONFIGURATION['DB_AUTH']['USER'], $this->CONFIG->CONFIGURATION['DB_AUTH']['PASSWORD'],
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            return true;
        } catch (\PDOException $e) {
            Logger::writeToLog($e->getMessage(), 'unitess', 'connection');
            return false;
        }
    }

    public function runQuery($sql)
    {
        try {
            $query = $this->DB_CONNECTION->query($sql);
            $this->QUERY_RESULT = $query->fetchAll(\PDO::FETCH_ASSOC);
            return self::getLastResult();
        } catch (\PDOException $e) {
            $this->QUERY_ERROR = $e->getMessage();
            Logger::writeToLog($e->getMessage(), 'unitess', 'query');
            return self::getLastError();
        }
    }

    public function getNewId($generatorName)
    {
        return self::runQuery('SELECT GEN_ID(' . $generatorName . ', 1) FROM RDB$DATABASE');
    }

    public function getConnectionObject()
    {
        return $this->DB_CONNECTION;
    }

    public function getLastResult()
    {
        return $this->QUERY_RESULT;
    }

    public function getLastError()
    {
        return $this->QUERY_ERROR;
    }

}