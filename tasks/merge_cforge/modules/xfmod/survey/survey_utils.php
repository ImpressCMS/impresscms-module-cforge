<?php
	/**
	*
	* SourceForge Survey Facility
	*
	* SourceForge: Breaking Down the Barriers to Open Source Development
	* Copyright 1999-2001(c) VA Linux Systems
	* http://sourceforge.net
	*
	* @version   $Id: survey_utils.php,v 1.8 2004/04/16 22:39:29 jcox Exp $
	*
	*/
	 
	 
	/*
	Survey System
	By Tim Perdue, Sourceforge, 11/99
	*/
	 
	function survey_header($group, $title = 'Surveys', $is_admin_page = false)
	{
		global $icmsUser, $icmsTheme;
		global $icmsForgeErrorHandler, $survey_page;
		global $perm;
		 
		//meta tag information
		$metaTitle = ": $title - ".$group->getPublicName();
		$metaDescription = strip_tags($group->getDescription());
		$metaKeywords = project_getmetakeywords($group->getID());
		 
		$content = '';
		if ($group)
		{
			$content .= project_title($group);
			$content .= project_tabs('surveys', $group->getID());
			 
			if (!$group->usesSurvey())
			{
				$content .= _XF_SUR_TURNEDOFFSURVEYS;
			}
			 
			$bar = "";
			if ($perm->isAdmin())
			{
				$content .= "<strong><a href='".ICMS_URL. "/modules/xfmod/survey/admin/?group_id=". $group->getID()."'>"._XF_G_ADMIN."</a></strong>";
				$bar = "|";
				 
				if ($is_admin_page)
				{
					$content .= " $bar <a href='".ICMS_URL. "/modules/xfmod/survey/admin/add_survey.php?group_id=". $group->getID()."'>"._XF_SUR_ADDSURVEYS."</a></strong>";
					$bar = "|";
					 
					$content .= " $bar <a href='".ICMS_URL. "/modules/xfmod/survey/admin/browse_surveys.php?group_id=". $group->getID()."'>"._XF_SUR_EDITSURVEYS."</a></strong>";
					$bar = "|";
					 
					$content .= " $bar <a href='".ICMS_URL. "/modules/xfmod/survey/admin/add_question.php?group_id=". $group->getID()."'>"._XF_SUR_ADDQUESTIONS."</a></strong>";
					$bar = "|";
					 
					$content .= " $bar <a href='".ICMS_URL. "/modules/xfmod/survey/admin/show_questions.php?group_id=". $group->getID()."'>"._XF_SUR_EDITQUESTIONS."</a></strong>";
					$bar = "|";
					 
					$content .= " $bar <a href='".ICMS_URL. "/modules/xfmod/survey/admin/show_results.php?group_id=". $group->getID()."'>"._XF_SUR_SURVEYRESULTS."</a></strong>";
				}
			}
			$content .= "</strong><p>";
		}
		// end if(valid group id)
		 
		return $content;
		//$icmsForgeErrorHandler->displayFeedback();
	}
?>