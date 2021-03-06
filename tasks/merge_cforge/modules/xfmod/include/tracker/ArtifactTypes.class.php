<?php
	/**
	* ArtifactTypes.class - Class to handle artifact types
	*
	* SourceForge: Breaking Down the Barriers to Open Source Development
	* Copyright 1999-2001(c) VA Linux Systems
	* http://sourceforge.net
	*
	* @version   $Id: ArtifactTypes.class,v 1.1.1.1 2003/08/01 19:13:48 devsupaul Exp $
	*
	*/
	 
	require_once(ICMS_ROOT_PATH."/modules/xfmod/include/Error.class.php");
	require_once(ICMS_ROOT_PATH."/modules/xfmod/include/tracker/ArtifactType.class.php");
	require_once(ICMS_ROOT_PATH."/modules/xfmod/include/tracker/ArtifactGroup.class.php");
	require_once(ICMS_ROOT_PATH."/modules/xfmod/include/tracker/ArtifactCategory.class.php");
	 
	class ArtifactTypes extends Error {
		var $db;
		/**
		* The artifact type object
		*
		* @var  object $ArtifactType
		*/
		var $Group; //group object
		 
		/**
		* Array of artifact data
		*
		* @var  array $data_array
		*/
		var $data_array;
		 
		/**
		* ArtifactTypes() - constructor.
		*
		* @param object The Group object
		* @return true/false
		*/
		function ArtifactTypes(&$Group)
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
			return true;
		}
		 
		/**
		* createTrackers() creates all the standard trackers for a given Group.
		*
		* @return true/false
		*/
		function createTrackers()
		{
			 
			// first, check if trackers already exist
			$res = $this->db->query("SELECT * FROM ".$this->db->prefix("xf_artifact_group_list")." " ."WHERE group_id='".$this->Group->getID()."' AND datatype > 0");
			 
			if ($this->db->getRowsNum($res) > 0)
			{
				return true;
			}
			 
			if (!$this->createBugTracker())
			{
				return false;
			}
			elseif(!$this->createSupportTracker())
			{
				return false;
			}
			elseif(!$this->createPatchTracker())
			{
				return false;
			}
			elseif(!$this->createFeatureTracker())
			{
				return false;
			}
			else
			{
				return true;
			}
		}
		 
		/**
		* createBugTracker() creates bug tracker
		*
		* @return true/false
		* @access private
		*/
		function createBugTracker()
		{
			$at = new ArtifactType($this->Group);
			if (!$at || !is_object($at))
			{
				$this->setError('Error Creating ArtifactType for Bug Tracker');
				return false;
			}
			else
			{
				if ($at->create(_XF_TRK_BUGS, _XF_TRK_BUGSDESC, 1, 1, 0, '', 30, 1, '', '', 1))
				{
					//
					// create a default category
					//
					$ac = new ArtifactCategory($at);
					$ac->create('Interface(example)', 100);
					 
					//
					// create a default group
					//
					$ag = new ArtifactGroup($at);
					$ag->create('v1.0(example)');
					return true;
				}
				else
				{
					$this->setError('Failed to create bug tracker: '.$at->getErrorMessage());
					return false;
				}
			}
		}
		 
		/**
		* createSupportTracker() creates support tracker
		*
		* @return true/false
		* @access private
		*/
		function createSupportTracker()
		{
			$at = new ArtifactType($this->Group);
			if (!$at || !is_object($at))
			{
				$this->setError('Error Creating ArtifactType for support Tracker');
				return false;
			}
			else
			{
				if ($at->create(_XF_TRK_SUPPORTREQUESTS, _XF_TRK_SUPPORTREQUESTSDESC, 1, 1, 0, '', 15, 0, '', '', 2))
				{
					//
					//  create a default category
					//
					$ac = new ArtifactCategory($at);
					$ac->create('Install Problem(example)', 100);
					 
					//
					//  create a default group
					//
					$ag = new ArtifactGroup($at);
					$ag->create('v1.0(example)');
					return true;
				}
				else
				{
					$this->setError('Failed to create support tracker: '.$at->getErrorMessage());
					return false;
				}
			}
		}
		 
		/**
		* createPatchTracker() creates patch tracker
		*
		* @return true/false
		* @access private
		*/
		function createPatchTracker()
		{
			//
			//  Set up a patch tracker for this group
			//
			$at = new ArtifactType($this->Group);
			if (!$at || !is_object($at))
			{
				$this->setError('Error Creating ArtifactType for patch Tracker');
				return false;
			}
			else
			{
				if ($at->create(_XF_TRK_PATCHES, _XF_TRK_PATCHESDESC, 1, 1, 0, '', 15, 1, '', '', 3))
				{
					//
					//  create a default category
					//
					$ac = new ArtifactCategory($at);
					$ac->create('Widget(example)', 100);
					 
					//
					//  create a default group
					//
					$ag = new ArtifactGroup($at);
					$ag->create('Unstable(example)');
					return true;
				}
				else
				{
					$this->setError('Failed to create patch tracker: '.$at->getErrorMessage());
					return false;
				}
			}
		}
		 
		/**
		* createFeatureTracker() creates feature tracker
		*
		* @return true/false
		* @access private
		*/
		function createFeatureTracker()
		{
			//
			//  Set up a feature request tracker for this group
			//
			$at = new ArtifactType($this->Group);
			if (!$at || !is_object($at))
			{
				$this->setError('Error Creating ArtifactType for feature Tracker');
				return false;
			}
			else
			{
				if ($at->create(_XF_TRK_FEATUREREQUESTS, _XF_TRK_FEATUREREQUESTSDESC, 1, 1, 0, '', 45, 0, '', '', 4))
				{
					//
					//  create a default category
					//
					$ac = new ArtifactCategory($at);
					$ac->create('Interface Improvements(example)', 100);
					 
					//
					//  create a default group
					//
					$ag = new ArtifactGroup($at);
					$ag->create('Next Release(example)');
					return true;
				}
				else
				{
					$this->setError('Failed to create feature tracker: '.$at->getErrorMessage());
					return false;
				}
			}
		}
	}
?>