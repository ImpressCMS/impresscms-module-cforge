<?php
	/**
	*
	* SourceForge User's Personal Page
	*
	* Confirmation page for users' removing themselves from project.
	*
	* SourceForge: Breaking Down the Barriers to Open Source Development
	* Copyright 1999-2001 (c) VA Linux Systems
	* http://sourceforge.net
	*
	* @version   $Id: rmproject.php,v 1.3 2004/01/08 16:58:58 devsupaul Exp $
	*
	*/
	include_once ("../../mainfile.php");
	 
	$langfile = "my.php";
	require_once(ICMS_ROOT_PATH."/modules/xfmod/include/pre.php");
	 
	if (!$icmsUser)
		{
		redirect_header(ICMS_URL."/user.php", 2, _NOPERM . "called from ".__FILE__." line ".__LINE__ );
		exit;
	}
	 
	if (!empty($_POST)) foreach($_POST as $k => $v) ${$k} = StopXSS($v);
	if (!empty($_GET)) foreach($_GET as $k => $v) ${$k} = StopXSS($v);
	 
	$group = group_get_object($group_id);
	 
	if ($confirm)
	{
		 
		$user_id = $icmsUser->getVar("uid");
		 
		if (!$group->removeUser($user_id))
		{
			echo 'ERROR<br />'.$group->getErrorMessage();
			exit;
		}
		else
		{
			redirect_header(ICMS_URL."/modules/xfaccount/", 0, _XF_MY_TAKINGBACK);
			exit;
		}
	}
	 
	/*
	Main code
	*/
	 
	$perm = $group->getPermission($icmsUser );
	 
	if ($perm->isAdmin() )
	{
		redirect_header(ICMS_URL."/modules/xfaccount/", 20, sprintf(_XF_MY_PROJECTADMINERROR, $group_id));
		exit;
	}
	 
	$metaTitle = ": "_XF_MY_QUITTINGPROJECT;

$mhandler = icms_gethandler('module');
$icmsModule = $xoopsModule = $mhandler->getByDirname('xfaccount');
global $icmsModule;

	include("../../header.php");
	 
	echo "<H4 style='text-align:left;'>"._XF_MY_QUITTINGPROJECT."</H4>" ."<p>" ."<a href='".ICMS_URL."/modules/xfaccount/'>"._XF_MY_MYPERSONALPAGE."</a> | " ."<a href='".ICMS_URL."/modules/xfaccount/diary.php'>"._MY_XF_DIARYNOTES."</a> | " ."<a href='".ICMS_URL."/user.php'>"._XF_MY_MYACCOUNT."</a>" ."<p>";
	 
	echo '
		<H4>'._XF_MY_QUITTINGPROJECT.'</H4>
		<p>'._XF_MY_ABOUTTOREMOVE.'</p>
		 
		<table>
		<tr><td>
		 
		<form action="'.$_SERVER['PHP_SELF'].'" method="POST">
		<input type="hidden" name="confirm" value="1">
		<input type="hidden" name="group_id" value="'.$group_id.'">
		<input type="submit" value="'._XF_G_REMOVE.'">
		</form>
		 
		</td><td>
		 
		<form action="'.ICMS_URL.'/modules/xfaccount/" method="GET">
		<input type="submit" value="'._XF_G_CANCEL.'">
		</form>
		 
		</td></tr>
		</table>
		';
	 
	include("../../footer.php");
?>