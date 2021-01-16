<?php
namespace UnitessB24;

class Bitrix24
{
    public  $INCOMING_REQUEST;
    public  $ENTITY_FIELDS;
    public  $CONFIG;

    public function __construct()
    {
        $this->CONFIG = Configuration::getInstance();
    }

    public function setHookRequest($request)
    {
        if ($this->CONFIG->CONFIGURATION['B24_AUTH']['HOOKS']['OUT'][$request['event']] === $request['auth']['application_token']) {
            $this->INCOMING_REQUEST = $request;
            return true;
        }else{
            return false;
        }
    }

    public function sendRequest($method, $params = [], $type = 'POST')
    {
        $url = $this->CONFIG->CONFIGURATION['B24_AUTH']['URL'].'/rest/'
            .$this->CONFIG->CONFIGURATION['B24_AUTH']['USER'].'/'
            .$this->CONFIG->CONFIGURATION['B24_AUTH']['HOOKS']['IN'].'/'.$method;

        $curlOptions = array(
            CURLOPT_RETURNTRANSFER => true
        );
        if ($type === 'POST') {
            $curlOptions[CURLOPT_TIMEOUT ] = 10;
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = http_build_query($params);
        } elseif (!empty($datacase)) {
            $url .= strpos($url, "?") > 0 ? "&" : "?";
            $url .= http_build_query($datacase);
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, $curlOptions);
        $response = curl_exec($curl);

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if($httpcode != 200){
            Logger::writeToLog(curl_error($curl), 'b24', 'curl');
            curl_close($curl);
            return false;
        }
        if (isset($response['error'])) {
            Logger::writeToLog($response, 'b24', 'query');
            curl_close($curl);
            return false;
        }
        curl_close($curl);

        return json_decode($response, true);
    }
}