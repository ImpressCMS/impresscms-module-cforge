<?php
	 
	function cmp($a, $b)
	{
		if ($a['pubDate'] == $b['pubDate'])
		{
			return 0;
		}
		return($a['pubDate'] > $b['pubDate']) ? -1 :
		 1;
	}
	 
	function get_recent_discussions($group_id)
	{
		global $icmsForge;
		 
		if ($icmsForge['forum_type'] == "newsportal")
		{
			return get_nntp_discussions($group_id);
		}
		else
		{
			return get_forum_discussions($group_id);
		}
	}
	 
	include_once("../newsportal/config.inc");
	include_once("../newsportal/$file_newsportal");
	 
	function get_nntp_discussion_array($group_id)
	{
		global $icmsDB, $server, $port;
		 
		$sql = "SELECT DISTINCT g.unix_group_name, g.group_name, fnl.forum_name, fnl.group_id FROM" ." ".$icmsDB->prefix("xf_groups")." AS g" .",".$icmsDB->prefix("xf_trove_group_link")." AS gl" .",".$icmsDB->prefix("xf_forum_nntp_list")." AS fnl" ." WHERE gl.group_id=g.group_id" ." AND fnl.group_id=g.group_id" ." AND gl.trove_cat_id=".$group_id ." OR fnl.group_id=g.group_id" ." AND fnl.group_id=".$group_id;
		$result = $icmsDB->query($sql);
		$ns = OpenNNTPconnection($server, $port);
		socket_set_timeout($ns, 0, 300);
		$feeds = array();
		 
		while ($row = $icmsDB->fetchArray($result))
		{
			$group = $row['forum_name'];
			$id = getLastArticle($ns, $group);
			if (!$id) continue;
			for($i = 0; $i < 2; $i++)
			{
				$message = read_message(($id-$i), 0, $group);
				if (!$message)
				{
					break;
				}
				else
				{
					$tmp = array();
					$tmp['title'] = $message->header->subject;
					$tmp['group_name'] = $row['group_name'];
					$tmp['unix_group_name'] = $row['unix_group_name'];
					$tmp['description'] = $message->body[0];
					$tmp['author'] = $message->header->from."(".$message->header->name.")";
					$tmp['link'] = ICMS_URL."/modules/xfmod/newsportal/article.php?group_id=".$row['group_id']."&msg_id=".($id-$i)."&group=$group";
					$tmp['pubDate'] = $message->header->date;
					$tmp['comments'] = $tmp['link'];
					$feeds[] = $tmp;
				}
			}
		}
		usort($feeds, "cmp");
		return array_slice($feeds, 0, 15);
	}
	 
	function get_nntp_discussions($group_id)
	{
		global $icmsForge, $imgdir;
		 
		$feeds = get_nntp_discussion_array($group_id);
		 
		$discussion_content = "";
		if (count($feeds) > 0)
		{
			$discussion_content .= "<BR/><table border='0' cellpadding='0' cellspacing='0' valign='top' width='100%' class='bg2'><tr><td>\n";
			$discussion_content .= "<table width='100%' border='0' cellpadding='2' cellspacing='1'>\n";
			$discussion_content .= "<tr class='bg3'>";
			$discussion_content .= "<td><a href='rssfeed.php?group_id=$group_id'>" ."<img src='../newsportal/$imgdir/xml.gif' border=0 align='right' width=36 height=14 alt='XML'></a>" ."&nbsp;<strong>" ._XF_COMM_TOPIC."</strong></td>" ."<td align='center'><strong>" ._XF_COMM_LASTUPDATED."</strong></td>";
			$discussion_content .= "</tr>";
			foreach($feeds as $arr)
			{
				$discussion_content .= "<tr class='bg1'>";
				$discussion_content .= "<td><a href='".$arr['link']."'>";
				$discussion_content .= "<strong>".$arr["title"]."</strong>";
				$discussion_content .= "</a>&nbsp; &nbsp; &nbsp;<i>(" ."<a href='".ICMS_URL."/modules/xfmod/project/?".$arr['unix_group_name']."'>" .$arr["group_name"] ."</a>)</i></td>"; //&nbsp; &nbsp; &nbsp<i>".$arr["body"]."</i>
				 
				$discussion_content .= "<td align='center' nowrap>".date("Y-m-d", $arr["pubDate"])."</td>";
			}
			$discussion_content .= "</tr></table></td></tr><tr class='bg4'><td align='right'><span style='font-weight:bold;'>&raquo;&raquo;</span>&nbsp;<strong><a href='".ICMS_URL."/modules/xfmod/".$icmsForge['forum_type']."/?group_id=".$group_id."'>"._XF_COMM_VSTFRMS."</a></strong>";
			$discussion_content .= "</td></tr></table>";
		}
		else
		{
			$discussion_content .= "<BR/><strong>No current discussions available.</strong><BR/>";
		}
		 
		return $discussion_content;
	}
	 
	function send_rss_feed($group_id)
	{
		global $icmsDB;
		 
		$feeds = get_nntp_discussion_array($group_id);
		 
		$sql = "SELECT unix_group_name, group_name, short_description FROM ".$icmsDB->prefix("xf_groups")." WHERE group_id=".$group_id;
		$result = $icmsDB->query($sql);
		list($project_short_name, $project_name, $project_desc) = $icmsDB->fetchRow($result);
		 
		header("Content-Type: text/xml");
		echo '<?xml version="1.0" ?>';
		echo '<rss version="2.0">';
		echo '<channel>';
		echo '<title>Novell Forge : Community - '.$project_name.'</title>';
		echo '<link>'.ICMS_URL.'/modules/xfmod/community/?'.$project_short_name.'</link>';
		echo '<description>'.htmlspecialchars($project_desc).'</description>';
		echo '<copyright>Novell, Inc.   See terms at http://www.novell.com/company/legal/</copyright>';
		echo '<lastBuildDate>'.date("r").'</lastBuildDate>';
		echo '<generator>Novell Forge - forge.novell.com</generator>';
		 
		$valid_tags = array('title', 'description', 'author', 'link', 'pubDate', 'comments');
		if (count($feeds) > 0)
		{
			foreach($feeds as $feed)
			{
				echo "<item>";
				foreach($feed as $key => $value)
				{
					if (in_array($key, $valid_tags))
					{
						if ($key == 'pubDate') $value = date("r", $value);
						echo "<$key>".htmlspecialchars(utf8_encode($value))."</$key>";
					}
				}
				echo "</item>";
			}
		}
		else
		{
			echo '<item><title>No Available Articles</title><description>There are no articles in this forum yet.</description>';
			echo '<link>'.ICMS_URL.'modules/xfmod/newsportal/?group_id='.$group_id.'</link></item>';
		}
		echo '</channel></rss>';
		exit;
	}
	 
	function get_forum_discussions($group_id)
	{
		global $icmsDB, $options;
		$trove_gl = $icmsDB->prefix("xf_trove_group_link");
		$forum = $icmsDB->prefix("xf_forum");
		$forum_group = $icmsDB->prefix("xf_forum_group_list");
		 
		$query = "SELECT DISTINCT f.msg_id, fg.group_forum_id, fg.group_id, f.thread_id, f.posted_by, f.subject, f.body, f.date, f.most_recent_date " . "FROM ".$forum." f, ".$forum_group." fg, ".$trove_gl." gl " . "WHERE(fg.group_id='".$group_id."' AND fg.group_forum_id = f.group_forum_id)" . "OR (gl.trove_cat_id='".$group_id."' AND gl.group_id=fg.group_id AND fg.group_forum_id = f.group_forum_id)" . "ORDER BY f.date DESC LIMIT 10";
		 
		$discussion_content = "";
		if (!$result = $icmsDB->query($query, $options[0], 0))
		{
			$discussion_content .= "ERROR";
		}
		if ($icmsDB->getRowsNum($result) > 0)
		{
			$discussion_content .= "<BR/><table border='0' cellpadding='0' cellspacing='0' valign='top' width='100%' class='bg2'><tr><td>\n";
			$discussion_content .= "<table width='100%' border='0' cellpadding='2' cellspacing='1'>\n";
			$discussion_content .= "<tr class='bg3'>";
			if ($options[1] != 0)
			{
				$discussion_content .= "<td>&nbsp;<strong>" ._XF_COMM_FORUM."</strong></td>";
				 
			}
			 
			$discussion_content .= "<td>&nbsp;<strong>" ._XF_COMM_TOPIC."</strong></td><td align='center'><strong>" ._XF_COMM_LASTUPDATED."</strong></td>";
			if ($options[1] != 0)
			{
				$discussion_content .= "<td align='right'><strong>" ._XF_COMM_LPOST."</strong></td>";
			}
			$discussion_content .= "</tr>";
			while ($arr = $icmsDB->fetchArray($result))
			{
				$discussion_content .= "<tr class='bg1'>";
				if ($options[1] != 0)
				{
					$discussion_content .= "<td>&nbsp;<a href='".ICMS_URL."/modules/xfmod/forum/forum.php?forum_id=" . $arr["group_id"] . "'>";
					$discussion_content .= $arr["subject"];
					$discussion_content .= "</a></td>";
				}
				$forum_group = group_get_object($arr["group_id"]);
				 
				$discussion_content .= "<td><a href='".ICMS_URL."/modules/xfmod/forum/forum.php?forum_id=" . $arr["group_forum_id"] . "&amp;thread_id=" . $arr["thread_id"] . "'>";
				$discussion_content .= "<strong>".$arr["subject"]."</strong>";
				$discussion_content .= "</a>&nbsp; &nbsp; &nbsp;<i>(".$forum_group->getPublicName().")</i></td>"; //&nbsp; &nbsp; &nbsp<i>".$arr["body"]."</i>
				 
				$discussion_content .= "<td align='center' nowrap>".date("Y-m-d", $arr["date"])."</td>";
				if ($options[1] != 0)
				{
					$discussion_content .= "<td align='right'>".formatTimestamp($arr["date"], "m"). "</td>";
				}
			}
			$discussion_content .= "</tr></table></td></tr><tr class='bg4'><td align='right'><span style='font-weight:bold;'>&raquo;&raquo;</span>&nbsp;<strong><a href='".ICMS_URL."/modules/xfmod/forum/?group_id=".$group_id."'>"._XF_COMM_VSTFRMS."</a></strong>";
			$discussion_content .= "</td></tr></table>";
		}
		else
		{
			$discussion_content .= "<BR/><strong>No current discussions available.</strong><BR/>";
		}
		return $discussion_content;
	}
?>