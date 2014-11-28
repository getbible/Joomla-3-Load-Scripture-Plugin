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

class PlgContentLoadscripture extends JPlugin
{
	protected $component;
	protected $document;
	protected $com_params;
	protected $action;
	protected $diplayOption;
	protected $buket;
	protected $cURLheader;
	protected $referer;
	
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
		$callClass = $this->params->get('callClass', 'getBible');
		
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
			$this->buket['div'] 	= '';
			$this->buket['script'] 	= '';
			foreach ($matches as $match) {
				// $match[0] is full pattern match, $match[1] is the item id or alias
				$scripture 	= trim($match[1]);
				$target 	= preg_quote($match[0]);
				if ($scripture) {
					$output = $this->_setScript($scripture);
					// We should replace only first occurrence in order to allow positions with the same name to regenerate their content:
					$preg_replace["|$target|"] = $output;
				}
			}
			$article->text = preg_replace(array_keys($preg_replace), array_values($preg_replace), $article->text, 1);
			$article->text .=  $this->buket['div'];
			if($this->params->get('callOption') == 1) {
				$article->text .= ' <script type="text/javascript">'.$this->buket['script'].'</script> ';
			}
		}
		return true;
	}

	protected function _setScript($scripture)
	{
		// set defaults
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
			if( $this->params->get('method') == 1){
				$version 	= 'kjv';
			} else {
				$version 	= $this->com_params->get('defaultStartVersion');
			}
			
			$scripture 	= current(explode('[', $scripture));
		} else {
			$scripture 	= current(explode('(', $scripture));
		}
		// remove all white space
		$get_scripture 	= preg_replace('/\s+/', '', $scripture);
		
		if($this->params->get('callOption') == 2) {
			$request = '&p='.urlencode($get_scripture).'&v='.strtolower(urlencode($version));
			return $this->setCurl($scripture, $id, $request, $version);
		} else {
			$request = 'p='.urlencode($get_scripture).'&v='.strtolower(urlencode($version));
			return $this->setAjax($scripture, $id, $request, $version);
		}
	}
	
	protected function setCurl($scripture, $id, $request, $version)
	{
		$recievedResult = $this->getScriptureFormated($scripture, $request, $version);
		// load result based on desplay option
		if($this->diplayOption == 2){
			// offcanvas display option
			$this->buket['div'] .= '<div id="'.$id.'" class="uk-offcanvas"><div class="uk-offcanvas-bar"><div class="uk-panel" id="can_'.$id.'">'.$recievedResult.'</div></div></div>';
			// return the html
			return  '<a href="#'.$id.'" data-uk-offcanvas>'.$this->htmlEscape($scripture).'</a>';
			
		} else if($this->diplayOption == 3){
			// popup display option
			$this->buket['div'] .= '<div id="'.$id.'" class="uk-modal"><div class="uk-modal-dialog"><a class="uk-modal-close uk-close"></a><div class="uk-panel" id="pop_'.$id.'">'.$recievedResult.'</div></div></div>';
			// return the html
			return  '<a href="#'.$id.'" data-uk-modal>'.$this->htmlEscape($scripture).'</a>';
			
		} elseif($this->diplayOption == 4){
			// inline display option
			// return the html
			return '<span >'.$recievedResult.'</span>';			
		} else {
			// tooltip display option
			// return the html
			return '<span style="cursor: pointer;" data-uk-tooltip="{pos:\'bottom-left\'}" title="'.$this->htmlEscape($recievedResult).'">'.$this->htmlEscape($scripture).'</span>';
		}
	}
	
	protected function setAjax($scripture, $id, $request, $version)
	{
		// load result based on desplay option
		if($this->diplayOption == 2){
			// offcanvas display option
			$this->buket['div'] .= '<div id="'.$id.'" class="uk-offcanvas"><div class="uk-offcanvas-bar"><div class="uk-panel" id="can_'.$id.'"> loading '.$this->htmlEscape($scripture).'... </div></div></div>';
			$this->buket['script'] .= "jQuery('#".$id."').click(loadscripture('".$request."','can_".$id."','diplay_2', '".strtoupper($version)."'));";
			// return the html
			return  '<a href="#'.$id.'" data-uk-offcanvas>'.$this->htmlEscape($scripture).'</a>';
			
		} else if($this->diplayOption == 3){
			// popup display option
			$this->buket['div'] .= '<div id="'.$id.'" class="uk-modal"><div class="uk-modal-dialog"><a class="uk-modal-close uk-close"></a><div class="uk-panel" id="pop_'.$id.'"> loading '.$this->htmlEscape($scripture).'... </div></div></div>';
			$this->buket['script'] .= "jQuery('#".$id."').click(loadscripture('".$request."','pop_".$id."','diplay_3', '".strtoupper($version)."'));";
			// return the html
			return  '<a href="#'.$id.'" data-uk-modal>'.$this->htmlEscape($scripture).'</a>';
			
		} elseif($this->diplayOption == 4){
			// inline display option			
			$this->buket['script'] .= "loadscripture('".$request."','in_".$id."','diplay_4', '".strtoupper($version)."');";
			// return the html
			return '<span id="in_'.$id.'"> loading '.$this->htmlEscape($scripture).'... </span>';			
		} else {
			// tooltip display option
			$this->buket['script'] .= "jQuery('#".$id."').hover(loadscripture('".$request."','".$id."','diplay_1', '".strtoupper($version)."'));";
			// return the html
			return '<span style="cursor: pointer;" id="'.$id.'" data-uk-tooltip="{pos:\'bottom-left\'}" title="">'.$this->htmlEscape($scripture).'</span>';
		}
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
		// set the getBible component params
		if( $this->params->get('method') == 1){
			$this->com_params = false;
		} else {
			$this->com_params = &JComponentHelper::getParams('com_getbible');
		}
		// make sure all scripts are loaded
		if (!$this->css_loaded('uikit')) {
			if( $this->params->get('method') == 1){
				$this->document->addScript($this->params->get('network_url').'/media/com_getbible/css/uikit.min.css');
			} else {
				if ($this->com_params->get('jsonQueryOptions') == 1){
					$this->document->addStyleSheet(JURI::base( true ) .DS.'media'.DS.'com_getbible'.DS.'css'.DS.'uikit.min.css');
				} elseif ($this->com_params->get('jsonQueryOptions') == 2) {
					$this->document->addScript('https://getbible.net/media/com_getbible/css/uikit.min.css');
				} else {
					$this->document->addScript('http://getbible.net/media/com_getbible/css/uikit.min.css');
				}
			}
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
		if (!$this->js_loaded('jquery')) {	
			JHtml::_('jquery.framework');
		}
		if (!$this->js_loaded('uikit')) {
			if( $this->params->get('method') == 1){
				$this->document->addScript($this->params->get('network_url').'/media/com_getbible/js/uikit.min.js');
			} else {
				if ($this->com_params->get('jsonQueryOptions') == 1){
					$this->document->addScript(JURI::base( true ) .DS.'media'.DS.'com_getbible'.DS.'js'.DS.'uikit.min.js');
				} elseif ($this->com_params->get('jsonQueryOptions') == 2) {
					$this->document->addScript('https://getbible.net/media/com_getbible/js/uikit.min.js');
				} else {
					$this->document->addScript('http://getbible.net/media/com_getbible/js/uikit.min.js');
				}
			}
		}
		if($this->params->get('callOption') == 2) { // setup for the curl query
			// set security keys
			$key = '';
			if( $this->params->get('method') == 1){
				if(strlen($this->params->get('network_key')) > 0){
					$key = "&key=".$this->params->get('network_key'); 
				}
			} else {
				if($this->com_params->get('jsonAPIaccess')){
					$key = "&appKey=".JSession::getFormToken();
				}
			}
			if( $this->params->get('method') == 1){
				$this->action = $this->params->get('network_url').'/index.php?option=com_getbible&view=json'.$key;
			} else {
				if ($this->com_params->get('jsonQueryOptions') == 1){
					$this->action = 'index.php?option=com_getbible&view=json'.$key;
				} else {
					$this->action = 'https://getbible.net/index.php?option=com_getbible&view=json'.$key;
				}
			}
			$this->referer 	= JURI::root();
			// setup the curl header data once here
			$this->cURLheader[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
			$this->cURLheader[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
			$this->cURLheader[] = "Cache-Control: max-age=0";
			$this->cURLheader[] = "Connection: keep-alive";
			$this->cURLheader[] = "Keep-Alive: 300";
			$this->cURLheader[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
			$this->cURLheader[] = "Accept-Language: en-us,en;q=0.5";
			$this->cURLheader[] = "Pragma: ";
			 
		} else { // setup for the ajax query
			if (!$this->js_loaded('json')) {
				if( $this->params->get('method') == 1){
					$this->document->addScript($this->params->get('network_url').'/media/com_getbible/js/jquery.json.min.js');
				} else {
					if ($this->com_params->get('jsonQueryOptions') == 1){
						$this->document->addScript(JURI::base( true ) .DS.'media'.DS.'com_getbible'.DS.'js'.DS.'jquery.json.min.js');
					} elseif ($this->com_params->get('jsonQueryOptions') == 2) {
						$this->document->addScript('https://getbible.net/media/com_getbible/js/jquery.json.min.js');
					} else {
						$this->document->addScript('http://getbible.net/media/com_getbible/js/jquery.json.min.js');
					}
				}
			}
			if (!$this->js_loaded('jstorage')) {
				if( $this->params->get('method') == 1){
					$this->document->addScript($this->params->get('network_url').'/media/com_getbible/js/jstorage.min.js');
				} else {
					if ($this->com_params->get('jsonQueryOptions') == 1){
						$this->document->addScript(JURI::base( true ) .DS.'media'.DS.'com_getbible'.DS.'js'.DS.'jstorage.min.js');
					} elseif ($this->com_params->get('jsonQueryOptions') == 2) {
						$this->document->addScript('https://getbible.net/media/com_getbible/js/jstorage.min.js');
					} else {
						$this->document->addScript('http://getbible.net/media/com_getbible/js/jstorage.min.js');
					}
				}
			}
			// load the correct url
			if( $this->params->get('method') == 1){
				$this->action = $this->params->get('network_url').'/index.php?option=com_getbible&view=json';
			} else {
				if ($this->com_params->get('jsonQueryOptions') == 1){
					$this->action = 'index.php?option=com_getbible&view=json';
				} elseif ($this->com_params->get('jsonQueryOptions') == 2) {
					$this->action = 'https://getbible.net/index.php?option=com_getbible&view=json';
				} else {
					$this->action = 'https://getbible.net/index.php?option=com_getbible&view=json';
				}
			}
			$script = "";
			// set security keys
			if( $this->params->get('method') == 1){
				if(strlen($this->params->get('network_key')) > 0){
					$script .= "var key = '".$this->params->get('network_key')."';"; 
				}
			} else {
				if($this->com_params->get('jsonAPIaccess')){
					$key	= JSession::getFormToken();
					$script .= "var appKey = '".$key."';"; 
				}
			}
			// set inline option
			if($this->params->get('inlineOption')){
				$script .= "var inlineOption = ".$this->params->get('inlineOption').";";
			} else {
				$script .= "var inlineOption = 1;";
			}
			// load the javascript needed to get the text
			$script .= $this->javascriptFunc();
			$this->document->addScriptDeclaration($script); 
		}
	}
	
	protected function javascriptFunc()
	{
		return "
			function loadscripture(request,addTo,diplayOption,version) {
				var requestStore = request;
				// if memory is too full remove some
				if(jQuery.jStorage.storageSize() > 4500000){ 
					var storeIndex = jQuery.jStorage.index();
					// now remove the first once set when full
					jQuery.jStorage.deleteKey(storeIndex[5]);
					jQuery.jStorage.deleteKey(storeIndex[6]);
					jQuery.jStorage.deleteKey(storeIndex[7]);
					jQuery.jStorage.deleteKey(storeIndex[8]);
					jQuery.jStorage.deleteKey(storeIndex[9]);
				}
				if (typeof appKey !== 'undefined') {
					request = request+'&appKey='+appKey;
				} else if (typeof key !== 'undefined') {
					request = request+'&key='+key;
				}
				// Check if requestStore exists in the local storage
				var jsonStore = jQuery.jStorage.get(requestStore);
				if(!jsonStore){
					jQuery.ajax({
					 url:'".$this->action."',
					 dataType: 'jsonp',
					 data: request,
					 jsonp: 'getbible',
					 success:function(json){
						 // and save the result
						 jQuery.jStorage.set(requestStore,json);
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
				} else {
					 // set text direction
					 if (jsonStore.direction == 'RTL'){
						var direction = 'rtl';
					 } else {
						var direction = 'ltr'; 
					 }
					 // check jsonStore
					 if (jsonStore.type == 'verse'){
						setVerses(jsonStore,direction,addTo,diplayOption,version);
					 } else if (jsonStore.type == 'chapter'){
						 if(diplayOption == 'diplay_1') {
							jQuery('#'+addTo).prop('title', 'Whole chapter/s not allowed in tooltip mode, please select another.');
						} else {
							setChapter(jsonStore,direction,addTo,diplayOption,version);

						}
					 } else if (jsonStore.type == 'book'){
						 if(diplayOption == 'diplay_1') {
							jQuery('#'+addTo).prop('title', 'Whole book/s! not allowed in tooltip mode, please select another.');
						} else {
							setBook(jsonStore,direction,addTo,diplayOption,version);
						}
					 } 
				}
			}
			
			// Set Verses
			function setVerses(json,direction,addTo,diplayOption,version){
				var output = '';
					jQuery.each(json.book, function(index, value) {
						
						if(diplayOption == 'diplay_1') {
							output += '<p class=\"'+direction+'\">';
						} else if(diplayOption == 'diplay_4' && inlineOption == 1) {
							output +=  '<span class=\"'+direction+'\"><span class=\"ltr uk-text-muted\">'+value.book_name+'</span>&#160;';
							var chapter_nr = value.chapter_nr;
						} else {
							output += '<center><b>'+value.book_name+'&#160;'+value.chapter_nr+'</b></center><br/><p class=\"'+direction+'\">';
						}
						
						jQuery.each(value.chapter, function(index, value) {
							if(diplayOption == 'diplay_4' && inlineOption == 1) {
								output += '&#160;<span class=\"ltr uk-text-muted\">('+ chapter_nr+':'+value.verse_nr+ ')</span>&#160;';
							} else {
								output += '&#160;&#160;<small class=\"ltr\">' +value.verse_nr+ '</small>&#160;&#160;';
							}
							output += escapeHtmlEntities(value.verse);
							
							if(diplayOption == 'diplay_4' && inlineOption == 1) {
								output += '';
							} else {
								output += '<br />';
							}
						});
						if(diplayOption == 'diplay_4' && inlineOption == 1) {
							output += '</span> <small class=\"ltr uk-text-muted\"> (Taken From '+version+')</small>&#160;';
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
	}
	
	protected function js_loaded($script_name)
	{
		// UIkit check point
		if($script_name == 'uikit'){
			$app            	= JFactory::getApplication();
			$getTemplateName  	= $app->getTemplate('template')->template;
			
			if (strpos($getTemplateName,'yoo') !== false) {
				return true;
			}
		}
		
		$document 	=& JFactory::getDocument();
		$head_data 	= $document->getHeadData();
		foreach (array_keys($head_data['scripts']) as $script) {
			if (stristr($script, $script_name)) {
				return true;
			}
		}

		return false;
	}
	
	protected function css_loaded($script_name)
	{
		// UIkit check point
		if($script_name == 'uikit'){
			$app            	= JFactory::getApplication();
			$getTemplateName  	= $app->getTemplate('template')->template;
			
			if (strpos($getTemplateName,'yoo') !== false) {
				return true;
			}
		}
		
		$document 	=& JFactory::getDocument();
		$head_data 	= $document->getHeadData();
		
		foreach (array_keys($head_data['styleSheets']) as $script) {
			if (stristr($script, $script_name)) {
				return true;
			}
		}

		return false;
	}
	
	protected function getScriptureFormated($scripture, $request, $version)
	{
		// set the url to use in curl command
		$url 	= $this->action.$request;
		// get the result set from the set url
		$result = $this->getScriptureCurl($url);
		
		if(is_object($result)){
			// save the result (((we may want to store the results in the session, since this will save making the query again if page reload happens)))
			
			// set text direction
			if ($result->direction == 'RTL'){
				$direction = 'rtl';
			} else {
				$direction = 'ltr'; 
			}
			// check the type of result returned
			if ($result->type == 'verse'){
				return $this->setVerses($result,$direction,$version);
			} else if ($result->type == 'chapter'){
				if($this->diplayOption == '1') {
					return "Whole chapter's not allowed in tooltip mode, please select another.";
				} else {
					return $this->setChapter($result,$direction,$version);
				}
			} else if ($result->type == 'book'){
				if($this->diplayOption == '1') {
					return "Whole book's! not allowed in tooltip mode, please select another.";
				} else {
					return $this->setBook($result,$direction,$version);
				}
			} 
		}
		// if no results retuned return the following error messages
		if($this->diplayOption == '1') {
			return "No scripture was returned, please fix scripture reference!";
		} else {
			return '<span class="uk-text-danger">No scripture was returned, please fix scripture reference!</span>';
		}		
	}
	
	protected function setVerses($result,$direction,$version)
	{
		$output = '';
		foreach($result->book as $in => &$book) {
			if($this->diplayOption == 1) {
				$output .= '<p class="'.$direction.'">';
			} else if($this->diplayOption == 4 && $this->params->get('inlineOption') == 1) {
				$output .=  '<span class="'.$direction.'"><span class="ltr uk-text-muted">'.$book->book_name.'</span>&#160;';
			} else {
				$output .= '<center><b>'.$book->book_name.'&#160;'.$book->chapter_nr.'</b></center><br/><p class="'.$direction.'">';
			}
			
			foreach($book->chapter as $as => &$chapter) {
				if($this->diplayOption == 4 && $this->params->get('inlineOption') == 1) {
					$output .= '&#160;<span class="ltr uk-text-muted">('.$book->chapter_nr.':'.$chapter->verse_nr.')</span>&#160;';
				} else {
					$output .= '&#160;&#160;<small class="ltr">'.$chapter->verse_nr.'</small>&#160;&#160;';
				}
				$output .= $this->htmlEscape($chapter->verse);
				
				if($this->diplayOption == 4 && $this->params->get('inlineOption') == 1) {
					$output .= '';
				} else {
					$output .= '<br />';

				}
			}
			
			if($this->diplayOption == 4 && $this->params->get('inlineOption') == 1) {
				$output .= '</span> <small class="ltr uk-text-muted"> (Taken From '.$version.')</small>&#160;';
			} else {
				$output .= '<small class="uk-text-right"> (Taken From '.$version.')</small></p>';
			}
		}
		return $output;		
	}
	
	protected function setChapter($result,$direction,$version)
	{
		$output = '<center><b>'.$result->book_name.'&#160;'.$result->chapter_nr.'</b></center><br/><p class="'.$direction.'">';
		foreach($result->chapter as $in => &$chapter) {
			$output .= '&#160;&#160;<small class="ltr">'.$chapter->verse_nr.'</small>&#160;&#160;';
			$output .= $this->htmlEscape($chapter->verse);
			$output .= '<br/>';
		}
		$output .= '<small class="uk-text-right"> (Taken From '.$version.')</small></p>';
		return $output;
	}
	
	protected function setBook($result,$direction,$version)
	{
		$output = '';
		foreach($result->book as $in => &$book) {
			$output .= '<center><b>'.$result->book_name.'&#160;'.$result->chapter_nr.'</b></center><br/><p class="'.$direction.'">';
			foreach($book->chapter as $chapter) {
				$output .= '&#160;&#160;<small class="ltr">'.$chapter->verse_nr.'</small>&#160;&#160;';
				$output .= $this->htmlEscape($chapter->verse);
				$output .= '<br/>';
			}
			$output .= '<small class="uk-text-right"> (Taken From '.$version.')</small></p>';
		}
		return $output;
	}
	
	protected function getScriptureCurl($url)
	{
		// startup curl
		$curl 		= curl_init();
		// set curl options
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:33.0) Gecko/20100101 Firefox/33.0');
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->cURLheader);
		curl_setopt($curl, CURLOPT_REFERER, $this->referer);
		curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');
		curl_setopt($curl, CURLOPT_AUTOREFERER, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10);
		// get results from execution of curl command
		$results = curl_exec($curl);
		// close curl
		curl_close($curl);
		// fix the result set for json
		$results = rtrim($results, ";");
		$results = rtrim($results, ")");
		$results = ltrim($results, '(');
		// retun object
		return json_decode($results);
	}
}
