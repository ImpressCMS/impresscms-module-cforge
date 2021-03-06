<?php
	 
	/**
	* Group object
	*
	* Sets up database results and preferences for a group and abstracts this info.
	*
	* Foundry.class and Project.class call this.
	*
	* Project.class contains all the deprecated API from the old group.php file
	*
	* DEPENDS on user.php being present and setup properly
	*
	* GENERALLY YOU SHOULD NEVER INSTANTIATE THIS OBJECT DIRECTLY
	* USE group_get_object() to instantiate properly
	*
	*
	* SourceForge: Breaking Down the Barriers to Open Source Development
	* Copyright 1999-2001(c) VA Linux Systems
	* http://sourceforge.net
	*
	* @version   $Id: Group.class,v 1.34 2004/04/29 23:16:02 jcox Exp $
	* @author Tim Perdue <tperdue@valinux.com>
	* @date 2000-08-28
	*
	*/
	require_once(ICMS_ROOT_PATH."/modules/xfmod/include/tracker/ArtifactTypes.class.php");
	require_once(ICMS_ROOT_PATH."/modules/xfmod/include/frs.class.php");
	require_once(ICMS_ROOT_PATH."/modules/xfmod/forum/forum_utils.php");
	 
	include_once(ICMS_ROOT_PATH."/kernel/user.php");
	include_once(ICMS_ROOT_PATH."/modules/xfmod/include/nxoopsLDAP.php");
	 
	$GROUP_OBJ = array();
	 
	/**
	*  group_get_object() - Get the group object.
	*
	*  group_get_object() is useful so you can pool group objects/save database queries
	*  You should always use this instead of instantiating the object directly.
	*
	*  You can now optionally pass in a db result handle. If you do, it re-uses that query
	*  to instantiate the objects.
	*
	*  IMPORTANT! That db result must contain all fields
	*  from groups table or you will have problems
	*
	*  @param  int  Required
	*  @param  int  Result set handle("SELECT * FROM groups WHERE group_id=xx")
	*  @return a group object or false on failure
	*/
	function group_get_object($group_id, $res = false)
	{
		global $GROUP_OBJ, $icmsDB;
		 
		if (!isset($GROUP_OBJ["_".$group_id."_"]))
		{
			if ($res)
			{
				//the db result handle was passed in
			}
			else
			{
				$res = $icmsDB->queryF("SELECT * FROM ".$icmsDB->prefix("xf_groups")." WHERE group_id='$group_id'");
			}
			if (!$res || $icmsDB->getRowsNum($res) < 1)
			{
				$GROUP_OBJ["_".$group_id."_"] = false;
			}
			else
			{
				/*
				check group type and set up object
				*/
				$res_arr = $icmsDB->fetchArray($res);
				if ($res_arr['type'] == 1)
				{
					//project
					$GROUP_OBJ["_".$group_id."_"] = new Project($group_id, $res);
				}
				else if($res_arr['type'] == 2)
				{
					//foundry
					$GROUP_OBJ["_".$group_id."_"] = new Foundry($group_id, $res);
				}
				else
				{
					//invalid
					$GROUP_OBJ["_".$group_id."_"] = false;
				}
			}
		}
		return $GROUP_OBJ["_".$group_id."_"];
	}
	 
	class Group extends Error {
		/**
		* DB Handle
		*/
		var $db;
		/**
		* Associative array of data from db
		*
		* @var array $data_array
		*/
		var $data_array;
		 
		/**
		* The group ID
		*
		* @var int $group_id
		*/
		var $group_id;
		 
		/**
		* Database result set handle
		*
		* @var int $db_result
		*/
		var $db_result;
		 
		/**
		* Permissions data row from db
		*
		* @var array $perm_data_array
		*/
		var $perm_data_array;
		 
		/**
		* Whether the use is an admin/super user of this project
		*
		* @var bool $is_admin
		*/
		var $is_admin;
		 
		/**
		* Artifact types result handle
		*
		* @var int $types_res;
		*/
		var $types_res;
		 
		/**
		* Group() - Group object constructor - use group_get_object() to instantiate
		*
		* @param int  Required - group_id of the group you want to instantiate
		* @param int  Database result from select query
		*/
		function Group($id = false, $res = false)
		{
			global $icmsDB;
			 
			$this->db = $icmsDB;
			$this->Error();
			if (!$id)
			{
				//setting up an empty object
				//probably going to call create()
				return true;
			}
			 
			$this->group_id = $id;
			if (!$res)
			{
				$this->db_result = $this->db->query("SELECT * FROM ".$this->db->prefix("xf_groups")." WHERE group_id=".$id);
			}
			else
				{
				$this->db_result = $res;
			}
			 
			if ($id == 100 || $this->db->getRowsNum($this->db_result) < 1)
			{
				//function in class we extended
				$this->setError('Group Not Found');
				$this->data_array = array();
			}
			else
				{
				//set up an associative array for use by other functions
				 
				unofficial_ResetResult($this->db_result);
				 
				$this->data_array = $this->db->fetchArray($this->db_result);
			}
		}
		 
		 
		/**
		* refreshGroupData() - May need to refresh database fields if an update occurred
		*/
		function refreshGroupData()
		{
			$this->db_result = $this->db->query("SELECT * FROM ".$this->db->prefix("xf_groups")." WHERE group_id='". $this->getID() ."'");
			$this->data_array = $this->db->fetchArray($this->db_result);
		}
		 
		 
		/**
		* create() - Create new group
		*
		* This method should be called on empty Group object
		*
		*  @param object The User object
		*  @param string The full name of the user
		*  @param string The Unix name of the user
		*  @param string The new group description
		*  @param int The ID of the license to use
		*  @param string The 'other' license to use if any
		*  @param string The purpose of the group
		*/
		 
		function create(&$user, $full_name, $unix_name, $description, $license,
			$license_other, $purpose, $is_foundry = false, $use_cvs = true, $anon_cvs = true)
		{
			global $ts;
			 
			if ($this->getID() != 0)
			{
				$this->setError("Group::create: "._XF_GRP_GROUPOBJECTALREADYEXISTS);
				return false;
			}
			 
			srand((double)microtime() * 1000000);
			$random_num = rand(0, 1000000);
			 
			$res = 1;
			if ($is_foundry)
			{
				$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_groups")."(" ."group_name,is_public,unix_group_name,short_description,status,register_time,rand_hash,use_bugs,use_patch,use_forum,use_pm,use_cvs,use_support,type) VALUES(" ."'".$ts->makeTareaData4Save($full_name)."',1,'$unix_name','".$ts->makeTareaData4Save($description)."','P',".time().",'".md5($random_num)."','0','0','0','0','0','0','2')");
			}
			else
				{
				$user_cvs = ($use_cvs == true) ? 1 :
				 0;
				$anon_cvs = ($anon_cvs == true) ? 1 :
				 0;
				 
				$res = $this->db->queryF(
				"INSERT INTO " . $this->db->prefix("xf_groups") . "(group_name,is_public,unix_group_name,short_description,http_domain,homepage,status,unix_box,license,register_purpose,register_time,license_other,rand_hash,use_cvs,anon_cvs) VALUES(" ."'".$ts->makeTareaData4Save($full_name)."',1,'$unix_name','".$ts->makeTareaData4Save($description)."','$unix_name','".ICMS_URL."/modules/xfmod/project/?".$unix_name."','P','shell1','$license','".$ts->makeTareaData4Save($purpose)."',".time().",'".$ts->makeTareaData4Save($license_other) . "','" . md5($random_num) . "','$use_cvs','$anon_cvs')");
			}
			 
			if (!$res || unofficial_getAffectedRows($res) < 1)
			{
				$this->setError("ERROR: "._XF_GRP_COULDNOTCREATEGROUP.": ".$this->db->error());
				return false;
			}
			 
			$this->group_id = $this->db->getInsertId();
			 
			//
			// Now, make the user an admin
			//
			$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_user_group")."(" ."user_id,group_id,admin_flags,cvs_flags,artifact_flags,forum_flags) VALUES(" .$user->getVar("uid").",".$this->getID().",'A',1,2,2)");
			 
			if (!$res || unofficial_getAffectedRows($res) < 1)
			{
				$this->setError('ERROR: '._XF_GRP_COULDNOTADDADMIN.': '.$this->db->error());
				return false;
			}
			 
			if ($is_foundry)
			{
				// We need to add a record to the xf_foundry_data and xf_foundry_projects tables.
				$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_foundry_data")."(" ."foundry_id) VALUES(".$this->group_id.")");
				if ($res && unofficial_getAffectedRows($res) >= 1)
				{
					$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_foundry_projects")."(" ."foundry_id,project_id) VALUES(".$this->group_id.",".$this->group_id.")");
					if (! $res || unofficial_getAffectedRows($res) < 1)
					{
						$this->setError("ERROR:  "._XF_GRP_COULDNOTCREATEFOUNDRYDATA.": ".$this->db->error());
						return false;
					}
				}
				else
					{
					$this->setError("ERROR:  "._XF_GRP_COULDNOTCREATEFOUNDRYDATA.": ".$this->db->error());
					return false;
				}
			}
			 
			 
			$this->refreshGroupData();
			return true;
		}
		 
		/**
		* updateAdmin($user) - Update core properties of group object
		*
		* This function require site admin privilege
		*
		* @param object User requesting operation(for access control)
		* @param bool Whether group is publicly accessible(0/1)
		* @param string Project's license(string ident)
		* @param int  Group type(1-project, 2-foundry)
		* @param string Machine on which group's home directory located
		* @param string Domain which serves group's WWW
		* @return status
		* @access public
		*/
		function updateAdmin(&$user, $is_public, $license, $type, $http_domain)
		{
			global $icmsForge;
			$perm = $this->getPermission($user);
			 
			if (!$perm || !is_object($perm))
			{
				$this->setError(''._XF_GRP_COULDNOTGETPERMISSION);
				return false;
			}
			 
			if (!$perm->isSuperUser())
			{
				$this->setError(''._XF_G_PERMISSIONDENIED);
				return false;
			}
			$frs = new FRS($this->getID());
			if ($is_public)
			{
				$frs->chmodpath($icmsForge['ftp_path']."/".$this->getUnixName(), 0775);
			}
			else
				{
				$frs->chmodpath($icmsForge['ftp_path']."/".$this->getUnixName(), 0770);
			}
			$res = $this->db->queryF("UPDATE ".$this->db->prefix("xf_groups")." " ."SET is_public='$is_public'," ."license='$license'," ."type='$type'," ."http_domain='$http_domain' " ."WHERE group_id='".$this->getID()."'");
			 
			if (!$res)
			{
				$this->setError('ERROR: DB: '._XF_GRP_COULDNOTCHANGEGROUP.': '.$this->db->error());
				return false;
			}
			 
			/*
			If this is a foundry, see if they have a preferences row, if not, create one
			*/
			if ($type == '2')
			{
				 
				$res = $this->db->query("SELECT * FROM ".$this->db->prefix("xf_foundry_data")." WHERE foundry_id='".$this->getID()."'");
				 
				if ($this->db->getRowsNum($res) < 1)
				{
					$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_foundry_data")."(foundry_id) VALUES('".$this->getID()."')");
					 
					if (!$res)
					{
						$this->setError(_XF_FND_COULDNOTINSERTFOUNDRY.': '.$this->db->error());
						return false;
					}
				}
			}
			 
			// Log the audit trail
			if ($is_public != $this->isPublic())
			{
				$this->addHistory('is_public', $this->isPublic());
			}
			if ($license != $this->data_array['license'])
			{
				$this->addHistory('license', $this->data_array['license']);
			}
			if ($type != $this->data_array['type'])
			{
				$this->addHistory('type', $this->data_array['type']);
			}
			if ($http_domain != $this->data_array['http_domain'])
			{
				$this->addHistory('http_domain', $this->data_array['http_domain']);
			}
			 
			$this->refreshGroupData();
			return true;
		}
		 
		/**
		* update() - Update number of common properties
		*
		* Unlike updateAdmin(), this function accessible to project
		* admin.
		*
		* @param object User requesting operation(for access control)
		* @param bool Whether group is publicly accessible(0/1)
		* @param string Project's license(string ident)
		* @param int  Group type(1-project, 2-foundry)
		* @param string Machine on which group's home directory located
		* @param string Domain which serves group's WWW
		* @return status
		* @access public
		*/
		function update(&$user, $group_name, $homepage, $short_description, $use_mail, $use_survey, $use_forum,
			$use_faq, $use_pm, $use_pm_depend_box, $use_cvs, $use_news, $use_docman, $use_sample, $use_tracker,
			$new_task_address, $send_all_tasks, $logo_image_id, $anon_cvs = true)
		{
			global $ts;
			$perm = $this->getPermission($user);
			 
			if (!$perm || !is_object($perm))
			{
				$this->setError(''._XF_GRP_COULDNOTGETPERMISSION);
				return false;
			}
			 
			if (!$perm->isAdmin())
			{
				$this->setError(''._XF_G_PERMISSIONDENIED);
				return false;
			}
			 
			// Validate some values
			if (!$group_name)
			{
				$this->setError(''._XF_GRP_INVALIDGROUPNAME);
				return false;
			}
			 
			if ($new_task_address && !validate_email($new_task_address))
			{
				$this->setError(''._XF_TSK_TASKADDRESSINVALID);
				return false;
			}
			 
			// in the database, these all default to '1',
			// so we have to explicity set 0
			$use_mail = ($use_mail) ? 1 :
			 0;
			$use_survey = ($use_survey) ? 1 :
			 0;
			$use_forum = ($use_forum) ? 1 :
			 0;
			$use_faq = ($use_faq) ? 1 :
			 0;
			$use_pm = ($use_pm) ? 1 :
			 0;
			$use_pm_depend = ($use_pm_depend) ? 1 :
			 0;
			$use_cvs = ($use_cvs) ? 1 :
			 0;
			$anon_cvs = ($anon_cvs) ? 1 :
			 0;
			$use_news = ($use_news) ? 1 :
			 0;
			$use_docman = ($use_docman) ? 1 :
			 0;
			$use_sample = ($use_sample) ? 1 :
			 0;
			$use_tracker = ($use_tracker) ? 1 :
			 0;
			$send_all_tasks = ($send_all_tasks) ? 1 :
			 0;
			 
			if (!$homepage)
			{
				$homepage = ICMS_URL."/modules/xfmod/project/?".$this->getUnixName();
			}
			 
			$sql = "UPDATE ".$this->db->prefix("xf_groups")." SET " ."group_name='".$ts->makeTareaData4Save($group_name)."'," ."homepage='$homepage'," ."short_description='".$ts->makeTareaData4Save($short_description)."'," ."use_mail='$use_mail'," ."use_survey='$use_survey'," ."use_forum='$use_forum'," ."use_faq='$use_faq'," ."use_pm='$use_pm'," ."use_pm_depend_box='$use_pm_depend_box'," ."use_cvs='$use_cvs'," ."anon_cvs='$anon_cvs'," ."use_news='$use_news'," ."use_docman='$use_docman'," ."use_sample='$use_sample'," ."use_tracker='$use_tracker'," ."new_task_address='$new_task_address'," ."send_all_tasks='$send_all_tasks' " ."WHERE group_id='".$this->getID()."'
				";
			icms_debug_info('the actual SQL', $sql );
			 
			$res = $this->db->queryF($sql);
			 
			if (!$res)
			{
				$this->setError(_XF_GRP_ERRORUPDATINGPROJECT.': '.$this->db->error());
				echo $this->db->error();
				 
				return false;
			}
			 
			// Log the audit trail
			$this->addHistory(''._XF_GRP_CHANGEDPUBLICINFO, '');
			 
			$this->refreshGroupData();
			return true;
		}
		 
		/**
		* getID() - Simply return the group_id for this object
		*
		* @return integer group_id
		*/
		function getID()
		{
			return $this->group_id;
		}
		 
		/**
		* getType() - Foundry, project, etc
		*
		* @return the type flag from the database
		*/
		function getType()
		{
			return $this->data_array['type'];
		}
		 
		 
		/**
		* getStatus()
		*
		* Statuses include I,H,A,D
		*/
		function getStatus()
		{
			return $this->data_array['status'];
		}
		 
		/**
		* setStatus($user, $status)
		*
		* Statuses include I,H,A,D
		*
		* @param object User requesting operation(for access control)
		* @param string Status value
		*
		* @access public
		*/
		function setStatus(&$user, $status)
		{
			 
			$perm = $this->getPermission($user);
			 
			if (!$perm || !is_object($perm))
			{
				$this->setError(''._XF_GRP_COULDNOTGETPERMISSION);
				return false;
			}
			 
			if (!$perm->isSuperUser() && !$perm->isAdmin())
			{
				$this->setError(''._XF_G_PERMISSIONDENIED);
				return false;
			}
			 
			// Projects in 'A' status can only go to 'H' or 'D'
			// Projects in 'D' status can only go to 'A'
			// Projects in 'P' status can only go to 'A' OR 'D'
			// Projects in 'I' status can only go to 'P'
			// Projects in 'H' status can only go to 'A' OR 'D'
			$allowed_status_changes = array(
			'AH' => 1, 'AD' => 1, 'DA' => 1, 'PA' => 1, 'PD' => 1,
				'IP' => 1, 'HA' => 1, 'HD' => 1 );
			 
			// Check that status transition is valid
			if ($this->getStatus() != $status && !$allowed_status_changes[$this->getStatus().$status])
			{
				$this->setError(''._XF_GRP_INVALIDSTATUSCHANGE);
				return false;
			}
			 
			$res = $this->db->queryF("UPDATE ".$this->db->prefix("xf_groups")." SET status='$status' WHERE group_id='". $this->getID()."'");
			 
			if (!$res || unofficial_getAffectedRows($res) < 1)
			{
				$this->setError('ERROR: DB: '._XF_GRP_COULDNOTCHANGEGROUPSTATUS.': '.$this->db->error());
				return false;
			}
			 
			if ($status == 'A')
			{
				if (!$this->activateUsers())
				{
					return false;
				}
			}
			// Make sure that active group have default trackers
			if ($status == 'A')
			{
				$ats = new ArtifactTypes($this);
				if (!$ats || !is_object($ats))
				{
					$this->setError(''._XF_ART_ERRORCREATINGARTIFACTTYPES);
					return false;
				}
				else if($ats->isError())
				{
					$this->setError($ats->getErrorMessage());
					return false;
				}
				if (!$ats->createTrackers())
				{
					$this->setError($ats->getErrorMessage());
					return false;
				}
			}
			// Log the audit trail
			if ($status != $this->getStatus())
			{
				$this->addHistory('status', $this->getStatus());
			}
			 
			$this->data_array['status'] = $status;
			return true;
		}
		 
		/**
		* isFoundry() - Simple boolean test to see if it's a foundry or not
		*
		* @return true/false
		*/
		function isFoundry()
		{
			return($this->getType() == 2);
		}
		 
		/**
		* isProject() - Simple boolean test to see if it's a project or not
		*
		* @return true/false
		*/
		function isProject()
		{
			return($this->getType() == 1);
		}
		 
		/**
		* isPublic() - Simply returns the is_public flag from the database
		*
		* @return true/false
		*/
		function isPublic()
		{
			return $this->data_array['is_public'];
		}
		 
		/**
		* isActive() - Database field status of 'A' returns true
		*
		* @return true/false
		*/
		function isActive()
		{
			return($this->getStatus() == 'A');
		}
		 
		/**
		* isInactive() - Database field status of 'N' returns true
		*
		* @return true/false
		*/
		function isInactive()
		{
			return($this->getStatus() == 'N');
		}
		 
		/**
		*  getUnixName()
		*
		*  @return text unix_name
		*/
		function getUnixName()
		{
			return strtolower($this->data_array['unix_group_name']);
		}
		 
		/**
		*  getPublicName()
		*
		*  @return text group_name
		*/
		function getPublicName()
		{
			return $this->data_array['group_name'];
		}
		 
		/**
		*  getDescription()
		*
		*  @return text description
		*/
		function getDescription()
		{
			return $this->data_array['short_description'];
		}
		 
		/**
		*  getStartDate()
		*
		*  @return integer(unix time) of registration
		*/
		function getStartDate()
		{
			return $this->data_array['register_time'];
		}
		 
		/**
		*  getLogoImageID()
		*
		*  @return ID of logo image in db_images table(or 100 if none)
		*/
		function getLogoImageID()
		{
			return $this->data_array['logo_image_id'];
		}
		 
		/**
		*  getUnixBox()
		*
		*  @return name of the unix machine for the group
		*/
		function getUnixBox()
		{
			return $this->data_array['unix_box'];
		}
		 
		/**
		*  getDomain()
		*
		*  @return name of the group [web] domain
		*/
		function getDomain()
		{
			return $this->data_array['http_domain'];
		}
		 
		/**
		*  getLicense()
		*
		*  @return string ident of group license
		*/
		function getLicense()
		{
			return $this->data_array['license'];
		}
		 
		/**
		*  getLicenseOther()
		*
		*  @return text custom license
		*/
		function getLicenseOther()
		{
			if ($this->getLicense() == 'other')
			{
				return $this->data_array['license_other'];
			}
			else
			{
				return '';
			}
		}
		 
		/**
		*  getRegistrationPurpose()
		*
		*  @return text application for project hosting
		*/
		function getRegistrationPurpose()
		{
			return $this->data_array['register_purpose'];
		}
		 
		/*
		 
		Common Group preferences for tools
		 
		*/
		 
		/**
		* usesCVS() - whether or not this group has opted to use CVS
		*
		* @return true/false
		*/
		function usesCVS()
		{
			return $this->data_array['use_cvs'];
		}
		 
		function updateCVS($usesCVS, $anonCVS)
		{
			$add = false;
			$update = false;
			if ($usesCVS)
			{
				if (!$this->usesCVS())
				{
					$add = true;
					// Creating CVS project
				}
				 
				$usesCVS = 1;
				// Set to 1 for db update
				 
				if ($anonCVS)
				{
					if (!$this->anonCVS())
					{
						$update = true;
					}
					 
					$anonCVS = 1; // Set to 1 for db update
				}
				else
					{
					if ($this->anonCVS())
					$update = true;
					 
					$anonCVS = 0; // Set to 0 for db update
				}
			}
			else
				{
				// Set to integer vals for db update
				$usesCVS = 0;
				$anonCVS = 0;
			}
			 
			$this->data_array['use_cvs'] = $usesCVS;
			$this->data_array['anon_cvs'] = $anonCVS;
			 
			if ($add)
			{
				// Create CVS project in LDAP
				if (!$this->createCVSProject())
				{
					return false;
				}
			}
			else if($update)
			{
				$cc = false;
				$unixName = $this->getUnixName();
				$anonFlag = ($anonCVS != 0) ? true :
				 false;
				$lldap = new nxoopsLDAP;
				if ($lldap->connect())
				{
					if ($lldap->bindAdmin())
					{
						if ($lldap->setAnonAllowed($unixName, $anonFlag))
						{
							$cc = true;
						}
					}
					 
					if ($cc == false)
					{
						$this->setError("Failed to create LDAP Group for project " . $this->getUnixName() . ": " . $lldap->lastError());
					}
					 
					$lldap->cleanUp();
				}
				else
					{
					$this->setError("Failed to create LDAP Group for project " . $this->getUnixName() . ": " . $lldap->lastError());
				}
				 
				unset($lldap);
				if (!$cc)
				{
					return false;
				}
			}
			 
			// Update database
			 
			$res = $this->db->queryF("UPDATE ".$this->db->prefix("xf_groups")." SET " ."use_cvs='$usesCVS'," ."anon_cvs='$anonCVS' WHERE group_id='".$this->getID()."'");
			 
			if (!$res)
			{
				$this->setError(_XF_GRP_ERRORUPDATINGPROJECT.': '.$this->db->error());
				return false;
			}
			 
			return true;
		}
		 
		function anonCVS()
		{
			return $this->data_array['anon_cvs'];
		}
		 
		/**
		*  usesNews() - whether or not this group has opted to use news
		*
		* @return true/false
		*/
		function usesNews()
		{
			return $this->data_array['use_news'];
		}
		 
		/**
		* usesForum() - whether or not this group has opted to use discussion forums
		*
		*  @return true/false
		*/
		function usesForum()
		{
			return $this->data_array['use_forum'];
		}
		 
		/**
		*  usesFAQ() - whether or not this group has opted to use FAQs.  Should
		*  only be available to communities.
		*
		*  @return true/false
		*/
		function usesFAQ()
		{
			//commented line below keeps faqs from being served via projects
			//return $this->isProject()?false:$this->data_array['use_faq'];
			return $this->data_array['use_faq'];
		}
		 
		/**
		*  usesDocman() - whether or not this group has opted to use docman
		*
		*  @return true/false
		*/
		function usesDocman()
		{
			return $this->data_array['use_docman'];
		}
		 
		/**
		*  usesMail() - whether or not this group has opted to use mailing lists
		*
		*  @return true/false
		*/
		function usesMail()
		{
			return $this->data_array['use_mail'];
		}
		 
		/**
		*  usesSurvey() - whether or not this group has opted to use surveys
		*
		*  @return true/false
		*/
		function usesSurvey()
		{
			return $this->data_array['use_survey'];
		}
		 
		/**
		*  usesSamples() - whether or not this group has opted to use samples
		*
		*  @return true/false
		*/
		function usesSamples()
		{
			return $this->data_array['use_sample'];
		}
		 
		/**
		*  usesTracker() - whether or not this group has opted to use samples
		*
		*  @return true/false
		*/
		function usesTracker()
		{
			return $this->data_array['use_tracker'];
		}
		 
		/**
		*  usesPM() - whether or not this group has opted to Project Manager
		*
		*  @return true/false
		*/
		function usesPM()
		{
			return $this->data_array['use_pm'];
		}
		 
		/**
		*  usesPMDependencies() - whether or not this group has opted to use task dependencies
		*
		*  @return true/false
		*/
		function usesPMDependencies()
		{
			return $this->data_array['use_pm_depend'];
		}
		 
		// Warning: names for 2 functions below were choosen to be
		// consistent with trackers code
		 
		/**
		*  PMEmailAddress() - get email address to send PM notifications to
		*
		*  @return true/false
		*/
		function PMEmailAddress()
		{
			return $this->data_array['new_task_address'];
		}
		 
		/**
		*  PMEmailAll() - whether or not this group has opted to use task dependencies
		*
		*  @return true/false
		*/
		function PMEmailAll()
		{
			return $this->data_array['send_all_tasks'];
		}
		 
		 
		/**
		* getHomePage() - The URL for this project's home page
		*
		* @return text homepage URL
		*/
		function getHomePage()
		{
			$hpage = $this->data_array['homepage'];
			if (!isset($hpage))
			{
				$hpage = "".ICMS_URL."/modules/xfmod/project/?" . $this->getUnixName();
			}
			else
				{
				if (!stristr($hpage, "http://"))
				{
					if (!stristr($hpage, "https://"))
					{
						$hpage = "http://" . $hpage;
					}
				}
			}
			 
			return $hpage;
		}
		 
		/**
		* setHomePage() - The URL for this project's home page
		*
		* @return boolean
		*/
		function setHomePage($homepage)
		{
			$res = $this->db->queryF("UPDATE ".$this->db->prefix("xf_groups")." SET homepage='$homepage' WHERE group_id='". $this->getID()."'");
			if (!$res || unofficial_getAffectedRows($res) < 1)
			{
				$this->setError('ERROR: DB: '._XF_GRP_COULDNOTCHANGEGROUPSTATUS.': '.$this->db->error());
				return false;
			}
			 
			$this->data_array['homepage'] = $homepage;
			return true;
		}
		 
		/**
		* getArtifactTypes() - Get all Artifact types for this group
		*
		* @return result set
		*/
		function getArtifactTypes()
		{
			if (!isset($this->types_res))
			{
				$sql = "SELECT group_artifact_id,name FROM ".$this->db->prefix("xf_artifact_group_list")." WHERE group_id='".$this->getID()."' ORDER BY name";
				$this->types_res = $this->db->query($sql);
			}
			return $this->types_res;
		}
		 
		/**
		* getPermission() - Return a Permission for this Group and the specified User
		*
		* @param object The user you wish to get permission for(usually the logged in user)
		* @return permission
		*/
		function getPermission(&$_user)
		{
			$perm = permission_get_object($this, $_user);
			return $perm;
		}
		 
		/*
		 
		 
		Basic functions to add/remove users to/from a group
		and update their permissions
		 
		 
		*/
		 
		function isMemberOfGroup(&$user)
		{
			if (!$user)
			{
				return false;
			}
			 
			$sql = "SELECT user_id FROM ".$this->db->prefix("xf_user_group")." " ."WHERE user_id='".$user->getVar("uid")."' AND group_id='". $this->getID() ."'";
			 
			$res_member = $this->db->query($sql);
			 
			return $this->db->getRowsNum($res_member);
		}
		 
		/**
		* addUser() - controls adding a user to a group
		*
		*  @param string Unix name of the user to add
		* @return true/false
		* @access public
		*/
		function addUser(&$user)
		{
			global $icmsUser;
			 
			/*
			Admins can add users to groups
			*/
			 
			$perm = $this->getPermission($icmsUser);
			 
			if (!$perm || !is_object($perm) || !$perm->isAdmin())
			{
				$this->setError(''._XF_GRP_NOTADMINTHISGROUP);
				return false;
			}
			 
			//
			// make sure user is active
			//
			if (!$user->isActive())
			{
				$this->setError(''._XF_GRP_USERNOTACTIVE);
				return false;
			}
			//
			// if not already a member, add them
			//
			$res_member = $this->db->query("SELECT user_id FROM ".$this->db->prefix("xf_user_group")." " ."WHERE user_id='".$user->getVar("uid")."' AND group_id='". $this->getID() ."'");
			 
			if ($this->db->getRowsNum($res_member) < 1)
			{
				//
				// Create this user's row in the user_group table
				//
				$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_user_group")." " ."(user_id,group_id,admin_flags,forum_flags,project_flags," ."doc_flags,cvs_flags,member_role,release_flags,artifact_flags) VALUES(" ."'".$user->getVar("uid")."','". $this->getID() ."','','0','0','0','0','100','0','0')");
				 
				//verify the insert worked
				if (!$res || unofficial_getAffectedRows($res) < 1)
				{
					$this->setError('ERROR: '._XF_GRP_COULDNOTADDUSERTOGROUP);
					return false;
				}
			}
			 
			//
			// audit trail
			//
			$this->addHistory(''._XF_GRP_ADDEDUSER, $user->getVar("uname"));
			 
			return true;
		}
		 
		/**
		*  removeUser() - controls removing a user from a group
		*
		*  Users can remove themselves
		*
		*  @param int  The ID of the user to remove
		* @return true/false
		*/
		function removeUser($user_id)
		{
			global $icmsUser;
			 
			if ($user_id == $icmsUser->getVar("uid"))
			{
				//users can remove themselves
				//everyone else must be a project admin
			}
			else
				{
				$perm = $this->getPermission($icmsUser);
				 
				if (!$perm || !is_object($perm) || !$perm->isAdmin())
				{
					$this->setError(''._XF_GRP_NOTADMINTHISGROUP);
					return false;
				}
			}
			 
			$res = $this->db->query("SELECT * FROM ".$this->db->prefix("xf_user_group")." " ."WHERE group_id='".$this->getID()."' AND user_id='".$user_id."' AND admin_flags='A'");
			 
			if ($this->db->getRowsNum($res) > 0)
			{
				$this->setError(''._XF_GRP_CANNOTREMOVEADMIN);
				return false;
			}
			 
			$res = $this->db->queryF("DELETE FROM ".$this->db->prefix("xf_user_group")." " ."WHERE group_id='".$this->getID()."' AND user_id='".$user_id."' AND admin_flags<>'A'");
			 
			if (!$res)
			{
				$this->setError('ERROR: DB: '._XF_GRP_USERNOTREMOVED);
				return false;
			}
			else
				{
				//
				// remove them from artifact types
				//
				$sql_result = $this->db->query("SELECT DISTINCT group_artifact_id FROM ".$this->db->prefix("xf_artifact_group_list")." WHERE group_id='".$this->getID()."'");
				while ($artifacts = $this->db->fetchArray($sql_result))
				{
					$this->db->queryF("DELETE FROM ".$this->db->prefix("xf_artifact_perm")." " ."WHERE group_artifact_id='".$artifacts['group_artifact_id']."' AND user_id='".$user_id."'");
					 
				}
				//audit trail
				$this->addHistory(''._XF_GRP_REMOVEDUSER, $user_id);
			}
			 
			$remUser = new IcmsUser($user_id);
			if ($remUser)
			{
				if ($this->isProject() && $this->usesCVS())
				{
					$this->removeUserFromCVS($remUser);
				}
			}
			 
			return true;
		}
		 
		/**
		* isFoundryMember($user_id)
		*
		*
		* @return true/false
		* @access public
		*/
		function isFoundryMember($user_id)
		{
			$res_member = $this->db->query("SELECT user_id FROM ".$this->db->prefix("xf_user_foundry_groups")." " ."WHERE user_id='".$user_id."' AND group_id='". $this->getID() ."'");
			 
			if ($this->db->getRowsNum($res_member) >= 1)
			{
				return true;
			}
			else
				{
				return false;
			}
		}
		 
		/**
		* addFoundryMember() - controls adding a user to a foundry group
		*
		*
		* @return true/false
		* @access public
		*/
		function addFoundryMembership($user_id)
		{
			/*
			can only add self.
			*/
			 
			//
			// if not already a member, add them
			//
			 
			$res_member = $this->db->query("SELECT user_id FROM ".$this->db->prefix("xf_user_foundry_groups")." " ."WHERE user_id='".$user_id."' AND group_id='". $this->getID() ."'");
			 
			if ($this->db->getRowsNum($res_member) < 1)
			{
				//
				// Create this user's row in the user_group table
				//
				$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_user_foundry_groups")." " ."(user_id,group_id, join_date) VALUES('".$user_id."','". $this->getID() ."', '".time()."')");
				 
				//verify the insert worked
				if (!$res || unofficial_getAffectedRows($res) < 1)
				{
					$this->setError('ERROR: '._XF_GRP_COULDNOTADDUSERTOGROUP);
					return false;
				}
			}
			 
			//
			// audit trail
			//
			// TODO: localize
			$this->addHistory('Added foundry membership', ''.$user_id);
			 
			return true;
		}
		 
		/**
		*  removeFoundryMembership() - controls removing a user from a Foundry group
		*
		*  Users can remove themselves
		*
		*  @return true/false
		*/
		function removeFoundryMembership($user_id)
		{
			 
			 
			$res_member = $this->db->query("SELECT user_id FROM ".$this->db->prefix("xf_user_foundry_groups")." " ."WHERE user_id='".$user_id."' AND group_id='". $this->getID() ."'");
			 
			if (!($this->db->getRowsNum($res_member) >= 1))
			{
				 
				$this->setError('Cannot remove');
				return false;
				 
			}
			 
			 
			$res = $this->db->queryF("DELETE FROM ".$this->db->prefix("xf_user_foundry_groups")." " ."WHERE group_id='".$this->getID()."' AND user_id='".$user_id."'");
			 
			if (!$res)
			{
				$this->setError('ERROR: DB: '._XF_GRP_USERNOTREMOVED);
				return false;
			}
			else
				{
				 
				//audit trail
				// TODO: localize
				$this->addHistory('Removed foundry membership', $user_id);
			}
			 
			return true;
		}
		 
		 
		/**
		*  updateUser() - controls updating a user's perms in this group
		*
		*  @param int  The ID of the user
		*  @param string         The admin flag for the user
		*  @param int  The forum flag for the user
		*  @param int  The project flag for the user
		*  @param int  The doc flag for the user
		*  @param int  The sample flag for the user
		*  @param int  The CVS flag for the user
		*  @param int  The release flag for the user
		*  @param int  The member role for the user
		*  @param int  The artifact flags for the user
		*  @return true/false
		*/
		function updateUser($user, $group_id, $admin_flags = '', $forum_flags = 0, $project_flags = 1, $doc_flags = 0, $sample_flags = 0, $cvs_flags = 0, $release_flags = 1, $member_role = 100, $artifact_flags = 0)
		{
			global $icmsUser;
			 
			$user_id = $user->getVar("uid");
			 
			$perm = $this->getPermission($icmsUser);
			 
			if (!$perm || !is_object($perm) || !$perm->isAdmin())
			{
				$this->setError(''._XF_GRP_NOTADMINTHISGROUP);
				return false;
			}
			 
			if ($icmsUser->getVar("uid") == $user_id)
			{
				$admin_flags = 'A';
			}
			 
			if ($this->isProject() && $this->usesCVS())
			{
				list($old_cvs_flags) = $this->db->fetchRow($this->db->query("SELECT cvs_flags" ." FROM ".$this->db->prefix("xf_user_group")
				." WHERE group_id='$group_id'" ." AND user_id='$user_id'"));
			}
			 
			$sql = "UPDATE ".$this->db->prefix("xf_user_group")." SET " ."admin_flags='$admin_flags'," ."forum_flags='$forum_flags'," ."project_flags='$project_flags'," ."doc_flags='$doc_flags'," ."sample_flags='$sample_flags'," ."cvs_flags='$cvs_flags'," ."release_flags='$release_flags'," ."artifact_flags='$artifact_flags'," ."member_role='$member_role' " ."WHERE user_id='".$user_id."' AND group_id='".$this->getID()."'
				";
			 
			$res = $this->db->queryF($sql);
			 
			if (!$res)
			{
				$this->setError('ERROR: '._XF_GRP_COULDNOTCHANGEDMEMBER.': '.$this->db->error());
				return false;
			}
			 
			if ($this->isProject() && $this->usesCVS())
			{
				// Only if this is a project
				if (!$old_cvs_flags && $cvs_flags)
				{
					$this->addUserToCVS($user);
				}
				else if($old_cvs_flags && !$cvs_flags)
				{
					$this->removeUserFromCVS($user);
				}
			}
			 
			return true;
		}
		 
		/**
		* addHistory() - Makes an audit trail entry for this project
		*
		*  @param string The name of the field
		*  @param string The Old Value for this $field_name
		* @return database result handle
		* @access public
		*/
		function addHistory($field_name, $old_value)
		{
			global $icmsUser;
			 
			$sql = "INSERT INTO ".$this->db->prefix("xf_group_history")."(group_id,field_name,old_value,mod_by,date) " ."VALUES('". $this->getID() ."','".$field_name."','".$old_value."','". $icmsUser->getVar("uid") ."','".time()."')";
			 
			return $this->db->queryF($sql);
		}
		 
		/**
		* activateUsers() - Make sure that group members have unix accounts
		*
		* Setup unix accounts for group members. Can be called even
		* if members are already active.
		*
		* @access private
		*
		*/
		function activateUsers()
		{
			 
			/*
			Activate member(s) of the project
			*/
			 
			$member_res = $this->db->query("SELECT uid " ."FROM ".$this->db->prefix("users")." u,".$this->db->prefix("xf_user_group")." ug " ."WHERE ug.group_id=".$this->getID()." " ."AND u.uid=ug.user_id");
			 
			while ($member = $this->db->fetchArray($member_res))
			{
				$user = new IcmsUser($member['uid']);
				if (!$this->addUser($user))
				{
					return false;
				}
			}
			 
			return true;
		}
		 
		/**
		* approve() - Approve pending project
		*
		*  @param object The User object
		* @access public
		*
		*/
		function approve(&$user)
		{
			if ($this->getStatus() == 'A')
			{
				$this->setError(""._XF_GRP_GROUPALREADYACTIVE);
				return false;
			}
			if ($this->isProject() && $this->usesCVS())
			{
				if (!$this->createCVSProject())
				{
					return false;
				}
				$this->addUserToCVS($user);
				 
			}
			// Step 1: Activate group
			if (!$this->setStatus($user, 'A'))
			{
				return false;
			}
			// Step 2: Setup forums for this group
			//forum_create_forum($this->getID(),''._XF_FRM_OPENDISCUSSION,1,''._XF_FRM_OPENDISCUSSIONDESC);
			//forum_create_forum($this->getID(),''._XF_FRM_HELP,1,''._XF_FRM_HELPDESC);
			//forum_create_forum($this->getID(),''._XF_FRM_DEVELOPERS,0,''._XF_FRM_DEVELOPERSDESC);
			 
			// Step 3: Setup default DocManager doc_group
			$this->db->queryF("INSERT INTO ".$this->db->prefix("xf_doc_groups")."(groupname,group_id) " ."VALUES('"._XF_DOC_UNCATEGORIZEDSUBS."',".$this->getID()."),('"._XF_DOC_DEVELOPER."',".$this->getID()."),('"._XF_DOC_PROJECT."',".$this->getID()."),('"._XF_DOC_RELATEDRESOURCES."',".$this->getID().")");
			 
			// Step 4: Setup default SampleManager sample_group
			$this->db->queryF("INSERT INTO ".$this->db->prefix("xf_sample_groups")."(groupname,group_id) " ."VALUES('"._XF_DOC_UNCATEGORIZEDSUBS."',".$this->getID().")");
			 
			 
			if ($this->isProject())
			{
				//No file releases for foundries.
				 
				//Step 5: Setup default filerelease on the file system
				$frs = new FRS($this->getID());
				$frs->mkpath($this->getUnixName(), 0775);
			}
			 
			$this->sendApprovalEmail();
			$this->addHistory(''._XF_GRP_APPROVEDPROJECT, 'x');
			 
			return true;
		}
		 
		/**
		* Create project in LDAP for this Group
		* @access private
		*/
		function createCVSProject()
		{
			$cc = false;
			if ($this->isProject() && $this->usesCVS())
			{
				$unixName = $this->getUnixName();
				 
				$lldap = new nxoopsLDAP;
				if ($lldap->connect())
				{
					if ($lldap->bindAdmin())
					{
						if ($lldap->createProject($unixName, $this->group_id,
							$this->anonCVS()))
						{
							$cc = true;
						}
					}
					 
					if ($cc == false)
					{
						$this->setError("Failed to create LDAP Group for project " . $this->getUnixName() . ": " . $lldap->lastError());
					}
					 
					$lldap->cleanUp();
				}
				else
					{
					$this->setError("Failed to create LDAP Group for project " . $this->getUnixName() . ": " . $lldap->lastError());
				}
				 
				unset($lldap);
			}
			return $cc;
		}
		 
		/**
		* Add user to CVS project
		* @access private
		*/
		function addUserToCVS($theUser)
		{
			$cc = false;
			 
			if ($this->isProject() && $this->usesCVS())
			{
				$lldap = new nxoopsLDAP;
				if ($lldap->connect())
				{
					if ($lldap->bindAdmin())
					{
						if ($lldap->addUserToGroup($theUser, $this->getUnixName()))
						{
							$cc = true;
						}
					}
					 
					$lldap->cleanUp();
				}
			}
			 
			return $cc;
		}
		 
		/**
		* Remove user from CVS project
		* @access private
		*/
		function removeUserFromCVS($theUser)
		{
			$cc = false;
			if ($this->isProject() && $this->usesCVS())
			{
				$lldap = new nxoopsLDAP;
				if ($lldap->connect())
				{
					if ($lldap->bindAdmin())
					{
						if ($lldap->removeUserFromGroup($theUser, $this->getUnixName()))
						{
							$cc = true;
						}
					}
					 
					$lldap->cleanUp();
				}
			}
			 
			return $cc;
		}
		 
		/**
		* sendApprovalEmail() - Send new project email
		*
		* @return completion status
		* @access public
		*
		*/
		function sendApprovalEmail()
		{
			global $icmsForge;
			 
			$res_admins = $this->db->query("SELECT u.uid " ."FROM ".$this->db->prefix("users")." u,".$this->db->prefix("xf_user_group")." ug " ."WHERE u.uid=ug.user_id " ."AND ug.group_id=".$this->getID()." " ."AND ug.admin_flags='A'");
			 
			if ($this->db->getRowsNum($res_admins) < 1)
			{
				$this->setError(''._XF_GRP_GROUPHASNOADMINS);
				return false;
			}
			 
			// send one email per admin
			$message = grpGetApprovalMessage($this->getUnixName(), $this->getPublicName(), $this->getID(), $this->getType());
			 
			$icmsMailer = $icmsMailer = getMailer();
			while ($row_admins = $this->db->fetchArray($res_admins))
			{
				$icmsMailer->setToUsers(new IcmsUser($row_admins['uid']));
			}
			$icmsMailer->setFromName("Novell Forge - Noreply");
			$icmsMailer->setFromEmail($icmsForge['noreply']);
			$icmsMailer->setSubject($message['subject']);
			$icmsMailer->setBody($message['body']);
			$icmsMailer->useMail();
			$icmsMailer->send();
			 
			return true;
		}
		 
		 
		/*
		* sendRejectionEmail() - Send project rejection email
		*
		* This function sends out a rejection message to a user who
		* registered a project.
		*
		*      @param int  The id of the response to use
		* @param string         The rejection message
		* @return completion status
		* @access public
		*
		*/
		function sendRejectionEmail($response_id, $message = "zxcv")
		{
			global $ts;
			$res_admins = $this->db->query("SELECT u.uname,u.email " ."FROM ".$this->db->prefix("users")." u,".$this->db->prefix("xf_user_group")." ug " ."WHERE u.uid=ug.user_id " ."AND ug.group_id='".$this->getID()."' " ."AND ug.admin_flags='A'");
			 
			$response = grpGetDeniedMessage($this->getUnixName(), $this->getPublicName(), $this->getType());
			 
			// Check to see if they want to send a custom rejection response
			if ($response_id == 0)
			{
				$response['body'] .= $message;
			}
			else
			{
				$result = $this->db->fetchArray($this->db->query("SELECT response_text FROM ".$this->db->prefix("xf_canned_responses")." WHERE response_id='".$response_id."'"));
				$response['body'] .= $result['response_text'];
			}
			 
			$icmsMailer = $icmsMailer = getMailer();
			while ($admins = $this->db->fetchArray($res_admins))
			{
				$icmsMailer->setToUsers($admins['email']);
			}
			$icmsMailer->setSubject($ts->makeTboxData4Edit($response['subject']));
			$icmsMailer->setBody($ts->makeTboxData4Edit($response['body']));
			$icmsMailer->useMail();
			$icmsMailer->send(false);
			 
			return true;
		}
		/**
		* addFeaturedProject()
		*
		* Adds a project to the featured list for this Foundry.
		*/
		function addFeaturedProject($project_id, $description)
		{
			$sql = "DELETE FROM ".$this->db->prefix("xf_foundry_featured_projects")." " ."where foundry_id='".$this->getID()."' AND project_id='".$project_id."'";
			 
			$this->db->queryF($sql);
			 
			$sql = "INSERT INTO ".$this->db->prefix("xf_foundry_featured_projects")."(foundry_id,project_id,description) " ."VALUES('". $this->getID() ."','".$project_id."','".$description."')";
			 
			return $this->db->queryF($sql);
		}
		 
		/**
		* removeFeaturedProject()
		*
		* Adds a project to the featured list for this Foundry.
		*/
		function removeFeaturedProject($project_id, $description)
		{
			$sql = "DELETE FROM ".$this->db->prefix("xf_foundry_featured_projects")." " ."where foundry_id='".$this->getID()."' AND project_id='".$project_id."'";
			 
			return $this->db->queryF($sql);
		}
		 
		/**
		* isFeaturedProject()
		*
		* Adds a project to the featured list for this Foundry.
		*/
		function isFeaturedProject($project_id)
		{
			$sql = "SELECT * FROM ".$this->db->prefix("xf_foundry_featured_projects")." " ."WHERE foundry_id='".$this->getID()."' AND project_id='".$project_id."'";
			 
			$results = $this->db->queryF($sql);
			 
			if (!$results or $this->db->getRowsNum($results) < 1)
			{
				return false;
			}
			return true;
		}
		/**
		* hasFeaturedProject()
		*
		* returns a boolean representing the state of having featured projects
		*/
		function hasFeaturedProject()
		{
			$sql = "SELECT * FROM ".$this->db->prefix("xf_foundry_featured_projects")." " ."WHERE foundry_id='".$this->getID()."'";
			 
			$results = $this->db->queryF($sql);
			 
			if (!$results or $this->db->getRowsNum($results) < 1)
			{
				return false;
			}
			return true;
		}
		 
		/**
		* getFeaturedProjectDescription()
		*
		* returns the description of the given project
		*/
		function getFeaturedProjectDescription($project_id)
		{
			$sql = "SELECT * FROM ".$this->db->prefix("xf_foundry_featured_projects")." " ."WHERE foundry_id='".$this->getID()."' AND project_id='".$project_id."'";
			 
			$results = $this->db->queryF($sql);
			$featured_count = $this->db->getRowsNum($results);
			 
			if (!$results or $featured_count < 1)
			{
				$this->setError('No description for this freatured project!!');
				return "No description for this freatured project!!";
			}
			else
				{
				while ($featured = $this->db->fetchArray($results))
				{
					$desc = $featured['description'];
					 
					return $desc;
				}
			}
			 
		}
	}
?>