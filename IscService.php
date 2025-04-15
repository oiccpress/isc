<?php

namespace APP\plugins\importexport\isc;

class IscService {

    public const XML_WSDL = 'https://xml.isc.ac/iscServer/ISC_Service_item.svc?singleWsdl';

    protected \SoapClient $client;
    protected $username, $password, $journalTitle, $issn;

    public function __construct($plugin, $context) {

        if(!class_exists('SoapClient')) {
            die('PHP SOAP extension seems to not be installed!!');
        }

        $this->client = new \SoapClient(static::XML_WSDL);
        $this->journalTitle = $plugin->getSetting( $context->getId(), 'isc_journalTitle' );
        $this->issn = $plugin->getSetting( $context->getId(), 'isc_issn' );
        $this->username = $plugin->getSetting( $context->getId(), 'isc_username' );
        $this->password = $plugin->getSetting( $context->getId(), 'isc_password' );
    }

    public function submitIssue($year, $volume, $issue, $fileNo, $fileUrl, $filename) {

        $args = [
            'title' => $this->journalTitle,
            'issn' => $this->issn,
            'lockinfo' => $this->username,
            'year' => strval($year),
            'issue' => $issue,
            'vol' => $volume,
            'fileNo' => strval($fileNo),
            'fileUrl' => $fileUrl,
            'filename' => $filename,
            'keyinfo' => $this->password,
        ];
        $result = $this->client->GetFile_issn($args);
        /*
        Successful result:
        object(stdClass)#1314 (1) { ["GetFile_issnResult"]=> string(7) "Success" } 
        */
        return $result;

    }

    public function getIndexingResult($year, $volume, $issue) {

        $args = [
            'lockinfo' => $this->username,
            'keyinfo' => $this->password,
            'issn' => $this->issn,
            'year' => $year,
            'vol' => $volume,
            'issue' => $issue,
        ];

        $result = $this->client->GetIndexingResult($args);

        if(is_object($result)) {

            if($result->GetIndexingResultResult) {
                return json_decode($result->GetIndexingResultResult, true);
            }

        } else {
            return null;
        }
        return null;


    }

}