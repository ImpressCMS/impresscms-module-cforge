<?php
/**
  *
  * SourceForge Code Snippets Repository
  *
  * SourceForge: Breaking Down the Barriers to Open Source Development
  * Copyright 1999-2001 (c) VA Linux Systems
  * http://sourceforge.net
  *
  * @version   $Id: snippet_utils.php,v 1.8 2004/02/27 15:23:33 devsupaul Exp $
  *
  */


/*
	Code Snippet System
	By Tim Perdue, Sourceforge, Jan 2000
*/
/*
$SCRIPT_LICENSE = array();
$SCRIPT_LICENSE[0] = 'GNU General Public License';
$SCRIPT_LICENSE[1] = 'GNU Library Public License';
$SCRIPT_LICENSE[2] = 'BSD License';
$SCRIPT_LICENSE[3] = 'MIT/X Consortium License';
$SCRIPT_LICENSE[4] = 'Artistic License';
$SCRIPT_LICENSE[5] = 'Mozilla Public License';
$SCRIPT_LICENSE[6] = 'Qt Public License';
$SCRIPT_LICENSE[7] = 'IBM Public License';
$SCRIPT_LICENSE[8] = 'Collaborative Virtual Workspace License';
$SCRIPT_LICENSE[9] = 'Ricoh Source Code Public License';
$SCRIPT_LICENSE[10] = 'Python License';
$SCRIPT_LICENSE[11] = 'zlib/libpng License';
$SCRIPT_LICENSE[12] = 'WebSite Only';
$SCRIPT_LICENSE[13] = 'Other';
*/
require_once(XOOPS_ROOT_PATH."/modules/xfmod/include/vars.php");

function addMsg($msg)
{
	global $feedback;

	if(strlen($feedback) < 1)
		$feedback = $msg;
	else
		$feedback = "<br/>$msg";
}


function snippet_header($title="Code Snippets", $description="", $keywords="") {
  global $xoopsUser, $xoopsForge, $feedback;

  $metaTitle=$title;
  $metaDescription=$description;
  $metaKeywords=$keywords;
  
  $content = "<H2>".$title."</H2>";
  $content .= "<P><B>";
	$content .= "<A HREF='".XOOPS_URL."/modules/xfsnippet/'>"._XF_SNP_BROWSE."</A>";

	if(!$xoopsUser) {
		$content .="
		 | <a href='".XOOPS_URL."/user.php?xoops_redirect=/modules/xfsnippet/submit.php'>"._XF_SNP_SUBMITNEWSNIPPET."</A>
		 | <a href='".XOOPS_URL."/user.php?xoops_redirect=/modules/xfsnippet/package.php'>"._XF_SNP_CREATEAPACKAGE."</A></B>";
	}
	else {
		$content .="
		 | <a href='submit.php'>"._XF_SNP_SUBMITNEWSNIPPET."</A>
		 | <a href='package.php'>"._XF_SNP_CREATEAPACKAGE."</A></B>";
	}
	if ($feedback)
		$content .= "<div class='errorMsg'>$feedback</div>";

	return $content;
}

function snippet_show_package_snippets($id,$package_version) {
	global $xoopsDB, $ts;
	
	//show the latest version
	$sql = "SELECT spi.snippet_version_id,sv.version,s.name,s.snippet_id,u.uname as user_name"
		    ." FROM ".$xoopsDB->prefix("xf_snippet")." s,".$xoopsDB->prefix("xf_snippet_version")." sv,".$xoopsDB->prefix("xf_snippet_package_item")." spi,".$xoopsDB->prefix("users")." u "
				." WHERE s.snippet_id=sv.snippet_id "
				." AND u.uid=sv.submitted_by "
				." AND sv.snippet_version_id=spi.snippet_version_id "
				." AND spi.snippet_package_version_id='$package_version'";

	$result = $xoopsDB->query($sql);
	$rows = $xoopsDB->getRowsNum($result);

	echo '
	<P>
	<H4>'._XF_SNP_SNIPPETSINPACKAGE.':</H4>
	<P>';

   echo "<table border='0' width='100%'>"
       ."<tr class='bg2'>"
       ."<td><b>"._XF_SNP_TITLE."</b></td>"
       ."<td><b>"._XF_SNP_DOWNLOADVERSION."</b></td>"
       ."<td><b>"._XF_SNP_AUTHOR."</b></td>"
  	   ."</tr>";

	if (!$result || $rows < 1) {
		echo $xoopsDB->error();
		echo '
			<TR><TD COLSPAN="4"><H4>'._XF_SNP_NOSNIPPETSINPACKAGE.'</H4></TD></TR>';
	} else {

		//get the newest version, so we can display it's code
		//$version = unofficial_getDBResult($result,0,'snippet_version_id');

		for ($i=0; $i<$rows; $i++) {
			echo '
			<TR class="'.($i%2>0?'bg1':'bg3').'">'
				.'<TD><A HREF="'.XOOPS_URL.'/modules/xfsnippet/detail.php?type=package&id='.$id.'&package_version='.$package_version
				.'&snippet_id='.unofficial_getDBResult($result,$i,'snippet_id')
				.'&version='.unofficial_getDBResult($result,$i,'snippet_version_id').'">'.
				$ts->makeTboxData4Show(unofficial_getDBResult($result,$i,'name')).'</A></TD>'
				.'<TD>'.$ts->makeTboxData4Show(unofficial_getDBResult($result,$i,'version')).'</TD>'
				.'<TD>'.unofficial_getDBResult($result,$i,'user_name').'</TD></TR>';
		}
	}
	echo '</TABLE>';

}

function snippet_show_package_details($id) {
	global $xoopsDB, $ts;

	$sql = "SELECT sp.name as name,sp.description as description,sl.name as language,sc.name as category"
	      ." FROM ".$xoopsDB->prefix("xf_snippet_package")." as sp,".$xoopsDB->prefix("xf_snippet_language")." as sl,".$xoopsDB->prefix("xf_snippet_category")." as sc "
				." WHERE snippet_package_id=".$id
				." AND sl.type_id=sp.language"
				." AND sc.type_id=sp.category";

	$result = $xoopsDB->query($sql);
	if($result){
		list($name,$description,$language,$category) = $xoopsDB->fetchRow($result);
	}
	if(!$name){
		return false;
	}
	
	echo snippet_header(_XF_SNP_SNIPPETSPACKAGES." - ".$name,$description,_XF_SNP_LANGUAGE.": ".$language.", "._XF_SNP_CATEGORY.": ".$category);

	echo '
		<p><table width="100%" border="0" cellspacing="0" cellpadding="0">
		<tr class="bg2">
		<td colspan="2" align=center><b>'._XF_SNP_DESCRIPTION.'</b></td>
		</tr>
		<tr>
		<td colspan="2">'.$ts->makeTareaData4Show($description).'</td>
		</tr>
		<tr><td colspan="2">&nbsp;</td></tr>
		<tr class="bg4">
		<td><b>'._XF_SNP_CATEGORY.'</b></td>
		<td><b>'._XF_SNP_LANGUAGE.'</b></td>
		</tr>
		<tr>
		<td>'.$category.'</td>
		<td>'.$language.'</td>
		</tr>
		</table>';
	return true;
}

function snippet_show_snippet_details($id)
{
	global $SCRIPT_LICENSE, $xoopsDB, $ts;

	$sql = "SELECT s.name,s.description,s.license,st.type_id,st.name,sl.name,sc.name".
		" from ".$xoopsDB->prefix(xf_snippet)." s, ".
		$xoopsDB->prefix(xf_snippet_language)." sl, ".
		$xoopsDB->prefix(xf_snippet_category)." sc, ".
		$xoopsDB->prefix(xf_snippet_type)." st".
		" where s.snippet_id='$id'".
		" and st.type_id=s.type".
		" and sl.type_id=s.language".
		" and sc.type_id=s.category";

	$sql_type = $xoopsDB->query($sql);
	$myra = $xoopsDB->fetchRow($sql_type);
	list($name,$description,$license,$type,$typename,$language,$category) = $myra;
	if(!$name){
		return false;
	}
	
	echo snippet_header(_XF_SNP_SNIPPETS." - ".$name,$description,_XF_SNP_TYPE.": ".$typename.", "._XF_SNP_LANGUAGE.": ".$language.", "._XF_SNP_CATEGORY.": ".$category);

	echo '
		<p><table width="100%" border="0" cellspacing="0" cellpadding="0">
		<tr class="bg2">
		<td colspan="4" align=center><b>'._XF_SNP_DESCRIPTION.'</b></td>
		</tr>
		<tr>
		<td colspan="2">'.$ts->makeTareaData4Show($description).'</td>
		</tr>
		<tr><td colspan="4">&nbsp;</td></tr>
		<tr class="bg4">
		<td><b>'._XF_SNP_TYPE.'</b></td>
		<td><b>'._XF_SNP_CATEGORY.'</b></td>
		<td><b>'._XF_SNP_LICENSE.'</b></td>
		<td><b>'._XF_SNP_LANGUAGE.'</b></td>
		</tr>
		<tr>
		<td>'.$typename.'</td>
		<td>'.$category.'</td>
		<td>'.$SCRIPT_LICENSE[$license].'</td>
		<td>'.$language.'</td>
		</tr>
		</table>';
	return true;
}

function snippet_show_snippet($id,$version){
	global $xoopsDB,$ts;
	/*
		show the latest version of this snippet's code
	*/
	$result = $xoopsDB->query("SELECT v.code,v.version"
								." FROM ".$xoopsDB->prefix("xf_snippet_version")." as v"
								.", ".$xoopsDB->prefix("xf_snippet")." as s"
								." WHERE s.snippet_id=v.snippet_id"
								." AND s.snippet_id=".$id
								." AND v.snippet_version_id=".$version);	

	echo '<p><B>'._XF_SNP_VERSION.': '.$ts->makeTboxData4Show(unofficial_getDBResult($result,0,'version')).'</B>';
//	echo ' - <a href="'.XOOPS_URL.'/modules/xfsnippet/download.php?id='.$id.'&version='.$version.'">Download</a>';	
	
	echo '<BR><TEXTAREA cols=84 rows=20>'.$ts->makeTboxData4Edit(unofficial_getDBResult($result,0,'code')).'</TEXTAREA>';
	
	
}
?>
