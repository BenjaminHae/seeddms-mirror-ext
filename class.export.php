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


/**
 * Extension to export files into a directory structure
 *
 * @author  Benjamin Haeublein <benjaminhaeublein@gmail.com>
 * @package SeedDMS
 * @subpackage  Mirror
 */
class SeedDMS_FileMirror extends SeedDMS_ExtBase {
    
    var $_documentHandler = new SeedDMS_FileMirror_DocumentHandler();
    var $_fileHandler = new SeedDMS_FileMirror_FileHandler();

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
		$GLOBALS['SEEDDMS_HOOKS']['view']['addDocument'][] = new SeedDMS_FileMirror_AddDocument($this->_handler);
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
/**
 * Class containing methods for hooks when a document is added
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  example
 */
class SeedDMS_FileMirror_AddDocument extends SeedDMS_FileMirror_HookBase{

	/**
	 * Hook after successfully adding a new document
     * Adds it to the mirror directory
	 */
	function postAddDocument($document) {
        $this->handler.addDocument($document);
	}
}

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
	 * @const string pipe for shell_exec
	 */
	const _PIPE = " 2>&1";
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

	private function Attribute(){
	  if ($this->_attributObject == NULL){
	    $this->_attributObject = $this->_dms->getAttributeDefinitionByName(self::_REPOATTRIBUTE);
	  }
	  return $this->_attributObject;
	}

	function addDocumentContent($document){//todo: alter content bleibt beibehalten, wenn sich Dateityp ändert
		if (!$this->belongsFileToRepository($document)){
			$this->log($this->DocumentGetCorePath($document)." is set to ignoreInGit");
			return false;
		}
		$destinationPath = $this->DocumentGetGitPath($document);
		$this->forceDirectories($destinationPath);
		$this->log("Adding Document ".$document->getName());
		if (file_exists($destinationPath)){		
			$this->log("copying file ".$this->DocumentGetCorePath($document)." to ".$this->DocumentGetGitFullPath($document));
			if (copy($this->DocumentGetCorePath($document),$this->DocumentGetGitFullPath($document))){
				$this->gitAdd($this->DocumentGetGitFullPath($document));
				$this->_gitCommitMessage .= "added File ".$document->getName()."\r\n";
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
	function endsWith($haystack, $needle){
		return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
	}
	
	function DocumentGetGitPath($document){
		return $this->FolderGetGitFullPath($document->getFolder());
	}
	
	function DocumentGetGitFileName($document, $latestContent=NULL){
		return $this->DocumentGetGitFileNameX($document->getName(),$document,$latestContent);
	}
	
	function DocumentGetGitFileNameX($name, $document, $latestContent=NULL){
		if($latestContent==NULL)
			$latestContent = $document->getLatestContent();
		//Independent of case
		if ($this->endsWith(strtolower($name), strtolower($latestContent->getFileType())))
			return $name;
		return $name.$latestContent->getFileType();
	}
	
	function DocumentGetGitFullPath($document, $latestContent=NULL){
		return $this->DocumentGetGitPath($document).'/'.$this->DocumentGetGitFileName($document,$latestContent);
	}
	
	function DocumentGetCorePath($document){
		$latestContent = $document->getLatestContent();
		if (is_object($latestContent))
			return $this->_dms->contentDir.$latestContent->getPath();
		else
			return false;
	}
	
	function FolderGetGitFullPath($folder){
		return $this->_path.$this->FolderGetRelativePath($folder);
	}
	
	function FolderGetRelativePath($folder){
		$path="";
		$folderPath = $folder->getPath();
		$start = 0;
		if(!self::_ROOTCONTAINSMAINFOLDER)
			$start = 1;
		for ($i = 1; $i < count($folderPath); $i++) {
			$path .= $folderPath[$i]->getName();
			if ($i +1 < count($folderPath)){
				$path .= "/";
			}
		}
		//printf($folderPath);
		return $path;
	}
	function belongsFileToRepository($document){
	  if ($document->getAttributeValue($this->Attribute()) == "true")
	    return false;
	  $curr = $document->getFolder();
	  return $this->belongsFolderToRepository($curr);
	}
	
	function belongsFolderToRepository($folder){
	  $curr = $folder;
	  while (true){
	    if (!$curr)
	      break;
	    if ($curr->getAttributeValue($this->Attribute()) == "true")
	      return false;
	    if (!isset($curr->_parentID) || ($curr->_parentID == "") || ($curr->_parentID == 0) || ($curr->_id == $curr->_dms->rootFolderID)) 
	      break;
	    $curr = $curr->getParent();
	  }
	  return true;
	}	
	private function log($msg, $priority = null){
		global $logger;
		if(trim($msg)!=""){
			if(is_object($logger))
				$logger->log("Git"." (".$_SERVER['REMOTE_ADDR'].") ".basename($_SERVER["REQUEST_URI"], ".php")." ".$msg, $priority);
		}
	}
	function forceDirectories($path){
		if (!file_exists($path)) {
			mkdir($path, 0777, true);//ToDo Berechtigungen
		}
	}
}

class SeedDMS_FileMirror_FileHandler {
    var $_path;
    function __construct($path) {
        $this->_path = $path;
    }
}
?>
