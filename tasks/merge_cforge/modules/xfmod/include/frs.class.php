<?php
	/**
	* Base class for FRS(File Release System) and QRS(Quick-file Release System)
	* SourceForge: Breaking Down the Barriers to Open Source Development
	* Copyright 1999-2001(c) VA Linux Systems
	* http://sourceforge.net
	*
	* @version   $Id: frs.class,v 1.15 2004/05/06 16:14:46 devsupaul Exp $
	* @author Darrell Brogdon <dbrogdon@valinux.com>
	*/
	require_once(ICMS_ROOT_PATH."/modules/xfmod/include/Error.class.php");
	 
	class FRS extends Error {
		var $db;
		/**
		* The group ID
		*
		* @var                int                $group_id
		*/
		var $group_id = "";
		 
		/**
		* The package ID
		*
		* @var                int                $package_id
		*/
		var $package_id = "";
		 
		/**
		* The release ID
		*
		* @var                int                $release_id
		*/
		var $release_id = "";
		 
		/**
		* The project ID
		*
		* @var                int                $project_id
		*/
		var $project_id = "";
		 
		/**
		* The file ID
		*
		* @var                int                $file_id
		*/
		var $file_id = "";
		 
		/**
		* The file type
		*
		* @var                string        $file_type
		*/
		var $file_type = "";
		 
		/**
		* The processor type
		*
		* @var                string        $proc_type
		*/
		var $proc_type = "";
		 
		/**
		* The processor type
		*
		* @var                string        $proc_type
		*/
		var $ftp_conn = "";
		 
		/**
		* Caching variables
		*
		* @var          ResultSet  $res_frsVerifyFile
		* @var          ResultSet  $res_frsVerifyFileExist
		* @var          ResultSet  $res_frsVerifyFileRelease
		*/
		var $res_frsVerifyFile;
		var $res_frsVerifyFileExist;
		var $res_frsVerifyFileRelease;
		 
		/**
		* FRS() - Constructor
		*
		* Sets the value of Class vars if the function arguments exist.
		*
		* @param        int                The group ID
		* @param        int                The package ID
		* @param        int                The release ID
		*/
		function FRS($group_id)
		{
			global $icmsDB, $icmsForge;
			 
			$this->group_id = $group_id;
			$this->db = $icmsDB;
			$this->Error();
			 
			if ($icmsForge['ftp_server'] && $icmsForge['ftp_server'] != 'localhost' && $icmsForge['ftp_server'] != '127.0.0.1')
			{
				if (!$icmsForge['ftp_internal_server']) $icmsForge['ftp_internal_server'] = $icmsForge['ftp_server'];
				$this->ftp_conn = ftp_connect($icmsForge['ftp_internal_server']);
				if (! ftp_login($this->ftp_conn, $icmsForge['ftp_user'], $icmsForge['ftp_password']))
				{
					unset($this->ftp_conn);
					$this->setError(' Could not authenticate to the FTP server ');
				}
			}
		}
		 
		/**
		* mkpath
		*
		*@param string  The name of the path to create minus the ftp base directory sturcture
		*@param int  Octet string describing permissions for the directories
		*/
		 
		function mkpath($path, $mode = 0770)
		{
			global $icmsForge;
			$base = $icmsForge['ftp_path'];
			$dirs = explode("/", $path);
			if (!$icmsForge['ftp_server'] || $icmsForge['ftp_server'] == 'localhost' || $icmsForge['ftp_server'] == '127.0.0.1')
			{
				foreach($dirs as $dir)
				{
					$base = "$base/$dir";
					if (is_dir($base)) continue;
					if (!mkdir($base, $mode))
					{
						$this->setError(" "._XF_FRS_MAKEDIRFAILED." ");
						return false;
					}
				}
			}
			else
				{
				//a remote ftp server
				$last = array_pop($dirs);
				foreach($dirs as $dir)
				{
					$base = "$base/$dir";
					@ftp_mkdir($this->ftp_conn, $base);
				}
				$base = "$base/$last";
				if (!ftp_mkdir($this->ftp_conn, $base))
				{
					$this->setError(" "._XF_FRS_MAKEDIRFAILED." ");
					return false;
				}
				 
			}
			$this->chmodpath($base, $mode);
			return true;
		}
		 
		/**
		* rmpath
		*
		*@param string  The name of the directory to remove
		*/
		 
		function rmpath($path)
		{
			global $icmsForge;
			$base = $icmsForge['ftp_path'];
			if (!$icmsForge['ftp_server'] || $icmsForge['ftp_server'] == 'localhost' || $icmsForge['ftp_server'] == '127.0.0.1')
			{
				if (!rmdir("$base/$path"))
				{
					$this->setError(" "._XF_FRS_RMDIRFAILED." ");
					return false;
				}
			}
			else
				{
				if (!ftp_rmdir($this->ftp_conn, "$base/$path"))
				{
					$this->setError(" "._XF_FRS_RMDIRFAILED." ");
					return false;
				}
			}
			return true;
		}
		 
		/**
		* rmpath
		*
		*@param string  The name of the path to be renamed
		*@param string  The new name of the path
		*/
		function movepath($oldpath, $newpath)
		{
			global $icmsForge;
			$base = $icmsForge['ftp_path'];
			 
			if (!$icmsForge['ftp_server'] || $icmsForge['ftp_server'] == 'localhost' || $icmsForge['ftp_server'] == '127.0.0.1')
			{
				if (!rename("$base/$oldpath", "$base/$newpath"))
				{
					$this->setError(' '._XF_FRS_CHANGEPACKAGENAMEFAILED.'  ');
					return false;
				}
			}
			else
				{
				if (!ftp_rename($this->ftp_conn, "$base/$oldpath", "$base/$newpath"))
				{
					$this->setError(' '._XF_FRS_CHANGEPACKAGENAMEFAILED.'  ');
					return false;
				}
			}
			return true;
		}
		 
		/**
		* mkpath
		*
		*@param string  The name of the path to create minus the ftp base directory sturcture
		*@param int  Octet string describing permissions for the directories
		*/
		 
		function chmodpath($path, $mode = 0770)
		{
			global $icmsForge;
			 
			if (!$icmsForge['ftp_server'] || $icmsForge['ftp_server'] == 'localhost' || $icmsForge['ftp_server'] == '127.0.0.1')
			{
				if (!chmod($path, $mode))
				{
					 
				}
			}
			else
				{
				$mode = sprintf("%o", $mode);
				if (!ftp_site($this->ftp_conn, "chmod $mode $path"))
				{
					$this->setError(" "._XF_FRS_CHMODFAILED." ");
					return false;
				}
				 
			}
			return true;
		}
		 
		 
		/**
		* addfile
		*
		*@param string   The name of the file on the remote machine
		*@param string  The name of the local file to put on the remote machine
		*@param int  Octet string describing permissions for the directories
		*/
		function addfile($remote_file, $local_file, $mode = 0660)
		{
			global $icmsForge;
			$base = $icmsForge['ftp_path'];
			if (!$icmsForge['ftp_server'] || $icmsForge['ftp_server'] == 'localhost' || $icmsForge['ftp_server'] == '127.0.0.1')
			{
				// the ftp server is on the web server
				if (move_uploaded_file($local_file, "$base/$remote_file"))
				{
					$this->chmodpath("$base/$remote_file", $mode);
				}
				else
					{
					$this->setError(' '._XF_FRS_ADDFILEFAILED.': ' . 'Invalid Upload '.$base.'/'.$remote_file);
					return false;
				}
			}
			else
				{
				//a remote ftp server
				if (is_uploaded_file($local_file))
				{
					if (!ftp_put($this->ftp_conn, "$base/$remote_file", $local_file, FTP_BINARY))
					{
						$this->setError(' '._XF_FRS_ADDFILEFAILED.': ' . 'Could not copy file to FTP server');
						return false;
					}
					$this->chmodpath("$base/$remote_file", $mode);
				}
				else
					{
					$this->setError(' '._XF_FRS_ADDFILEFAILED.': ' . 'Invalid Upload '.$base.'/'.$remote_file);
					return false;
				}
			}
			return true;
		}
		 
		function rmfile($file)
		{
			global $icmsForge;
			$base = $icmsForge['ftp_path'];
			 
			if (!$icmsForge['ftp_server'] || $icmsForge['ftp_server'] == 'localhost' || $icmsForge['ftp_server'] == '127.0.0.1')
			{
				if (!unlink("$base/$file")) return false;
			}
			else
				{
				if (!ftp_delete($this->ftp_conn, "$base/$file")) return false;
			}
			return true;
		}
		 
		 
		//  function escape(&$str){
		//   $str = preg_replace('/([^\w|\/])/i',"\\\\\\1",$str);
		//   return $str;
		//  }
		 
		/**
		* frsAddChangeLog() - Add an entry to the change log
		*
		* @param        int                The text that is to be added to the change log
		* @return True on success, False on error
		*/
		function frsAddChangeLog($change_log_text)
		{
			global $ts;
			 
			if (!$this->frsVerifyRelease($release_id, $package_id))
			{
				$this->setError(' '._XF_FRS_ADDCHANGELOGFAILED.': ' . $this->getErrorMessage());
				return false;
			}
			 
			$res = $this->db->queryF("UPDATE ".$this->db->prefix("xf_frs_release")." SET changes='".$ts->makeTareaData4Save($change_log_text)."' " ."WHERE release_id='$this->release_id'");
			 
			if (!$res)
			{
				$this->setError(' '._XF_FRS_ADDCHANGELOGFAILED.' ');
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsAddFile() - Add a new file
		*
		* @param        int           Time of the release
		* @param        string        The name of the file to add
		* @param        int           The ID of the release this file to which this file is associated
		* @param        int           The file size
		* @param        int           The post date
		* @param        int           The ide of the release
		* @param        int           The ID of the package
		*/
		function frsAddFile($release_time, $filename, $file_url, $file_size, $post_date, $release_id, $package_id)
		{
			if ($file_url != "")
			{
				$this->frsAddRemoteFile($release_time, $filename, $file_url, $file_size, $post_date, $release_id, $package_id);
			}
			else
				{
				$this->frsAddLocalFile($release_time, $post_date, $release_id, $package_id);
			}
		}
		 
		function frsAddRemoteFile($release_time, $filename, $file_url, $file_size, $post_date, $release_id, $package_id)
		{
			global $project;
			if (!$this->frsVerifyRelease($release_id, $package_id))
			{
				$this->setError(' '._XF_FRS_ADDFILEFAILED.': ' . $this->getErrorMessage());
				return false;
			}
			if (strlen($filename) < 1)
			{
				$filename = basename($file_url);
			}
			if (strlen($file_size) < 1)
			{
				$file_size = 0;
			}
			 
			$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_frs_file")." " ."(release_time,filename,file_url,release_id,file_size,post_date) VALUES(" ."'$release_time','$filename','$file_url','$release_id','$file_size','$post_date')");
			if (!$res)
			{
				$this->setError(' '._XF_FRS_ADDFILEFAILED.': ' . $this->db->error());
				return false;
			}
			 
			$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_frs_dlstats_file_agg")." " ."(file_id) VALUES(" ."'".$this->db->getInsertId()."')");
			 
			if (!$res)
			{
				$this->setError(' '._XF_FRS_ADDFILEFAILED.': ' . $this->db->error());
				return false;
			}
			 
			return true;
			 
		}
		 
		function frsAddLocalFile($release_time, $post_date, $release_id, $package_id)
		{
			global $project, $icmsForge;
			 
			if (!$this->frsVerifyRelease($release_id, $package_id))
			{
				$this->setError(' '._XF_FRS_ADDFILEFAILED.': ' . $this->getErrorMessage());
				return false;
			}
			$filename = $_FILES['file1']['name'];
			$file_size = $_FILES['file1']['size'];
			 
			$tmp_name = $_FILES['file1']['tmp_name'];
			if (VirusScan($tmp_name))
			{
				unlink($tmp_name);
				$this->setError(' '._XF_FRS_VIRUSSCANFAILED.' ');
				return false;
			}
			$res = $this->frsGetRelease($release_id);
			$unix_name = $project->getUnixName();
			$release_name = unofficial_getDBResult($res, 0, "release_name");
			$package_name = unofficial_getDBResult($res, 0, "package_name");
			$file_url = $unix_name."/".$package_name."/".$release_name."/".$filename;
			 
			if (!$this->addfile($file_url, $tmp_name, 0664))
			{
				return false;
			}
			 
			$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_frs_file")
			."(release_time,filename,file_url,release_id,file_size,post_date)" ." VALUES('$release_time','$filename','$filename','$release_id','$file_size','$post_date')");
			 
			if (!$res)
			{
				$this->setError(' '._XF_FRS_ADDFILEFAILED.': ' . $this->db->error());
				return false;
			}
			 
			$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_frs_dlstats_file_agg")." " ."(file_id) VALUES(" ."'".$this->db->getInsertId()."')");
			 
			if (!$res)
			{
				$this->setError(' '._XF_FRS_ADDFILEFAILED.': ' . $this->db->error());
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsAddRelease() - Add a new release
		*
		* @param        string        The name of the new release
		* @param        int           The ID of the package with which this release is to be associated
		* @return Database result ID
		*/
		function frsAddRelease($release_name, $package_id, $notes = "", $changes = "", $dependencies = "")
		{
			global $icmsUser, $ts;
			if (!$this->frsVerifyPackage($package_id))
			{
				$this->setError(' '._XF_FRS_ADDRELEASEFAILED.': ' . $this->getErrorMessage());
				return false;
			}
			$sql = "SELECT g.unix_group_name, p.name" ." FROM ".$this->db->prefix("xf_groups")." AS g" .",".$this->db->prefix("xf_frs_package")." AS p" ." WHERE p.group_id=g.group_id" ." AND p.package_id=$package_id";
			$res = $this->db->query($sql);
			list($unix_group_name, $package_name) = $this->db->fetchRow($res);
			 
			if (!$this->mkpath("$unix_group_name/$package_name/$release_name", 0770))
			{
				//private by default
				return false;
			}
			 
			$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_frs_release")."(package_id,name,notes,changes,status_id,release_date,released_by,dependencies) " ."VALUES('$package_id','".$ts->makeTareaData4Save($release_name)."','".$ts->makeTareaData4Save($notes)."','".$ts->makeTareaData4Save($changes)."','3','". time() ."','". $icmsUser->getVar("uid") ."','". $ts->makeTareaData4Save($dependencies)."')");
			if (!$res)
			{
				$this->setError(' '._XF_FRS_ADDRELEASEFAILED.' ');
				return false;
			}
			else
			{
				return $this->db->getInsertId();
			}
		}
		 
		/**
		* frsAddPackage() - Create a new package
		*
		* @param        string        Name of the package to create
		* @return True on success, False on error
		*/
		function frsAddPackage($package_name, $unix_group_name)
		{
			global $ts;
			 
			if (!$this->frsVerifyPackageName($package_name))
			{
				return false;
			}
			if (!$this->mkpath("$unix_group_name/$package_name", 0775))
			{
				return false;
			}
			$res = $this->db->queryF("INSERT INTO ".$this->db->prefix("xf_frs_package")."(group_id,name,status_id) VALUES('".$this->group_id."','".$ts->makeTboxData4Save($package_name)."',1)");
			if (!$res)
			{
				//remove the path that was just created!
				$this->rmpath("$unix_group_name/$package_name");
				$this->setError(' '._XF_FRS_CREATEPACKAGEFAILED.' ');
				return false;
			}
			$this->setError(' '._XF_PRJ_ADDEDPACKAGE.' '.$this->getErrorMessage());
			return true;
			 
		}
		 
		/**
		* frsAddNotes() - Add a new release note
		*
		* @param        string        The text of the release notes
		* @return True on success, False on error
		*/
		function frsAddNotes($notes_text, $release_id)
		{
			global $ts;
			if (!$this->frsVerifyRelease($release_id, $package_id))
			{
				$this->setError(' '._XF_FRS_ADDNOTESFAILED.': ' . $this->getErrorMessage());
				return false;
			}
			 
			$res = $this->db->queryF("UPDATE ".$this->db->prefix("xf_frs_release")." SET notes='".$ts->makeTareaData4Save($notes_text)."' " ."WHERE release_id='$release_id'");
			if (!$res)
			{
				$this->setError(' '._XF_FRS_ADDNOTESFAILED.' ');
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsAddDependencies() - Add a new release dependencies
		*
		* @param        string        The text of the release dependencies
		* @return True on success, False on error
		*/
		function frsAddDependencies($dependencies_text, $release_id)
		{
			global $ts;
			if (!$this->frsVerifyRelease($release_id, $package_id))
			{
				$this->setError(' '._XF_FRS_ADDDEPENDENCIESFAILED.': ' . $this->getErrorMessage());
				return false;
			}
			 
			$res = $this->db->queryF("UPDATE ".$this->db->prefix("xf_frs_release")." SET dependencies='".$ts->makeTareaData4Save($dependencies_text)."' " ."WHERE release_id='$release_id'");
			if (!$res)
			{
				$this->setError(' '._XF_FRS_ADDDEPENDENCIESFAILED.' ');
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsChangePackageName() - Change a package name
		*
		* @param        string        The new name to give to a package
		* @param        string        The package ID for which to change the name
		* @return True on success, False on error
		*/
		function frsChangePackageName($new_name, $package_id)
		{
			global $ts;
			if (!$this->frsVerifyPackage($package_id))
			{
				$this->setError(' '._XF_FRS_CHANGEPACKAGENAMEFAILED.': ' . $this->getErrorMessage());
				return false;
			}
			 
			$res = $this->db->queryF("UPDATE ".$this->db->prefix("xf_frs_package")." SET name='".$ts->makeTboxData4Save($new_name)."' " ."WHERE package_id='$package_id' AND group_id='$this->group_id");
			 
			if (!$res)
			{
				$this->setError(' '._XF_FRS_CHANGEPACKAGENAMEFAILED.' ');
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsChangeFile() - Change a file
		*
		* @param        int                Time of the release
		* @param        int                ID of the file type
		* @param        int                ID of the processor type
		* @param        int                ID of the file to change
		* @param        int                The file url is no longer used
		* @param        int                ID of the release to which this file is related
		* @param        int                ID of the package this release belongs to
		* @return True on success, False on error
		*/
		function frsChangeFile($file_id, $file_url, $filename, $file_size, $release_time, $release_id, $package_id)
		{
			if (!$this->frsVerifyRelease($release_id, $package_id))
			{
				$this->setError(' '._XF_FRS_CHANGEFILEFAILED.': ' . $this->getErrorMessage());
				return false;
			}
			 
			$date_list = split("-", $release_time, 3);
			//
			//
			//        date validation?
			//
			//
			 
			$unix_release_time = mktime(0, 0, 0, $date_list[1], $date_list[2], $date_list[0]);
			 
			$res = $this->db->queryF("UPDATE ".$this->db->prefix("xf_frs_file")." SET release_time='$unix_release_time',file_size='$file_size',file_url='$file_url',filename='$filename' " ." WHERE file_id='$file_id'");
			 
			if (!$res)
			{
				$this->setError(' '._XF_FRS_CHANGEFILEFAILED.' ');
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsChangeFileRelease() - Change a file's release
		*
		* @param        int                The ID of the file
		* @param        int                The ID of the release
		* @return True on success, False on error
		*/
		function frsChangeFileRelease($file_id, $release_id)
		{
			if (!$this->frsVerifyRelease($release_id, $package_id))
			{
				$this->setError(' '._XF_FRS_CHANGEFILERELEASEFAILED.': ' . $this->getErrorMessage());
				return false;
			}
			 
			if (!$this->frsVerifyFile($file_id))
			{
				$this->setError(' '._XF_FRS_CHANGEFILERELEASEFAILED.': ' . $this->getErrorMessage());
				return false;
			}
			 
			$res = $this->db->queryF("UPDATE ".$this->db->prefix("xf_frs_file")." SET release_id='$release_id' " ."WHERE file_id='$file_id'");
			 
			if (!$res)
			{
				$this->setError(' '._XF_FRS_CHANGEFILERELEASEFAILED.' ');
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsChangeRelease() - Change a release
		*
		* @param        int           The date of the release
		* @param        string        The name of the release
		* @param        int           The flag to store notes and changes as pre-formatted text
		* @param        int           The status ID of the release
		* @param        int           The package ID to which the release is related
		* @param        string        The release notes text
		* @param        string        The change log text
		* @param        int           The ID of the release
		* @param        string        The dependencies of the release
		*/
		function frsChangeRelease($release_date, $release_name, $preformatted, $status_id, $notes, $changes, $new_package_id, $package_id, $release_id, $dependencies)
		{
			global $ts, $project, $perm, $icmsForge;
			if (!$this->frsVerifyRelease($release_id, $package_id))
			{
				$this->setError(' '._XF_FRS_CHANGERELEASEFAILED.': ' . $this->getErrorMessage());
				return false;
			}
			 
			// Make sure the date format is correct.
			// If so then turn it into a timestamp.
			if (!$this->frsVerifyReleaseDate($release_date))
			{
				$this->setError(' '._XF_FRS_CHANGERELEASEFAILED.': ' . $this->getErrorMessage());
				return false;
			}
			else
			{
				$date_expl = explode("-", $release_date);
				$release_date = mktime(0, 0, 0, $date_expl[1], $date_expl[2], $date_expl[0]);
			}
			 
			$res = $this->db->query("SELECT name from ".$this->db->prefix("xf_frs_release")." WHERE release_id=".$release_id);
			$row = $this->db->fetchArray($res);
			 
			//these three items are need to change both a release name and package_id below.
			$result = $this->frsGetRelease($release_id);
			$unix_name = $project->getUnixName();
			$package_name = unofficial_getDBResult($result, 0, "package_name");
			 
			//if the release name changed, rename the directory that contains the files.
			if ($row['name'] != $release_name)
			{
				if (!$this->frsVerifyReleaseName($release_name, $package_id)) return false;
				if (!$this->movepath("$unix_name/$package_name/".$row['name'], "$unix_name/$package_name/$release_name"))
				{
					return false;
				}
			}
			 
			$mode = ($status_id == 3)?0770:
			0775;
			$this->chmodpath($icmsForge['ftp_path']."/$unix_name/$package_name/$release_name", $mode);
			 
			$res = $this->db->queryF("UPDATE ".$this->db->prefix("xf_frs_release")." SET " ."release_date='$release_date'," ."name='$release_name'," ."preformatted='$preformatted'," ."status_id='$status_id'," ."notes='".$ts->makeTareaData4Save($notes)."'," ."changes='".$ts->makeTareaData4Save($changes)."'," ."dependencies='" . $ts->makeTareaData4Save($dependencies) . "' " ."WHERE release_id='$release_id'");
			 
			if (!$res)
			{
				$this->setError(' '._XF_FRS_CHANGERELEASEFAILED.' ');
				return false;
			}
			 
			if ($new_package_id != $package_id)
			{
				if (!$this->frsVerifyReleaseName($release_name, $new_package_id)) return false;
				$path_old = $unix_name."/".$package_name."/".$release_name;
				$sql = "SELECT p.name as name, s.status_id as status_id, s.name as status_name FROM" ." ".$this->db->prefix("xf_frs_package")." AS p" ." , ".$this->db->prefix("xf_frs_status")." AS s" ." WHERE package_id=$new_package_id" ." AND p.status_id=s.status_id";
				$res = $this->db->query($sql);
				if (!$res)
				{
					$this->setError("  Could not find the package you were trying to move your release to.  ");
					return false;
				}
				$row = $this->db->fetchArray($res);
				$new_package_name = $row['name'];
				if ($row['status_id'] == 2 && !$perm->isReleaseAdmin())
				{
					$this->setError("  You must be a Release Admin to move a release into a ".strtolower($row['status_name'])." package. ");
					return false;
				}
				if ($row['status_id'] == 3 && $status_id != 3)
				{
					$this->setError("  You must make your release private before moving it into a ".strtolower($row['status_name'])." package.  ");
					return false;
				}
				$path_new = $unix_name."/".$new_package_name."/".$release_name;
				if (!$this->movepath($path_old, $path_new))
				{
					return false;
				}
				 
				$res = $this->db->queryF("UPDATE ".$this->db->prefix("xf_frs_release")." SET" ." package_id='$new_package_id'" ." WHERE release_id='$release_id'");
				if (!$res)
				{
					$this->setError(' '._XF_FRS_CHANGERELEASEFAILED.' ');
					return false;
				}
			}
			 
			return true;
		}
		 
		/**
		* frsChangeRelease() - Change a release
		*
		* @param        int           The package id
		* @param        string        The possibly new package name
		* @param        int           The possibly new status of the package(ie development, stable, private)
		*/
		 
		function frsChangePackage(&$group, $package_id, $package_name, $status_id)
		{
			global $icmsForge, $ts;
			//check for renaming problems
			$unix_name = $group->getUnixName();
			$res = $this->db->query("SELECT name FROM ".$this->db->prefix("xf_frs_package")." WHERE package_id=".$package_id);
			$old_name = unofficial_getDBResult($res, 0, 'name');
			if ($old_name != $package_name)
			{
				if (!$this->frsVerifyPackageName($package_name)) return false;
				if (!$this->movepath("$unix_name/$old_name", "$unix_name/$package_name"))
				{
					return false;
				}
			}
			$mode = ($status_id == 3)?0770:
			0775;
			$this->chmodpath($icmsForge['ftp_path']."/$unix_name/$package_name", $mode);
			 
			$package_name = $ts->makeTboxData4Save($package_name);
			//update an existing package
			$sql = "UPDATE ".$this->db->prefix("xf_frs_package")." SET name='".$package_name."'";
			if ($status_id) $sql .= ", status_id='$status_id'";
			$sql .= " WHERE package_id='$package_id' AND group_id='".$this->group_id."'";
			$this->db->queryF($sql);
			if (!$this->isError()) $this->setError(' '._XF_PRJ_UPDATEDPACKAGE.'  ');
			return true;
		}
		 
		/**
		* frsGetReleaseMonitors() - Get a count of the users that are monitoring a release
		*
		* @param        int                ID of the package that users are monitoring
		* @return A count of the number of users monitoring $package_id
		*/
		function frsGetReleaseMonitors($package_id)
		{
			$result = unofficial_getDBResult($this->db->query("SELECT COUNT(*) FROM ".$this->db->prefix("xf_filemodule_monitor")." WHERE filemodule_id='$package_id'"), 0, 0);
			 
			return $result;
		}
		 
		/**
		* frsGetReleaseList() - Get a list of the releases
		*
		* @param        string        String used to narrow the search
		* @return Database result ID
		*/
		function frsGetReleaseList($pkg_str = "")
		{
			$res = $this->db->query("SELECT r.release_id,p.name AS package_name,p.package_id,r.name AS release_name,r.status_id,s.name AS status_name " ."FROM ".$this->db->prefix("xf_frs_release")." r,".$this->db->prefix("xf_frs_package")." p,".$this->db->prefix("xf_frs_status")." s " ."WHERE p.group_id='$this->group_id' AND " ."r.package_id=p.package_id " ."$pkg_str AND " ."s.status_id=r.status_id");
			 
			if (!$res)
			{
				$this->setError(' '._XF_FRS_GETRELEASELISTFAILED.' ');
				return false;
			}
			 
			return $res;
		}
		 
		/**
		* frsGetPackageList() - Wrapper for getReleaseList
		*
		* @param        int                The ID of the package... duh!
		*/
		function frsGetPackageList($package_id)
		{
			return $this->frsGetReleaseList("AND ".$this->db->prefix("xf_frs_package").".package_id='$package_id'");
		}
		 
		/**
		* frsGetRelease() - Get a specific release
		*
		* @param        int                ID of the release
		* @return Database result ID
		*/
		function frsGetRelease($release_id)
		{
			$sql = "SELECT r.release_date,r.package_id,r.name AS release_name,r.status_id,r.notes,r.changes,r.preformatted,p.name AS package_name, r.dependencies " ."FROM ".$this->db->prefix("xf_frs_release")." r,".$this->db->prefix("xf_frs_package")." p " ."WHERE r.release_id='$release_id' " ."AND p.package_id=r.package_id " ."AND p.group_id='$this->group_id'";
			 
			$result = $this->db->query($sql);
			 
			if (!$result || $this->db->getRowsNum($result) < 1)
			{
				$this->setError(" "._XF_FRS_GETRELEASEFAILED." [$release_id:$this->group_id] ");
				return false;
			}
			 
			return $result;
		}
		 
		/**
		* frsGetReleaseFiles() - Get files associated with a release
		*
		* @param        int                ID of the release
		* @return Database result ID
		*/
		function frsGetReleaseFiles($release_id)
		{
			$sql = "SELECT * FROM ".$this->db->prefix("xf_frs_file")." WHERE release_id='$release_id'";
			 
			$result = $this->db->query($sql);
			 
			if (!$result)
			{
				$this->setError(' '._XF_FRS_GETRELEASEFILESFAILED.' ');
				return false;
			}
			 
			return $result;
		}
		 
		/**
		* frsVerifyFileOwnership() - Verify the ownership of a file
		*
		* @param        int                ID of the file to verify
		* @return Database result ID
		*/
		function frsVerifyFileOwnership($file_id)
		{
			 
			$res = $this->db->query("SELECT f.filename " ."FROM ".$this->db->prefix("xf_frs_package")." p,".$this->db->prefix("xf_frs_release")." r,".$this->db->prefix("xf_frs_file")." f " ."WHERE p.group_id='$this->group_id' AND " ."r.release_id=f.release_id AND " ."r.package_id=p.package_id AND " ."f.file_id='$file_id'");
			 
			if (!$res || $this->db->getRowsNum($res) < 1)
			{
				$this->setError(' '._XF_FRS_VERIFYFILEOWNERSHIPFAILED.': ');
				return false;
			}
			 
			return $res;
		}
		 
		/**
		* frsVerifyRelease() - Verify whether a release belongs to a project
		*
		* @param        int                ID of the release
		* @param        int                ID of the package
		* @return True on success, False on error
		*/
		function frsVerifyRelease($release_id, $package_id)
		{
			$sql = "SELECT p.package_id " ."FROM ".$this->db->prefix("xf_frs_package")." p" .",".$this->db->prefix("xf_frs_release")." r" ." WHERE p.package_id='$package_id'" ." AND p.group_id='$this->group_id'" ." AND r.release_id='$release_id'" ." AND r.package_id=p.package_id";
			$res = $this->db->query($sql);
			 
			if (!$res || $this->db->getRowsNum($res) < 1)
			{
				$this->setError(' '._XF_FRS_VERIFYRELEASEFAILED.' [' . $release_id . ':' . $package_id . ']');
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsVerifyPackage() - Verify whether a package belongs to a group
		*
		* @param        int                ID of the package
		* @return True on success, False on error
		*/
		function frsVerifyPackage($package_id)
		{
			$sql = "SELECT * FROM ".$this->db->prefix("xf_frs_package")
			." WHERE package_id='$package_id'" ." AND group_id='$this->group_id'";
			$res = $this->db->query($sql);
			 
			if (!$res || $this->db->getRowsNum($res) < 1)
			{
				$this->setError(' '._XF_FRS_VERIFYPACKAGEFAILED.' ');
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsVerifyReleaseName() - Verify that a release name is valid for a project
		*
		* @param        int                The group ID
		* @param        string        The name of the package
		*/
		function frsVerifyReleaseName($release_name, $package_id)
		{
			 
			$sql = "SELECT r.name FROM ".$this->db->prefix("xf_frs_package")." p" .",".$this->db->prefix("xf_frs_release")." r" ." WHERE p.group_id='".$this->group_id."'" ." AND p.package_id=r.package_id" ." AND p.package_id='$package_id'" ." AND r.name='$release_name'";
			$res = $this->db->query($sql);
			 
			if (!$res || $this->db->getRowsNum($res) > 0)
			{
				$this->setError(' '._XF_FRS_VERIFYRELEASENAMEFAILED.' ');
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsVerifyPackagename() - Verify that a package name is valid for a project
		*
		* @param        int                The group ID
		* @param        string        The name of the package
		*/
		function frsVerifyPackageName($package_name)
		{
			 
			$sql = "SELECT name FROM ".$this->db->prefix("xf_frs_package")
			." WHERE group_id='".$this->group_id."'" ." AND name='$package_name'";
			$res = $this->db->query($sql);
			 
			if (!$res || $this->db->getRowsNum($res) > 0)
			{
				$this->setError(' '._XF_FRS_VERIFYPACKAGENAMEFAILED.' ');
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsVerifyFile() - Verify whether a file belongs a release/project/package
		*
		* @param        int                ID of the file to verify
		* @param        int                ID of the group to which the file belongs
		* @return True on success, False on error
		*/
		function frsVerifyFile($file_id, $group_id)
		{
			//
			//
			//        Why is group_id passed in here?
			//
			if (!isset($this->res_frsVerifyFile))
			{
				$sql = "SELECT p.package_id " ."FROM ".$this->db->prefix("xf_frs_package")." p,".$this->db->prefix("xf_frs_release")." r,".$this->db->prefix("xf_frs_file")." f " ."WHERE p.group_id='$group_id' AND " ."r.release_id=f.release_id AND " ."r.package_id=p.package_id AND " ."f.file_id='$file_id'";
				 
				$this->res_frsVerifyFile = $this->db->query($sql);
			}
			 
			if (!$this->res_frsVerifyFile || $this->db->getRowsNum($this->res_frsVerifyFile) > 0)
			{
				$this->setError(' '._XF_FRS_VERIFYFILEFAILED.': ');
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsVerifyFilExist() - Verify that a file doesn't exist in the database
		*
		* @param        string        Name of the file
		* @param        int                ID of the release
		* @param        int                ID of the group
		*/
		function frsVerifyFileExist($filename, $release_id, $group_id)
		{
			//
			//
			//        why is group_id passed in here?
			//
			 
			if (!isset($this->res_frsVerifyFileExist))
			{
				$sql = "SELECT p.package_id " ."FROM ".$this->db->prefix("xf_frs_package")." p,".$this->db->prefix("xf_frs_release")." r,".$this->db->prefix("xf_frs_file")." f " ."WHERE p.group_id='$group_id' AND " ."r.release_id=f.release_id AND " ."r.package_id=p.package_id AND " ."f.filename='$filename'";
				 
				$this->res_frsVerifyFileExist = $this->db->query($sql);
			}
			 
			if (!$this->res_frsVerifyFileExist || $this->db->getRowsNum($this->res_frsVerifyFileExist) < 1)
			{
				return true;
			}
			else
			{
				$this->setError(' '._XF_FRS_FILEALREADYEXISTS.' ');
				return false;
			}
		}
		 
		/**
		* frsVerifyFileRelease() - Verify that a file belongs to a release
		*
		* @param        int                ID of the group
		* @param        int                ID of the release
		* @return True on success, False on error
		*/
		function frsVerifyFileRelease($group_id, $release_id)
		{
			//
			//
			//        why is group_id passed in here?
			//
			if (!isset($this->res_frsVerifyFileRelease))
			{
				$sql = "SELECT r.package_id FROM ".$this->db->prefix("xf_frs_package")." p,".$this->db->prefix("xf_frs_release")." r " ."WHERE p.group_id='$group_id' AND " ."r.release_id='$release_id' AND " ."r.package_id=p.package_id";
				 
				$this->res_frsVerifyFileRelease = $this->db->query($sql);
			}
			 
			if (!$this->res_frsVerifyFileRelease || $this->db->getRowsNum($this->res_frsVerifyFileRelease) < 1)
			{
				$this->setError(' '._XF_FRS_VERIFYFILERELEASEFAILED.' ');
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsVerifyProject() - Verify whether a package belongs to a project
		*
		* @param        int                ID of the package
		* @param        int                ID of the project
		* @return True on success, False on error
		*/
		function frsVerifyProject($package_id, $project_id)
		{
			//
			//
			//        why is group_id passed in here?
			//
			//
			$res1 = $this->db->query("SELECT * FROM ".$this->db->prefix("xf_frs_package")." WHERE package_id='$package_id' AND group_id='$this->group_id'");
			if (!$res1 || $this->db->getRowsNum($res1) < 1)
			{
				$this->setError(' '._XF_FRS_VERIFYPROJECTFAILED.' ');
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsVerifyReleaseDate() - Verify the release date format
		*
		* @param        int                Date ofthe release to verify
		* @return True on success, False on error
		*/
		function frsVerifyReleaseDate($release_date)
		{
			if (!ereg("[0-9]{4}-[0-9]{2}-[0-9]{2}", $release_date))
			{
				$this->setError(' '._XF_FRS_VERIFYRELEASEDATEFAILED.' ');
				return false;
			}
			 
			return true;
		}
		 
		/**
		* frsDeleteFile() - Delete a file
		*
		* @param        int                ID of the file to delete
		* @return True on success, False on error
		*/
		function frsDeleteFile($file_id)
		{
			global $project, $icmsForge;
			 
			$result = $this->db->query("SELECT file_url,release_id FROM ".$this->db->prefix("xf_frs_file")." WHERE file_id='$file_id'");
			$filename = unofficial_getDBResult($result, 0, 'file_url');
			if (false == strstr($filename, "://"))
			{
				$res = $this->frsGetRelease(unofficial_getDBResult($result, 0, 'release_id'));
				$unix_name = $project->getUnixName();
				$release_name = unofficial_getDBResult($res, 0, "release_name");
				$package_name = unofficial_getDBResult($res, 0, "package_name");
				$file_url = $unix_name."/".$package_name."/".$release_name."/".$filename;
				if (!$this->rmfile($file_url))
				{
					$this->setError(' Could not remove file ');
					return false;
				}
			}
			//remove references to the file from the database
			$result = $this->db->queryF("DELETE FROM ".$this->db->prefix("xf_frs_file")." WHERE file_id='$file_id'");
			$result = $this->db->queryF("DELETE FROM ".$this->db->prefix("xf_frs_dlstats_file_agg")." WHERE file_id='$file_id'");
			$result = $this->db->queryF("DELETE FROM ".$this->db->prefix("xf_frs_dlnames")." WHERE fileid='$file_id'");
			$result = $this->db->queryF("DELETE FROM ".$this->db->prefix("xf_frs_target")." WHERE file_id='$file_id'");
			$result = $this->db->queryF("DELETE FROM ".$this->db->prefix("xf_frs_webservice_publish")." WHERE file_id='$file_id'");
			 
			return true;
		}
		 
		/**
		* frsDeletePackage() - Delete an empty package
		*
		* @param        int                ID of the package
		* @return    bool    Sucess or Fail
		*/
		function frsDeletePackage($package_id, $unix_group_name)
		{
			 
			$res = $this->db->query("SELECT release_id FROM ".$this->db->prefix("xf_frs_release")." WHERE package_id=".$package_id);
			if ($this->db->getRowsNum($res) > 0)
			{
				$this->setError(' '._XF_FRS_DELETERELEASEFIRST.' ');
				return false;
			}
			 
			$res = $this->db->query("SELECT name FROM ".$this->db->prefix("xf_frs_package")." WHERE package_id=".$package_id);
			list($package_name) = $this->db->fetchRow($res);
			if (!$this->rmpath("$unix_group_name/$package_name")) return false;
			$this->db->queryF("DELETE FROM ".$this->db->prefix("xf_frs_package")." WHERE package_id=".$package_id);
			$this->db->queryF("DELETE FROM ".$this->db->prefix("xf_filemodule_monitor")." WHERE filemodule_id=".$package_id);
			 
			return true;
		}
		 
		/**
		* frsSendNotice() - Send a release update notice
		*
		* @param        int                ID of the release
		* @param        int                ID of the package
		* @param        &Permissions       Reference to the permissions object for the user
		* @return Database result ID
		*/
		function frsDeleteRelease($release_id, $package_id, &$perm)
		{
			 
			//make sure the package is not of type stable.
			$res = $this->db->query("SELECT status_id FROM ".$this->db->prefix("xf_frs_package")." WHERE package_id='$package_id'");
			$row = $this->db->fetchRow($res);
			if ($row[0] == 2 && !$perm->isReleaseAdmin())
			{
				$this->setError(" You must be a release admin to delete releases from a ".frs_get_status_name($row[0])." package.");
			}
			else
				{
				$res = $this->db->query("SELECT file_id FROM ".$this->db->prefix("xf_frs_file")." WHERE release_id=".$release_id);
				if ($this->db->getRowsNum($res) > 0)
				{
					$this->setError(' You must delete the files in this release before deleting the release itself. ');
				}
				else
					{
					$sql = "SELECT g.unix_group_name,p.name,r.name FROM" ." ".$this->db->prefix("xf_frs_release")." AS r" .",".$this->db->prefix("xf_frs_package")." AS p" .",".$this->db->prefix("xf_groups")." AS g" ." WHERE g.group_id=p.group_id" ." AND p.package_id=r.package_id" ." AND release_id=".$release_id;
					$res = $this->db->query($sql);
					list($unix_group_name, $package_name, $release_name) = $this->db->fetchRow($res);
					$this->rmpath("$unix_group_name/$package_name/$release_name");
					$this->db->queryF("DELETE FROM ".$this->db->prefix("xf_frs_release")." WHERE release_id=".$release_id);
				}
			}
		}
		 
		/**
		* frsSendNotice() - Send a release update notice
		*
		* @param        int                ID of the group
		* @param        int                ID of the release
		* @param        int                ID of the package
		* @return Database result ID
		*/
		function frsSendNotice($group_id, $release_id, $package_id)
		{
			global $icmsForge;
			//
			//
			//        why is group_id passed in here?
			//
			 
			$sql = "SELECT u.email,u.uname,p.name,group_name,unix_group_name " ."FROM ".$this->db->prefix("users")." u,".$this->db->prefix("xf_filemodule_monitor")." m, ".$this->db->prefix("xf_frs_package")." p,".$this->db->prefix("xf_groups")." g " ."WHERE g.group_id=p.group_id AND " ."u.uid=m.user_id AND " ."u.level <> 0 AND " ."m.filemodule_id=p.package_id AND " ."m.filemodule_id='$package_id' AND " ."p.group_id='$group_id'";
			 
			$result = $this->db->query($sql);
			 
			if ($result && $this->db->getRowsNum($result) > 0)
			{
				//send the email
				$package = unofficial_getDBResult($result, 0, 'name');
				$full_group = unofficial_getDBResult($result, 0, 'group_name');
				$unix_group = unofficial_getDBResult($result, 0, 'unix_group_name');
				$date = date('Y-m-d H:i', time());
				 
				$message = frsGetNoticeMessage($unix_group, $full_group, $package, $date, $group_id, $release_id, $package_id);
				 
				/*
				Send the email
				*/
				return xoopsForgeMail($icmsForge['noreply'], $icmsConfig['sitename'], $message['subject'], $message['body'], array($icmsForge['noreply']), util_result_column_to_array($result));
			}
			else
			{
				$this->setError(' '._XF_FRS_SENDNOTICEFAILED.' ');
				return false;
			}
		}
		 
		/**
		* frsResolveRelease() - Get a release name from the release ID
		*
		* @param        int                The ID of the release
		*/
		function frsResolveRelease($release_id)
		{
			$res = $this->frsGetRelease($release_id, $this->group_id);
			if (!$res || $this->db->getRowsNum($res) < 1)
			{
				$this->setError(" "._XF_FRS_RESOLVERELEASEFAILED.": ");
				return false;
			}
			 
			$name = unofficial_getDBResult($res, 0, "release_name");
			return $name;
		}
	}
	 
?>