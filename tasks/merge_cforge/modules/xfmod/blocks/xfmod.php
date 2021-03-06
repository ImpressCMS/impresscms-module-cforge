<?php
	include_once(ICMS_ROOT_PATH."/class/xoopsformloader.php");
	 
	function b_xfmod_show($options)
	{
		global $icmsDB;
		$ts = MyTextSanitizer::getInstance();
		 
		 
		$block = array();
		$block['title'] = _MB_XFMOD_LATEST_NEWS;
		$block['content'] = "";
		 
		$limit = $options[0];
		$show_summaries = ($options[1] == 1?true:false);
		$flat = ($options[2] == 1?true:false);
		$tail_headlines = $options[3];
		 
		$sql = "SELECT g.group_id,g.unix_group_name,g.group_name,g.type,u.uid,u.uname,nb.forum_id,nb.summary,nb.date,nb.details " ."FROM ".$icmsDB->prefix("users")." u,".$icmsDB->prefix("xf_news_bytes")." nb,".$icmsDB->prefix("xf_groups")." g " ."WHERE nb.is_approved=1 " ."AND u.uid=nb.submitted_by " ."AND nb.group_id=g.group_id " ."ORDER BY date DESC";
		 
		$result = $icmsDB->query($sql, $limit + $tail_headlines);
		$rows = $icmsDB->getRowsNum($result);
		 
		if (!$result || $rows < 1)
		{
			$block['content'] .= _MB_XFMOD_NO_FOUND;
			$block['content'] .= $icmsDB->error();
		}
		else
		{
			$block['content'] .= "<DL COMPACT>";
			for($i = 0; $i < $rows; $i++)
			{
				$item = $icmsDB->fetchArray($result);
				 
				if ($show_summaries && $limit)
				{
					//get the first paragraph of the story
					$arr = explode("\r\n", $item['details']);
					//if the first paragraph is short, and so are following paragraphs, add the next paragraph on
					if ((strlen($arr[0]) < 200) && (strlen($arr[1].$arr[2]) < 300) && (strlen($arr[2]) > 5))
					{
						$summ_txt = "<BR/>".$arr[0]."<BR/>".$arr[1]."<BR/>".$arr[2];
					}
					else
						{
						$summ_txt = "<BR/>".$arr[0];
					}
					//show the project name
					if ($type == 2)
					{
						$group_type = "/foundry";
					}
					else
						{
						$group_type = "/project";
					}
					$proj_name = " &nbsp; - &nbsp; <a href='".ICMS_URL."/xf".$group_type."/?".$item['unix_group_name']."'>".$item['group_name']."</a>";
				}
				else
					{
					$proj_name = "";
					$summ_txt = "";
				}
				 
				if (!$limit)
				{
					$block['content'] .= "<li><a href='".ICMS_URL."/modules/xfmod/forum/forum.php?forum_id=".$item['forum_id']."'><strong>".$ts->makeTareaData4Show($item['summary'])."</strong></a>";
					$block['content'] .= " &nbsp; <I>".date(_SHORTDATESTRING, $date)."</I><br>";
				}
				else
					{
					$block['content'] .= "<a href='".ICMS_URL."/modules/xfmod/forum/forum.php?forum_id=".$item['forum_id']."'><strong>".$ts->makeTareaData4Show($item['summary'])."</strong></a>";
					if (!$flat)
					{
						$block['content'] .= "<BR/>&nbsp;";
					}
					$block['content'] .= "&nbsp;&nbsp;&nbsp;<I><a href='".ICMS_URL."/userinfo.php?uid=".$item['uid']."'>".$item['uname']."</a> - " .date(_SHORTDATESTRING, $item['date'])."</I>" .$proj_name ."<BR />". $ts->makeTareaData4Show($summ_txt);
					 
					$sql = "SELECT COUNT(f.msg_id) AS count " ." FROM ".$icmsDB->prefix("xf_forum")." f" .",".$icmsDB->prefix("xf_forum_group_list")." fgl" .",".$icmsDB->prefix("xf_config")." c" ." WHERE f.group_forum_id=fgl.group_forum_id" ." AND fgl.is_public=1 " ." AND c.name='sysnews'" ." AND fgl.group_id=c.value" ." AND f.group_forum_id=".$item['forum_id'];
					 
					$res2 = $icmsDB->query($sql);
					list($num_comments) = $icmsDB->fetchRow($res2);
					 
					$block['content'] .= "<div align='center'> [ <a href='".ICMS_URL."/modules/xfmod/forum/forum.php?forum_id=".$item['forum_id']."'>";
					if ($num_comments)
					{
						$block['content'] .= $num_comments." ";
						$block['content'] .= ($num_comments == 1)?_MB_XFMOD_COMMENT:
						_MB_XFMOD_COMMENTS;
					}
					else
						{
						$block['content'] .= _MB_XFMOD_READ."/"._MB_XFMOD_COMMENT;
					}
					$block['content'] .= "</a> ]</div><HR width='100%' size='1' noshade>";
				}
				 
				if ($limit == 1 && $tail_headlines)
				{
					$block['content'] .= "<ul>";
				}
				if ($limit)
				{
					$limit--;
				}
			}
		}
		 
		if ($tail_headlines)
		{
			$block['content'] .= "</ul><HR width='100%' size='1' noshade>\n";
		}
		 
		$block['content'] .= "<div align='center'>" ."[ <a href='".ICMS_URL."/modules/xfmod/news/'>"._MB_XFMOD_NEWS_ARCHIVE."</a> ]</div>";
		 
		return $block;
	}
	 
	function b_xfmod_edit($options)
	{
		$summaries = new XoopsFormRadioYN(_MB_XFMOD_SHOW_SUMM, "options[1]", $options[1]);
		$flat = new XoopsFormRadioYN(_MB_XFMOD_SHOW_FLAT, "options[2]", $options[2]);
		$form = _MB_XFMOD_LIMIT_HEAD."&nbsp;<input type='text' name='options[]' value='".$options[0]."' />&nbsp;"._MB_XFMOD_ARTICLES."<br />";
		$form .= _MB_XFMOD_SHOW_SUMM."&nbsp;".$summaries->render()."<br />";
		$form .= _MB_XFMOD_SHOW_FLAT."&nbsp;".$flat->render()."<br />";
		$form .= _MB_XFMOD_TAIL_HEAD."&nbsp;<input type='text' name='options[]' value='".$options[3]."' />&nbsp;"._MB_XFMOD_ARTICLES;
		 
		return $form;
	}
	 
	 
	function b_xfmod_communities()
	{
		global $icmsDB;
		 
		$block = array();
		$block['title'] = _MB_XFMOD_COMM;
		$block['content'] = "<table border=0 cellpadding=0 cellspacing=0>";
		 
		$sql = "SELECT group_name, unix_group_name FROM ".$icmsDB->prefix("xf_groups")." WHERE type=2 AND is_public=1 AND status='A'";
		$result = $icmsDB->query($sql);
		$rows = $icmsDB->getRowsNum($result);
		if (!$result || $rows < 1)
		{
			$block['content'] .= "<tr><td>"._MB_XFMOD_NOCOMM."</td></tr>";
		}
		else
		{
			for($i = 0; $i < $rows; $i++)
			{
				$curr_group = $icmsDB->fetchArray($result);
				$block['content'] .= "<tr><td valign='top'><img src='".ICMS_URL."/modules/xfmod/images/n_arrows_grey.gif' width='7' height='7' border='0' alt=''>&nbsp;</td><td><a href='".ICMS_URL."/modules/xfmod/community/?".$curr_group['unix_group_name']."'>";
				$block['content'] .= $curr_group['group_name']."</a></td></tr>";
			}
		}
		$block['content'] .= "</table>";
		 
		return $block;
	}
	 
	function b_xfmod_pending_items()
	{
		global $icmsDB, $icmsUser;
		if (!$icmsUser) return array();
		 
		//$icmsDB = IcmsDatabaseFactory::getDatabaseConnection();
		$module_handler = icms_gethandler('module');
		$block = array();
		if ($icmsUser->isAdmin())
		{
			if ($module_handler->getCount(new Criteria('dirname', 'news')))
			{
				$result = $icmsDB->query("SELECT COUNT(*) FROM ".$icmsDB->prefix("stories")." WHERE published=0");
				if ($result)
				{
					list($rows) = $icmsDB->fetchRow($result);
					if ($rows > 0)
					{
						$block['modules'][0]['adminlink'] = ICMS_URL."/modules/news/admin/index.php?op=newarticle";
						$block['modules'][0]['pendingnum'] = $rows;
						$block['modules'][0]['lang_linkname'] = "<span style='color: #FF0000'>"._MB_XFMOD_SUBMS."</span>";
					}
				}
			}
			$result = $icmsDB->query("SELECT COUNT(*) FROM ".$icmsDB->prefix("icmscomments")." WHERE com_status=1");
			if ($result)
			{
				list($rows) = $icmsDB->fetchRow($result);
				if ($rows > 0)
				{
					$block['modules'][7]['adminlink'] = ICMS_URL."/modules/system/admin.php?module=0&status=1&fct=comments";
					$block['modules'][7]['pendingnum'] = $rows;
					$block['modules'][7]['lang_linkname'] = "<span style='color: #FF0000'>"._MB_XFMOD_COMPEND."</span>";
				}
			}
			$result = $icmsDB->query("SELECT group_id FROM ".$icmsDB->prefix("xf_groups")." WHERE status='P'");
			if ($result)
			{
				$rows = $icmsDB->getRowsNum($result);
				if ($rows > 0)
				{
					$block['modules'][8]['lang_linkname'] = "<span style='color: #FF0000'>"._MB_XFMOD_PPROJECTS."</span>";
					$block['modules'][8]['pendingnum'] = $rows;
					$block['modules'][8]['adminlink'] = ICMS_URL."/modules/xfmod/admin.php?fct=groups&op=GroupApprove";
				}
			}
			$date = time()-60 * 60 * 24 * 14;
			$query = "SELECT count(id) FROM ".$icmsDB->prefix("xf_news_bytes")." AS nb" .", ".$icmsDB->prefix("xf_groups")." AS g" ." WHERE is_approved=0 AND nb.group_id=g.group_id AND g.is_public=1 AND g.status='A' AND date>$date";
			$result = $icmsDB->query($query);
			if ($result)
			{
				list($rows) = $icmsDB->fetchRow($result);
				if ($rows > 0)
				{
					$block['modules'][9]['lang_linkname'] = "<span style='color: #FF0000'>"._MB_XFMOD_PNEWS."</span>";
					$block['modules'][9]['pendingnum'] = $rows;
					$block['modules'][9]['adminlink'] = ICMS_URL."/modules/xfmod/news/admin/?group_id=";
				}
			}
		}
		$query = "SELECT COUNT(*) FROM ".$icmsDB->prefix("priv_msgs")." WHERE to_userid = ".$icmsUser->getVar("uid")." AND read_msg=0";
		$result = $icmsDB->query($query);
		if ($result)
		{
			list($rows) = $icmsDB->fetchRow($result);
			if ($rows > 0)
			{
				$block['modules'][10]['lang_linkname'] = "<span style='color: #FF0000'>"._MB_XFMOD_MESSAGES."</span>";
				$block['modules'][10]['pendingnum'] = $rows;
				$block['modules'][10]['adminlink'] = ICMS_URL."/viewpmsg.php";
			}
		}
		return $block;
	}
	 
	function b_xfmod_howdoi()
	{
		//global $_SERVER['PHP_SELF'];
		global $icmsDB;
		$block['title'] = _MB_XFMOD_HOWDOI;
		$block['content'] = "<table border=0 cellpadding=0 cellspacing=0>";
		 
		$path = dirname($_SERVER['PHP_SELF']);
		$sql = "SELECT title, help_url FROM ".$icmsDB->prefix("xf_context_sensitive_help")." WHERE for_page='".$_SERVER['PHP_SELF']."' ORDER BY weight";
		$result = $icmsDB->query($sql);
		if ($result)
		{
			while ($row = $icmsDB->fetchArray($result))
			{
				$block['content'] .= "<tr><td class='newsText'><img src='".ICMS_URL."/modules/xfmod/images/n_arrows_grey.gif' width='7' height='7' border='0' alt=''>  <a href='".$row['help_url']."'>".$row['title']."</a></td></tr>";
			}
		}
		$sql = "SELECT title, help_url FROM ".$icmsDB->prefix("xf_context_sensitive_help")." WHERE for_page='".$path."' ORDER BY weight";
		$result = $icmsDB->query($sql);
		if ($result)
		{
			while ($row = $icmsDB->fetchArray($result))
			{
				$block['content'] .= "<tr><td class='newsText'><img src='".ICMS_URL."/modules/xfmod/images/n_arrows_grey.gif' width='7' height='7' border='0' alt=''>  <a href=\"".$row['help_url']."\">".$row['title']."</a></td></tr>";
			}
		}
		$block['content'] .= "<tr><td class='newsText'><img src='".ICMS_URL."/modules/xfmod/images/n_arrows_grey.gif' width='7' height='7' border='0' alt=''>  <a href='".ICMS_URL."/modules/xfmod/help/about.php'>Get Help?</a></td></tr></table>";
		return $block;
	}
?>