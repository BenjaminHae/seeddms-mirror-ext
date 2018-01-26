<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Uwe Steinmann <uwe@steinmann.cx>
 *  All rights reserved
 *
 *  This script is part of the SeedDMS project. The SeedDMS project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

//ToDo implement edit of document/folder, handle change of ignore Attribute
// handle settings
// change of filetype by reuploading
// logging

/**
 * Extension to export files into a directory structure
 *
 * @author  Benjamin Haeublein <benjaminhaeublein@gmail.com>
 * @package SeedDMS
 * @subpackage  Mirror
 */
class SeedDMS_FileMirror extends SeedDMS_ExtBase {

    var $_documentHandler;
    var $_mirrorPath;

    /**
     * Initialization
     *
     * Use this method to do some initialization like setting up the hooks
     * You have access to the following global variables:
     * $GLOBALS['dms'] : object representing dms
     * $GLOBALS['user'] : currently logged in user
     * $GLOBALS['session'] : current session
     * $GLOBALS['settings'] : current global configuration
     * $GLOBALS['settings']['_extensions']['example'] : configuration of this extension
     * $GLOBALS['LANG'] : the language array with translations for all languages
     * $GLOBALS['SEEDDMS_HOOKS'] : all hooks added so far
     */
    function init() { /* {{{ */
        //$settings = $GLOBALS['settings']['_extensions']['export']; doesn't work
        $this->_mirrorPath = '/var/local/seeddms/mirror';
        $this->_documentHandler = new SeedDMS_FileMirror_DocumentHandler($this->_mirrorPath);
        $GLOBALS['SEEDDMS_HOOKS']['controller']['addDocument'][] = new SeedDMS_FileMirror_AddDocument($this->_documentHandler);
        $GLOBALS['SEEDDMS_HOOKS']['controller']['removeDocument'][] = new SeedDMS_FileMirror_RemoveDocument($this->_documentHandler);
        $GLOBALS['SEEDDMS_HOOKS']['controller']['updateDocument'][] = new SeedDMS_FileMirror_UpdateDocument($this->_documentHandler);
        $GLOBALS['SEEDDMS_HOOKS']['controller']['editDocument'][] = new SeedDMS_FileMirror_EditDocument($this->_documentHandler);
        $GLOBALS['SEEDDMS_HOOKS']['controller']['removeFolder'][] = new SeedDMS_FileMirror_RemoveFolder($this->_documentHandler);
        $GLOBALS['SEEDDMS_HOOKS']['controller']['editFolder'][] = new SeedDMS_FileMirror_EditFolder($this->_documentHandler);
    } /* }}} */

    function main() { /* {{{ */
    } /* }}} */
}

class SeedDMS_FileMirror_HookBase {
    var $_handler;

    function __construct($handler) {
        $this->_handler = $handler;
    }
}
/* Classes for Handling Document Hooks */
class SeedDMS_FileMirror_AddDocument extends SeedDMS_FileMirror_HookBase{
    function postAddDocument($controller, $document) {
        $this->_handler->addDocumentContent($document);
    }
}
class SeedDMS_FileMirror_RemoveDocument extends SeedDMS_FileMirror_HookBase{
    function removeDocument($controller, $document) {
        $this->_handler->removeDocument($document);
    }
}
// Only updating contents
class SeedDMS_FileMirror_UpdateDocument extends SeedDMS_FileMirror_HookBase{
    function postUpdateDocument($controller, $document) {
        $this->_handler->addDocumentContent($document, true);
    }
}
/* Classes for Handling Folder Hooks */
class SeedDMS_FileMirror_RemoveFolder extends SeedDMS_FileMirror_HookBase{
    function removeFolder($controller, $folder) {
        $this->_handler->removeFolder($folder);
    }
}
// changing metadata
class SeedDMS_FileMirror_EditDocument extends SeedDMS_FileMirror_HookBase{
    //Todo check if filename/location changed
    function editDocument($controller, $document) {
        //$this->_handler->removeDocument($document);
    }
    function postEditDocument($controller, $document) {
        //$this->_handler->removeDocument($folder);
    }
}
class SeedDMS_FileMirror_EditFolder extends SeedDMS_FileMirror_HookBase{
    //Todo check if foldername/location changed
    function editFolder($controller, $folder) {
        //$this->_handler->removeDocument($folder);
    }
    function postEditFolder($controller, $folder) {
        //$this->_handler->removeDocument($folder);
    }
}

/* Document Handler Class for copying, removing and renaming document */

class SeedDMS_FileMirror_DocumentHandler {
    /**
     * @const bool if true in the mirror directory every file from the DMS is inside another folder
     */
    const _ROOTCONTAINSMAINFOLDER = false;
    /**
     * @const string name of attribute which decides whether a file should be in repo 
     */
    const _REPOATTRIBUTE = "ignoreInGit";
    /**
     * @const bool be verbose in log for debugging
     */
    const _VERBOSE = true;

    /**
     * @var string path of mirror directory, including trailing path delimiter
     *
     * @access protected
     */
    var $_path;

    var $_attributeObject = NULL;

    function __construct($path){
        $this->log("extension init");
        $this->_path = rtrim($path,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    }

    private function Attribute(){
        global $dms;
        if (!$this->_attributeObject){
            $this->_attributeObject = $dms->getAttributeDefinitionByName(self::_REPOATTRIBUTE);
        }
        return $this->_attributeObject;
    }

    function addDocumentContent($document, $update = false){//todo: alter content bleibt beibehalten, wenn sich Dateityp ändert
        if (!$this->belongsFileToRepository($document)){
            $this->log($this->DocumentGetCorePath($document)." is set to ignoreInGit");
            return false;
        }
        $destinationPath = $this->DocumentGetGitPath($document);
        $this->forceDirectories($destinationPath);
        $this->log("Adding Document ".$document->getName());
        if (file_exists($destinationPath)){
            $destination = $this->DocumentGetGitFullPath($document);
            $this->log("copying file ".$this->DocumentGetCorePath($document)." to ".$destination);
            if (file_exists($destination)) {
                if ($update) {
                    $this->log("Removing destination file before updating");
                    unlink($destination);
                }
                else {
                    $this->log("Destination file already exists, aborting");
                    return false;
                }
            }
            if (copy($this->DocumentGetCorePath($document),$destination)){
                chmod($destination, 0770);
                return true;
            }
            else{
                $this->log(print_r(error_get_last(), true), PEAR_LOG_ERR);
            }
        }
        else{
            $this->log("addDocumentContent: destinationPath doesn't exist", PEAR_LOG_ERR);
        }
        return false;
    }

    function removeDocument($document){
        if (!$this->belongsFileToRepository($document)){
            $this->log($this->DocumentGetCorePath($document)." is set to ignoreInGit");
            return false;
        }
        unlink($this->DocumentGetGitFullPath($document));
        return true;
    }

    function removeFolder($folder){
        if (!$this->belongsFolderToRepository($folder)){
            $this->log($this->DocumentGetCorePath($folder)." is set to ignoreInGit");
            return false;
        }
        if(unlink($this->FolderGetGitFullPath($folder))){
            return true;
        }
        else{
            $this->log(print_r(error_get_last(), true), PEAR_LOG_ERR);
        }
        return false;
    }

    function endsWith($haystack, $needle){
        return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
    }

    function DocumentGetGitPath($document){
        return $this->FolderGetGitFullPath($document->getFolder());
    }

    function DocumentGetGitFileName($document){
        return $this->DocumentGetGitFileNameX($document->getName(),$document);
    }

    //with file extension
    function DocumentGetGitFileNameX($name, $document){
        $latestContent = $document->getLatestContent();
        //Independent of case
        if ($this->endsWith(strtolower($name), strtolower($latestContent->getFileType()))) {
            return $name;
        }
        return $name.$latestContent->getFileType();
    }

    function DocumentGetGitFullPath($document){
        return $this->DocumentGetGitPath($document).'/'.$this->DocumentGetGitFileName($document);
    }

    function DocumentGetCorePath($document){
        global $dms;
        $latestContent = $document->getLatestContent();
        if (is_object($latestContent)) {
            return $dms->contentDir.$latestContent->getPath();
        }
        else {
            return false;
        }
    }

    function FolderGetGitFullPath($folder){
        return $this->_path.$this->FolderGetRelativePath($folder);
    }

    function FolderGetRelativePath($folder){
        $path="";
        $folderPath = $folder->getPath();
        $start = 0;
        if(!self::_ROOTCONTAINSMAINFOLDER) {
            $start = 1;
        }
        for ($i = 1; $i < count($folderPath); $i++) {
            $path .= $folderPath[$i]->getName();
            if ($i + 1 < count($folderPath)){
                $path .= "/";
            }
        }
        //printf($folderPath);
        return $path;
    }
    function belongsFileToRepository($document){
        if (($this->Attribute() !== false) && ($document->getAttributeValue($this->Attribute()) == "true")) {
            return false;
        }
        $curr = $document->getFolder();
        return $this->belongsFolderToRepository($curr);
    }

    function belongsFolderToRepository($folder){
        global $dms;
        $curr = $folder;
        while (true){
            if (!$curr) {
                break;
            }
            if (($this->Attribute() !== false) && ($curr->getAttributeValue($this->Attribute()) == "true")) {
                return false;
            }
            if (!isset($curr->_parentID) || ($curr->_parentID == "") || ($curr->_parentID == 0) || ($curr->_id == $dms->rootFolderID)) {
                break;
            }
            $curr = $curr->getParent();
        }
        return true;
    }   
    private function log($msg, $priority = null){
        global $logger;
        if(trim($msg)!=""){
            if(is_object($logger)) {
                $logger->log("Git"." (".$_SERVER['REMOTE_ADDR'].") ".basename($_SERVER["REQUEST_URI"], ".php")." ".$msg, $priority);
            }
            else {
                error_log("seeddms"."Git"." (".$_SERVER['REMOTE_ADDR'].") ".basename($_SERVER["REQUEST_URI"], ".php")." ".$msg);
            }
        }
    }
    function forceDirectories($path){
        if (!file_exists($path)) {
            mkdir($path, 0770, true);
        }
    }
}
?>
