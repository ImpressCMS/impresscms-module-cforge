<?php
	/**
	* Wrapper for Foundry data.
	*
	* An object wrapper for Foundry(as opposed to project) data.
	* Extends the base object, Group.
	*
	* Example of proper use:
	*
	* // Instantiates the object
	* $grp = new Foundry($group_id);
	*
	* // Now use the object to get the unix_name for the project
	* $grp->getUnixName();
	*
	*
	* SourceForge: Breaking Down the Barriers to Open Source Development
	* Copyright 1999-2001(c) VA Linux Systems
	* http://sourceforge.net
	*
	* @version   $Id: Foundry.class,v 1.1.1.1 2003/08/01 19:13:48 devsupaul Exp $
	* @author Tim Perdue <tperdue@valnux.com>
	* @date 2000-08-28
	*
	*/
	 
	class Foundry extends Group {
		/**
		* Foundry preferences, etc - associative array from the database
		*
		* @var array $foundry_data_array
		*/
		var $foundry_data_array;
		 
		/**
		* Database result set handle for foundry_data
		*
		* @var int $foundry_db_result
		*/
		var $foundry_db_result;
		 
		/**
		* Foundry() - Constructor
		* Constructor for the Error class.
		* Basically just call the parent to set up everything
		*
		* @param int  The foundry ID
		* @param int  Database resource ID
		*
		*/
		function Foundry($id, $res = false)
		{
			$this->Group($id, $res);
			 
			//now set up the foundry data
			 
			$this->foundry_db_result = $this->db->query("SELECT * FROM ".$this->db->prefix("xf_foundry_data")." WHERE foundry_id='$id'");
			 
			if ($this->db->getRowsNum($this->foundry_db_result) < 1)
			{
				//function in class we extended
				$this->setError('Foundry Data Not Found');
				$this->foundry_data_array = array();
			}
			else
			{
				//set up an associative array for use by other functions
				$this->foundry_data_array = $this->db->fetchArray($this->foundry_db_result);
			}
		}
		 
		/**
		* refreshFoundryData() - Refresh object member values.
		* Retreive the latest foundry data and update object members with the new data.
		*
		*/
		function refreshFoundryData()
		{
			$this->refreshGroupData();
			$this->foundry_db_result = $this->db->queryF("SELECT * FROM ".$this->db->prefix("xf_foundry_data")." WHERE foundry_id='". $this->getID() ."'");
			$this->foundry_data_array = $this->db->fetchArray($this->foundry_db_result);
		}
		 
		/**
		* getFreeformHTML1() - Return value of 'freeform1_html' from the foundry_data table.
		* Return value of 'freeform1_html'.
		*
		* @return $foundry_data_array['freeform1_html'] The value of 'freeform1_html' from the foundry_data table.
		*
		*/
		function getFreeformHTML1()
		{
			return $this->foundry_data_array['freeform1_html'];
		}
		 
		/**
		* getFreeformHTML2() - Return value of 'freeform2_html' from the foundry_data table.
		* Return value of 'freeform2_html'.
		*
		* @return $foundry_data_array['freeform2_html'] The value of 'freeform2_html' from the foundry_data table.
		*
		*/
		function getFreeformHTML2()
		{
			return $this->foundry_data_array['freeform2_html'];
		}
		 
		/**
		* getSponsorHTML1() - Return value of 'sponsor1_html' from the foundry_data table.
		* Return value of 'sponsor1_html'.
		*
		* @return $foundry_data_array['sponsor1_html'] The value of 'sponsor1_html' from the foundry_data table.
		*
		*/
		function getSponsorHTML1()
		{
			return $this->foundry_data_array['sponsor1_html'];
		}
		 
		/**
		* getSponsorHTML2() - Return value of 'sponsor2_html' from the foundry_data table.
		* Return value of 'sponsor2_html'.
		*
		* @return $foundry_data_array['sponsor2_html'] The value of 'sponsor2_html' from the foundry_data table.
		*
		*/
		function getSponsorHTML2()
		{
			return $this->foundry_data_array['sponsor2_html'];
		}
		 
		/**
		* getGuideImageID() - Return value of 'guide_image_id' from the foundry_data table.
		* Returns the ID number that corresponds to the appropriate ID # in
		* the db_images table
		*
		* @return $foundry_data_array['guide_image_id'] The value of 'guide_image_id' from the foundry_data table.
		*
		*/
		function getGuideImageID()
		{
			return $this->foundry_data_array['guide_image_id'];
		}
		 
		/**
		* getLogoImageID() - Return value of 'logo_image_id' from the foundry_data table.
		* Get the ID number that corresponds to the appropriate ID # in the db_images table.
		*
		* @return $foundry_data_array['logo_image_id'] The value of 'logo_image_id' from the foundry_data table.
		*
		*/
		function getLogoImageID()
		{
			return $this->foundry_data_array['logo_image_id'];
		}
		 
		/**
		* getTroveCategories() - Get the trove categories.
		* Return value of 'trove_categories' from the foundry_data table.
		*
		* @return $foundry_data_array['trove_categories'] The value of 'trove_categories' from the foundry_data table.
		*
		*/
		function getTroveCategories()
		{
			return $this->foundry_data_array['trove_categories'];
		}
		 
		/**
		* getProjectCommaSep() - Get comma separated list of projects.
		* Returns a comma separated list of member project ids
		*
		* @see getMemberProjects()
		*
		*/
		function getProjectsCommaSep()
		{
			return implode(',', $this->getMemberProjects());
		}
		 
		/**
		* getMemberProjects() - Return an array of member project ID's.
		* Returns an array of member project ids
		*
		* @see utils.php::util_result_column_to_array()
		*
		*/
		function getMemberProjects()
		{
			//return an array of group_id's in this project
			$sql = "SELECT DISTINCT project_id FROM ".$this->db->prefix("xf_foundry_projects")." WHERE foundry_id='". $this->getID() ."' ORDER BY project_id ASC";
			$result = $this->db->query($sql);
			return util_result_column_to_array($result);
		}
	}
	 
?>