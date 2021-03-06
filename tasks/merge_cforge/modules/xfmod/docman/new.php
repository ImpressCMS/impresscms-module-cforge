<?php
/**
*
* SourceForge Documentaion Manager
*
* SourceForge: Breaking Down the Barriers to Open Source Development
* Copyright 1999-2001(c) VA Linux Systems
* http://sourceforge.net
*
* @version   $Id: new.php,v 1.17 2004/03/24 22:34:05 devsupaul Exp $
*
*/
 
 
/*
by Quentin Cregan, SourceForge 06/2000
*/
include_once("../../../mainfile.php");
 
$langfile = "docman.php";
require_once(ICMS_ROOT_PATH."/modules/xfmod/include/pre.php");
require_once(ICMS_ROOT_PATH."/modules/xfmod/docman/doc_utils.php");
$icmsOption['template_main'] = 'docman/xfmod_new.html';
 
if (!empty($_POST)) foreach($_POST as $k => $v) ${$k} = StopXSS($v);
if (!empty($_GET)) foreach($_GET as $k => $v) ${$k} = StopXSS($v);
 
// Commented out jcarey
// get current information
//project_check_access($group_id);
 
if (isset($_POST['op']))
$op = $_POST['op'];
elseif(isset($_GET['op']))
$op = $_GET['op'];
else
	$op = null;
 
if ($group_id)
{
	$project = group_get_object($group_id);
	$perm = $project->getPermission($icmsUser);
	 
	//group is private
	if (!$project->isPublic())
	{
		//if it's a private group, you must be a member of that group
		if (!$project->isMemberOfGroup($icmsUser) && !$perm->isSuperUser())
		{
			redirect_header(ICMS_URL."/", 4, _XF_PRJ_PROJECTMARKEDASPRIVATE);
			exit;
		}
	}
	 
	if ($op == "add")
	{
		 
		if (!$doc_group)
		{
			//cannot add a doc unless an appropriate group is provided
			redirect_header($_SERVER["HTTP_REFERER"], 4, "Error<br />"._XF_DOC_NOVALIDDOCGROUPSELECTED);
			exit;
		}
		 
		if (!$doc_name || !$description)
		{
			include_once(ICMS_ROOT_PATH."/header.php");
			$icmsTpl->assign("docman_header", docman_header($project, $group_id, _XF_DOC_NEWSUBMISSION));
			$icmsTpl->assign("feedback", "Please provide a name and description for your document.  Go back and try again.");
			include_once(ICMS_ROOT_PATH."/footer.php");
			exit;
		}
		 
		if (!$upload_instead && !$_FILES['file1'])
		{
			redirect_header($_SERVER["HTTP_REFERER"], 4, "Error<br />"._XF_PM_MISSINGREQPARAMETERS);
			exit;
		}
		 
		if ($icmsUser)
		{
			$user_id = $icmsUser->getVar("uid");
		}
		else
		{
			$user_id = 100;
		}
		if ($_FILES['file1']['tmp_name'] && $_FILES['file1']['tmp_name'] != 'none' && $_FILES['file1']['size'] > 0)
		{
			$tmp_name = $_FILES['file1']['tmp_name'];
			if ($error = VirusScan($tmp_name))
			{
				unlink($tmp_name);
				include_once(ICMS_ROOT_PATH."/header.php");
				$icmsTpl->assign("docman_header", docman_header($project, $group_id, _XF_DOC_NEWSUBMISSION));
				$icmsTpl->assign("feedback", $error);
				include_once(ICMS_ROOT_PATH."/footer.php");
				exit;
			}
			 
			$filename = $_FILES['file1']['name'];
			$file_url = $project->getUnixName()."/docs/".$_FILES['file1']['name'];
			$frs = new FRS($group_id);
			$frs->mkpath($project->getUnixName()."/docs", 0775);
			if (!$frs->addfile($file_url, $tmp_name, 0660))
			{
				include_once(ICMS_ROOT_PATH."/header.php");
				$icmsTpl->assign("docman_header", docman_header($project, $group_id, _XF_DOC_NEWSUBMISSION));
				$icmsTpl->assign("feedback", $frs->getErrorMessage());
				include_once(ICMS_ROOT_PATH."/footer.php");
				exit;
			}
		}
		else if($url)
		{
			$filename = $url;
		}
		else
		{
			include_once(ICMS_ROOT_PATH."/header.php");
			$icmsTpl->assign("docman_header", docman_header($project, $group_id, _XF_DOC_NEWSUBMISSION));
			$icmsTpl->assign("feedback", "You did not specify a file or a url to your documentation.  Please go back and try again.");
			include_once(ICMS_ROOT_PATH."/footer.php");
			exit;
		}
		$query = "INSERT INTO ".$icmsDB->prefix("xf_doc_data")." " ."(stateid,title,data,createdate,updatedate,created_by,doc_group,description) " ."VALUES('3'," // state = 3 == pending
		."'".$ts->addSlashes($doc_name)."'," ."'".$filename."'," //."'".$ts->makeTareaData4Save($data)."',"
		."'".time()."'," ."'".time()."'," ."'".$user_id."'," ."'".$doc_group."'," ."'".$ts->makeTboxData4Save($description)."')";
		 
		$res = $icmsDB->queryF($query);
		 
		//PROBLEM check the query
		include_once(ICMS_ROOT_PATH."/header.php");
		$icmsTpl->assign("docman_header", docman_header($project, $group_id, _XF_DOC_NEWSUBMISSION));
		if (!$res && $file_url)
		{
			if (!$frs) $frs = new FRS($group_id);
			$frs->rmfile($file_url);
			$icmsTpl->assign("feedback", "<div class='errorMsg'>"._XF_DOC_ERRORADDINGDOCUMENT.": ".$icmsDB->error()."</div>");
		}
		else
		{
			$icmsTpl->assign("feedback", "<div class='resultMsg'>"._XF_DOC_THANKYOUONSUBMISSION."<br/><br/><a href='".ICMS_URL."/modules/xfmod/docman/index.php?group_id=".$group_id."'>"._XF_G_BACK."</a></div>");
			//send email to doc editors so they know there is now doc available.
			$message['subject'] = sprintf(_XF_DOC_NEW_MAIL_SUBJECT, $project->getPublicName());
			$message['body'] = sprintf(_XF_DOC_NEW_MAIL_MESSAGE, $project->getPublicName(), rawurlencode("/modules/xfmod/docman/admin/index.php?group_id=$group_id"));
			$sql = "SELECT email FROM" ." ".$icmsDB->prefix("users")." AS u" .",".$icmsDB->prefix("xf_user_group")." AS ug" ." WHERE u.uid=ug.user_id" ." AND group_id=$group_id" ." AND ug.doc_flags=1";
			$result = $icmsDB->query($sql);
			while (list($email) = $icmsDB->fetchRow($result))
			{
				$email_array[] = $email;
			}
			xoopsForgeMail($icmsForge['noreply'], $icmsConfig['sitename'], $message['subject'], $message['body'], $email_array);
		}
		include(ICMS_ROOT_PATH."/footer.php");
		 
	}
	else
	{
		include_once(ICMS_ROOT_PATH."/header.php");
		$icmsTpl->assign("docman_header", docman_header($project, $group_id, _XF_DOC_ADDDOCUMENTATION));
		$content = "";
		if (get_group_count($group_id) > 0)
		{
			if (!$icmsUser)
			{
				$icmsTpl->assign("feedback", "<p>"._XF_DOC_NOTLOGGEDINNOCREDIT."</p>");
			}
			else if(!ini_get('file_uploads'))
			{
				$icmsTpl->assign("feedback", "<p>"._XF_DOC_UPLOAD_OFF."</p>");
			}
			else
				{
				$content .= "<p>Please give your documentation a relevant title and short description and then supply either a full URL <strong>or</strong> upload a file that contains the documentation. ";
				$content .= sprintf(_XF_DOC_MAX_UPLOAD, ini_get('upload_max_filesize'));
				$content .= ' '._XF_DOC_DISCLAIMER.': <a href="#" onclick="window.open(\'../include/contract.php\',\'contract\', \'width=750, height=350, resizable=yes, scrollbars=yes, toolbar=no, directories=no, location=no, status=no\');">'._XF_DOC_DISCLAIMERLINK.'</a></p>';
				$content .= "<form name='adddata' action='new.php' method='POST' enctype='multipart/form-data'>\n" . "<input type='hidden' name='group_id' value='".$group_id."'>\n" . "<input type='hidden' name='op' value='add'>\n" . "<table border='0' width='75%'><tr>\n" . "<td><strong>"._XF_DOC_DOCUMENTTITLE.":</strong></td>\n" . "<td><input type='text' name='doc_name' size='40' maxlength='255'></td></tr>\n" . "<tr><td><strong>"._XF_DOC_DESCRIPTION.":</strong></td>\n" . "<td><textarea name='description' cols='45' rows='6' maxlength='255'></textarea></td></tr>\n" . "<tr><td><strong>URL:</strong></td>\n" . "<td><input type='text' name='url' size='40'><br><br></td></tr>\n" . "<tr><td><strong>"._XF_DOC_FILE1.":</strong></td>\n" . "<td><input type='file' name='file1' size='40'><br><br></td></tr>\n" . "<tr><td><strong>"._XF_DOC_GROUPDOCBELONGSIN.":</strong></td>\n" . "<td>";
				$content .= display_groups_option($group_id);
				$content .= "</td></tr></table>\n" . "<input type=\"submit\" name=\"submit\" value=\""._XF_G_SUBMIT."\">" . "</form>\n";
			} // end if(project has doc categories)
		}
		else
		{
			$icmsTpl->assign("feedback", "<p>"._XF_DOC_MUSTDEFINEDOCCATEGORY."<BR />");
			 
			$perm = $project->getPermission($icmsUser);
			 
			// if an admin, prompt for adding a category
			if ($perm->isDocEditor() || $perm->isAdmin())
			{
				$content .= "<p>[ <a href='".ICMS_URL."/modules/xfmod/docman/admin/index.php?mode=editgroups&group_id=".$group_id."'>"._XF_DOC_ADDADOCGROUP."</a> ]";
			}
		}
		$icmsTpl->assign("content", $content);
		include(ICMS_ROOT_PATH."/footer.php");
	} // end else.
	 
}
else
{
	redirect_header($_SERVER["HTTP_REFERER"], 2, "Error<br />No Group");
	exit;
}
?>