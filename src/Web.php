<?php

/**
 * Created by PhpStorm.
 * User: user
 * Date: 2015-02-25
 * Time: 18:32
 */
class Web
{

    const REMOTE_URL = 'http://www.example.org';
    const LOCAL_FILE = 'data/example/{FILENAME}.ext'; // relative to web root
    const FILENAME_LATEST = 'latest';

    protected $content_local = '';
    protected $content_remote = '';
    protected $changes_detected = false; // if remote changes has been detected

    // then changes_detected must be set to true.

    public function set($name, $value)
    {
        $this->$name = $value;
    }

    public function get($name)
    {
        return $this->$name;
    }

    protected function mergeDomNodeListToHtml(DOMNodeList $DomNodeList)
    {

        $resultHtml = '';

        foreach ($DomNodeList as $DomElement) {
            if (!($DomElement instanceof DOMElement)) {
                continue; // analyze DOMElement elements only (just some sort of security)..
            }

            $resultHtml .= $this->getInnerHtml($DomElement);

        }

        return $resultHtml;

    }

    protected function getInnerHtml(DOMElement $Node)
    {
        $innerHTML = '';

        if (!$Node->hasChildNodes()) {
            return $innerHTML;
        }

        $children = $Node->childNodes;
        foreach ($children as $child) {
            $innerHTML .= $child->ownerDocument->saveXML($child);
        }

        return $innerHTML;
    }

    protected function removeDynamicElementById($htmlString, $idToRemove)
    {

        $DomDocument = $this->getDomContent($htmlString, false);

        // remove $idToRemove from the content
        $DynamicDomElement = $DomDocument->getElementById($idToRemove);
        if ($DynamicDomElement instanceof DOMElement) {
            $DynamicDomElement->parentNode->removeChild($DynamicDomElement);
        }

        return $DomDocument->saveHTML();

    }

    protected function getDomContent($content, $content_is_url = true)
    {

        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = FALSE;

        //$context = $this->getDomContentContext();

        if ($content_is_url) {
            @$doc->loadHTML(mb_convert_encoding(file_get_contents(trim($content), false), 'HTML-ENTITIES', 'UTF-8'));
        } else {
            @$doc->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
        }
        //@$doc->loadHTMLFile($content);
        return $doc;//->saveHTML();
    }

    protected function getDomContentContext()
    {
        // Create a stream
        $options = array(
            'http' => array(
                'method' => "GET",
                'header' => "Accept-language: en\r\n" .
                    "Cookie: foo=bar\r\n",
                'user_agent' => "Mozilla/5.0 (Windows NT 6.3; WOW64; rv:35.0) Gecko/20100101 Firefox/35.0",
                'follow_location' => 0
            )
        );

        return stream_context_create($options);

    }

    protected function getLocalFileLatest()
    {
        return str_replace('{FILENAME}', $this::FILENAME_LATEST, $this::LOCAL_FILE);
    }

    protected function getLocalFileArchive()
    {
        return str_replace('{FILENAME}', date('Y-m-d--H-i-s'), $this::LOCAL_FILE);
    }

    protected function createNewLocalFile($pathinfo, $content)
    {

        $basename = $pathinfo['basename'];
        $dirname = $pathinfo['dirname'];

        if (!is_dir($dirname)) {
            mkdir($dirname, 0775, true);
        }

        file_put_contents(
            trim(rtrim($dirname, '/\\') . DIRECTORY_SEPARATOR . ltrim($basename, '/\\')),
            trim($content),
            FILE_APPEND
        );
    }

    public function isLocalAnRemoteContentTheSame()
    {
        return ($this->get('content_local') == $this->get('content_remote'));
    }

    public function archiveLocalLatest()
    {
        rename($this->getLocalFileLatest(), $this->getLocalFileArchive());
        $this->set('content_local', null);

    }

    public function moveRemoteToLatest()
    {

        //$content_local = $this->get('content_local');
        $content_remote = $this->get('content_remote');

        $this->createNewLocalFile(pathinfo($this->getLocalFileLatest()), $this->get('content_remote'));
        $this->set('content_local', $content_remote);

    }


}