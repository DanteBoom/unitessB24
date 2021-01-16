<?php

include 'lib/Logger.php';
include 'lib/Configuration.php';
include 'lib/Bitrix24.php';
include 'lib/UnitessDB.php';

use UnitessB24\Logger;
use UnitessB24\Bitrix24;
use UnitessB24\UnitessDB;

if (isset($_REQUEST['auth']['application_token'])) {
    $b24 = new Bitrix24();
    if (!$b24->setHookRequest($_REQUEST)){
        exit();
    }

    if ($b24->INCOMING_REQUEST['event'] === 'ONCRMDEALUPDATE' && $b24->INCOMING_REQUEST['data']['FIELDS']['ID']) {
        if ($deal = $b24->sendRequest('crm.deal.get', ['id' => $b24->INCOMING_REQUEST['data']['FIELDS']['ID']])){
            $b24->ENTITY_FIELDS = $deal['result'];

            if (in_array($b24->ENTITY_FIELDS['STAGE_ID'], $b24->CONFIG->CONFIGURATION['B24_STATUSES'], true)){

                if (isset($b24->ENTITY_FIELDS[$b24->CONFIG->CONFIGURATION['B24_FIELDS']['DEAL']['ID заявки в UNITESS']])
                    && !empty($b24->ENTITY_FIELDS[$b24->CONFIG->CONFIGURATION['B24_FIELDS']['DEAL']['ID заявки в UNITESS']]))
                {
                    $unitess = new UnitessDB();
                    if($unitess->connect()) {
                        $updateAppeal = [];
                        $updateSample = [];
                        foreach ($b24->ENTITY_FIELDS as $key => $value) {
                            if (in_array($key, $b24->CONFIG->CONFIGURATION['DB_BITRIX_FIELDS'], true)
                                && $key !== $b24->CONFIG->CONFIGURATION['DB_BITRIX_FIELDS']['ID_REQ']) {
                                if (array_search($key, $b24->CONFIG->CONFIGURATION['DB_BITRIX_FIELDS']) === 'REQ_AGRM') {
                                    $updateAppeal['REQ_AGRM'] = $value;
                                } elseif (array_search($key, $b24->CONFIG->CONFIGURATION['DB_BITRIX_FIELDS']) === 'DUT_DESCRIPTION'
                                    || array_search($key, $b24->CONFIG->CONFIGURATION['DB_BITRIX_FIELDS']) === 'DUT_SERNUM'
                                    || array_search($key, $b24->CONFIG->CONFIGURATION['DB_BITRIX_FIELDS']) === 'DUT_NOTES'
                                    || array_search($key, $b24->CONFIG->CONFIGURATION['DB_BITRIX_FIELDS']) === 'DUT_KIT') {
                                    $updateSample[array_search($key, $b24->CONFIG->CONFIGURATION['DB_BITRIX_FIELDS'])] = $value;
                                }
                            }
                        }

                        if (!empty($updateAppeal)) {
                            $query = "UPDATE I_REQUEST SET";
                            $trigger = false;
                            foreach ($updateAppeal as $key => $value) {
                                if ($trigger){
                                    $query .= ",";
                                }else{
                                    $trigger = true;
                                }
                                $query .= " " . $key . "='" . $value . "'";
                            }
                            $query .= " WHERE ID_REQ=" . $b24->ENTITY_FIELDS[$b24->CONFIG->CONFIGURATION['B24_FIELDS']['DEAL']['ID заявки в UNITESS']] . ";";
                            $unitess->runQuery($query);
                        }
                        if (!empty($updateSample)) {
                            $query = "UPDATE I_DUT SET";
                            $trigger = false;
                            foreach ($updateSample as $key => $value) {
                                if ($trigger){
                                    $query .= ",";
                                }else{
                                    $trigger = true;
                                }
                                $query .= " " . $key . "='" . $value . "'";
                            }
                            $query .= " WHERE ID_REQ=" . $b24->ENTITY_FIELDS[$b24->CONFIG->CONFIGURATION['B24_FIELDS']['DEAL']['ID заявки в UNITESS']] . ";";
                            $unitess->runQuery($query);
                        }
                    }
                }else{
                    $unitess = new UnitessDB();
                    $unitess->connect();
                    $db = $unitess->getConnectionObject();

                    //region организация
                    if (isset($b24->ENTITY_FIELDS['COMPANY_ID']) && !empty($b24->ENTITY_FIELDS['COMPANY_ID'])){
                        $company = $b24->sendRequest('crm.company.get', ['id' => $b24->ENTITY_FIELDS['COMPANY_ID']]);

                        if (isset($company['result'][$b24->CONFIG->CONFIGURATION['B24_FIELDS']['COMPANY']['Unitess ID']])
                            && !empty($company['result'][$b24->CONFIG->CONFIGURATION['B24_FIELDS']['COMPANY']['Unitess ID']]))
                        {
                            $sql = 'SELECT id_org FROM S_ORGANIZATION WHERE id_org='.$company['result'][$b24->CONFIG->CONFIGURATION['B24_FIELDS']['COMPANY']['Unitess ID']].';';
                            $unitess->runQuery($sql);

                            if (empty($unitess->getLastResult())){
                                $newUnitessIdOrganization = $unitess->getNewId('GEN_ORG')[0]['GEN_ID'];

                                $requisiteCompany = $b24->sendRequest('crm.requisite.list', [
                                    'select' => ['ID', 'RQ_COMPANY_NAME'],
                                    'filter' => ['ENTITY_TYPE_ID' => '4', 'ENTITY_ID' => $company['result']['ID']]
                                ]);
                                $requisiteCompanyAddress = $b24->sendRequest('crm.address.list', [
                                    'select' => ['*'],
                                    'filter' => ['ENTITY_TYPE_ID' => '8', 'ENTITY_ID' => $requisiteCompany['result'][0]['ID']]
                                ]);
                                $sqlFields = 'ID_ORG, ORG_NAME_SHORT, ORG_NAME_LONG, ORG_ADDRESS';
                                $sqlValues = ':id, :name_short, :name, :address';

                                if ($company['result']['HAS_PHONE'] === 'Y'){
                                    $sqlFields .= ', ORG_PHONES';
                                    $sqlValues .= ', :phone';
                                }
                                if ($company['result']['HAS_EMAIL'] === 'Y'){
                                    $sqlFields .= ', ORG_NOTES';
                                    $sqlValues .= ', :email';
                                }

                                $sql = mb_convert_encoding('INSERT INTO S_ORGANIZATION (' . $sqlFields . ') VALUES (' . $sqlValues . ');', 'CP1251');

                                try {
                                    $query = $db->prepare($sql);
                                    $query->bindValue(':id', $newUnitessIdOrganization);
                                    $query->bindValue(':name_short', mb_convert_encoding($requisiteCompany['result'][0]['RQ_COMPANY_NAME'], 'CP1251'));
                                    $query->bindValue(':name', mb_convert_encoding($requisiteCompany['result'][0]['RQ_COMPANY_NAME'], 'CP1251'));
                                    $query->bindValue(':address', mb_convert_encoding($requisiteCompanyAddress['result'][0]['COUNTRY'] . ', '
                                        . $requisiteCompanyAddress['result'][0]['PROVINCE'] . ', '
                                        . $requisiteCompanyAddress['result'][0]['REGION'] . ', '
                                        . $requisiteCompanyAddress['result'][0]['CITY'] . ', '
                                        . $requisiteCompanyAddress['result'][0]['ADDRESS_1'] . ', '
                                        . $requisiteCompanyAddress['result'][0]['ADDRESS_2'] . ', '
                                        . $requisiteCompanyAddress['result'][0]['POSTAL_CODE'], 'CP1251'));

                                    if ($company['result']['HAS_PHONE'] === 'Y'){
                                        $query->bindValue(':phone', $company['result']['PHONE'][0]['VALUE']);
                                    }
                                    if ($company['result']['HAS_EMAIL'] === 'Y'){
                                        $query->bindValue(':email', mb_convert_encoding($company['result']['EMAIL'][0]['VALUE'], 'CP1251'));
                                    }

                                    $query->execute();

                                    $b24->sendRequest(
                                        'crm.company.update',
                                        [
                                            'id' => $company['result']['ID'],
                                            'fields' => [$b24->CONFIG->CONFIGURATION['B24_FIELDS']['COMPANY']['Unitess ID'] => $newUnitessIdOrganization]
                                        ]
                                    );
                                    $unitess->ENTITIES['organization'] = $newUnitessIdOrganization;

                                } catch (\PDOException $e) {
                                    Logger::writeToLog($db->errorInfo());
                                }
                            }else{
                                $unitess->ENTITIES['organization'] = $company['result'][$b24->CONFIG->CONFIGURATION['B24_FIELDS']['COMPANY']['Unitess ID']];
                            }
                        }else{
                            $newUnitessIdOrganization = $unitess->getNewId('GEN_ORG')[0]['GEN_ID'];

                            $requisiteCompany = $b24->sendRequest('crm.requisite.list', [
                                'select' => ['ID', 'RQ_COMPANY_NAME'],
                                'filter' => ['ENTITY_TYPE_ID' => '4', 'ENTITY_ID' => $company['result']['ID']]
                            ]);
                            $requisiteCompanyAddress = $b24->sendRequest('crm.address.list', [
                                'select' => ['*'],
                                'filter' => ['ENTITY_TYPE_ID' => '8', 'ENTITY_ID' => $requisiteCompany['result'][0]['ID']]
                            ]);
                            $sqlFields = 'ID_ORG, ORG_NAME_SHORT, ORG_NAME_LONG, ORG_ADDRESS';
                            $sqlValues = ':id, :name_short, :name, :address';

                            if ($company['result']['HAS_PHONE'] === 'Y'){
                                $sqlFields .= ', ORG_PHONES';
                                $sqlValues .= ', :phone';
                            }
                            if ($company['result']['HAS_EMAIL'] === 'Y'){
                                $sqlFields .= ', ORG_NOTES';
                                $sqlValues .= ', :email';
                            }

                            $sql = mb_convert_encoding('INSERT INTO S_ORGANIZATION (' . $sqlFields . ') VALUES (' . $sqlValues . ');', 'CP1251');

                            try {
                                $query = $db->prepare($sql);
                                $query->bindValue(':id', $newUnitessIdOrganization);
                                $query->bindValue(':name_short', mb_convert_encoding($requisiteCompany['result'][0]['RQ_COMPANY_NAME'], 'CP1251'));
                                $query->bindValue(':name', mb_convert_encoding($requisiteCompany['result'][0]['RQ_COMPANY_NAME'], 'CP1251'));
                                $query->bindValue(':address', mb_convert_encoding($requisiteCompanyAddress['result'][0]['COUNTRY'] . ', '
                                    . $requisiteCompanyAddress['result'][0]['PROVINCE'] . ', '
                                    . $requisiteCompanyAddress['result'][0]['REGION'] . ', '
                                    . $requisiteCompanyAddress['result'][0]['CITY'] . ', '
                                    . $requisiteCompanyAddress['result'][0]['ADDRESS_1'] . ', '
                                    . $requisiteCompanyAddress['result'][0]['ADDRESS_2'] . ', '
                                    . $requisiteCompanyAddress['result'][0]['POSTAL_CODE'], 'CP1251'));

                                if ($company['result']['HAS_PHONE'] === 'Y'){
                                    $query->bindValue(':phone', $company['result']['PHONE'][0]['VALUE']);
                                }
                                if ($company['result']['HAS_EMAIL'] === 'Y'){
                                    $query->bindValue(':email', mb_convert_encoding($company['result']['EMAIL'][0]['VALUE'], 'CP1251'));
                                }

                                $query->execute();

                                $b24->sendRequest(
                                    'crm.company.update',
                                    [
                                        'id' => $company['result']['ID'],
                                        'fields' => [$b24->CONFIG->CONFIGURATION['B24_FIELDS']['COMPANY']['Unitess ID'] => $newUnitessIdOrganization]
                                    ]
                                );
                                $unitess->ENTITIES['organization'] = $newUnitessIdOrganization;

                            } catch (\PDOException $e) {
                                Logger::writeToLog($db->errorInfo());
                            }
                        }
                    }
                    //endregion

                    $newUnitessIdAppeal = $unitess->getNewId('GEN_REQ')[0]['GEN_ID'];

                    //region заявка
                    $sqlFields = 'ID_REQ, ID_USER_REG';
                    $sqlValues = ':id, :user';
                    $sqlFields .= ', REQ_NUMBER,
                        REQ_LETTER_NUM,
                        REQ_NOTES,
                        REQ_TOTAL_COST,
                        ID_TOC
                    ';
                    $sqlValues .= ', :number, :letter, :notes, :totalcost, :toc';
                    if (!empty($unitess->ENTITIES['organization'])){
                        $sqlFields .= ', ID_ORG';
                        $sqlValues .= ', :organization';
                    }
                    if (isset($b24->ENTITY_FIELDS['UF_CRM_1592552182777']) && !empty($b24->ENTITY_FIELDS['UF_CRM_1592552182777'])){
                        $sqlFields .= ', REQ_AGRM';
                        $sqlValues .= ', :contract';
                    }
                    if (isset($b24->ENTITY_FIELDS['UF_CRM_1592230664']) && !empty($b24->ENTITY_FIELDS['UF_CRM_1592230664'])){
                        $sqlFields .= ', REQ_INVOICE_DATE, ID_SOP';
                        $sqlValues .= ', :paymentDate, :paymentStatus';
                    }else{
                        $sqlFields .= ', ID_SOP';
                        $sqlValues .= ', :paymentStatus';
                    }

                    $sql = 'INSERT INTO I_REQUEST (' . $sqlFields . ') VALUES (' . $sqlValues . ');';

                    try {
                        $query = $db->prepare($sql);
                        $query->bindValue(':id', $newUnitessIdAppeal);
                        $query->bindValue(':user', '1');
                        $query->bindValue(':number', '');
                        $query->bindValue(':letter', '');
                        $query->bindValue(':notes', '');
//                        $query->bindValue(':otbor', '');
                        $query->bindValue(':totalcost', 0);
                        $query->bindValue(':toc', 1);   //ID_TOC fk S_TYPE_OF_CURRENCY валюты

                        if (!empty($unitess->ENTITIES['organization'])){
                            $query->bindValue(':organization', $unitess->ENTITIES['organization']);
                        }
                        if (isset($b24->ENTITY_FIELDS['UF_CRM_1592552182777']) && !empty($b24->ENTITY_FIELDS['UF_CRM_1592552182777'])) {
                            $query->bindValue(':contract', $b24->ENTITY_FIELDS['UF_CRM_1592552182777']);
                        }
                        if (isset($b24->ENTITY_FIELDS['UF_CRM_1592230664']) && !empty($b24->ENTITY_FIELDS['UF_CRM_1592230664'])) {
                            $query->bindValue(':paymentDate', DateTime::createFromFormat(DATE_ATOM, $b24->ENTITY_FIELDS['UF_CRM_1592230664'])->format('Y-m-d H:i:s'));
                            $query->bindValue(':paymentStatus', '100');
                        }else{
                            $query->bindValue(':paymentStatus', '1');
                        }
                        $query->execute();

                        //region образец
                        if (isset($b24->ENTITY_FIELDS[$b24->CONFIG->CONFIGURATION['B24_FIELDS']['DEAL']['Образец: комплект']])
                            && !empty($b24->ENTITY_FIELDS[$b24->CONFIG->CONFIGURATION['B24_FIELDS']['DEAL']['Образец: комплект']]))
                        {
                            $newUnitessIdSample = $unitess->getNewId('GEN_DUT')[0]['GEN_ID'];

                            $sqlFields = 'ID_DUT, ID_REQ';
                            $sqlValues = ':id, :appeal';
                            $sqlFields .= ', ID_SOP, ID_USER_OWNER, ID_USER_REG, DUT_TOTAL_COST, ID_TOC, DUT_DESCRIPTION, DUT_SERNUM, DUT_KIT, DUT_NOTES';
                            $sqlValues .= ', :paymentStatus, :userOwner, :userReg, :totalcost, :toc, :description, :serNum, :kit, :manufacturer';
                            if (!empty($unitess->ENTITIES['organization'])){
                                $sqlFields .= ', ID_ORG';
                                $sqlValues .= ', :organization';
                            }

                            $sql = 'INSERT INTO I_DUT (' . $sqlFields . ') VALUES (' . $sqlValues . ');';

                            $query = $db->prepare($sql);

                            $query->bindValue(':id', $newUnitessIdSample);
                            $query->bindValue(':appeal', $newUnitessIdAppeal);
                            $query->bindValue(':paymentStatus', '1');
                            $query->bindValue(':userOwner', '1');
                            $query->bindValue(':userReg', '1');
                            $query->bindValue(':totalcost', 0);
                            $query->bindValue(':toc', 1);
                            $query->bindValue(':description', $b24->ENTITY_FIELDS[$b24->CONFIG->CONFIGURATION['B24_FIELDS']['DEAL']['Образец: изделие']]);
                            $query->bindValue(':serNum', $b24->ENTITY_FIELDS[$b24->CONFIG->CONFIGURATION['B24_FIELDS']['DEAL']['Идентификационный номер изделия']]);
                            $query->bindValue(':kit', $b24->ENTITY_FIELDS[$b24->CONFIG->CONFIGURATION['B24_FIELDS']['DEAL']['Образец: комплект']]);
                            $query->bindValue(':manufacturer', $b24->ENTITY_FIELDS[$b24->CONFIG->CONFIGURATION['B24_FIELDS']['DEAL']['Образец: изготовитель']]);
//                            $query->bindValue(':description', mb_convert_encoding($b24->ENTITY_FIELDS[$b24->CONFIG->CONFIGURATION['B24_FIELDS']['DEAL']['Образец: изделие']], 'CP1251'));
//                            $query->bindValue(':serNum', mb_convert_encoding($b24->ENTITY_FIELDS[$b24->CONFIG->CONFIGURATION['B24_FIELDS']['DEAL']['Идентификационный номер изделия']], 'CP1251'));
//                            $query->bindValue(':kit', mb_convert_encoding($b24->ENTITY_FIELDS[$b24->CONFIG->CONFIGURATION['B24_FIELDS']['DEAL']['Образец: комплект']], 'CP1251'));
//                            $query->bindValue(':manufacturer', mb_convert_encoding($b24->ENTITY_FIELDS[$b24->CONFIG->CONFIGURATION['B24_FIELDS']['DEAL']['Образец: изготовитель']], 'CP1251'));
                            if (!empty($unitess->ENTITIES['organization'])){
                                $query->bindValue(':organization', $unitess->ENTITIES['organization']);
                            }

                            $query->execute();
                        }
                        //endregion

                        $b24->sendRequest(
                            'crm.deal.update',
                            [
                                'id' => $b24->INCOMING_REQUEST['data']['FIELDS']['ID'],
                                'fields' => ['UF_CRM_1592480185' => $newUnitessIdAppeal]
                            ]
                        );

                    } catch (\PDOException $e) {
                        Logger::writeToLog($e->getMessage());
                    }
                    //endregion
                }
            }
        }
    }

//    if ($b24->INCOMING_REQUEST['event'] === 'ONCRMDEALADD' && $b24->INCOMING_REQUEST['data']['FIELDS']['ID']) {
//        if ($deal = $b24->sendRequest('crm.deal.get', ['id' => $b24->INCOMING_REQUEST['data']['FIELDS']['ID']])){
//            $b24->ENTITY_FIELDS = $deal['result'];
//            Logger::writeToLog($b24->ENTITY_FIELDS);
//        }
//    }

}

try {
//    $unitess = new UnitessDB();
//    $unitess->connect();
//    $db = $unitess->getConnectionObject();
    $db = new \PDO(
        'firebird:dbname=192.168.88.162:C:\UnitessDB\UNITESSDB.FDB;charset=utf8;',
        'UT_OWNER', '123',
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
    );
//    $newUnitessIdSample = $unitess->getNewId('GEN_DUT')[0]['GEN_ID'];
    echo "<pre>";
    print_r($db);
    echo "</pre>";
}catch (\Exception $e){

    echo "<pre>";
    print_r($e);
    echo "</pre>";
}