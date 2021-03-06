<?php
/**
 * PHPWord
 *
 * Copyright (c) 2011 PHPWord
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category   PHPWord
 * @package    PHPWord
 * @copyright  Copyright (c) 010 PHPWord
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt    LGPL
 * @version    Beta 0.6.3, 08.07.2011
 */


/**
 * PHPWord_DocumentProperties
 *
 * @category   PHPWord
 * @package    PHPWord
 * @copyright  Copyright (c) 2009 - 2011 PHPWord (http://www.codeplex.com/PHPWord)
 */
class PHPWord_Template {

    /**
     * ZipArchive
     *
     * @var ZipArchive
     */
    private $_objZip;

    /**
     * Temporary Filename
     *
     * @var string
     */
    private $_tempFileName;

    /**
     * Document XML
     *
     * @var string
     */
    private $_documentXML;

    /**
     * Style XML
     *
     * @var string
     */
    private $_styleXML;

    /**
     * Create a new Template Object
     *
     * @param string $strFilename
     */
    public function __construct($strFilename) {
        $path = dirname($strFilename);
        $this->_tempFileName = $path.DIRECTORY_SEPARATOR.time().'.docx';

        copy($strFilename, $this->_tempFileName); // Copy the source File to the temp File

        $this->_objZip = new ZipArchive();
        $this->_objZip->open($this->_tempFileName);

        $this->_documentXML = $this->_objZip->getFromName('word/document.xml');
        $this->_styleXML = $this->_objZip->getFromName('word/styles.xml');
    }

    /**
     * Set a Template value
     *
     * @param string $search
     * @param string $replace
     */
    public function setValue($search, $replace) {
        if(substr($search, 0, 2) !== '${' && substr($search, -1) !== '}') {
            $search = '${'.$search.'}';
        }

        if(mb_detect_encoding($replace, mb_detect_order(), true) !== 'UTF-8') {
            $replace = utf8_encode($replace);
        }

        $this->_documentXML = str_replace($search, $replace, $this->_documentXML);
    }

    /**
     * Set a Template XML
     *
     * @param string $search
     * @param string $replace (Must be legitimate XML string)
     */
    public function setValueExtend($search, $replace) {
        if(substr($search, 0, 2) !== '${' && substr($search, -1) !== '}') {
            $search = '${'.$search.'}';
        }

        if(mb_detect_encoding($replace, mb_detect_order(), true) !== 'UTF-8') {
            $replace = utf8_encode($replace);
        }

        $replace = str_replace(["\r\n", "\r", "\n"], '', $replace);

        $document = new DOMDocument();
        $document->loadXML($this->_documentXML);
        $xpath = new DomXPath($document);

        $wBody = $xpath->query('//w:body');
        $wP = $xpath->query('//w:p[descendant::* = "' . $search . '"]');
        $wTemplate = simplexml_load_string(
            '<w:template xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' . $replace . '</w:template>'
        )->xpath('//w:template/child::*[position()=1]');

        if (!$wBody->length || !$wP->length || !count($wTemplate)) {
            return False;
        }

        $element = $document->importNode(dom_import_simplexml($wTemplate[0]), true);
        $wBody->item(0)->replaceChild($element, $wP->item(0));

        $this->_documentXML = $document->saveXML();
    }

    /**
     * Save Template
     *
     * @param string $strFilename
     */
    public function save($strFilename) {
        if(file_exists($strFilename)) {
            unlink($strFilename);
        }

        $this->_objZip->addFromString('word/document.xml', $this->_documentXML);
        $this->_objZip->addFromString('word/styles.xml', $this->_styleXML);

        // Close zip file
        if($this->_objZip->close() === false) {
            throw new Exception('Could not close zip file.');
        }

        rename($this->_tempFileName, $strFilename);
    }

    /**
     * 向文档末尾附加内容
     *
     * @param string $xmlString 期待一个文档xml结构, 可通过
     * PHPWord_IOFactory::createWriter($PHPWord, 'Word2007')->getWriterPart('document')->getObjectAsText($table) 获取
     */
    public function appendXMLText($xmlString) {

        $pattern = '/(?<!\<\/w:sectPr\>)(.*)<w:sectPr>(?!\<w:sectPr\>)(.*)<\/w:sectPr>/i';

        $this->_documentXML = preg_replace(
            $pattern,
            str_replace(
                ["\r\n", "\r", "\n"],
                '',
                '${1}' . $xmlString . '<w:sectPr>${2}</w:sectPr>'
            ),
            $this->_documentXML,
            1
        );
    }

    /**
     * 向style.xml附加样式
     *
     * @param string $xmlString 期待一个完整xml结构, 可通过
     * PHPWord_IOFactory::createWriter($PHPWord, 'Word2007')->getWriterPart('styles')->writeStyles($PHPWord) 获取
     */
    public function appendXMLStyle($xmlString) {

        $styleXML = $this->_styleXML;

        //从$xmlString获取样式
        $orgdoc = new DOMDocument;
        $orgdoc->loadXML($xmlString);

        //读取style.xml原来内容
        $newdoc = new DOMDocument;
        $newdoc->loadXML($styleXML);

        $nodes = $orgdoc->getElementsByTagName("style");

        foreach ($nodes as $node) {
            $node = $newdoc->importNode($node, true);
            $newdoc->documentElement->appendChild($node);
        }

        $this->_styleXML = str_replace(
            ["\r\n", "\r", "\n"],
            '',
            $newdoc->saveXML()
        );

    }
}
?>
