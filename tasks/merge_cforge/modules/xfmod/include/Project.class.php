<?php
	/**
	* Project class
	*
	* An object wrapper for project(as opposed to foundry) data
	* Extends the base object, Group
	*
	* Example of proper use:
	*
	* //get a local handle for the object
	* $grp = group_get_object($group_id);
	*
	* //now use the object to get the unix_name for the project
	* $grp->getUnixName();
	*
	*
	* SourceForge: Breaking Down the Barriers to Open Source Development
	* Copyright 1999-2001(c) VA Linux Systems
	* http://sourceforge.net
	*
	* @version   $Id: Project.class,v 1.1.1.1 2003/08/01 19:13:48 devsupaul Exp $
	* @author Tim Perdue <tperdue@valinux.com>
	* @date 2000-08-28
	*
	*/
	 
	class Project extends Group {
		 
		/**
		* An array containing project data.
		*
		* @var  array $project_data_array
		*/
		var $project_data_array;
		 
		/**
		* Project() - Constructor
		* Basically just call the parent to set up everything
		*
		* @param int  The project ID
		* @param int  An optional database resource ID
		*
		*/
		function Project($id, $res = false)
		{
			$this->Group($id, $res);
			//echo "\r\nProject::Project() - Rows: ".$this->db->getRowsNum($res);
			 
			//for right now, just point our prefs
			//array at Group's data array
			//this will change later when we split the
			//project_data table off from groups table
			$this->project_data_array = &$this->data_array;
		}
		 
		 
		/**
		* usesCVS() - Returns whether a project uses CVS
		*
		*/
		function usesCVS()
		{
			return $this->project_data_array['use_cvs'];
		}
		 
		function anonCVS()
		{
			return $this->project_data_array['anon_cvs'];
		}
		 
		/**
		* usesPM() - Returns whether a project uses Project Manager
		*
		*/
		function usesPM()
		{
			return $this->project_data_array['use_pm'];
		}
		 
		/**
		* usesPmDependencies() - Returns whether a projected uses PM dependencies
		*
		*/
		function usesPmDependencies()
		{
			return $this->project_data_array['use_pm_depend_box'];
		}
		 
		/**
		* getNewTaskAddress() - Returns the default address to which new task submissions are sent
		*
		*/
		function getNewTaskAddress()
		{
			return $this->project_data_array['new_task_address'];
		}
		 
		/**
		* sendAllTaskUpdates() - Returns whether all task updates should be sent to the default address
		*
		* @see getNewTaskAddress()
		*
		*/
		function sendAllTaskUpdates()
		{
			return $this->project_data_array['send_all_tasks'];
		}
	}
	 
	/**
	* group_getname() - get the group name
	*
	* @param  int  The group ID
	* @deprecated
	*
	*/
	function group_getname($group_id = 0)
	{
		$grp = group_get_object($group_id);
		if ($grp)
		{
			return $grp->getPublicName();
		}
		else
		{
			return 'Invalid';
		}
	}
	 
	/**
	* group_getunixname() - get the unixname for a group
	*
	* @param  int  The group ID
	* @deprecated
	*
	*/
	function group_getunixname($group_id)
	{
		$grp = group_get_object($group_id);
		if ($grp)
		{
			return $grp->getUnixName();
		}
		else
		{
			return 'Invalid';
		}
	}
	 
	/**
	* group_get_result() - Get the group object result ID.
	*
	* @param  int  The group ID
	* @deprecated
	*
	*/
	function group_get_result($group_id = 0)
	{
		$grp = group_get_object($group_id);
		if ($grp)
		{
			return $grp->getData();
		}
		else
		{
			return 0;
		}
	}
	 
?>