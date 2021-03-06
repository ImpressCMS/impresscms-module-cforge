<?php
	/**
	* ArtifactCanned.class - Class to handle canned responses
	*
	* SourceForge: Breaking Down the Barriers to Open Source Development
	* Copyright 1999-2001(c) VA Linux Systems
	* http://sourceforge.net
	*
	* @version   $Id: ArtifactCanned.class,v 1.1.1.1 2003/08/01 19:13:48 devsupaul Exp $
	*
	*/
	require_once(ICMS_ROOT_PATH."/modules/xfmod/include/Error.class.php");
	 
	class ArtifactCanned extends Error {
		var $db;
		/**
		* The artifact type object
		*
		* @var  object $ArtifactType
		*/
		var $ArtifactType;
		 
		/**
		* Array of artifact data
		*
		* @var  array $data_array
		*/
		var $data_array;
		 
		/**
		*  ArtifactCanned() - constructor
		*
		*  Use this constructor if you are modifying an existing artifact
		*
		* @param object The Artifact Type object
		*  @param array (all fields from artifact_file_user_vw) OR id from database
		*  @return true/false
		*/
		function ArtifactCanned(&$ArtifactType, $data = false)
		{
			global $icmsDB;
			 
			$this->db = $icmsDB;
			$this->Error();
			 
			//was ArtifactType legit?
			if (!$ArtifactType || !is_object($ArtifactType))
			{
				$this->setError('ArtifactCanned: No Valid ArtifactType');
				return false;
			}
			//did ArtifactType have an error?
			if ($ArtifactType->isError())
			{
				$this->setError('ArtifactCanned: '.$Artifact->getErrorMessage());
				return false;
			}
			$this->ArtifactType = $ArtifactType;
			 
			if ($data)
			{
				if (is_array($data))
				{
					$this->data_array = $data;
					return true;
				}
				else
				{
					if (!$this->fetchData($data))
					{
						return false;
					}
					else
					{
						return true;
					}
				}
			}
			else
			{
				$this->setError('No ID Passed');
			}
		}
		 
		/**
		* create() - create a new item in the database
		*
		* @param string The item title
		* @param string The item body
		*  @return id on success / false on failure
		*/
		function create($title, $body)
		{
			global $ts;
			 
			//
			// data validation
			//
			if (!$title || !$body)
			{
				$this->setError('ArtifactCanned: '._XF_TRK_AC_NAMEASSIGNEEREQUIRED);
				return false;
			}
			if (!$this->ArtifactType->userIsAdmin())
			{
				$this->setError(_XF_G_PERMISSIONDENIED);
				return false;
			}
			 
			$sql = "INSERT INTO ".$this->db->prefix("xf_artifact_canned_responses")."(group_artifact_id,title,body) " ."VALUES('".$this->ArtifactType->getID()."'," ."'". $ts->makeTareaData4Save($title) ."','". $ts->makeTareaData4Save($body) ."')";
			 
			$result = $this->db->queryF($sql);
			 
			if ($result && unofficial_getAffectedRows($result) > 0)
			{
				$this->clearError();
				return true;
			}
			else
			{
				$this->setError($this->db->error());
				return false;
			}
		}
		 
		/**
		* fetchData() - re-fetch the data for this ArtifactCanned from the database
		*
		* @param int  Data ID
		* @return true/false
		*/
		function fetchData($id)
		{
			$res = $this->db->query("SELECT * FROM ".$this->db->prefix("xf_artifact_canned_responses")." WHERE id='$id'");
			if (!$res || $this->db->getRowsNum($res) < 1)
			{
				$this->setError('ArtifactCanned: Invalid ArtifactCanned ID');
				return false;
			}
			$this->data_array = $this->db->fetchArray($res);
			return true;
		}
		 
		/**
		* getArtifactType() - get the ArtifactType Object this ArtifactCanned message is associated with
		*
		* @return ArtifactType
		*/
		function getArtifactType()
		{
			return $this->ArtifactType;
		}
		 
		/**
		* getID() - get this ArtifactCanned message's ID
		*
		* @return the id #
		*/
		function getID()
		{
			return $this->data_array['id'];
		}
		 
		/**
		* getTitle() - get the title
		*
		* @return text title
		*/
		function getTitle()
		{
			return $this->data_array['title'];
		}
		 
		/**
		* getBody() - get the body of this message
		*
		* @return text message body
		*/
		function getBody()
		{
			return $this->data_array['body'];
		}
		 
		/**
		*  update() - update an ArtifactCanned message
		*
		*  @param string Title of the message
		*  @param string Body of the message
		*  @return true/false
		*/
		function update($title, $body)
		{
			global $ts;
			 
			if (!$this->ArtifactType->userIsAdmin())
			{
				$this->setError(_XF_G_PERMISSIONDENIED);
				return false;
			}
			if (!$title || !$body)
			{
				$this->setError(_XF_TRK_A_MISSINGPARAMETERS);
				return false;
			}
			 
			$sql = "UPDATE ".$this->db->prefix("xf_artifact_canned_responses")." " ."SET title='". $ts->makeTareaData4Save($title) ."',body='". $ts->makeTareaData4Save($body) ."' " ."WHERE group_artifact_id='". $this->ArtifactType->getID() ."' AND id='". $this->getID() ."'";
			 
			$result = $this->db->queryF($sql);
			 
			if ($result && unofficial_getAffectedRows($result) > 0)
			{
				return true;
			}
			else
			{
				$this->setError($this->db->error());
				return false;
			}
		}
	}
?>