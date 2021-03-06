<?php
	/**
	* ArtifactType.class - Class to artifact an type
	*
	* SourceForge: Breaking Down the Barriers to Open Source Development
	* Copyright 1999-2001(c) VA Linux Systems
	* http://sourceforge.net
	*
	* @version   $Id: ArtifactType.class,v 1.1.1.1 2003/08/01 19:13:48 devsupaul Exp $
	*
	*/
	require_once(ICMS_ROOT_PATH."/modules/xfmod/include/Error.class.php");
	 
	class ArtifactType extends Error {
		var $db;
		/**
		* The Group object
		*
		* @var  object $Group
		*/
		var $Group; //group object
		 
		/**
		* Artifact type ID
		*
		* @var  int  $artifact_type_id
		*/
		var $artifact_type_id;
		 
		/**
		* Categories db resource ID
		*
		* @var  int  $categories_res
		*/
		var $categories_res;
		 
		/**
		* Resolutions db resource ID
		*
		* @var  int  $resolutions_res
		*/
		var $resolutions_res;
		 
		/**
		* Groups db resource ID
		*
		* @var  int  $groups_res
		*/
		var $groups_res;
		 
		/**
		* Current user permissions
		*
		* @var  int  $current_user_perm
		*/
		var $current_user_perm;
		 
		/**
		* Technicians db resource ID
		*
		* @var  int  $technicians_res
		*/
		var $technicians_res;
		 
		/**
		* Status db resource ID
		*
		* @var  int  $status_res
		*/
		var $status_res;
		 
		/**
		* Canned responses resource ID
		*
		* @var  int  $cannecresponses_res
		*/
		var $cannedresponses_res;
		 
		/**
		* Array of artifact data
		*
		* @var  array $data_array
		*/
		var $data_array;
		 
		/**
		* ArtifactType() - constructor
		*
		* @param object The Group object
		* @param int  The id # assigned to this artifact type in the db
		* @return true/false
		*/
		function ArtifactType(&$Group, $artifact_type_id = false)
		{
			global $icmsDB;
			 
			$this->db = $icmsDB;
			$this->Error();
			if (!$Group || !is_object($Group))
			{
				$this->setError('No Valid Group Object');
				return false;
			}
			if ($Group->isError())
			{
				$this->setError('ArtifactType: '.$Group->getErrorMessage());
				return false;
			}
			$this->Group = $Group;
			if ($artifact_type_id)
			{
				$this->artifact_type_id = $artifact_type_id;
				 
				if (!$this->fetchData($artifact_type_id))
				{
					return false;
				}
				else
				{
					return true;
				}
			}
			else
			{
				$this->setError('No ID Passed');
			}
		}
		 
		/**
		* create() - use this to create a new ArtifactType in the database
		*
		* @param string The type name
		* @param string The type description
		* @param bool (1) true(0) false - viewable by general public
		* @param bool (1) true(0) false - whether non-logged-in users can submit
		* @param bool (1) true(0) false - whether to email on all updates
		* @param string The address to send new entries and updates to
		* @param int  Days before this item is considered overdue
		* @param bool (1) trye(0) false - whether the resolution box should be shown
		* @param string Free-form text that project admins can place on the submit page
		* @param string Free-form text that project admins can place on the browse page
		* @param int  (1) bug tracker,(2) Support Tracker,(3) Patch Tracker,(4) features(0) other
		* @return id on success, false on failure
		*/
		function create($name, $description, $is_public, $allow_anon, $email_all, $email_address,
			$due_period, $use_resolution, $submit_instructions, $browse_instructions, $datatype = 0)
		{
			global $icmsUser, $ts;
			 
			$perm = $this->Group->getPermission($icmsUser);
			 
			if (!$perm || !is_object($perm) || !$perm->isArtifactAdmin())
			{
				$this->setError('ArtifactType: '._XF_G_PERMISSIONDENIED);
				return false;
			}
			 
			if (!$name || !$description || !$due_period)
			{
				$this->setError('ArtifactType: '._XF_TRK_AT_NAMEDESCDUEREQUIRED);
				return false;
			}
			 
			if ($email_address && !validate_email($email_address))
			{
				$email_address = '';
			}
			if ($email_all && !$email_address)
			{
				$email_all = 0;
			}
			 
			$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_artifact_group_list")."(group_id,name,description,is_public,allow_anon,email_all_updates,email_address,due_period,status_timeout,use_resolution,submit_instructions,browse_instructions,datatype) VALUES(" ."'". $this->Group->getID() ."'," ."'". $ts->makeTboxData4Save($name) ."'," ."'". $ts->makeTboxData4Save($description) ."'," ."'$is_public'," ."'$allow_anon'," ."'$email_all'," ."'$email_address'," ."'".($due_period * (60 * 60 * 24)) ."'," ."'1209600'," ."'$use_resolution'," ."'".$ts->makeTareaData4Save($submit_instructions)."'," ."'".$ts->makeTareaData4Save($browse_instructions)."'," ."'$datatype')");
			 
			$this->artifact_type_id = $this->db->getInsertId();
			 
			if (!$res || !$this->artifact_type_id)
			{
				$this->setError('ArtifactType: '.$this->db->error());
				return false;
			}
			else
			{
				 
				if (!$this->fetchData($this->artifact_type_id))
				{
					return false;
				}
				else
				{
					$this->clearError();
					return $this->artifact_type_id;
				}
			}
		}
		 
		/**
		*  fetchData() - re-fetch the data for this ArtifactType from the database
		*
		*  @param int  The artifact type ID
		*  @return true/false
		*/
		function fetchData($artifact_type_id)
		{
			$res = $this->db->query("SELECT * FROM ".$this->db->prefix("xf_artifact_group_list")." " ."WHERE group_artifact_id='$artifact_type_id' " ."AND group_id='". $this->Group->getID() ."'");
			 
			if (!$res || $this->db->getRowsNum($res) < 1)
			{
				$this->setError('ArtifactType: Invalid ArtifactTypeID');
				return false;
			}
			$this->data_array = $this->db->fetchArray($res);
			 
			return true;
		}
		 
		/**
		*   getGroup() - get the Group object this ArtifactType is associated with
		*
		*   @return the Group object
		*/
		function getGroup()
		{
			return $this->Group;
		}
		 
		/**
		*   getID() - get this ArtifactTypeID
		*
		*   @return the group_artifact_id #
		*/
		function getID()
		{
			return $this->artifact_type_id;
		}
		 
		/**
		*   allowsAnon() - determine if non-logged-in users can post
		*
		*   @return true/false
		*/
		function allowsAnon()
		{
			return $this->data_array['allow_anon'];
		}
		 
		/**
		*   getSubmitInstructions() - get the free-form text strings
		*
		*   @return text instructions
		*/
		function getSubmitInstructions()
		{
			return $this->data_array['submit_instructions'];
		}
		 
		/**
		*   getBrowseInstructions() - get the free-form text strings
		*
		*   @return text instructions
		*/
		function getBrowseInstructions()
		{
			return $this->data_array['browse_instructions'];
		}
		 
		/**
		*   emailAll() - determine if we're supposed to email on every event
		*
		*   @return true/false
		*/
		function emailAll()
		{
			return $this->data_array['email_all_updates'];
		}
		 
		/**
		*   emailAddress() - defined email address to send events to
		*
		*   @return text email
		*/
		function getEmailAddress()
		{
			return $this->data_array['email_address'];
		}
		 
		/**
		*   isPublic() - whether non-group-members can view
		*
		*   @return true/false
		*/
		function isPublic()
		{
			return $this->data_array['is_public'];
		}
		 
		/**
		*   getName() - the name of this ArtifactType
		*
		*   @return text name
		*/
		function getName()
		{
			return $this->data_array['name'];
		}
		 
		/**
		*   getDescription() - the description of this ArtifactType
		*
		*   @return text description
		*/
		function getDescription()
		{
			return $this->data_array['description'];
		}
		 
		/**
		*   getDuePeriod() - how many seconds until it's considered overdue?
		*
		*   @return int seconds
		*/
		function getDuePeriod()
		{
			return $this->data_array['due_period'];
		}
		 
		/**
		*    getStatusTimeout() - how many seconds until an item is stale?
		*
		*    @return int seconds
		*/
		function getStatusTimeout()
		{
			return $this->data_array['status_timeout'];
		}
		 
		/**
		*   useResolution() - whether this ArtifactType uses the resolution feature
		*
		*   @return true/false
		*/
		function useResolution()
		{
			return $this->data_array['use_resolution'];
		}
		 
		/**
		*   getDataType() - flag that is generally unused but can mark the difference between bugs, patches, etc
		*
		*   @return(1) bug(2) support(3) patch(4) feature(0) other
		*/
		function getDataType()
		{
			return $this->data_array['datatype'];
		}
		 
		/**
		* getCategories() - List of possible categories set up for this artifact type
		*
		* @return result set
		*/
		function getCategories()
		{
			if (!isset($this->categories_res))
			{
				$sql = "SELECT id,category_name FROM ".$this->db->prefix("xf_artifact_category")." " ."WHERE group_artifact_id='$this->artifact_type_id'";
				 
				$this->categories_res = $this->db->query($sql);
			}
			return $this->categories_res;
		}
		 
		/**
		* getGroups() - List of possible groups set up for this artifact type
		*
		* @return result set
		*/
		function getGroups()
		{
			if (!isset($this->groups_res))
			{
				$sql = "SELECT id,group_name FROM ".$this->db->prefix("xf_artifact_group")." " ."WHERE group_artifact_id='$this->artifact_type_id'";
				 
				$this->groups_res = $this->db->query($sql);
			}
			return $this->groups_res;
		}
		 
		/**
		* getResolutions() - List of possible resolutions
		*
		* @return result set
		*/
		function getResolutions()
		{
			if (!isset($this->resolutions_res))
			{
				$sql = "SELECT id,resolution_name FROM ".$this->db->prefix("xf_artifact_resolution");
				 
				$this->resolutions_res = $this->db->query($sql);
			}
			return $this->resolutions_res;
		}
		 
		/**
		* getTechnicians() - returns a result set of technicians
		*
		* @return result set
		*/
		function getTechnicians()
		{
			if (!isset($this->technicians_res))
			{
				$sql = "SELECT user_id,uname FROM ".$this->db->prefix("xf_artifact_perm").",".$this->db->prefix("users")." " ."WHERE group_artifact_id='$this->artifact_type_id' " ."AND uid=user_id " ."AND(perm_level=1 OR perm_level=2)";
				 
				$this->technicians_res = $this->db->query($sql);
			}
			return $this->technicians_res;
		}
		 
		/**
		* getCannedResponses() - returns a result set of canned responses
		*
		* @return result set
		*/
		function getCannedResponses()
		{
			if (!isset($this->cannedresponses_res))
			{
				$sql = "SELECT id,title FROM ".$this->db->prefix("xf_artifact_canned_responses")." " ."WHERE group_artifact_id='$this->artifact_type_id'";
				 
				$this->cannedresponses_res = $this->db->query($sql);
			}
			return $this->cannedresponses_res;
		}
		 
		/**
		* getStatuses() - returns a result set of statuses
		*
		* @return result set
		*/
		function getStatuses()
		{
			if (!isset($this->status_res))
			{
				$sql = "SELECT * FROM ".$this->db->prefix("xf_artifact_status");
				 
				$this->status_res = $this->db->query($sql);
			}
			return $this->status_res;
		}
		 
		/**
		* getStatusName() - returns the name of this status
		*
		* @param int  The status ID
		* @return name
		*/
		function getStatusName($id)
		{
			$sql = "SELECT * FROM ".$this->db->prefix("xf_artifact_status")." WHERE id='$id'";
			$result = $this->db->query($sql);
			 
			if ($result && $this->db->getRowsNum($result) > 0)
			{
				return unofficial_getDBResult($result, 0, 'status_name');
			}
			else
			{
				return _XF_TRK_AT_STATUSNAMENOTFOUND;
			}
		}
		 
		/**
		* addUser() - add a user to this ArtifactType - depends on UNIQUE INDEX preventing duplicates
		*
		* @param int  user_id of the new user
		* @return true/false
		*/
		function addUser($id)
		{
			if (!$this->userIsAdmin())
			{
				$this->setError(_XF_G_PERMISSIONDENIED);
				return false;
			}
			if (!$id)
			{
				$this->setError(_XF_TRK_A_MISSINGPARAMETERS);
				return false;
			}
			 
			$sql = "INSERT INTO ".$this->db->prefix("xf_artifact_perm")."(group_artifact_id,user_id,perm_level) " ."VALUES('".$this->getID()."','$id',0)";
			 
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
		 
		/**
		* updateUser() - update a user's permissions
		*
		* @param int  user_id of the user to update
		* @param int  (1) tech only,(2) admin & tech(3) admin only
		* @return true/false
		*/
		function updateUser($id, $perm_level)
		{
			if (!$this->userIsAdmin())
			{
				$this->setError(_XF_G_PERMISSIONDENIED);
				return false;
			}
			if (!$id)
			{
				$this->setError(_XF_TRK_A_MISSINGPARAMETERS.': '.$id.'|'.$perm_level);
				return false;
			}
			 
			$sql = "UPDATE ".$this->db->prefix("xf_artifact_perm")." SET perm_level='$perm_level' " ."WHERE user_id='$id' AND group_artifact_id='".$this->getID()."'";
			 
			$result = $this->db->queryF($sql);
			 
			if ($result)
			{
				return true;
			}
			else
			{
				$this->setError($this->db->error());
				return false;
			}
		}
		 
		/**
		* deleteUser() - delete a user's permissions
		*
		* @param int  user_id of the user who's permissions to delete
		* @return true/false
		*/
		function deleteUser($id)
		{
			if (!$this->userIsAdmin())
			{
				$this->setError(_XF_G_PERMISSIONDENIED);
				return false;
			}
			if (!$id)
			{
				$this->setError(_XF_TRK_A_MISSINGPARAMETERS);
				return false;
			}
			 
			$sql = "DELETE FROM ".$this->db->prefix("xf_artifact_perm")." " ."WHERE user_id='$id' AND group_artifact_id='".$this->getID()."'";
			 
			$result = $this->db->queryF($sql);
			 
			if ($result)
			{
				return true;
			}
			else
			{
				$this->setError($this->db->error());
				return false;
			}
		}
		 
		/*
		 
		USER PERMISSION FUNCTIONS
		 
		*/
		 
		/**
		*   userCanView() - determine if the user can view this artifact type
		*
		*   @return true/false
		*/
		function userCanView()
		{
			global $icmsUser;
			 
			if ($this->isPublic())
			{
				return true;
			}
			else
			{
				if (!$icmsUser)
				{
					return false;
				}
				else
				{
					//
					// For now, we let any member of a project view this ArtifactType
					// A future change might be to restrict to only those people with
					// a corresponding entry in artifact_perm table
					//
					$perm = $this->Group->getPermission($icmsUser);
					return $perm->isMember();
				}
			}
		}
		 
		/**
		* userIsAdmin() - see if the logged-in user's perms are >= 2 or Group ArtifactAdmin
		*
		* @return true/false
		*/
		function userIsAdmin()
		{
			global $icmsUser;
			 
			$perm = $this->Group->getPermission($icmsUser);
			 
			if (($this->getCurrentUserPerm() >= 2) || ($perm->isArtifactAdmin()))
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		 
		/**
		* getCurrentUserPerm() - get the logged-in user's perms from artifact_perm
		*
		* @return int perm level for the logged-in user
		*/
		function getCurrentUserPerm()
		{
			global $icmsUser;
			 
			if (!$icmsUser)
			{
				return 0;
			}
			else
			{
				if (!isset($this->current_user_perm))
				{
					 
					$sql = "SELECT perm_level FROM ".$this->db->prefix("xf_artifact_perm")." " ."WHERE group_artifact_id='$this->artifact_type_id' " ."AND user_id='".$icmsUser->uid()."'";
					 
					$this->current_user_perm = unofficial_getDBResult($this->db->query($sql), 0, 0);
				}
				return $this->current_user_perm;
			}
		}
		 
		/**
		*  update() - use this to update this ArtifactType in the database
		*
		*  @param string The item name
		*  @param string The item description
		*  @param bool (1) true(0) false - viewable by general public
		*  @param bool (1) true(0) false - whether non-logged-in users can submit
		*  @param bool (1) true(0) false - whether to email on all updates
		*  @param string The address to send new entries and updates to
		*  @param int  Days before this item is considered overdue
		*  @param int  Days before stale items time out
		*  @param bool (1) trye(0) false - whether the resolution box should be shown
		*  @param string Free-form text that project admins can place on the submit page
		*  @param string Free-form text that project admins can place on the browse page
		*  @return true on success, false on failure
		*/
		function update($name, $description, $is_public, $allow_anon, $email_all, $email_address,
			$due_period, $status_timeout, $use_resolution, $submit_instructions,
			$browse_instructions)
		{
			global $icmsUser, $ts;
			 
			$perm = $this->Group->getPermission($icmsUser);
			 
			if (!$perm || !is_object($perm) || !$perm->isArtifactAdmin())
			{
				$this->setError('ArtifactType: '._XF_G_PERMISSIONDENIED);
				return false;
			}
			 
			if ($this->getDataType())
			{
				$name = $this->getName();
				$description = $this->getDescription();
			}
			 
			if (!$name || !$description || !$due_period || !$status_timeout)
			{
				$this->setError('ArtifactType: '._XF_TRK_AT_NAMEDESCDUESTATUSREQUIRED);
				return false;
			}
			 
			if ($email_address && !checkEmail($email_address))
			{
				$email_address = '';
			}
			if ($email_all && !$email_address)
			{
				$email_all = 0;
			}
			$sql = "UPDATE ".$this->db->prefix("xf_artifact_group_list")." SET " ."name='".$ts->makeTboxData4Save($name)."'," ."description='".$ts->makeTboxData4Save($description)."'," ."is_public='$is_public'," ."allow_anon='$allow_anon'," ."email_all_updates='$email_all'," ."email_address='$email_address'," ."due_period='".($due_period * (60 * 60 * 24)) ."'," ."status_timeout='".($status_timeout * (60 * 60 * 24)) . "'," ."use_resolution='$use_resolution'," ."submit_instructions='".$ts->makeTareaData4Save($submit_instructions)."'," ."browse_instructions='".$ts->makeTareaData4Save($browse_instructions)."' " ."WHERE " ."group_artifact_id='". $this->getID() ."' " ."AND group_id='". $this->Group->getID() ."'";
			 
			$res = $this->db->queryF($sql);
			 
			if (!$res)
			{
				$this->setError('ArtifactType::Update(): '.$this->db->error());
				return false;
			}
			else
			{
				$this->fetchData($this->getID());
				return true;
			}
		}
	}
?>