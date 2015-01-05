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

// No direct access to this file
defined('_JEXEC') or die('Restricted access');

// Added for Joomla 3.0
if(!defined('DS')){
	define('DS',DIRECTORY_SEPARATOR);
};

/**
 * Script file of Code Box component
 */
class plgcontentloadscriptureInstallerScript
{
	protected $network = false;
	/**
	 * method to install the component
	 *
	 *
	 * @return void
	 */
	function install($parent)
	{

	}

	/**
	 * method to uninstall the component
	 *
	 * @return void
	 */
	function uninstall($parent)
	{
		echo '	<h2>What went wrong? Please let us know at <a href="mailto:support@vdm.io">support@vdm.io</a></h2>
					<p>We are committed to building applications that serve you well!
					<br />Visit us at <a href="https://www.vdm.io" target="_blank">VDM</a>';
	}

	/**
	 * method to update the component
	 *
	 * @return void
	 */
	function update($parent)
	{

	}

	/**
	 * method to run before an install/update/uninstall method
	 *
	 * @return void
	 */
	function preflight($type, $parent)
	{
		if ($type == 'uninstall') {
			return true;
		}
		
		$app = JFactory::getApplication();
		
		if (!file_exists(JPATH_ROOT.DS.'components'.DS.'com_getbible'.DS.'helpers'.DS.'script_checker.php')) {
			$this->network = true;
			$app->enqueueMessage('Please note that you will need to setup your network url in the plugin settings, or install the <a href="https://getbible.net/downloads" target="_blank">GetBible component</a> before continuing.', 'Message');
		}
		
		$jversion = new JVersion();
		if (!$jversion->isCompatible('3.0.0')) {
			$app->enqueueMessage('Please upgrade to at least Joomla! 3.0.0 before continuing!', 'error');
			return false;
		}
		
		return true;
	}

	/**
	 * method to run after an install/update/uninstall method
	 *
	 * @return void
	 */
	function postflight($type, $parent)
	{
		if ($type == 'install') {
			// Set Global Settings
			$db = JFactory::getDBO();
			$query = $db->getQuery(true);
			
			// Fields to update.
			if($this->network){
				$fields = array(
					$db->quoteName('params') . ' = ' . $db->quote('{"callClass":"getBible","diplayOption":"1","method":"1","network_url":"http:\/\/getbible.net","network_key":""}'),
					$db->quoteName('enabled') . ' = ' . $db->quote('1')
				);
			} else {
				$fields = array(
					$db->quoteName('params') . ' = ' . $db->quote('{"callClass":"getBible","diplayOption":"1","method":"0","network_url":"","network_key":""}'),
					$db->quoteName('enabled') . ' = ' . $db->quote('1')
				);
			}
			$conditions = array(
				$db->quoteName('type').' = '.$db->quote('plugin'), 
				$db->quoteName('element').' = '.$db->quote('loadscripture'),
				$db->quoteName('folder').' = '.$db->quote('content')
			);
			$query->update($db->quoteName('#__extensions'))->set($fields)->where($conditions);
 			$db->setQuery($query);
			
 			$result = $db->query();
			
			if($result){
				echo '	<h2 style="text-align:center">Congratulations! The Load Scripture plugin for getBible is now installed!</h2>
						<p style="text-align:center">It is already activated and can be used with the <b>&lt;span class=&quot;getBible&quot;&gt;1 John 3:16 (kjv)&lt;/span&gt;</b> string in your content.</p>';
			} else {
				$app = JFactory::getApplication();
				$app->enqueueMessage('There was an error setting the plugin status, please do it manualy!', 'error');
				return false;
			}
		}
		
		if ($type == 'update') {
					
			echo '	<h2 style="text-align:center">Congratulations! You have successfully updated the Load Scripture plugin for getBible!</h2>';
		}
	}
}