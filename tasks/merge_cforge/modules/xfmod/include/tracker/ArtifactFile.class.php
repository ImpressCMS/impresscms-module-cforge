<?php
	/**
	* ArtifactFile.class - Class to handle files within an artifact
	*
	* SourceForge: Breaking Down the Barriers to Open Source Development
	* Copyright 1999-2001(c) VA Linux Systems
	* http://sourceforge.net
	*
	* @version   $Id: ArtifactFile.class,v 1.1.1.1 2003/08/01 19:13:48 devsupaul Exp $
	*
	*/
	require_once(ICMS_ROOT_PATH."/modules/xfmod/include/Error.class.php");
	 
	class ArtifactFile extends Error {
		var $db;
		/**
		* The artifact type object
		*
		* @var  object $ArtifactType
		*/
		var $Artifact; //object
		 
		/**
		* Array of artifact data
		*
		* @var  array $data_array
		*/
		var $data_array;
		 
		/**
		*  ArtifactFile() - constructor
		*
		*  Use this constructor if you are modifying an existing artifact
		*
		* @param object The Artifact object
		*  @param array (all fields from artifact_file_user_vw) OR id from database
		*  @return true/false
		*/
		function ArtifactFile(&$Artifact, $data = false)
		{
			global $icmsDB;
			 
			$this->db = $icmsDB;
			$this->Error();
			 
			//was Artifact legit?
			if (!$Artifact || !is_object($Artifact))
			{
				$this->setError('ArtifactFile: No Valid Artifact');
				return false;
			}
			//did ArtifactType have an error?
			if ($Artifact->isError())
			{
				$this->setError('ArtifactFile: '.$Artifact->getErrorMessage());
				return false;
			}
			$this->Artifact = $Artifact;
			 
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
		* @para string Filename of the item
		* @param string Item filetype
		* @param string Item filesize
		* @param binary Binary item data
		* @param string Item description
		*  @return id on success / false on failure
		*/
		function create($filename, $filetype, $filesize, $bin_data, $description = 'None')
		{
			global $icmsUser, $ts;
			 
			// Some browsers don't supply mime type if they don't know it
			if (!$filetype)
			{
				// Let's be on safe side?
				$filetype = 'application/octet-stream';
			}
			//
			// data validation
			//
			// echo '<p>|'.$filename.'|'.$filetype.'|'.$filesize.'|'.$bin_data.'|';
			if (!$filename || !$filetype || !$filesize || !$bin_data)
			{
				$this->setError('ArtifactFile: File name, type, size, and data are Required');
				return false;
			}
			 
			if ($icmsUser)
			{
				$userid = $icmsUser->getVar("uid");
			}
			else
			{
				$userid = 100;
			}
			$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_artifact_file")." " ."(artifact_id,description,bin_data,filename,filesize,filetype,adddate,submitted_by) " ."VALUES(" ."'".$this->Artifact->getID()."'," ."'".$ts->makeTboxData4Save($description)."'," ."'". base64_encode($bin_data) ."'," ."'".$ts->makeTboxData4Save($filename)."'," ."'$filesize'," ."'$filetype'," ."'". time() ."'," ."'$userid')");
			 
			$id = $this->db->getInsertId();
			 
			if (!$res || !$id)
			{
				$this->setError('ArtifactFile: '.$this->db->error());
				return false;
			}
			else
			{
				$this->Artifact->addHistory(_XF_TRK_AF_FILEADDED, $id.': '.$filename);
				$this->clearError();
				return $id;
			}
		}
		 
		/**
		* delete() - delete this artifact file from the db
		*
		* @return true/false
		*/
		function delete()
		{
			if (!$this->Artifact->ArtifactType->userIsAdmin())
			{
				$this->setError('ArtifactFile: Permission Denied');
				return false;
			}
			$res = $this->db->queryF("DELETE FROM ".$this->db->prefix("xf_artifact_file")." WHERE id='". $this->getID() ."'");
			if (!$res)
			{
				// || unofficial_getAffectedRows($res) < 1) {
				$this->setError('ArtifactFile: Unable to Delete');
				return false;
			}
			else
			{
				$this->Artifact->addHistory(_XF_TRK_AF_FILEDELETED, $this->getID().': '.$filename);
				return true;
			}
		}
		 
		/**
		* fetchData() - re-fetch the data for this ArtifactFile from the database
		*
		* @param int  Data ID
		* @return true/false
		*/
		function fetchData($id)
		{
			$res = $this->db->query("SELECT * FROM ".$this->db->prefix("xf_artifact_file").",".$this->db->prefix("users")." WHERE uid=submitted_by AND id='$id'");
			 
			if (!$res || $this->db->getRowsNum($res) < 1)
			{
				$this->setError('ArtifactFile: Invalid ArtifactFile ID');
				return false;
			}
			$this->data_array = $this->db->fetchArray($res);
			return true;
		}
		 
		/**
		* getArtifact() - get the Artifact Object this ArtifactFile is associated with
		*
		* @return Artifact
		*/
		function getArtifact()
		{
			return $this->Artifact;
		}
		 
		/**
		* getID() - get this ArtifactFile's ID
		*
		* @return the id #
		*/
		function getID()
		{
			return $this->data_array['id'];
		}
		 
		/**
		* getName() - get the filename
		*
		* @return text filename
		*/
		function getName()
		{
			return $this->data_array['filename'];
		}
		 
		/**
		* getType() - get the type
		*
		* @return text type
		*/
		function getType()
		{
			return $this->data_array['filetype'];
		}
		 
		/**
		* getData() - get the binary data from the db
		*
		* @return binary
		*/
		function getData()
		{
			return base64_decode($this->data_array['bin_data']);
		}
		 
		/**
		* getSize() - get the size
		*
		* @return int size
		*/
		function getSize()
		{
			return $this->data_array['filesize'];
		}
		 
		/**
		* getDescription() - get the description
		*
		* @return text description
		*/
		function getDescription()
		{
			return $this->data_array['description'];
		}
		 
		/**
		* getDate() - get the date file was added
		*
		* @return int unix time
		*/
		function getDate()
		{
			return $this->data_array['adddate'];
		}
		 
		/**
		* getSubmittedBy() - get the user_id of the submitter
		*
		* @return int user_id
		*/
		function getSubmittedBy()
		{
			return $this->data_array['submitted_by'];
		}
		 
		/**
		* getSubmittedRealName() - get the real name of the submitter
		*
		* @return text name
		*/
		function getSubmittedRealName()
		{
			return $this->data_array['name'];
		}
		 
		/**
		* getSubmittedUnixName() - get the unix name of the submitter
		*
		* @return text unixname
		*/
		function getSubmittedUnixName()
		{
			return $this->data_array['uname'];
		}
	}
?>