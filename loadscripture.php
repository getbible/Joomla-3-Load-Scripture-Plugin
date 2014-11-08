<?php
/**
* 
* 	@version 	1.0.1  November 7, 2014
* 	@package 	Get Bible - Load Scripture Plugin
* 	@author  	Llewellyn van der Merwe <llewellyn@vdm.io>
* 	@copyright	Copyright (C) 2013 Vast Development Method <http://www.vdm.io>
* 	@license	GNU General Public License <http://www.gnu.org/copyleft/gpl.html>
*
**/

defined('_JEXEC') or die;

jimport('joomla.application.component.helper');

// Added for Joomla 3.0
if(!defined('DS')){
	define('DS',DIRECTORY_SEPARATOR);
};

// load the helper function from component
require_once( JPATH_ROOT.DS.'components'.DS.'com_getbible'.DS.'helpers'.DS.'script_checker.php' );

class PlgContentLoadscripture extends JPlugin
{
	protected $component;
	protected $document;
	protected $com_params;
	protected $action;
	protected $diplayOption;
	protected $buket;
	
	public function onPrepareContent(&$row, &$params, $page=0)
	{
		return $this->_prepareLoadScripture($row, $params, $page);
	}

	public function onContentPrepare($context, &$article, &$params, $page = 0)
	{
		return $this->_prepareLoadScripture($article, $params, $page);
	}

	protected function _prepareLoadScripture(&$article, &$params, $page = 0)
	{
		// get call string
		$callClass = $this->params->def('callClass', 'getBible');
		
		// Simple performance check to determine whether bot should process further
		if (strpos($article->text, $callClass) === false)
		{
			return true;
		}
		// setup regex match
		$callClass = preg_quote($callClass);
		$regex = '/<span class="'.$callClass.'">(.*?)<\/span>/i';
		
		$this->_doWork($article,$regex);

		return true;

	}
	
	protected function _doWork(&$article,$regex)
	{
		// find all instances of plugin and put in $matches
		preg_match_all($regex, $article->text, $matches, PREG_SET_ORDER);
		// No matches, skip this
		if (count($matches)){
			// load all the defaults once
			$this->setDefaults();
			foreach ($matches as $match) {
				// $match[0] is full pattern match, $match[1] is the item id or alias
				$scripture 	= trim($match[1]);
				$target 	= preg_quote($match[0]);
				if ($scripture) {
					$output = $this->_setScript($scripture);
					// We should replace only first occurrence in order to allow positions with the same name to regenerate their content:
					$article->text = preg_replace("|$target|", $output, $article->text, 1);
				}
			}
			$article->text = $article->text.$this->buket;
		}
		return true;
	}

	protected function _setScript($scripture)
	{
		// set loacl defaults
		$id 				= $this->randomkey(8);
		$version 			= $this->getStringIn($scripture,'(',')');
		$diplayOption 		= $this->getStringIn($scripture,'[',']');
		if($diplayOption){
			switch($diplayOption){
				case 'tip':
				case 1:
				$this->diplayOption = 1;
				break;
				case 'can':
				case 2:
				$this->diplayOption = 2;
				break;
				case 'pop':
				case 3:
				$this->diplayOption = 3;
				break;
				case 'in':
				case 4:
				$this->diplayOption = 4;
				break;
				default:
				$this->diplayOption = $this->params->get('diplayOption');
			}
		} else {
			$this->diplayOption = $this->params->get('diplayOption');
		}
		if(!$version){
			$version 	= $this->com_params->get('defaultStartVersion');
			$scripture 	= current(explode('[', $scripture));
		} else {
			$scripture 	= current(explode('(', $scripture));
		}
		// remove all white space
		$json_scripture 	= preg_replace('/\s+/', '', $scripture);
		$request 			= 'p='.urlencode($json_scripture).'&v='.strtolower(urlencode($version));
		// load result based on desplay option
		if($this->diplayOption == 2){
			
			$script = '<a href="#'.$id.'" data-uk-offcanvas>'.$this->htmlEscape($scripture).'</a>';
			$this->buket .= '<div id="'.$id.'" class="uk-offcanvas"><div class="uk-offcanvas-bar"><div class="uk-panel" id="can_'.$id.'"> loading '.$this->htmlEscape($scripture).'... </div></div></div>';
			$this->buket .= "<script type=\"text/javascript\"> jQuery('#".$id."').click(loadscripture('".$request."','can_".$id."','diplay_2', '".strtoupper($version)."'));</script>";
			
		} else if($this->diplayOption == 3){
			
			$script = '<a href="#'.$id.'" data-uk-modal>'.$this->htmlEscape($scripture).'</a>';
			$this->buket .= '<div id="'.$id.'" class="uk-modal"><div class="uk-modal-dialog"><a class="uk-modal-close uk-close"></a><div class="uk-panel" id="pop_'.$id.'"> loading '.$this->htmlEscape($scripture).'... </div></div></div>';
			$this->buket .= "<script type=\"text/javascript\"> jQuery('#".$id."').click(loadscripture('".$request."','pop_".$id."','diplay_3', '".strtoupper($version)."'));</script>";
			
		} elseif($this->diplayOption == 4){
			
			$script .= '<span id="in_'.$id.'"> loading '.$this->htmlEscape($scripture).'... </span>';
			$this->buket .= "<script type=\"text/javascript\"> loadscripture('".$request."','in_".$id."','diplay_4', '".strtoupper($version)."') ;</script>";
			
		} else {
			$script = '<span style="cursor: pointer;" id="'.$id.'" data-uk-tooltip="{pos:\'bottom-left\'}" title="">'.$this->htmlEscape($scripture).'</span>';
			$this->buket .= "<script type=\"text/javascript\"> jQuery('#".$id."').hover(loadscripture('".$request."','".$id."','diplay_1', '".strtoupper($version)."')); </script>";
		}
		return $script;
	}
	
	protected function getStringIn($string, $fist = '(', $last = ')'){
		$string = " ".$string;
		$foo = strpos($string,$fist);
		if($foo == 0) {
			return false;
		}
		$foo += strlen($fist);
		$var = strpos($string,$last,$foo) - $foo;
		return substr($string,$foo,$var);
		
	}
	
	protected function htmlEscape($val)
	{
		return htmlentities($val, ENT_COMPAT, 'UTF-8');
	}
	
	protected function randomkey($size) {
		$bag = "abcefghijknop1234567890qrstuwxyzABCDDEFGHIJKLLMMNOPQRSTUVVWXYZabcddefghijkllmmnopqrs0987654321tuvvwxyzABCEFGHIJKNOPQRSTUWXYZ";
		$key = array();
		$bagsize = strlen($bag) - 1;
		for ($i = 0; $i < $size; $i++) {
			$get = rand(0, $bagsize);
			$key[] = $bag[$get];
		}
		return implode($key);
	}
	
	protected function setDefaults()
	{
		// get the document
		$this->document		= &JFactory::getDocument();
		// set the getBible component defaults
        $this->component	= &JComponentHelper::getComponent('com_getbible');
		// set the getBible component params
        $this->com_params	= &JComponentHelper::getParams('com_getbible');
		// make sure all scripts are loaded
		if (!HeaderCheck::css_loaded('uikit')) {
			$this->document->addStyleSheet(JURI::base( true ) .DS.'media'.DS.'com_getbible'.DS.'css'.DS.'uikit.min.css');
		}
		// add css to page
		$css = "
		.rtl {
			direction: rtl; 
			text-align: right;
			unicode-bidi: bidi-override;
		}
		
		.ltr {
			direction: ltr; 
			text-align: left;
			unicode-bidi: bidi-override;
		}
		";
		$this->document->addStyleDeclaration($css);
		
		// add script to page
		if (!HeaderCheck::js_loaded('jquery')) {	
			JHtml::_('jquery.framework');
		}
		if (!HeaderCheck::js_loaded('uikit')) {
			$this->document->addScript(JURI::base( true ) .DS.'media'.DS.'com_getbible'.DS.'js'.DS.'uikit.min.js');
		}
		// load the correct url
		if ($this->com_params->get('jsonQueryOptions') == 1){
			$this->action = 'index.php?option=com_getbible&view=json';
		} elseif ($this->com_params->get('jsonQueryOptions') == 2) {
			$this->action = 'https://getbible.net/index.php?option=com_getbible&view=json';
		} else {
			$this->action = 'http://getbible.net/index.php?option=com_getbible&view=json';
		}
		$script = "";
		if($this->com_params->get('jsonAPIaccess')){
			$key	= JSession::getFormToken();
			$script .= "var appKey = '".$key."';"; 
		}
		$script .= "
		function loadscripture(request,addTo,diplayOption,version) {
			if (typeof appKey !== 'undefined') {
				request = request+'&appKey='+appKey;
			}
			jQuery.ajax({
			 url:'".$this->action."',
			 dataType: 'jsonp',
			 data: request,
			 jsonp: 'getbible',
			 success:function(json){
				 // set text direction
				 if (json.direction == 'RTL'){
					var direction = 'rtl';
				 } else {
					var direction = 'ltr'; 
				 }
				 // check json
				 if (json.type == 'verse'){
					setVerses(json,direction,addTo,diplayOption,version);
				 } else if (json.type == 'chapter'){
					 if(diplayOption == 'diplay_1') {
						jQuery('#'+addTo).prop('title', 'Whole chapter/s not allowed in tooltip mode, please select another.');
					} else {
						setChapter(json,direction,addTo,diplayOption,version);
					}
				 } else if (json.type == 'book'){
					 if(diplayOption == 'diplay_1') {
						jQuery('#'+addTo).prop('title', 'Whole book/s! not allowed in tooltip mode, please select another.');
					} else {
						setBook(json,direction,addTo,diplayOption,version);
					}
				 } 
			 },
			 error:function(){
				 	if(diplayOption == 'diplay_1') {
						jQuery('#'+addTo).prop('title', 'No scripture was returned, please fix scripture reference!');
					} else {
						jQuery('#'+addTo).html('<span class=\"uk-text-danger\">No scripture was returned, please fix scripture reference!</span>');
					}
				 },
			});
		}
		
		// Set Verses
		function setVerses(json,direction,addTo,diplayOption,version){
			var output = '';
				jQuery.each(json.book, function(index, value) {
					
					if(diplayOption == 'diplay_1') {
						output += '<p class=\"'+direction+'\">';
					} else if(diplayOption == 'diplay_4') {
						output +=  '<span class=\"'+direction+'\"><span class=\"ltr uk-text-muted\">'+value.book_name+'</span>&#160;';
						var chapter_nr = value.chapter_nr;
					} else {
						output += '<center><b>'+value.book_name+'&#160;'+value.chapter_nr+'</b></center><br/><p class=\"'+direction+'\">';
					}
					
					jQuery.each(value.chapter, function(index, value) {
						if(diplayOption == 'diplay_4') {
							output += '&#160;<span class=\"ltr uk-text-muted\">('+ chapter_nr+':'+value.verse_nr+ ')</span>&#160;';
						} else {
							output += '&#160;&#160;<small class=\"ltr\">' +value.verse_nr+ '</small>&#160;&#160;';
						}
						output += escapeHtmlEntities(value.verse);
						
						if(diplayOption == 'diplay_4') {
							output += '';
						} else {
							output += '<br />';
						}
					});
					if(diplayOption == 'diplay_4') {
						output += '</span> <small class=\"ltr uk-text-muted\"> (Taken From '+version+')</small>';
					} else {
						output += '<small class=\"uk-text-right\"> (Taken From '+version+')</small></p>';
					}
				});
				if(addTo){
					if(diplayOption == 'diplay_1') {
						jQuery('#'+addTo).prop('title', output);
					} else {
						jQuery('#'+addTo).html(output);
					}
				} else {
				jQuery('#'+addTo).prop('title', 'error!');
			}
		}
		// Set Chapter
		function setChapter(json,direction,addTo,diplayOption,version){
			var output = '<center><b>'+json.book_name+'&#160;'+json.chapter_nr+'</b></center><br/><p class=\"'+direction+'\">';
					jQuery.each(json.chapter, function(index, value) {
						output += '&#160;&#160;<small class=\"ltr\">' +value.verse_nr+ '</small>&#160;&#160;';
						output += escapeHtmlEntities(value.verse);
						output += '<br/>';
					});
					output += '<small class=\"uk-text-right\"> (Taken From '+version+')</small></p>';
					if(addTo){
						jQuery('#'+addTo).html(output);
					}
		}
		// Set Book
		function setBook(json,direction,addTo,diplayOption,version){
			var output = '';
				jQuery.each(json.book, function(index, value) {
					output += '<center><b>'+json.book_name+'&#160;'+value.chapter_nr+'</b></center><br/><p class=\"'+direction+'\">';
					jQuery.each(value.chapter, function(index, value) {
						output += '&#160;&#160;<small class=\"ltr\">' +value.verse_nr+ '</small>&#160;&#160;';
						output += escapeHtmlEntities(value.verse);
						output += '<br/>';
					});
					output += '<small class=\"uk-text-right\"> (Taken From '+version+')</small></p>';
				});
				if(addTo){
					jQuery('#'+addTo).html(output);
				}
		}
		function escapeHtmlEntities (str) {
		  if (typeof jQuery !== 'undefined') {
			// Create an empty div to use as a container,
			// then put the raw text in and get the HTML
			// equivalent out.
			return jQuery('<div/>').text(str).html();
		  }
		
		  // No jQuery, so use string replace.
		  return str
			.replace(/&/g, '&amp;')
			.replace(/>/g, '&gt;')
			.replace(/</g, '&lt;')
			.replace(/\"/g, '&quot;');
		}

		";
		$this->document->addScriptDeclaration($script); 
		
	}
}
