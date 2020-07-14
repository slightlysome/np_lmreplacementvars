<?php
/*
    LMReplacementVars Nucleus plugin
    Copyright (C) 2012-2014 Leo (http://nucleus.slightlysome.net/leo)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
	(http://www.gnu.org/licenses/gpl-2.0.html)
	
	See lmreplacementvars/help.html for plugin description, install, usage and change history.
*/
class NP_LMReplacementVars extends NucleusPlugin
{
	var $oSkin;
	var $skinType;
	var $commentFormShown; // TRUE if the commentform has ween shown

	// name of plugin 
	function getName()
	{
		return 'LMReplacementVars';
	}

	// author of plugin
	function getAuthor()
	{
		return 'Leo (http://nucleus.slightlysome.net/leo)';
	}

	// an URL to the plugin website
	// can also be of the form mailto:foo@bar.com
	function getURL()
	{
		return 'http://nucleus.slightlysome.net/plugins/lmreplacementvars';
	}

	// version of the plugin
	function getVersion()
	{
		return '1.1.0';
	}

	// a description to be shown on the installed plugins listing
	function getDescription()
	{
		return 'Gives alternative implementations of some key Nucleus core skin variables. 
			These implementations increase the available events for other plugins to facilitate,
			and makes it possible for plugins to refine the behavior of these skin variables without 
			duplicating the core skinvar functionality in each plugin.';
	}

	function supportsFeature ($what)
	{
		switch ($what)
		{
			case 'SqlTablePrefix':
				return 1;
			case 'SqlApi':
				return 1;
			case 'HelpPage':
				return 1;
			default:
				return 0;
		}
	}
	
	function hasAdminArea()
	{
		return 1;
	}
	
	function getMinNucleusVersion()
	{
		return '360';
	}
	
	function getTableList()
	{	
		return 	array();
	}
	
	function getEventList() 
	{ 
		return array('AdminPrePageFoot', 'InitSkinParse', 'PostAuthentication', 'PreSkinParse', 'PostAddComment', 'PostParseURL'); 
	}
	
	function install()
	{
		$sourcedataversion = $this->getDataVersion();

		$this->upgradeDataPerform(1, $sourcedataversion);
		$this->setCurrentDataVersion($sourcedataversion);
		$this->upgradeDataCommit(1, $sourcedataversion);
		$this->setCommitDataVersion($sourcedataversion);					
	}
	
	////////////////////////////////////////////////////////////////////////
	// Events

	function event_AdminPrePageFoot(&$data)
	{
		// Workaround for missing event: AdminPluginNotification
		$data['notifications'] = array();
			
		$this->event_AdminPluginNotification($data);
			
		foreach($data['notifications'] as $aNotification)
		{
			echo '<h2>Notification from plugin: '.htmlspecialchars($aNotification['plugin'], ENT_QUOTES, _CHARSET).'</h2>';
			echo $aNotification['text'];
		}
	}
	
	function event_AdminPluginNotification(&$data)
	{
		global $member, $manager;
		
		$actions = array('overview', 'pluginlist', 'plugin_LMReplacementVars');
		$text = "";
		
		if(in_array($data['action'], $actions))
		{			
			$sourcedataversion = $this->getDataVersion();
			$commitdataversion = $this->getCommitDataVersion();
			$currentdataversion = $this->getCurrentDataVersion();
		
			if($currentdataversion > $sourcedataversion)
			{
				$text .= '<p>An old version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin files are installed. Downgrade of the plugin data is not supported. The correct version of the plugin files must be installed for the plugin to work properly.</p>';
			}
			
			if($currentdataversion < $sourcedataversion)
			{
				$text .= '<p>The version of the '.$this->getName().' plugin data is for an older version of the plugin than the version installed. ';
				$text .= 'The plugin data needs to be upgraded or the source files needs to be replaced with the source files for the old version before the plugin can be used. ';

				if($member->isAdmin())
				{
					$text .= 'Plugin data upgrade can be done on the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' <a href="'.$this->getAdminURL().'">admin page</a>.';
				}
				
				$text .= '</p>';
			}
			
			if($commitdataversion < $currentdataversion && $member->isAdmin())
			{
				$text .= '<p>The version of the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' plugin data is upgraded, but the upgrade needs to commited or rolled back to finish the upgrade process. ';
				$text .= 'Plugin data upgrade commit and rollback can be done on the '.htmlspecialchars($this->getName(), ENT_QUOTES, _CHARSET).' <a href="'.$this->getAdminURL().'">admin page</a>.</p>';
			}
		}
		
		if($text)
		{
			array_push(
				$data['notifications'],
				array(
					'plugin' => $this->getName(),
					'text' => $text
				)
			);
		}
	}

	function event_InitSkinParse(&$data)
	{
		global $blog, $itemid, $manager;
		
		if($data['type'] == 'item')
		{
			$aExtraQuery = array();

			$eventdata = array(
					'blog' => &$blog,
					'extraquery' => &$aExtraQuery
				);
			$manager->notify('LMReplacementVars_PrevNextExtraQuery', $eventdata);
			
			$extraquery = implode(' and ', $aExtraQuery);
			
			if($extraquery)
			{
				$extraquery = ' and '.$extraquery.' ';
			}
			
			$orderquery = '';
			
			$eventdata = array(
					'blog' => &$blog,
					'orderquery' => &$orderquery
				);
			$manager->notify('LMReplacementVars_PrevNextOrderQuery', $eventdata);
			
			if($extraquery || $orderquery)
			{
				$this->_findPrevNextItem($itemid, $extraquery, $orderquery);
			}
		}
	}

	function event_PostAuthentication(&$data)
	{
		global $CONF;
		
		$location = RequestVar('location');

		$fullurl = 'http://'.serverVar('HTTP_HOST').serverVar('REQUEST_URI');
		$link = '';
		
		$editcommenturl = $CONF['AdminURL'].'index.php?action=commentedit';
		
		if(substr($fullurl, 0, strlen($editcommenturl)) == $editcommenturl)
		{
			$commentid = intRequestVar('commentid');
			
			$link = $this->getAdminURL().'?action=commentedit&commentid='.$commentid;
		}

		$editcommenturl = $CONF['AdminURL'].'?action=commentedit';
		
		if(substr($fullurl, 0, strlen($editcommenturl)) == $editcommenturl)
		{
			$commentid = intRequestVar('commentid');
			
			$link = $this->getAdminURL().'?action=commentedit&commentid='.$commentid;			
		}
		
		if($link)
		{
			if($location)
			{
				$link .= '&location='.urlencode($location);
			}
			redirect($link);
		}
	}

	function event_PostParseURL(&$data)
	{
	    $action = requestVar('action');

		if($action == 'plugin')
		{
			$this->_callPlugin();
		
			if(isset($_POST['action']))
			{
				$_POST['action'] = '';
			}

			if(isset($_GET['action']))
			{
				$_GET['action'] = '';
			}

			if(isset($_REQUEST['action']))
			{
				$_REQUEST['action'] = '';
			}
		}
	}
	
	function event_PreSkinParse(&$data)
	{
		$this->oSkin = &$data['skin'];
		$this->skinType = $data['type'];
	}

	function event_PostAddComment(&$data)
	{
		$commentid = $data['commentid'];

		$url = postVar('url');
		
		if($url && postVar('action') == 'addcomment')
		{
			$url = str_replace('<%commentid%>', $commentid, $url);
			$_POST['url'] = $url;
		}
	}

	////////////////////////////////////////////////////////////
	//  Handle skin vars
	function doSkinVar($skinType, $vartype, $templatename = '')
	{
		global $manager;

		$aArgs = func_get_args(); 
		$num = func_num_args();

		$aSkinVarParm = array();
		
		for($n = 3; $n < $num; $n++)
		{
			$parm = explode("=", func_get_arg($n));
			
			if(is_array($parm) && count($parm) == 2)
			{
				$aSkinVarParm[$parm['0']] = $parm['1'];
			}
		}

		if($templatename)
		{
			$template =& $manager->getTemplate($templatename);
		}
		else
		{
			$template = array();
		}

		switch (strtoupper($vartype))
		{
			case 'CATEGORYLIST':
				$this->doSkinVar_categorylist($skinType, $template, $aSkinVarParm);
				break;
			case 'BLOG':
				$this->doSkinVar_blog($skinType, $templatename, $aSkinVarParm);
				break;
			case 'ARCHIVELIST':
				$this->doSkinVar_archivelist($skinType, $templatename, 'month', $aSkinVarParm);
				break;
			case 'ARCHIVEDAYLIST':
				$this->doSkinVar_archivelist($skinType, $templatename, 'day', $aSkinVarParm);
				break;
			case 'ARCHIVEYEARLIST':
				$this->doSkinVar_archivelist($skinType, $templatename, 'year', $aSkinVarParm);
				break;
			case 'ARCHIVE':
				$this->doSkinVar_archive($skinType, $templatename, $aSkinVarParm);
				break;
			case 'COMMENTS':
				$this->doSkinVar_comments($skinType, $template, $aSkinVarParm);
				break;
			case 'COMMENTFORM':
				$this->doSkinVar_commentform($skinType, $templatename, $aSkinVarParm);
				break;
			case 'ITEM':
				$this->doSkinVar_item($skinType, $templatename, $aSkinVarParm);
				break;
			default:
				echo "Unknown vartype: ".$vartype;
		}
	}

	function doSkinVar_blog($skinType, $templatename, $aSkinVarParm)
	{
		global $blog, $manager, $startpos;
		
		if(isset($aSkinVarParm['amount']))
		{
			$amount = $aSkinVarParm['amount'];
		}
		else
		{
			$amount = 10;
		}
		
		list($limit, $offset) = sscanf($amount, '%d(%d)');
		
		if(isset($aSkinVarParm['category']))
		{
			$category = $aSkinVarParm['category'];
		}
		else
		{
			$category = '';
		}
		
		$this->_setBlogCategory($blog, $category);

		$aExtraQuery = array();
		$eventdata = array(
				'blog' => &$blog,
				'skinvarparm' => $aSkinVarParm,
				'extraquery' => &$aExtraQuery
			);
		$manager->notify('LMReplacementVars_BlogExtraQuery', $eventdata);
		
		$extraquery = implode(' and ', $aExtraQuery);
		
		if($extraquery)
		{
			$extraquery = ' and '.$extraquery.' ';
		}
		
		$orderquery = '';
		
		$eventdata = array(
				'blog' => &$blog,
				'skinvarparm' => $aSkinVarParm,
				'orderquery' => &$orderquery
			);
		$manager->notify('LMReplacementVars_BlogOrderQuery', $eventdata);
		
		$orderby = '';

		if($orderquery)
		{
			$column = strtok($orderquery, ' ');
			$direction = strtok(' ');
			$addtime = false;
			
			switch(strtoupper($column))
			{
				case 'TITLE':
					$orderby = 'i.ititle';
					$addtime = true;
					break;
				case 'TIME':
					$orderby = 'i.itime';
					break;
				default:
					$orderby = 'i.itime';
					break;
			}
			
			if($direction && $orderby)
			{
				switch(strtoupper($direction))
				{
					case 'ASC':
						$orderby .= ' ASC';
						break;
					case 'DESC':
						$orderby .= ' DESC';
						break;
					default:
						$orderby .= ' DESC';
						break;
				}
			}
			
			if($addtime)
			{
				$orderby .= ', i.itime DESC';
			}
		}
		
		$this->_preBlogContent('blog',$blog, array(
					'templatename' => $templatename,
					'extraquery' => $extraquery, 
					'limit' => $limit, 
					'offset' => $offset, 
					'startpos' => &$startpos,
					'skinvarparm' => $aSkinVarParm,
					'orderquery' => $orderquery));

		$blog->amountfound = $this->_readLogAmount($blog, $templatename, $limit, $extraquery, '', 1, 1, $offset, $startpos, $orderby);

		$this->_postBlogContent('blog',$blog, array(
					'templatename' => $templatename,
					'extraquery' => $extraquery, 
					'limit' => $limit, 
					'offset' => $offset, 
					'startpos' => $startpos, 
					'skinvarparm' => $aSkinVarParm,
					'orderquery' => $orderquery));
	}

	function doSkinVar_archive($skinType, $templatename, $aSkinVarParm)
	{
		global $blog, $manager, $archive;
		
		sscanf($archive,'%d-%d-%d', $year, $month, $day);

		if ($day == 0 && $month != 0) {
			$timestamp_start = mktime(0,0,0,$month,1,$year);
			$timestamp_end = mktime(0,0,0,$month+1,1,$year);  // also works when $month==12
		} elseif ($month == 0) {
			$timestamp_start = mktime(0,0,0,1,1,$year);
			$timestamp_end = mktime(0,0,0,12,31,$year);  // also works when $month==12
		} else {
			$timestamp_start = mktime(0,0,0,$month,$day,$year);
			$timestamp_end = mktime(0,0,0,$month,$day+1,$year);
		}
		$archivequery = ' and i.itime>=' . mysqldate($timestamp_start)
					 . ' and i.itime<' . mysqldate($timestamp_end);

		if(isset($aSkinVarParm['category']))
		{
			$category = $aSkinVarParm['category'];
		}
		else
		{
			$category = '';
		}
		
		$this->_setBlogCategory($blog, $category);

		$aExtraQuery = array();
		
		$eventdata = array(
				'blog' => &$blog,
				'skinvarparm' => $aSkinVarParm,
				'extraquery' => &$aExtraQuery
			);
		$manager->notify('LMReplacementVars_ArchiveExtraQuery', $eventdata);
		
		$extraquery = implode(' and ', $aExtraQuery);
		
		if($extraquery)
		{
			$extraquery = ' and '.$extraquery.' ';

			$archivequery .= $extraquery;
		}
		
		$orderquery = '';
		
		$eventdata = array(
				'blog' => &$blog,
				'skinvarparm' => $aSkinVarParm,
				'orderquery' => &$orderquery
			);
		$manager->notify('LMReplacementVars_ArchiveOrderQuery', $eventdata);

		$orderby = '';
		
		if($orderquery)
		{
			$column = strtok($orderquery, ' ');
			$direction = strtok(' ');
			$addtime = false;
			
			switch(strtoupper($column))
			{
				case 'TITLE':
					$orderby = 'i.ititle';
					$addtime = true;
					break;
				case 'TIME':
					$orderby = 'i.itime';
					break;
				default:
					$orderby = 'i.itime';
					break;
			}
			
			if($direction && $orderby)
			{
				switch(strtoupper($direction))
				{
					case 'ASC':
						$orderby .= ' ASC';
						break;
					case 'DESC':
						$orderby .= ' DESC';
						break;
					default:
						$orderby .= ' DESC';
						break;
				}
			}
			
			if($addtime)
			{
				$orderby .= ', i.itime DESC';
			}
		}

		$this->_preBlogContent('archive', $blog, array(
					'templatename' => $templatename,
					'extraquery' => $extraquery, 
					'skinvarparm' => $aSkinVarParm,
					'orderquery' => $orderquery));

		$this->_readLogAmount($blog, $templatename, 0, $archivequery, '', 1, 1, 0, 0, $orderby);

		$this->_postBlogContent('archive', $blog, array(
					'templatename' => $templatename,
					'extraquery' => $extraquery, 
					'skinvarparm' => $aSkinVarParm,
					'orderquery' => $orderquery));
	}

	function doSkinVar_archivelist($skinType, $templatename, $mode, $aSkinVarParm)
	{
		global $manager, $blog;
		
		if(isset($aSkinVarParm['limit']))
		{
			$limit = $aSkinVarParm['limit'];
		}
		else
		{
			$limit = 0;
		}
		
		if(isset($aSkinVarParm['category']))
		{
			$category = $aSkinVarParm['category'];
			
			if($category == 'all')
			{
				$category = '';
			}
		}
		else
		{
			$category = '';
		}

		$this->_setBlogCategory($blog, $category);
		$categoryid = $blog->getSelectedCategory();

		$linkparams = array();

		if ($categoryid) {
			$linkparams = array('catid' => $categoryid);
		}

		$aExtraQuery = array();
		
		$eventdata = array(
				'blog' => &$blog,
				'skinvarparm' => $aSkinVarParm,
				'extraquery' => &$aExtraQuery
			);
		$manager->notify('LMReplacementVars_ArchListExtraQuery', $eventdata);
		
		$extraquery = implode(' and ', $aExtraQuery);
		
		if($extraquery)
		{
			$extraquery = ' and '.$extraquery.' ';
		}
					
		$this->_preBlogContent('archivelist', $blog, array(
					'templatename' => $templatename,
					'extraquery' => $extraquery, 
					'limit' => $limit, 
					'skinvarparm' => $aSkinVarParm));

		$template =& $manager->getTemplate($templatename);
		
		$data = array();
		$data['blogid'] = $blog->getID();

		$tplt = isset($template['ARCHIVELIST_HEADER']) ? $template['ARCHIVELIST_HEADER']
		                                               : '';
		echo TEMPLATE::fill($tplt, $data);

		$query = 'SELECT itime, SUBSTRING(itime,1,4) AS Year, SUBSTRING(itime,6,2) AS Month, SUBSTRING(itime,9,2) as Day FROM '.sql_table('item'). ' i '
		. ' WHERE iblog=' . $blog->getID()
		. ' and itime <=' . mysqldate($blog->getCorrectTime())	// don't show future items!
		. ' and idraft=0'; // don't show draft items

		if ($categoryid)
		{
			$query .= ' and i.icat=' . $categoryid . ' ';
		}	
		
		if($extraquery)
		{
			$query .= $extraquery;
		}

		$query .= ' GROUP BY Year';
		if ($mode == 'month' || $mode == 'day')
		{
			$query .= ', Month';
		}
		
		if ($mode == 'day')
		{
			$query .= ', Day';
		}
		
		$query .= ' ORDER BY itime DESC';

		if ($limit > 0)
		{
			$query .= ' LIMIT ' . intval($limit);
		}
		
		$res = sql_query($query);

		while ($current = sql_fetch_object($res)) 
		{
			$current->itime = strtotime($current->itime);	// string time -> unix timestamp

			if ($mode == 'day') 
			{
				$archivedate = date('Y-m-d',$current->itime);
				$archive['day'] = date('d',$current->itime);
				$data['day'] = date('d',$current->itime);
				$data['month'] = date('m',$current->itime);
				$archive['month'] = $data['month'];
			} 
			elseif ($mode == 'year') 
			{
				$archivedate = date('Y',$current->itime);
				$data['day'] = '';
				$data['month'] = '';
				$archive['day'] = '';
				$archive['month'] = '';
			} 
			else 
			{
				$archivedate = date('Y-m',$current->itime);
				$data['month'] = date('m',$current->itime);
				$archive['month'] = $data['month'];
				$data['day'] = '';
				$archive['day'] = '';
			}

			$data['year'] = date('Y',$current->itime);
			$archive['year'] = $data['year'];

			$data['linkparams'] = $linkparams;
			
			$eventdata = array(
					'listitem' => &$data
				);
			$manager->notify('LMReplacementVars_ArchListItemLinkPar', $eventdata);

			$data['archivelink'] = createArchiveLink($blog->getID(),$archivedate, $data['linkparams']);

			$eventdata = array(
					'listitem' => &$data
				);
			$manager->notify('PreArchiveListItem', $eventdata);

			$temp = TEMPLATE::fill($template['ARCHIVELIST_LISTITEM'],$data);
			echo strftime($temp,$current->itime);
		}

		sql_free_result($res);

		$tplt = isset($template['ARCHIVELIST_FOOTER']) ? $template['ARCHIVELIST_FOOTER']
		                                               : '';
		echo TEMPLATE::fill($tplt, $data);
		
		$this->_postBlogContent('archivelist', $blog, array(
					'templatename' => $templatename,
					'extraquery' => $extraquery, 
					'limit' => $limit, 
					'skinvarparm' => $aSkinVarParm));
	}

	function doSkinVar_categorylist($skinType, &$template, $aSkinVarParm)
	{
		global $CONF, $manager, $blog, $archive, $archivelist;
		
		if(isset($aSkinVarParm['blogname']))
		{
			$b =& $manager->getBlog(getBlogIDFromName($aSkinVarParm['blogname']));
		}
		else
		{
			$b =& $blog;
		}
		
		$this->_preBlogContent('categorylist',$b);
		
		if ($b->getSelectedCategory()) {
			$nocatselected = 'no';
		}
		else {
			$nocatselected = 'yes';
		} 

		$data = array(
			'catid' => 0,
			'blogid' => $b->getID(),
			'catiscurrent' => $nocatselected,
			'currentcat' => $nocatselected,
			'linkparams' => array() 
		);

		$eventdata = array(
				'listitem' => &$data
			);
		$manager->notify('LMReplacementVars_CatListItemLinkPar', $eventdata);
		
		$extra = $data['linkparams'];
		
		$linkparams = array();
		if ($archive) 
		{
			$blogurl = createArchiveLink($b->getID(), $archive, $extra);
			$linkparams['blogid'] = $b->getID();
			$linkparams['archive'] = $archive;
		} 
		else if ($archivelist) 
		{
			$blogurl = createArchiveListLink($b->getID(), $extra);
			$linkparams['archivelist'] = $archivelist;
		} 
		else 
		{
			$blogurl = createBlogidLink($b->getID(), $extra);
			$linkparams['blogid'] = $b->getID();
		}

		echo TEMPLATE::fill((isset($template['CATLIST_HEADER']) ? $template['CATLIST_HEADER'] : null),
							array(
								'blogid' => $b->getID(),
								'blogurl' => $blogurl,
								'self' => $CONF['Self'],
								'catiscurrent' => $nocatselected,
								'currentcat' => $nocatselected 
							));

		$query = 'SELECT catid, cdesc as catdesc, cname as catname FROM '.sql_table('category').' WHERE cblog=' . $b->getID() . ' ORDER BY cname ASC';
		$res = sql_query($query);

		while ($data = sql_fetch_assoc($res)) 
		{
			$data['blogid'] = $b->getID();
			$data['blogurl'] = $blogurl;
			$data['self'] = $CONF['Self'];
			
			$data['catiscurrent'] = 'no';
			$data['currentcat'] = 'no'; 

			if ($b->getSelectedCategory()) 
			{
				if ($b->getSelectedCategory() == $data['catid']) {
					$data['catiscurrent'] = 'yes';
					$data['currentcat'] = 'yes';
				}
			}
			else 
			{
				global $itemid;
				if (intval($itemid) && $manager->existsItem(intval($itemid),0,0)) 
				{
					$iobj =& $manager->getItem(intval($itemid),0,0);
					$cid = $iobj['catid'];
					if ($cid == $data['catid']) 
					{
						$data['catiscurrent'] = 'yes';
						$data['currentcat'] = 'yes';
					}
				}
			}

			$data['linkparams'] = $linkparams;
			
			$eventdata = array(
					'listitem' => &$data
				);
			$manager->notify('LMReplacementVars_CatListItemLinkPar', $eventdata);

			$data['catlink'] = createLink(
								'category',
								array(
									'catid' => $data['catid'],
									'name' => $data['catname'],
									'extra' => $data['linkparams']
								)
							   );

			$eventdata = array(
					'listitem' => &$data
				);
			$manager->notify('PreCategoryListItem', $eventdata);

			echo TEMPLATE::fill((isset($template['CATLIST_LISTITEM']) ? $template['CATLIST_LISTITEM'] : null), $data);

		}

		sql_free_result($res);

		echo TEMPLATE::fill((isset($template['CATLIST_FOOTER']) ? $template['CATLIST_FOOTER'] : null),
							array(
								'blogid' => $b->getID(),
								'blogurl' => $blogurl,
								'self' => $CONF['Self'],
								'catiscurrent' => $nocatselected,
								'currentcat' => $nocatselected  
							));
		
		$this->_postBlogContent('categorylist',$b);
	}

	function doSkinVar_comments($skinType, $template, $aSkinVarParm)
	{
		global $itemid, $manager, $blog, $highlight;

		$actions = new ITEMACTIONS($blog);
		$parser = new PARSER($actions->getDefinedActions(),$actions);
		$actions->setTemplate($template);
		$actions->setParser($parser);
		$item = ITEM::getitem($itemid, 0, 0);
		$actions->setCurrentItem($item);

		$comments = new COMMENTS($itemid);
		$comments->setItemActions($actions);
		$this->_showComments($comments, $template, -1, 1, $highlight, $aSkinVarParm, $blog, $item);
	}

	function doSkinVar_commentform($skinType, $templatename, $aSkinVarParm)
	{
		global $CONF, $itemid, $catid, $member, $manager, $DIR_NUCLEUS, $blog;

		if($this->commentFormShown)
		{
			return;
		}
		
		$this->commentFormShown = true;
		
		if(isset($aSkinVarParm['commentid']))
		{
			$commentid = $aSkinVarParm['commentid'];
		}
		else
		{
			$commentid = false;
		}

		if($catid)
		{
			$linkparams = array('catid' => $catid);
		}
		else
		{
			$linkparams = array();
		}

		if(isset($aSkinVarParm['destinationurl']))
		{
			if($aSkinVarParm['destinationurl'])
			{
				$destinationurl = htmlspecialchars($aSkinVarParm['destinationurl'], ENT_QUOTES, _CHARSET);
			}
		}
		else
		{
			$destinationurl = createItemLink($itemid, $linkparams);
		}

		$actionurl = $CONF['ActionURL'];

		if(isset($_POST['body']))
		{
			$user = postVar('user');
			$userid = postVar('userid');
			$email = postVar('email');
			$body = postVar('body');
			$retry = true;
		}
		else
		{
			$user = cookieVar($CONF['CookiePrefix'] .'comment_user');
			$userid = cookieVar($CONF['CookiePrefix'] .'comment_userid');
			$email = cookieVar($CONF['CookiePrefix'] .'comment_email');
			$body = '';
			$retry = false;
		}
		
		$ticket = $manager->_generateTicket();
		
		$actions = $this->oSkin->getAllowedActionsForType($this->skinType);
		$handler = new ACTIONS($this->skinType);
		$parser = new PARSER($actions, $handler);
		$handler->setParser($parser);
		$handler->setSkin($this->oSkin);

		$handler->formdata = array(
			'destinationurl' => $destinationurl,	// url is already HTML encoded
			'actionurl' => htmlspecialchars($actionurl, ENT_QUOTES, _CHARSET),
			'itemid' => $itemid,
			'user' => htmlspecialchars($user, ENT_QUOTES, _CHARSET),
			'userid' => htmlspecialchars($userid, ENT_QUOTES, _CHARSET),
			'email' => htmlspecialchars($email, ENT_QUOTES, _CHARSET),
			'body' => htmlspecialchars($body, ENT_QUOTES, _CHARSET),
			'membername' => $member->getDisplayName(),
			'rememberchecked' => cookieVar($CONF['CookiePrefix'] .'comment_user')?'checked="checked"':'',
			'ticket' => htmlspecialchars($ticket, ENT_QUOTES, _CHARSET)
		);
		
		$item =& $manager->getItem($itemid,0,0);

		if($item['closed'] || !$blog->commentsEnabled()) 
		{
			$formtype = 'commentform-closed';
		}
		elseif(!$blog->isPublic() && !$member->isLoggedIn()) 
		{
			$formtype = 'commentform-closedtopublic';
		}
		elseif($member->isLoggedIn()) 
		{
			$formtype = 'commentform-loggedin';
		} 
		else 
		{
			$formtype = 'commentform-notloggedin';
		}

		$contents = '';
		
		$eventdata = array('type' => $formtype, 
				'formdata' => &$handler->formdata, 
				'contents' => &$contents, 
				'retry' => $retry, 
				'commentid' => $commentid,
				'templatename' => $templatename);
				
		$manager->notify('LMReplacementVars_PreForm', $eventdata);

		if(!$contents)
		{
			$filename = $DIR_NUCLEUS.'forms/'.$formtype.'.template';

			if(file_exists($filename)) 
			{
				$contents = file_get_contents($filename);
			}
		}
		
		array_push($handler->parser->actions,'formdata','text','callback','errordiv','ticket');

		$handler->level = $handler->level + 1;
		$handler->parser->parse($contents);
		$handler->level = $handler->level - 1;

		array_pop($handler->parser->actions);		// errordiv
		array_pop($handler->parser->actions);		// callback
		array_pop($handler->parser->actions);		// text
		array_pop($handler->parser->actions);		// formdata
		array_pop($handler->parser->actions);		// ticket
	}

	function doSkinVar_item($skinType, $templatename, $aSkinVarParm)
	{
		global $blog, $itemid, $highlight;
		
		$this->_setBlogCategory($blog, '');
		
		$this->_preBlogContent('item',$blog, array(
					'templatename' => $templatename,
					'skinvarparm' => $aSkinVarParm,
					'itemid' => $itemid));

		$r = $this->_showOneItem($blog, $itemid, $templatename, $highlight);
		if ($r == 0)
		{
			echo _ERROR_NOSUCHITEM;
		}
		
		$this->_postBlogContent('item',$blog, array(
					'templatename' => $templatename,
					'skinvarparm' => $aSkinVarParm,
					'itemid' => $itemid));
	}

	////////////////////////////////////////////////////////////
	//  Handle template skin vars
	function doTemplateVar(&$item, $vartype, $templatename = '')
	{
		global $manager;

		$aArgs = func_get_args(); 
		$num = func_num_args();

		$aTemplateVarParm = array();
		
		for($n = 2; $n < $num; $n++)
		{
			$parm = explode("=", func_get_arg($n));
			
			if(is_array($parm) && count($parm) == 2)
			{
				$aTemplateVarParm[$parm['0']] = $parm['1'];
			}
		}

		if($templatename)
		{
			$template =& $manager->getTemplate($templatename);
		}
		else
		{
			$template = array();
		}

		switch (strtoupper($vartype))
		{
			case 'COMMENTS':
				$this->doTemplateVar_comments($item, $template, $aTemplateVarParm);
				break;
			default:
				echo "Unknown vartype: ".$vartype;
		}
	}

	function doTemplateVar_comments(&$item, $template, $aTemplateVarParm)
	{
		if($this->itemsActions)
		{
			if($this->itemsActions->showComments && $this->itemsActions->blog->commentsEnabled())
			{
				if(isset($aTemplateVarParm['maxtoshow']))
				{
					$maxtoshow = $aTemplateVarParm['maxtoshow'];
				}
				else
				{
					$maxtoshow = $this->itemsActions->blog->getMaxComments();
				}
				
				if(!$template)
				{
					$template = $this->itemsActions->template;
				}
				
				$comments = new COMMENTS($item->itemid); 

				$comments->setItemActions($this->itemsActions);
				
				$this->_showComments($comments, $template, $maxtoshow, $this->itemsActions->currentItem->closed ? 0 : 1, $this->itemsActions->strHighlight, $aTemplateVarParm, $this->itemsActions->blog, $item);
			}
		}
		else
		{
			echo ' LMReplacementVars skin variable must be used to show items. ';
		}
	}

	////////////////////////////////////////////////////////////
	//  Handle template comments skin vars
	function doTemplateCommentsVar(&$item, &$comment, $vartype, $templatename = '')
	{
		global $manager;

		$aArgs = func_get_args(); 
		$num = func_num_args();

		$aTemplateCommentsVarParm = array();
		
		for($n = 4; $n < $num; $n++)
		{
			$parm = explode("=", func_get_arg($n));
			
			if(is_array($parm) && count($parm) == 2)
			{
				$aTemplateCommentsVarParm[$parm['0']] = $parm['1'];
			}
		}

		switch (strtoupper($vartype))
		{
			case 'COMMENTFORM':
				$this->doTemplateCommentsVar_commentform($item, $comment, $templatename, $aTemplateCommentsVarParm);
				break;
			default:
				echo "Unknown vartype: ".$vartype;
		}
	}
	
	function doTemplateCommentsVar_commentform(&$item, &$comment, $templatename, $aTemplateCommentsVarParm)
	{
		global $manager;
		
		$commentid = $comment['commentid'];

		$continue = false;
		
		$eventdata = array('item' => &$item, 'comment' => &$comment, 'continue' => &$continue);
		$manager->notify('LMReplacementVars_CommentFormInComment', $eventdata);

		if($continue)
		{
			$aTemplateCommentsVarParm['commentid'] = $commentid;
			
			$this->doSkinVar_commentform($this->skinType, $templatename, $aTemplateCommentsVarParm);
		}
	}
	
	////////////////////////////////////////////////////////////
	//  Private functions

	function _preBlogContent($type, &$blog, $param = array()) 
	{
		global $manager;

		$eventdata = array_merge(array('blog' => &$blog, 'type' => $type), $param);
		$manager->notify('PreBlogContent', $eventdata);
	}

	function _postBlogContent($type, &$blog, $param = array()) 
	{
		global $manager;
		
		$eventdata = array_merge(array('blog' => &$blog, 'type' => $type), $param);
		$manager->notify('PostBlogContent', $eventdata);
	}
	
	function _setBlogCategory(&$blog, $catname) 
	{
		global $catid;

		if ($catname != '')
		{
			$blog->setSelectedCategoryByName($catname);
		}
		else
		{
			$blog->setSelectedCategory($catid);
		}
	}

	function _readLogAmount(&$blog, $template, $amountEntries, $extraQuery, $highlight, $comments, $dateheads, $offset = 0, $startpos = 0, $orderQuery = '')
	{
		$query = $this->_getSqlBlog($blog, $extraQuery, '', $orderQuery);

		if ($amountEntries > 0) 
		{
			   $query .= ' LIMIT ' . intval($startpos + $offset).',' . intval($amountEntries);
		}

		return $this->_showUsingQuery($blog, $template, $query, $highlight, $comments, $dateheads);
	}

	function _getSqlBlog(&$blog, $extraQuery, $mode = '', $orderQuery = '')
	{
		if ($mode == '')
		{
			$query = 'SELECT i.inumber as itemid, i.ititle as title, i.ibody as body, m.mname as author, m.mrealname as authorname, i.itime, i.imore as more, m.mnumber as authorid, m.memail as authormail, m.murl as authorurl, c.cname as category, i.icat as catid, i.iclosed as closed';
		}
		else
		{
			$query = 'SELECT COUNT(*) as result ';
		}

		$query .= ' FROM '.sql_table('item').' as i, '.sql_table('member').' as m, '.sql_table('category').' as c'
			   . ' WHERE i.iblog='.$blog->blogid
			   . ' and i.iauthor=m.mnumber'
			   . ' and i.icat=c.catid'
			   . ' and i.idraft=0'	// exclude drafts
					// don't show future items
			   . ' and i.itime<=' . mysqldate($blog->getCorrectTime());

		if ($blog->getSelectedCategory())
		{
			$query .= ' and i.icat=' . $blog->getSelectedCategory() . ' ';
		}

		$query .= $extraQuery;

		if ($mode == '')
		{
			if(!$orderQuery)
			{
				$orderQuery = 'i.itime DESC';
			}
			$query .= ' ORDER BY '.$orderQuery;
		}

		return $query;
	}
	
	function _findPrevNextItem($itemid, $extraquery, $orderquery)
	{
		global $blog, $catid;
		global $itemidprev, $itemidnext, $itemtitlenext, $itemtitleprev;

		$query = 'SELECT itime, ititle FROM ' . sql_table('item') . ' WHERE inumber=' . intval($itemid);
		$res = sql_query($query);
		$obj = sql_fetch_object($res);
		
		$timestamp = strtotime($obj->itime);
		$title = $obj->ititle;

		if($blog->isValidCategory($catid)) 
		{
			$catextra = ' and icat=' . $catid;
		} 
		else 
		{
			$catextra = '';
		}

		if(!$orderquery)
		{
			$orderquery = 'time DESC';
		}
		
		$tok = strtok(strtoupper($orderquery), ' ');
		$column = $tok;

		$tok = strtok(' ');
		$direction = $tok;
		
		switch($column)
		{
			case 'TITLE':
				switch($direction)
				{
					case 'ASC':
						$prevquery = '(i.ititle < "'.$title.'" OR (i.ititle = "'.$title.'" AND i.itime < '.mysqldate($timestamp).'))';
						$prevorder = 'i.ititle DESC, i.itime DESC';
						
						$nextquery = '(i.ititle > "'.$title.'" OR (i.ititle = "'.$title.'" AND i.itime > '.mysqldate($timestamp).'))';
						$nextorder = 'i.ititle ASC, i.itime ASC';
						break;
					case 'DESC':
					default:
						$prevquery = '(i.ititle > "'.$title.'" OR (i.ititle = "'.$title.'" AND i.itime > '.mysqldate($timestamp).'))';
						$prevorder = 'i.ititle ASC, i.itime ASC';
					
						$nextquery = '(i.ititle < "'.$title.'" OR (i.ititle = "'.$title.'" AND i.itime < '.mysqldate($timestamp).'))';
						$nextorder = 'i.ititle DESC, i.itime DESC';
						break;
				}
				break;
			case 'TIME':
			default:
				switch($direction)
				{
					case 'ASC':
						$prevquery = 'i.itime < '.mysqldate($timestamp);
						$prevorder = 'i.itime DESC';
					
						$nextquery = 'i.itime > '.mysqldate($timestamp);
						$nextorder = 'i.itime ASC';
						break;
					case 'DESC':
					default:
						$prevquery = 'i.itime > '.mysqldate($timestamp);
						$prevorder = 'i.itime ASC';
					
						$nextquery = 'i.itime < '.mysqldate($timestamp);
						$nextorder = 'i.itime DESC';
						break;
				}
				break;
		}

		$query = $this->_getSqlBlog($blog, $extraquery.' AND '.$prevquery, '', $prevorder);
		$query .= " LIMIT 1";
	
		$res = sql_query($query);
		$obj = sql_fetch_object($res);

		if ($obj) 
		{
			$itemidprev = $obj->itemid;
			$itemtitleprev = $obj->title;
		}
		else
		{
			$itemidprev = 0;
			$itemtitleprev = '';
		}
		
		$query = $this->_getSqlBlog($blog, $extraquery.' AND '.$nextquery, '', $nextorder);
		$query .= " LIMIT 1";
	
		$res = sql_query($query);
		$obj = sql_fetch_object($res);

		if ($obj) 
		{
			$itemidnext = $obj->itemid;
			$itemtitlenext = $obj->title;
		}
		else
		{
			$itemidnext = 0;
			$itemtitlenext = '';
		}
	}

	function _showUsingQuery(&$blog, $templateName, $query, $highlight = '', $comments = 0, $dateheads = 1) 
	{
		global $CONF, $manager;

		$lastVisit = cookieVar($CONF['CookiePrefix'].'lastVisit');
		if ($lastVisit != 0)
		{
			$lastVisit = $blog->getCorrectTime($lastVisit);
		}

		// set templatename as global variable (so plugins can access it)
		global $currentTemplateName;
		$currentTemplateName = $templateName;

		$template =& $manager->getTemplate($templateName);

		// create parser object & action handler
		$actions = new ITEMACTIONS($blog);
		$parser = new PARSER($actions->getDefinedActions(),$actions);
		$actions->setTemplate($template);
		$actions->setHighlight($highlight);
		$actions->setLastVisit($lastVisit);
		$actions->setParser($parser);
		$actions->setShowComments($comments);

		$this->itemsActions = $actions;
		
		// execute query
		$items = sql_query($query);

		// loop over all items
		$old_date = 0;
		while ($item = sql_fetch_object($items)) 
		{
			$item->timestamp = strtotime($item->itime);	// string timestamp -> unix timestamp

			// action handler needs to know the item we're handling
			$actions->setCurrentItem($item);

			// add date header if needed
			if ($dateheads) 
			{
				$new_date = date('dFY',$item->timestamp);
				if ($new_date != $old_date) 
				{
					// unless this is the first time, write date footer
					$timestamp = $item->timestamp;
					if ($old_date != 0) 
					{
						$oldTS = strtotime($old_date);
						$eventdata = array(
							'blog'		=> &$blog,
							'timestamp'	=>  $oldTS
						);
						$manager->notify('PreDateFoot', $eventdata);
						$tmp_footer = strftime(isset($template['DATE_FOOTER'])?$template['DATE_FOOTER']:'', $oldTS);
						$parser->parse($tmp_footer);
						$eventdata = array(
							'blog'		=> &$blog,
							'timestamp'	=>  $oldTS
						);
						$manager->notify('PostDateFoot', $eventdata);
					}
					$eventdata = array(
						'blog'		=> &$blog,
						'timestamp'	=>  $timestamp
					);
					$manager->notify('PreDateHead', $eventdata);
					// note, to use templatvars in the dateheader, the %-characters need to be doubled in
					// order to be preserved by strftime
					$tmp_header = strftime((isset($template['DATE_HEADER']) ? $template['DATE_HEADER'] : null), $timestamp);
					$parser->parse($tmp_header);
					$eventdata = array(
						'blog'		=> &$blog,
						'timestamp'	=>  $timestamp
					);
					$manager->notify('PostDateHead', $eventdata);
				}
				$old_date = $new_date;
			}

			// parse item
			$eventdata = array(
				'blog' => &$blog,
				'item' => &$item
			);
			$parser->parse($template['ITEM_HEADER']);
			$manager->notify('PreItem', $eventdata);
			$parser->parse($template['ITEM']);
			$manager->notify('PostItem', $eventdata);
			$parser->parse($template['ITEM_FOOTER']);

		}

		$numrows = sql_num_rows($items);

		// add another date footer if there was at least one item
		if ( ($numrows > 0) && $dateheads )
		{
			$eventdata = array(
				'blog'		=> &$blog,
				'timestamp'	=> strtotime($old_date)
			);
			$manager->notify('PreDateFoot', $eventdata);
			$parser->parse($template['DATE_FOOTER']);
			$manager->notify('PostDateFoot', $eventdata);
		}

		sql_free_result($items);	// free memory

		return $numrows;
	}

	function _showComments(&$comments, $template, $maxToShow = -1, $showNone = 1, $highlight = '', $aSkinVarParm, &$blog, &$item) 
	{
		global $CONF, $manager;

		// create parser object & action handler
		$actions = new COMMENTACTIONS($comments);
		$parser = new PARSER($actions->getDefinedActions(),$actions);
		$actions->setTemplate($template);
		$actions->setParser($parser);

		$aExtraQuery['select'] = array();
		$aExtraQuery['from'] = array();
		$aExtraQuery['where'] = array();
		$aExtraQuery['orderby'] = array();
		
		$eventdata = array(
				'blog' => &$blog,
				'item' => &$item,
				'skinvarparm' => $aSkinVarParm,
				'extraquery' => &$aExtraQuery
			);
			
		$manager->notify('LMReplacementVars_CommentsExtraQuery', $eventdata);

		$selectextra = implode(', ', $aExtraQuery['select']);

		if($selectextra)
		{
			$selectextra = ', '.$selectextra;
		}

		$fromextra = implode(', ', $aExtraQuery['from']);
		
		if($fromextra)
		{
			$fromextra = ', '.$fromextra;
		}

		$whereextra = implode(' and ', $aExtraQuery['where']);
		
		if($whereextra)
		{
			$whereextra = ' and '.$whereextra.' ';
		}
		
		$orderbyextra = implode(', ', $aExtraQuery['orderby']);
		
		if(!$orderbyextra)
		{
			$orderbyextra = 'c.ctime ASC';
		}

		if ($maxToShow == 0) 
		{
			$comments->commentcount = $this->_amountComments($comments, $fromextra, $whereextra);
		}
		else 
		{
			$query =  'SELECT c.citem as itemid, c.cnumber as commentid, c.cbody as body, c.cuser as user, c.cmail as userid, c.cemail as email, c.cmember as memberid, c.ctime, c.chost as host, c.cip as ip, c.cblog as blogid'.$selectextra
				   . ' FROM '.sql_table('comment').' as c'.$fromextra
				   . ' WHERE c.citem=' . $comments->itemid.$whereextra
				   . ' ORDER BY '.$orderbyextra;

			$res = sql_query($query);
			$comments->commentcount = sql_num_rows($res);
		}

		// if no result was found
		if ($comments->commentcount == 0) 
		{
			// note: when no reactions, COMMENTS_HEADER and COMMENTS_FOOTER are _NOT_ used
			if ($showNone)
			{
				$parser->parse($template['COMMENTS_NONE']);
			}
			return 0;
		}

		// if too many comments to show
		if (($maxToShow != -1) && ($comments->commentcount > $maxToShow)) 
		{
			$parser->parse($template['COMMENTS_TOOMUCH']);
			return 0;
		}

		$parser->parse($template['COMMENTS_HEADER']);

		while ( $comment = sql_fetch_assoc($res) ) 
		{
			$comment['timestamp'] = strtotime($comment['ctime']);
			$actions->setCurrentComment($comment);
			$actions->setHighlight($highlight);
			
			$eventdata = array('comment' => &$comment);

			$manager->notify('PreComment', $eventdata);
			$parser->parse($template['COMMENTS_BODY']);
			$manager->notify('PostComment', $eventdata);
		}

		$parser->parse($template['COMMENTS_FOOTER']);

		sql_free_result($res);

		return $comments->commentcount;
	}

	function _amountComments(&$comments, $fromextra, $whereextra) 
	{
		$query =  'SELECT COUNT(*)'
			   . ' FROM '.sql_table('comment').' as c'.$fromextra
			   . ' WHERE c.citem='. $comments->itemid.$whereextra;
		$res = sql_query($query);
		$arr = sql_fetch_row($res);

		return $arr[0];
	}
	
	function _callPlugin()
	{
		global $manager, $errormessage;

		$pluginName = 'NP_' . requestVar('name');
		$actionType = requestVar('type');

		// 1: check if plugin is installed
		if ( !$manager->pluginInstalled($pluginName) )
		{
			doError(_ERROR_NOSUCHPLUGIN);
		}

		// 2: call plugin
		$pluginObject =& $manager->getPlugin($pluginName);

		if ( $pluginObject )
		{
			$error = $pluginObject->doAction($actionType);
		}
		else
		{
			$error = 'Could not load plugin (see actionlog)';
		}

		// doAction returns error when:
		// - an error occurred (duh)
		// - no actions are allowed (doAction is not implemented)
		
		if($error)
		{
			if(!is_array($error))
			{
				doError($error);
				exit;
			}
			else
			{
				$errormessage = $error['message'];
			}
        }
	}

	function _showOneItem(&$blog, $itemid, $templatename, $highlight) 
	{
		$extraquery = ' and inumber=' . intval($itemid);

		return $this->_readLogAmount($blog, $templatename, 0, $extraquery, $highlight, 1, 0, 0, 0, '');
	}

	////////////////////////////////////////////////////////////////////////
	// Plugin Upgrade handling functions
	function getCurrentDataVersion()
	{
		$currentdataversion = $this->getOption('currentdataversion');
		
		if(!$currentdataversion)
		{
			$currentdataversion = 0;
		}
		
		return $currentdataversion;
	}

	function setCurrentDataVersion($currentdataversion)
	{
		$res = $this->setOption('currentdataversion', $currentdataversion);
		$this->clearOptionValueCache(); // Workaround for bug in Nucleus Core
		
		return $res;
	}

	function getCommitDataVersion()
	{
		$commitdataversion = $this->getOption('commitdataversion');
		
		if(!$commitdataversion)
		{
			$commitdataversion = 0;
		}

		return $commitdataversion;
	}

	function setCommitDataVersion($commitdataversion)
	{	
		$res = $this->setOption('commitdataversion', $commitdataversion);
		$this->clearOptionValueCache(); // Workaround for bug in Nucleus Core
		
		return $res;
	}

	function getDataVersion()
	{
		return 1;
	}
	
	function upgradeDataTest($fromdataversion, $todataversion)
	{
		// returns true if rollback will be possible after upgrade
		$res = true;
				
		return $res;
	}
	
	function upgradeDataPerform($fromdataversion, $todataversion)
	{
		// Returns true if upgrade was successfull
		
		for($ver = $fromdataversion; $ver <= $todataversion; $ver++)
		{
			switch($ver)
			{
				case 1:
					$this->createOption('currentdataversion', 'currentdataversion', 'text','0', 'access=hidden');
					$this->createOption('commitdataversion', 'commitdataversion', 'text','0', 'access=hidden');
					$res = true;
					break;
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}
		
		return true;
	}
	
	function upgradeDataRollback($fromdataversion, $todataversion)
	{
		// Returns true if rollback was successfull
		for($ver = $fromdataversion; $ver >= $todataversion; $ver--)
		{
			switch($ver)
			{
				case 1:
					$res = true;
					break;
				
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}

		return true;
	}

	function upgradeDataCommit($fromdataversion, $todataversion)
	{
		// Returns true if commit was successfull
		for($ver = $fromdataversion; $ver <= $todataversion; $ver++)
		{
			switch($ver)
			{
				case 1:
					$res = true;
					break;
				default:
					$res = false;
					break;
			}
			
			if(!$res)
			{
				return false;
			}
		}
		return true;
	}
}
?>
