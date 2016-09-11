<?php

/**
 * Created by PhpStorm.
 * User: Paulius
 * Date: 2015-02-26
 * Time: 00:41
 */
class IecQuota2015 extends Web
{

    const REMOTE_URL = 'http://www.cic.gc.ca/english/work/iec/data.xml';
    const LOCAL_FILE = 'data/iec2015-quota/{FILENAME}.xml'; // relative to web root
    const FILENAME_LATEST = 'latest';

    protected $content_local = '';
    protected $content_remote = '';
    protected $changes_detected = false;

    public function __construct()
    {

        // Set remote file
        $this->set('content_remote', $this->getRemoteFileContent());

        // Set local file
        $local_file_latest = $this->getLocalFileLatest();
        if (!is_file($local_file_latest)) {
            $this->moveRemoteToLatest();
        }
        $this->set('content_local', $this->getLocalFileContent());

    }


    private function getRemoteFileContent()
    {
        $SimpleXmlElement = simplexml_load_file($this::REMOTE_URL);
        if (!($SimpleXmlElement instanceof SimpleXMLElement)) {
            return false;
        }

        return trim($SimpleXmlElement->asXML());

    }

    private function getLocalFileContent()
    {
        $SimpleXmlElement = simplexml_load_file($this->getLocalFileLatest());

        if (!($SimpleXmlElement instanceof SimpleXMLElement)) {
            return false;
        }

        return trim($SimpleXmlElement->asXML());

    }

    public function getCountriesWithChangedQuota()
    {

        $countriesChanged = array();

        $SimpleXmlElementLocal = simplexml_load_string($this->get('content_local'));
        $SimpleXmlElementRemote = simplexml_load_string($this->get('content_remote'));

        foreach ($SimpleXmlElementRemote->children() as $countryRemote) {

            $countryRemoteLocation = (string)$countryRemote['location'];
            $countryRemoteCode = (string)$countryRemote['code'];
            $countryRemoteCategory = (string)$countryRemote['category'];
            $countryRemoteNumbers = $this->getInfoOnCountry($countryRemote);


            foreach ($SimpleXmlElementLocal->children() as $countryLocal) {

                $countryLocalLocation = (string)$countryLocal['location'];
                $countryLocalCode = (string)$countryLocal['code'];
                $countryLocalCategory = (string)$countryLocal['category'];
                $countryLocalNumbers = $this->getInfoOnCountry($countryLocal);


                if (
                    $countryRemoteLocation != $countryLocalLocation
                    || $countryRemoteCode != $countryLocalCode
                    || $countryRemoteCategory != $countryLocalCategory
                ) {
                    continue; // get next row, because this is from other country, category,
                }

                // Same country, check numbers now.
                if ($countryRemoteNumbers['quota'] != $countryLocalNumbers['quota']) {
                    // Quota has changed
                    $InfoObject = new stdClass();
                    $InfoObject->location = $countryLocalLocation;
                    $InfoObject->code = $countryLocalCode;
                    $InfoObject->category = $countryLocalCategory;
                    $InfoObject->info = $countryRemoteNumbers;

                    $countriesChanged[] = clone $InfoObject;
                }


            }

        }

        return $countriesChanged;

    }


    public function getCountryData($countryLocalLocation, $countryLocalCode, $countryLocalCategory
        , $returnAsString = false, $contentCase = 'content_remote')
    {

        $SimpleXmlElementRemote = simplexml_load_string($this->get($contentCase));

        foreach ($SimpleXmlElementRemote->children() as $countryRemote) {

            $countryRemoteLocation = (string)$countryRemote['location'];
            $countryRemoteCode = (string)$countryRemote['code'];
            $countryRemoteCategory = (string)$countryRemote['category'];
            $countryRemoteNumbers = $this->getInfoOnCountry($countryRemote);

            if (
                $countryRemoteLocation != $countryLocalLocation
                || $countryRemoteCode != $countryLocalCode
                || $countryRemoteCategory != $countryLocalCategory
            ) {
                continue; // get next row, because this is from other country, category,
            }

            // Same country, return info
            if ($returnAsString) {
                return (
                    ($countryRemote instanceof SimpleXMLElement)
                        ? trim($countryRemote->asXml()) : (string)$countryRemote
                );
            } else {
                return $countryRemoteNumbers;
            }

        }

        if ($returnAsString) {
            return '';
        } else {
            return array();
        }

    }


    private function getInfoOnCountry(SimpleXMLElement $country)
    {

        $quota = null;
        $places = null;
        $status = null;

        foreach ($country->children() as $case) { // running throw unique meanings, e.g.: quota number, places number, status string, etc.

            if (!($case instanceof SimpleXMLElement)) { // must be instance of SimpleXMLElement
                continue;
            }

            $case_name = $case->getName();

            switch ($case_name) {
                case 'quota':
                    $quota = strip_tags((string)$case);
                    break;
                case 'places':
                    $places = strip_tags((string)$case);
                    break;
                case 'status':
                    $status = strip_tags((string)$case);
                    break;
            }
        }

        return array('quota' => $quota, 'places' => $places, 'status' => $status);

    }

}