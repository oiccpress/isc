<?php

namespace APP\plugins\importexport\isc;

class IscService {

    public const XML_WSDL = 'https://xml.isc.ac/iscServer/ISC_Service_item.svc?wsdl';

    protected \SoapClient $client;
    protected $username, $password;

    public function __construct($plugin, $context) {

        if(!class_exists('SoapClient')) {
            die('PHP SOAP extension seems to not be installed!!');
        }

        $this->client = new \SoapClient(static::XML_WSDL);
        $this->username = $plugin->getSetting( $context->getId(), 'isc_username' );
        $this->password = $plugin->getSetting( $context->getId(), 'isc_password' );
    }

    public function getIndexingResult() {

        $result = $this->client->GetIndexingResult([
            'lockinfo' => $this->username,
            'keyinfo' => $this->password,
            'issn' => '',
            'year' => '',
            'vol' => '',
            'issue' => '',
        ]);

        var_dump($this->username);
        var_dump($this->password);

        var_dump($result);

        // TODO: Get this working and then figure out how to output it!?!

        die;

    }

}