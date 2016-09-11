<?php

/**
 * Created by PhpStorm.
 * User: user
 * Date: 2015-02-25
 * Time: 16:56
 */
class KompassRegister2015 extends Web
{

    const REMOTE_URL = 'https://kompass-2015-iec-eic.international.gc.ca/registration-inscription?regionCode=LT';
    const LOCAL_FILE = 'data/kompass2015-register/{FILENAME}.html'; // relative to web root
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
        $RemotePage = $this->getDomContent($this::REMOTE_URL);
        $Content = $RemotePage->getElementsByTagName('main');

        if (!($Content instanceof DOMNodeList)) {
            return false; // Web has been changed dramatically or so
        }
        $contentHtml = trim($this->mergeDomNodeListToHtml($Content));

        $contentHtml = $this->removeDynamicElementById($contentHtml, '__VIEWSTATE');

        return trim($contentHtml);

    }

    private function getLocalFileContent()
    {
        $DomDocument = $this->getDomContent($this->getLocalFileLatest());

        if (!($DomDocument instanceof DOMDocument)) {
            return false;
        }

        return trim($DomDocument->saveHTML());
    }


}