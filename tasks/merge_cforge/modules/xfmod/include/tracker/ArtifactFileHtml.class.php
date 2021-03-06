<?php
	/**
	*
	* SourceForge Generic Tracker facility
	*
	* SourceForge: Breaking Down the Barriers to Open Source Development
	* Copyright 1999-2001(c) VA Linux Systems
	* http://sourceforge.net
	*
	* @version   $Id: ArtifactFileHtml.class,v 1.2 2003/11/26 16:08:12 jcox Exp $
	*
	*/
	 
	require_once(ICMS_ROOT_PATH."/modules/xfmod/include/tracker/ArtifactFile.class.php");
	 
	class ArtifactFileHtml extends ArtifactFile {
		 
		/**
		*  ArtifactFileHtml() - constructor
		*
		*  Use this constructor if you are modifying an existing artifact
		*
		* @param $Artifact object
		*  @param $data associative array(all fields from artifact_file_user_vw) OR id from database
		*  @return true/false
		*/
		function ArtifactFileHtml(&$Artifact, $data = false)
		{
			return $this->ArtifactFile($Artifact, $data);
		}
		 
		function upload($input_file, $input_file_name, $input_file_type, $description)
		{
			$erm = util_check_fileupload($input_file);
			if ($erm != "OK")
			{
				$this->setError(_XF_TRK_AFHINVALIDFILENAME."(".$erm.")");
				return false;
			}
			$size = @filesize($input_file);
			if (($size > 20) && ($size < 256000))
			{
				//size is fine
				$fp = fopen($input_file, 'rb');
				$input_data = fread($fp, $size);
				fclose($fp);
				return $this->create($input_file_name, $input_file_type, $size, $input_data, $description);
			}
			else
			{
				//too big or small
				$this->setError(_XF_TRK_AFHFILESIZEINCORRECT);
				return false;
			}
		}
	}
	 
?>