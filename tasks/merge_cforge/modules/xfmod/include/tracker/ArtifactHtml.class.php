<?php
	/**
	*
	* SourceForge Generic Tracker facility
	*
	* SourceForge: Breaking Down the Barriers to Open Source Development
	* Copyright 1999-2001(c) VA Linux Systems
	* http://sourceforge.net
	*
	* @version   $Id: ArtifactHtml.class,v 1.2 2003/11/26 16:08:12 jcox Exp $
	*
	*/
	require_once(ICMS_ROOT_PATH."/modules/xfmod/include/tracker/Artifact.class.php");
	require_once(ICMS_ROOT_PATH."/class/icmsUser.php");
	 
	class ArtifactHtml extends Artifact {
		/**
		*  ArtifactHtml() - constructor
		*
		*  Use this constructor if you are modifying an existing artifact
		*
		*  @param $ArtifactType object
		*  @param $artifact_id integer(primary key from database)
		*  @return true/false
		*/
		function ArtifactHtml(&$ArtifactType, $artifact_id = false)
		{
			return $this->Artifact($ArtifactType, $artifact_id);
		}
		 
		function showMessages()
		{
			global $sys_datefmt, $ts;
			 
			$result = $this->getMessages();
			$rows = $this->db->getRowsNum($result);
			 
			if ($rows > 0)
			{
				$content = "<table border='0' width='100%'>" ."<tr class='bg2'>" ."<td><strong>"._XF_G_MESSAGE."</strong></td>" ."</tr>";
				 
				for($i = 0; $i < $rows; $i++)
				{
					$content .= "<tr class='".($i%2 > 0?"bg1":"bg3")."'>" ."<td><pre>" ._XF_G_DATE.": ".date($sys_datefmt, unofficial_getDBResult($result, $i, 'adddate'))
					."<br />" ._XF_G_SENDER.": ".unofficial_getDBResult($result, $i, 'uname')."<br />" .$ts->makeTareaData4Show(unofficial_getDBResult($result, $i, 'body'))."</pre></td>" ."</tr>";
				}
				$content .= "</table>";
			}
			else
			{
				$content = "<h4 style='text-align:left;'>"._XF_G_NOFOLLOWUPS."</h4>";
			}
			return $content;
		}
		 
		function showHistory()
		{
			global $sys_datefmt, $artifact_cat_arr, $artifact_grp_arr, $artifact_res_arr;
			 
			$result = $this->getHistory();
			$rows = $this->db->getRowsNum($result);
			 
			if ($rows > 0)
			{
				 
				$content = "<table border='0' width='100%'>" ."<tr class='bg2'>" ."<td><strong>"._XF_G_FIELD."</strong></td>" ."<td><strong>"._XF_G_OLDVALUE."</strong></td>" ."<td><strong>"._XF_G_DATE."</strong></td>" ."<td><strong>"._XF_G_BY."</strong></td>" ."</tr>";
				 
				$artifactType = $this->getArtifactType();
				 
				for($i = 0; $i < $rows; $i++)
				{
					$field = unofficial_getDBResult($result, $i, 'field_name');
					$content .= "<tr class='".($i%2 > 0?"bg1":"bg3")."'><td>".$field."</td><td>";
					 
					if ($field == 'status_id')
					{
						$content .= $artifactType->getStatusName(unofficial_getDBResult($result, $i, 'old_value'));
					}
					else if($field == 'resolution_id')
					{
						if (!$artifact_res_arr["_".unofficial_getDBResult($result, $i, 'old_value')])
						{
							$artifact_res_arr["_".unofficial_getDBResult($result, $i, 'old_value')] = new ArtifactResolution($artifactType, unofficial_getDBResult($result, $i, 'old_value'));
						}
						$content .= $artifact_res_arr["_".unofficial_getDBResult($result, $i, 'old_value')]->getName();
					}
					else if($field == 'category_id')
					{
						if (!$artifact_cat_arr["_".unofficial_getDBResult($result, $i, 'old_value')])
						{
							$artifact_cat_arr["_".unofficial_getDBResult($result, $i, 'old_value')] = new ArtifactCategory($artifactType, unofficial_getDBResult($result, $i, 'old_value'));
						}
						$content .= $artifact_cat_arr["_".unofficial_getDBResult($result, $i, 'old_value')]->getName();
					}
					else if($field == 'artifact_group_id')
					{
						if (!$artifact_grp_arr["_".unofficial_getDBResult($result, $i, 'old_value')])
						{
							$artifact_grp_arr["_".unofficial_getDBResult($result, $i, 'old_value')] = new ArtifactGroup($artifactType, unofficial_getDBResult($result, $i, 'old_value'));
						}
						$content .= $artifact_grp_arr["_".unofficial_getDBResult($result, $i, 'old_value')]->getName();
					}
					else if($field == 'assigned_to')
					{
						$content .= icmsUser::getUnameFromId(unofficial_getDBResult($result, $i, 'old_value'));
					}
					else if($field == 'close_date')
					{
						$content .= date($sys_datefmt, unofficial_getDBResult($result, $i, 'old_value'));
					}
					else
					{
						$content .= unofficial_getDBResult($result, $i, 'old_value');
					}
					$content .= '</td>'. '<td>'. date($sys_datefmt, unofficial_getDBResult($result, $i, 'entrydate')) .'</td>'. '<td>'. unofficial_getDBResult($result, $i, 'uname'). '</td></tr>';
				}
				$content .= '
					</table>';
				 
			}
			else
			{
				$content = "<h4 style='text-align:left;'>"._XF_G_NOCHANGES."</h4>";
			}
			return $content;
		}
	}
?>