<?php

/**
 * MyBBIntegrator - The integration class for MyBB and your website
 *
 * The MyBBIntegrator is a useful collection of variables and functions for easy MyBB integration
 * into the own website
 *
 * @author: David Olah (aka PHPDave - http://phpdave.com)
 * @version 1.3
 * @date Sep 28th, 2009
 * @copyright Copyright (c) 2009, David Olah
 *
 *
 *
 * 	This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
*/

class MyBBIntegrator
{	
	/**
	 * Cache Handler of MyBB
	 *
	 * @var object
	*/
	private $cache;
	
	/**
	 * Config Data of MyBB
	 *
	 * @var array
	*/
	private $config;
	
	/**
	 * Database Handler of MyBB
	 *
	 * @var object
	*/
	private $db;
	
	/**
	 * MyBB Super Variable containing a whole lot of information
	 *
	 * @var object
	*/
	private $mybb;
	
	/**
	 * MyBB's Post Parser
	 *
	 * @var object
	*/
	private $parser;

	/**
	 * Constructor
	 * If we include the global.php of MyBB in the constructor,
	 * weird error messages popup
	 * --> This sounds like a ToDo: Find out why?????? - My guess: Some evil eval() functions
	 *
	 * @param object $mybb Pass the Super MyBB Object as a reference so we can work with it
	 * @param object $db Pass the MyBB Database Handler as a reference so we can work with is
	*/
	public function __construct(&$mybb, &$db, &$cache, &$plugins, &$lang, &$config)
	{
		$this->mybb =& $mybb;
		$this->db =& $db;
		$this->cache = $cache;
		$this->plugins =& $plugins;
		$this->lang =& $lang;
		$this->config =& $config;
		
		define('MYBB_ADMIN_DIR', MYBB_ROOT.$this->config['admin_dir'].'/');
		
		// Some Constants for non-magic-numbers
		define('NON_FATAL', false);
		
		require_once MYBB_ROOT.'inc/class_parser.php';
		$this->parser = new postParser;
	}
	
	/**
	 * Shows a message for errors occuring in this class. 
	 * Afterwards it stops the script
	 *
	 * @param string $message The error message
	*/
	private function _errorAndDie($message)
	{
		echo '<div style="width:92%; margin:4px auto; border:1px #DDD solid; background:#F1F1F1; padding:5px; color:#C00; font-weight:bold;">An error occured during script run.<br />'.$message.'</div>';
		die;
	}
	
	/**
	 * Let's see if the correct password is given for a forum!
	 * Possible Todo: Pass passowrds in an array for defining passwords for parent categories (so far this only works when parent foums have same pass)
	 *
	 * @param integer $forum_id ID of Forum
	 * @param string $password Wow, what might this be??
	 * @return boolean
	*/
	public function checkForumPassword($forum_id, $password = '', $pid = 0)
	{
		global $forum_cache;
		
		if(!is_array($forum_cache))
		{
			$forum_cache = cache_forums();
			if(!$forum_cache)
			{
				return false;
			}
		}
		
		// Loop through each of parent forums to ensure we have a password for them too
		$parents = explode(',', $forum_cache[$fid]['parentlist']);
		rsort($parents);
		if(!empty($parents))
		{
			foreach($parents as $parent_id)
			{
				if($parent_id == $forum_id || $parent_id == $pid)
				{
					continue;
				}
				
				if($forum_cache[$parent_id]['password'] != "")
				{
					if (!$this->checkForumPassword($parent_id, $password))
					{
						return false;
					}
				}
				
			}
		}
		
		$forum_password = $forum_cache[$forum_id]['password'];
		
		// A password is required
		if ($forum_password)
		{
			if (empty($password))
			{
				if (!$this->mybb->cookies['forumpass'][$forum_id] || ($this->mybb->cookies['forumpass'][$forum_id] && md5($this->mybb->user['uid'].$forum_password) != $this->mybb->cookies['forumpass'][$forum_id]))
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
				if ($forum_password == $password)
				{
					$this->setCookie('forumpass['.$forum_id.']', md5($this->mybb->user['uid'].$password), NULL, true);
					return true;
				}
				else
				{
					return false;
				}
			}
		}
		else
		{
			return true;
		}	
	}
	
	/**
	 * Enables you to close one or more threads
	 * One thread: $thread_id is int
	 * More threads: $thread_id is array with ints
	 *
	 * @param integer|array $thread_id See above
	 * @param integer $forum_id ID of forum where the thread is located
	 * @return boolean
	*/
	public function closeThread($thread_id, $forum_id)
	{
		if (!is_moderator($forum_id, "canopenclosethreads"))
		{
			return false;
		}
		
		$this->lang->load('moderation');
		
		$this->MyBBIntegratorClassObject('moderation', 'Moderation', MYBB_ROOT.'/inc/class_moderation.php');
		
		$this->moderation->close_threads($thread_id);
		
		$modlogdata['fid'] = $forum_id;
		
		$this->logModeratorAction($modlogdata, $this->lang->mod_process);
		
		return true;
	}
	
	/**
	 * Insert a new Category into Database
	 *
	 * @param array $data Array with keys according to database layout, which holds the data of the forum
	 * @param array $permissions Array with Permission entries (structure: array( 'canview' => array( 'usergroupid' => 1 ) )) (an example)
	 * @param array $default_permissions Array which defines, if default permissions shall be used (structure: array( usergroupid => 0 / 1 )
	 * 								  	 Can be left empty, then this public function will take care of it
	 * @return $data with more values, like fid and parentlist
	*/
	public function createCategory($data, $permissions = array(), $default_permissions = array())
	{		
		require_once MYBB_ADMIN_DIR.'inc/functions.php';
		
		if (!isset($data['name']))
		{
			$this->_errorAndDie('A new forum needs to have a name and a type');
		}
		
		$data['type'] = 'c';
		
		// Let's leave the parentlist creation to the script and let's not trust the dev :)
		if ($data['parentlist'] != '')
		{
			$data['parentlist'] = '';
		}
		
		// If there is no defined Parent ID, parent ID will be set to 0
		if (!isset($data['pid']) || $data['pid'] < 0)
		{
			$data['pid'] = 0;
		}
		else
		{
			$data['pid'] = intval($data['pid']);
		}
		
		if (!empty($permissions))
		{		
			if (
				(!isset($permissions['canview']) || empty($permissions['canview'])) ||
				(!isset($permissions['canpostthreads']) || empty($permissions['canpostthreads'])) ||
				(!isset($permissions['canpostreplys']) || empty($permissions['canpostreplys'])) ||
				(!isset($permissions['canpostpolls']) || empty($permissions['canpostpolls'])) ||
				(!isset($permissions['canpostattachments']) || empty($permissions['canpostattachments']))
			   )
			{
				$this->_errorAndDie('The $permissions Parameter does not have the correct format. It requires following keys: <i>canview, canpostthreads, canpostreplys, canpostpolls and canpostattachments</i>');
			}
			
			/**
			 * If no default permissions are given, we will initiate them, default: yes
			 * Since there is the possibility of additional usergroups, we will get the usergroups from the permissions array!
			 * The structure of the inherit array is: keys = groupid
			 * If the value of an inherit array item is 1, this means that the default_permissions shall be used
			*/
			if (empty($default_permissions))
			{
				foreach ($permissions['canview'] as $gid)
				{
					$default_permissions[$gid] = 1;
				}
			}
		}
		
		$data['fid'] = $this->db->insert_query("forums", $data);
		
		$data['parentlist'] = make_parent_list($data['fid']);
		$this->db->update_query("forums", array("parentlist" => $data['parentlist']), 'fid=\''.$data['fid'].'\'');
		
		$this->cache->update_forums();
		
		if (!empty($permissions))
		{
			$inherit = $default_permissions;
			
			/**
			 * $permissions['canview'][1] = 1 OR $permissions['canview'][1] = 0
			 * --> $permissions[$name][$gid] = yes / no
			*/
				
			$canview = $permissions['canview'];
			$canpostthreads = $permissions['canpostthreads'];
			$canpostpolls = $permissions['canpostpolls'];
			$canpostattachments = $permissions['canpostattachments'];
			$canpostreplies = $permissions['canpostreplys'];
			save_quick_perms($data['fid']);
		}
		
		return $data;
	}
	
	/**
	 * Insert a new Forum into Database
	 *
	 * @param array $data Array with keys according to database layout, which holds the data of the forum
	 * @param array $permissions Array with Permission entries (structure: array( 'canview' => array( 'usergroupid' => 1 ) )) (an example)
	 * @param array $default_permissions Array which defines, if default permissions shall be used (structure: array( usergroupid => 0 / 1 )
	 * 								  	 Can be left empty, then this public function will take care of it
	 * @return $data with more values, like fid and parentlist
	*/
	public function createForum($data, $permissions = array(), $default_permissions = array())
	{		
		require_once MYBB_ADMIN_DIR.'inc/functions.php';
		
		if (!isset($data['name']))
		{
			$this->_errorAndDie('A new forum needs to have a name and a type');
		}
		
		$data['type'] = 'f';
		
		// Let's leave the parentlist creation to the script and let's not trust the dev :)
		if ($data['parentlist'] != '')
		{
			$data['parentlist'] = '';
		}
		
		// If there is no defined Parent ID, parent ID will be set to 0
		if (!isset($data['pid']) || $data['pid'] < 0)
		{
			$data['pid'] = 0;
		}
		else
		{
			$data['pid'] = intval($data['pid']);
		}
		
		if (!empty($permissions))
		{		
			if (
				(!isset($permissions['canview']) || empty($permissions['canview'])) ||
				(!isset($permissions['canpostthreads']) || empty($permissions['canpostthreads'])) ||
				(!isset($permissions['canpostreplys']) || empty($permissions['canpostreplys'])) ||
				(!isset($permissions['canpostpolls']) || empty($permissions['canpostpolls'])) ||
				(!isset($permissions['canpostattachments']) || empty($permissions['canpostattachments']))
			   )
			{
				$this->_errorAndDie('The $permissions Parameter does not have the correct format. It requires following keys: <i>canview, canpostthreads, canpostreplys, canpostpolls and canpostattachments</i>');
			}
			
			/**
			 * If no default permissions are given, we will initiate them, default: yes
			 * Since there is the possibility of additional usergroups, we will get the usergroups from the permissions array!
			 * The structure of the inherit array is: keys = groupid
			 * If the value of an inherit array item is 1, this means that the default_permissions shall be used
			*/
			if (empty($default_permissions))
			{
				foreach ($permissions['canview'] as $gid)
				{
					$default_permissions[$gid] = 1;
				}
			}
		}
		
		$data['fid'] = $this->db->insert_query("forums", $data);
		
		$data['parentlist'] = make_parent_list($data['fid']);
		$this->db->update_query("forums", array("parentlist" => $data['parentlist']), 'fid=\''.$data['fid'].'\'');
		
		$this->cache->update_forums();
		
		if (!empty($permissions))
		{
			$inherit = $default_permissions;
			
			/**
			 * $permissions['canview'][1] = 1 OR $permissions['canview'][1] = 0
			 * --> $permissions[$name][$gid] = yes / no
			*/
				
			$canview = $permissions['canview'];
			$canpostthreads = $permissions['canpostthreads'];
			$canpostpolls = $permissions['canpostpolls'];
			$canpostattachments = $permissions['canpostattachments'];
			$canpostreplies = $permissions['canpostreplys'];
			save_quick_perms($data['fid']);
		}
		
		return $data;
	}
	
	/**
	 * Create a new poll and assign it to a thread
	 * Taken frm polls.php
	 *
	 * @param integer $thread_id ID of Thread where the poll should be assigned to
	 * @param array $data The Data
	*/
	public function createPoll($thread_id, $data)
	{
		// Required keys in data array: options, question
		if (!isset($data['options']) || !isset($data['question']))
		{
			$this->_errorAndDie('One or more required array keys in parameter <i>$data</i> missing. Required keys are: <i>options</i>, <i>question</i>');
		}
		
		$this->lang->load('polls');
		
		$this->plugins->run_hooks("polls_do_newpoll_start");

		$query = $this->db->simple_select("threads", "*", "tid='".(int) $thread_id."'");
		$thread = $this->db->fetch_array($query);
		$fid = $thread['fid'];
		$forumpermissions = forum_permissions($fid);
		
		if (!$thread['tid'])
		{
			return $this->lang->error_invalidthread;
		}
		// No permission if: Not thread author; not moderator; no forum perms to view, post threads, post polls
		if (($thread['uid'] != $this->mybb->user['uid'] && !is_moderator($fid)) || ($forumpermissions['canview'] == 0 || $forumpermissions['canpostthreads'] == 0 || $forumpermissions['canpostpolls'] == 0))
		{
			return false;
		}
	
		if ($thread['poll'])
		{
			return $this->lang->error_pollalready;
		}
	
		$polloptions = count($data['options']);
		if($this->mybb->settings['maxpolloptions'] && $polloptions > $this->mybb->settings['maxpolloptions'])
		{
			$polloptions = $this->mybb->settings['maxpolloptions'];
		}
		
		if (!isset($data['postoptions']))
		{
			$data['postoptions'] = array('multiple', 'public');
		}
		
		$postoptions = $data['postoptions'];
		
		if ($postoptions['multiple'] != '1')
		{
			$postoptions['multiple'] = 0;
		}
	
		if ($postoptions['public'] != '1')
		{
			$postoptions['public'] = 0;
		}
		
		if ($polloptions < 2)
		{
			$polloptions = "2";
		}
		
		$optioncount = "0";
		
		$options = $data['options'];
		
		for($i = 0; $i < $polloptions; ++$i)
		{
			if (trim($options[$i]) != "")
			{
				$optioncount++;
			}
			
			if (my_strlen($options[$i]) > $this->mybb->settings['polloptionlimit'] && $this->mybb->settings['polloptionlimit'] != 0)
			{
				$lengtherror = 1;
				break;
			}
		}
		
		if ($lengtherror)
		{
			return $this->lang->error_polloptiontoolong;
		}
		
		if (empty($data['question']) || $optioncount < 2)
		{
			return $this->lang->error_noquestionoptions;
		}
		
		$optionslist = '';
		$voteslist = '';
		for($i = 0; $i < $optioncount; ++$i)
		{
			if(trim($options[$i]) != '')
			{
				if($i > 0)
				{
					$optionslist .= '||~|~||';
					$voteslist .= '||~|~||';
				}
				$optionslist .= $options[$i];
				$voteslist .= '0';
			}
		}
		
		if (!isset($data['timeout']))
		{
			$data['timeout'] = 0;
		}
		
		if($data['timeout'] > 0)
		{
			$timeout = intval($data['timeout']);
		}
		else
		{
			$timeout = 0;
		}
		
		$newpoll = array(
			"tid" => $thread['tid'],
			"question" => $this->db->escape_string($data['question']),
			"dateline" => TIME_NOW,
			"options" => $this->db->escape_string($optionslist),
			"votes" => $this->db->escape_string($voteslist),
			"numoptions" => intval($optioncount),
			"numvotes" => 0,
			"timeout" => $timeout,
			"closed" => 0,
			"multiple" => $postoptions['multiple'],
			"public" => $postoptions['public']
		);
	
		$this->plugins->run_hooks("polls_do_newpoll_process");
	
		$pid = $this->db->insert_query("polls", $newpoll);
	
		$this->db->update_query("threads", array('poll' => $pid), "tid='".$thread['tid']."'");
	
		$this->plugins->run_hooks("polls_do_newpoll_end");
		
		return true;
	}
	
	/**
	 * Insert a new post into Database
	 *
	 * @param array $data Post Data
	 * @return array|string When true it will return an array with postID and status of being visible - false = error array or inline string
	*/
	public function createPost($data, $inline_errors = true)
	{
		require_once MYBB_ROOT.'inc/functions_post.php';
		require_once MYBB_ROOT.'/inc/datahandlers/post.php';
		$posthandler = new PostDataHandler('insert');
		
		$this->plugins->run_hooks('newreply_do_newreply_start');
		
		$posthandler->set_data($data);
		
		if (!$posthandler->validate_post())
		{
			$errors = $posthandler->get_friendly_errors();
			return ($inline_errors === true) ? inline_error($errors) : $errors;
		}
		
		$this->plugins->run_hooks('newreply_do_newreply_end');
		
		return $posthandler->insert_post();
	}
	
	/**
	 * Inserts a thread into the database
	 *
	 * @param array $data Thread data
	 * @param boolean $inline_errors Defines if we want a formatted error string or an array
	 * @return array|string 
	 * @return array|string When true it will return an array with threadID, postID and status of being visible - false = error array or inline string 
	*/
	public function createThread($data, $inline_errors = true)
	{
		require_once MYBB_ROOT.'inc/functions_post.php';
		require_once MYBB_ROOT.'/inc/datahandlers/post.php';
		$posthandler = new PostDataHandler('insert');
		$posthandler->action = 'thread';
		$posthandler->set_data($data);
		if (!$posthandler->validate_thread())
		{
			$errors = $posthandler->get_friendly_errors();
			return ($inline_errors === true) ? inline_error($errors) : $errors;
		}
		return $posthandler->insert_thread();
	}
	
	/**
	 * Insert a new user into Database
	 *
	 * @param array $data User data
	 * @param boolean $inline_errors Defines if we want a formatted error string or an array
	 * @return array|string When true it will return an array with some user data - false = error array or inline string
	*/
	public function createUser($data, $inline_errors = true)
	{
		require_once MYBB_ROOT.'inc/functions_user.php';
		require_once MYBB_ROOT.'/inc/datahandlers/user.php';
		$userhandler = new UserDataHandler('insert');
		
		$this->plugins->run_hooks('admin_user_users_add');
		
		$userhandler->set_data($data);
		
		if (!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
			return ($inline_errors === true) ? inline_error($errors) : $errors;
		}
		
		$this->plugins->run_hooks('admin_user_users_add_commit');
		
		return $userhandler->insert_user();
	}
	
	/**
	 * Escapes a value for DB usage
	 *
	 * @param mixed $value Any value to use with the database
	 * @return string
	*/
	public function dbEscape($value)
	{
		return $this->db->escape_string($value);
	}
	
	/**
	 * Remove a poll
	 * Taken from moderation.php
	 *
	 * @param integer $poll_id ID of Poll to be deleted
	 * @return boolean|string
	*/
	public function deletePoll($poll_id)
	{
		$this->lang->load('moderation');
		
		$this->MyBBIntegratorClassObject('moderation', 'Moderation', MYBB_ROOT.'/inc/class_moderation.php');
		
		$query = $this->db->simple_select("polls", "*", "pid='$poll_id'");
		$poll = $this->db->fetch_array($query);
		if(!$poll['pid'])
		{
			return $this->lang->error_invalidpoll;
		}
		
		$thread = $this->getThread($poll['tid']);
		
		if(!is_moderator($thread['fid'], "candeleteposts"))
		{
			if($permissions['candeletethreads'] != 1 || $this->mybb->user['uid'] != $thread['uid'])
			{
				return false;
			}
		}
		
		$modlogdata = array();
		$modlogdata['tid'] = $poll['tid'];

		$this->plugins->run_hooks("moderation_do_deletepoll");

		$this->lang->poll_deleted = $this->lang->sprintf($this->lang->poll_deleted, $thread['subject']);
		$this->logModeratorAction($modlogdata, $this->lang->poll_deleted);

		$this->moderation->delete_poll($poll['pid']);
		
		return true;
	}
	
	/**
	 * Delete the poll of a thread
	 * Taken from moderation.php
	 *
	 * @param integer $thread_id Thread-ID where the poll is located
	 * @return boolean|string
	*/
	public function deletePollOfThread($thread_id)
	{
		$this->lang->load('polls');
		$this->lang->load('moderation');
		
		$this->MyBBIntegratorClassObject('moderation', 'Moderation', MYBB_ROOT.'/inc/class_moderation.php');
		
		$thread = $this->getThread($thread_id);
		$permissions = forum_permissions($thread['fid']);
		
		if (!is_moderator($thread['fid'], "candeleteposts"))
		{
			if($permissions['candeletethreads'] != 1 || $this->mybb->user['uid'] != $thread['uid'])
			{
				return false;
			}
		}
		
		$query = $this->db->simple_select("polls", "*", "tid='$thread_id'");
		$poll = $this->db->fetch_array($query);
		if(!$poll['pid'])
		{
			return $this->lang->error_invalidpoll;
		}
		
		$modlogdata = array();
		$modlogdata['tid'] = $poll['tid'];

		$this->plugins->run_hooks("moderation_do_deletepoll");

		$this->lang->poll_deleted = $this->lang->sprintf($this->lang->poll_deleted, $thread['subject']);
		$this->logModeratorAction($modlogdata, $this->lang->poll_deleted);

		$this->moderation->delete_poll($poll['pid']);
		
		return true;
	}
	
	/**
	 * Flag private messages as deleted
	 *
	 * @param integer|array $pm_id ID(s) of Private Messages (many IDs require an array)
	*/
	public function deletePrivateMessage($pm_id)
	{
		require_once MYBB_ROOT.'inc/functions_user.php';
		
		$this->plugins->run_hooks('private_delete_start');
		
		$data = array(
			'folder' => 4,
			'deletetime' => TIME_NOW
		);
		
		if (is_array($pm_id))
		{
			$this->db->update_query('privatemessages', $data, 'pmid IN ('.implode(',', array_map('intval', $pm_id)).')');
		}
		else
		{
			$this->db->update_query('privatemessages', $data, 'pmid = '.intval($pm_id));
		}
		
		update_pm_count();
		
		$this->plugins->run_hooks('private_delete_end');
		
	}
	
	/**
	 * Flag all private messages of a user as deleted
	 * It is also possible to flag pms as deleted of multiple users, when paramater is an array with IDs
	 *
	 * @param integer|array $pm_id ID(s) of User IDs (many IDs require an array)
	*/
	public function deletePrivateMessagesOfUser($user_id)
	{
		require_once MYBB_ROOT.'inc/functions_user.php';
		
		$this->plugins->run_hooks('private_delete_start');
		
		$data = array(
			'folder' => 4,
			'deletetime' => TIME_NOW
		);
		
		if (is_array($user_id))
		{
			$this->db->update_query('privatemessages', $data, 'uid IN ('.implode(',', array_map('intval', $user_id)).')');
		}
		else
		{
			$this->db->update_query('privatemessages', $data, 'uid = '.intval($user_id));
		}
		
		update_pm_count();
		
		$this->plugins->run_hooks('private_delete_end');
	}
	
	/**
	 * Generates a Captcha
	 *
	 * @return array
	*/
	public function generateCaptcha()
	{
		$randomstr = random_str(5);
		$imagehash = md5(random_str(12));
		$imagearray = array(
			"imagehash" => $imagehash,
			"imagestring" => $randomstr,
			"dateline" => TIME_NOW
		);
		$this->db->insert_query("captcha", $imagearray);
		return array_merge($imagearray, array(
			'captcha' => '<img src="'.$this->mybb->settings['bburl'].'/captcha.php?imagehash='.$imagehash.'" />'
		));
	}
	
	/**
	 * Generates a posthash
	 *
	 * @param integer $user_id User-ID
	 * @return string MD5
	*/
	public function generatePosthash($user_id = 0)
	{
		mt_srand((double) microtime() * 1000000);
		if ($user_id == 0)
		{
			return md5($this->mybb->user['uid'].mt_rand());
		}
		else
		{
			return md5($user_id.mt_rand());
		}
	}
	
	/**
	 * Get the Hottest Threads within a defined timespan
	 *
	 * @param integer $timespan The timespan you want to use for fetching the hottest topics (in seconds)
	 * @param string $post_sort_order Sort Order to the posts you are fetching (ordered by the dateline)
	 * @param string $postamount_sort_order Sort order of the threads (ordered by the amount of posts)
	 * @return array
	*/
	public function getBusyThreadsWithinTimespan($timespan = 86400, $post_sort_order = 'DESC', $postamount_sort_order = 'DESC')
	{
		$threads = array();
		
		// Make sure the parameters have correct values
		$post_sort_order = ($post_sort_order == 'DESC') ? 'DESC' : 'ASC';
		$postamount_sort_order = ($postamount_sort_order == 'DESC') ? 'DESC' : 'ASC';
		
		$query = $this->db->query('
			SELECT p.`pid`, p.`message`, p.`uid` as postuid, p.`username` as postusername, p.`dateline`,
				   t.`tid`, t.`fid`, t.`subject`, t.`uid` as threaduid, t.`username` as threadusername, t.`lastpost`, t.`lastposter`, t.`lastposteruid`, t.`views`, t.`replies`
			FROM '.TABLE_PREFIX.'posts p
			INNER JOIN '.TABLE_PREFIX.'threads t ON t.`tid` = p.`tid`
			WHERE p.`dateline` >= '.(TIME_NOW - $timespan).'
			ORDER BY p.`dateline` '.$post_sort_order.'
		');

		while ($post = $this->db->fetch_array($query))
		{
			/**
			 * The return array we are building is being filled with the thread itself, but also with the posts
			 * We will later increase the Postamount, so we can sort it 
			*/
			if (!isset($threads[$post['tid']]))
			{
				$threads[$post['tid']] = array(
					'tid' => $post['tid'],
					'fid' => $post['fid'],
					'subject' => $post['subject'],
					'uid' => $post['threaduid'],
					'username' => $post['threadusername'],
					'lastpost' => $post['lastpost'],
					'lastposter' => $post['lastposter'],
					'lastposteruid' => $post['lastposteruid'],
					'views' => $post['views'],
					'replies' => $post['replies'],
					'postamount' => 1,
					'posts' => array()
				);
				
				// The first run of one thread also brings a post, so we assign this post
				$threads[$post['tid']]['posts'][] = array(
					'pid' => $post['pid'],
					'message' => $post['message'],
					'uid' => $post['postuid'],
					'username' => $post['postusername'],
					'dateline' => $post['dateline']
				);
			}
			else
			{
				// The thread array key exists already, so we increment the postamount and save another post
				$threads[$post['tid']]['postamount']++;
				$threads[$post['tid']]['posts'][] = array(
					'pid' => $post['pid'],
					'message' => $post['message'],
					'uid' => $post['postuid'],
					'username' => $post['postusername'],
					'dateline' => $post['dateline']
				);
			}
		}
		
		// Sort public function for ascending posts
		function arraySortByPostamountASC($item1, $item2)
		{
			if ($item1['postamount'] == $item2['postamount'])
			{
				return 0;
			}
			
			if ($item1['postamount'] > $item2['postamount'])
			{
				return 1;
			}
			else
			{
				return -1;
			}
		}
		
		// Sort public function for descending posts
		function arraySortByPostamountDESC($item1, $item2)
		{
			if ($item1['postamount'] == $item2['postamount'])
			{
				return 0;
			}
			
			if ($item1['postamount'] > $item2['postamount'])
			{
				return -1;
			}
			else
			{
				return 1;
			}
		}
		
		// Let's sort the threads now
		usort($threads, 'arraySortByPostamount'.$postamount_sort_order);
		
		return $threads;
	}
	
	/**
	 * Returns data of a specified forum
	 * Refers to: inc/functions.php
	 *
	 * @param integer $forum_id ID of forum to fetch data from
	 * @param integer $active_override If set to 1, will override the active forum status
	 * @return array|boolean If unsuccessful, it returns false - Otherwise the Database row
	*/
	public function getForum($forum_id, $active_override = 0)
	{
		$forum = get_forum($forum_id, $active_override);
		
		// Do we have permission?
		$forumpermissions = forum_permissions($forum['fid']);
		if ($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
		{
			// error_no_permission();
			return false;
		}
		else
		{
			return $forum;
		}
	}
	
	/**
	 * Return members of the board with administrative function
	 * Taken from /showteam.php
	 *
	 * @return array
	*/
	public function getForumStaff()
	{
		$this->lang->load('showteam');
		
		$usergroups = array();
		$moderators = array();
		$users = array();

		// Fetch the list of groups which are to be shown on the page
		$query = $this->db->simple_select("usergroups", "gid, title, usertitle", "showforumteam=1", array('order_by' => 'disporder'));
		
		while($usergroup = $this->db->fetch_array($query))
		{
			$usergroups[$usergroup['gid']] = $usergroup;
		}
		
		if (empty($usergroups))
		{
			return $this->lang->error_noteamstoshow;
		}
		
		// Fetch specific forum moderator details
		if ($usergroups[6]['gid'])
		{
			$query = $this->db->query("
				SELECT m.*, f.name
				FROM ".TABLE_PREFIX."moderators m
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=m.uid)
				LEFT JOIN ".TABLE_PREFIX."forums f ON (f.fid=m.fid)
				WHERE f.active = 1
				ORDER BY u.username
			");
			
			while($moderator = $this->db->fetch_array($query))
			{
				$moderators[$moderator['uid']][] = $moderator;
			} 
		}
		
		// Now query the users of those specific groups
		$groups_in = implode(",", array_keys($usergroups));
		$users_in = implode(",", array_keys($moderators));
		if (!$groups_in)
		{
			$groups_in = 0;
		}
		if (!$users_in)
		{
			$users_in = 0;
		}
		
		$forum_permissions = forum_permissions();
		
		$query = $this->db->simple_select("users", "uid, username, displaygroup, usergroup, ignorelist, hideemail, receivepms", "displaygroup IN ($groups_in) OR (displaygroup='0' AND usergroup IN ($groups_in)) OR uid IN ($users_in)", array('order_by' => 'username'));
		
		while ($user = $this->db->fetch_array($query))
		{
			// If this user is a moderator
			if (isset($moderators[$user['uid']]))
			{
				foreach ($moderators[$user['uid']] as $forum)
				{
					if ($forum_permissions[$forum['fid']]['canview'] == 1)
					{
						$forum_url = get_forum_link($forum['fid']);
					}
				}
				$usergroups[6]['user_list'][$user['uid']] = $user;
			}
			
			if ($user['displaygroup'] == '6' || $user['usergroup'] == '6')
			{
				$usergroups[6]['user_list'][$user['uid']] = $user;
			}
			
			// Are they also in another group which is being shown on the list?
			if ($user['displaygroup'] != 0)
			{
				$group = $user['displaygroup'];
			}
			else
			{
				$group = $user['usergroup'];
			}
			
			if ($usergroups[$group] && $group != 6)
			{
				$usergroups[$group]['user_list'][$user['uid']] = $user;
			}
		}
		
		return $usergroups;
	}
	
	/**
	 * Return the latest threads of one forum, where a post has been posted
	 *
	 * @param integer $forum_id Forum ID to fetch threads from
	 * @param integer $limit Amount of threads to get
	 * @param boolean $excluse_invisible Shall we also get invisible threads?
	 * @return array
	*/
	public function getLatestActiveThreads($forum_id = 0, $limit = 7, $exclude_invisible = true)
	{
		if ($forum_id == 0)
		{
			$this->_errorAndDie('Specified forum ID cannot be 0!');
		}
		else
		{
			// Do we have permission?
			$forumpermissions = forum_permissions($forum_id);
			if ($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
			{
				// error_no_permission();
				return false;
			}
		}
		
		// This will be the array, where we can save the threads
		$threads = array();
		
		// We want to get a list of threads, starting with the newest one
		$query_params = array(
			'order_by' => 'lastpost',
			'order_dir' => 'DESC',
			'limit' => intval($limit)
		);
		
		/**
		 * If defined forum id is 0, we do not fetch threads from only one forum,
		 * but we fetch the latest threads of all forums
		 * Therefore we add the forum_id in the where condition
		 * We only fetch visible threads, if there is anything we want to hide ;)
		 * However we can also define that we want the invisible threads as well
		*/
		$fetch_invisible_threads = ($exclude_invisible == true) ? '1' : '0';
		$condition = ($forum_id != 0) ? ' `visible` = '.$fetch_invisible_threads.' AND `fid` = '.intval($forum_id) : '';
		
		// Run the Query
		$query = $this->db->simple_select('threads', '*', $condition, $query_params);
		
		// Now let's iterate through the fetched threads to create the return array
		while ($thread = $this->db->fetch_array($query))
		{
			$threads[] = $thread;
		}
		
		return $threads;
	}
	
	/**
	 * Return newly created threads, regardless of replies
	 *
	 * @param integer|array $forum_id Forum ID / Forum IDs to fetch threads from
	 * @param string $fields Name of fields if you want to fetch specific fields
	 * @param integer $limit Amount of threads to get
	 * @param boolean $excluse_invisible Shall we also get invisible threads?
	 * @param boolean $join_forums Shall we also get the information from the forums where the threads are located in?
	 * @param boolean $join_first_post Shall we get the first post of this thread as well?
	 * @return array
	*/
	public function getLatestThreads($forum_id = 0, $fields = '*', $limit = 7, $exclude_invisible = true, $join_forums = true, $join_first_post = true)
	{
		if ($forum_id != 0)
		{
			// If we have multiple values, we have to check permission for each forum!
			if (is_array($forum_id))
			{
				foreach ($forum_id as $single_forum_id)
				{
					$forum_permissions = forum_permissions($single_forum_id);
					if ($forum_permissions['canview'] != 1 || $forum_permissions['canviewthreads'] != 1)
					{
						// error_no_permission();
						return false;
					}
				}
			}
			else
			{
				// Do we have permission?
				$forumpermissions = forum_permissions($forum_id);
				if ($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
				{
					// error_no_permission();
					return false;
				}
			}
		}
		
		// This is what we will be returning
		$threads = array();
		
		// Do we want to get invisible threads as well?
		$fetch_invisible_threads = ($exclude_invisible == true) ? '1' : '0';
		$condition = 't.`visible` = '.$fetch_invisible_threads;
		
		// Are we fetching threads from multiple forums?
		if (is_array($forum_id) || is_object($forum_id))
		{
			$condition .= ' AND t.`fid` IN ('.implode(', ', $forum_id).')';
			
		}
		// Or are we just fetching threads from one forum?
		else
		{
			$condition .= ($forum_id == 0) ? '' : ' AND t.`fid` = '.$forum_id;
		}
		
		// Do we want to get information of the forum where the thread is located in?
		$forum_join = ($join_forums == true) ? 'INNER JOIN '.TABLE_PREFIX.'forums f ON f.`fid` = t.`fid`' : '';
		
		// Do we want to get the first post from the thread?
		$first_post_join = ($join_first_post == true) ? 'INNER JOIN '.TABLE_PREFIX.'posts p ON p.`pid` = t.`firstpost`' : '';
		
		// Run the Query
		$query = $this->db->query('
			SELECT '.$fields.'
			FROM '.TABLE_PREFIX.'threads t
			'.$forum_join.'
			'.$first_post_join.'
			WHERE '.$condition.'
			ORDER BY t.`dateline` DESC
			LIMIT '.intval($limit).'
		');
		
		// Iterate through the results and assign it to our returning array
		while ($thread = $this->db->fetch_array($query))
		{
			$threads[] = $thread;
		}
		
		return $threads;
	}
	
	/**
	 * Return recently posted posts
	 *
	 * @param integer|array Either a single Thread ID or an array containing thread IDs
	 * @param string Fields, which shall be fetched from the posts table
	 * @param integer How many posts shall be fetched?
	 * @param boolean Shall we also return invisible ones?
	 * @return array
	*/
	public function getLatestPosts($thread_id = 0, $fields = '*', $limit = 7, $exclude_invisible = true)
	{		
		// Posts will be stored in this array
		$posts = array();
		
		// Posts will be returned in descending order, starting with the newest
		$query_params = array(
			'order_by' => 'dateline',
			'order_dir' => 'DESC',
			'limit' => intval($limit)
		);
		
		// We want to fetch posts from multiple threads
		if (is_array($thread_id) || is_object($thread_id))
		{
			// Multiple threads = IN (...) Operator
			$condition = '`fid` IN ('.implode(', ', $thread_id).')';
		}
		else
		{
			// Single thread = normal WHERE X = Y - if set 0 we fetch posts from all threads
			$condition = ($thread_id == 0) ? '' : '`fid` = '.intval($thread_id);
		}
		
		/**
		 * If defined forum id is 0, we do not fetch threads from only one forum,
		 * but we fetch the latest threads of all forums
		 * Therefore we add the forum_id in the where condition
		 * We only fetch visible threads, if there is anything we want to hide ;)
		 * However we can also define that we want the invisible threads as well
		*/
		$fetch_invisible_threads = ($exclude_invisible == true) ? '1' : '0';
		$condition .= ' AND `visible` = '.$excluse_invisible;
		
		// Run the Query
		$query = $this->db->simple_select('posts', $fields, $condition, $query_params);
		
		// Now let's iterate through the fetched posts to create the return array
		while ($post = $this->db->fetch_array($query))
		{
			$posts[] = $post;
		}
		
		return $posts;
	}
	
	/**
	 * Retrieve member list
	 * Ideal to offer a multi-page member list
	 *
	 * @param array $data Contains data affecting the member query - List of Array keys below
	 *					  - orderby: What table column will the member list be sorted by?
	 *					  - orderdir: Ascending or Descending order direction
	 *					  - perpage: Amount of members to fetch (set 0 for all members)
	 *					  - letter: Beginning character of member name
	 *					  - username: Searching for a matching username
	 *					  - username_match: Set this to "begins" when username shall being with given token - otherwise it goes or "contains"
	 *					  - website: String contained in website
	 *					  - aim: Search for an AIM
	 *					  - icq: Search for an ICQ number
	 *					  - msn: Search for a MSN ID
	 *					  - yahoo: Search for a Yahoo ID
	 *					  - page: Which page of the list will we be retrieving
	 * @return array
	*/
	public function getMembers($data = array())
	{
		/**
		 *  Make sure we have initial values in the data array	
		*/
		
		$data['orderby'] = (!isset($data['orderby'])) ? 'u.`username`' : $data['orderby'];		
		$data['orderdir'] = (!isset($data['orderdir'])) ? 'ASC' : strtoupper($data['orderdir']);
		$data['orderdir'] = ($data['orderdir'] == 'ASC') ? 'ASC' : 'DESC';		
		$data['perpage'] = (!isset($data['perpage'])) ? (int) $this->mybb->settings['membersperpage'] : (int) $data['perpage'];		
		$data['letter'] = (!isset($data['letter'])) ? '' : $data['letter'];			
		$data['username'] = (!isset($data['username'])) ? '' : $data['username'];
		$data['username_match'] = (!isset($data['username_match'])) ? 'begins' : $data['username_match'];	
		$data['website'] = (!isset($data['website'])) ? '' : $data['website'];		
		$data['aim'] = (!isset($data['aim'])) ? '' : $data['aim'];
		$data['icq'] = (!isset($data['icq'])) ? '' : $data['icq'];		
		$data['msn'] = (!isset($data['msn'])) ? '' : $data['msn'];		
		$data['yahoo'] = (!isset($data['yahoo'])) ? '' : $data['yahoo'];
		$data['page'] = (!isset($data['page'])) ? 1 : (int) $data['page'];
		
		/**
		 * Let's build the DB query now!
		*/
		
		$sql_where = 'WHERE 1 = 1';
		
		// Username begins with a letter or number
		if (strlen($data['letter']) == 1)
		{
			$data['letter'] = chr(ord($data['letter']));
			// Letter is 0: Shall start with number
			if ($data['letter'] == '0')
			{
				$sql_where .= " AND u.`username` NOT REGEXP('[a-zA-Z]')";
			}
			// letter is not 0, so it will be fetching names according to first char
			else
			{
				$sql_where .= " AND u.`username` LIKE '".$this->db->escape_string($data['letter'])."%'";
			}
		}
		
		// Search for matching username
		if (strlen($data['username']) > 0)
		{
			$data['username'] = htmlspecialchars_uni($data['username']);
			if ($data['username_match'] == 'begins')
			{
				$sql_where .= " AND u.`username` LIKE '".$this->db->escape_string_like($data['username'])."%'";
			}
			else
			{
				$sql_where .= " AND u.`username` LIKE '%".$this->db->escape_string_like($data['username'])."%'";
			}
		}
		
		// Search for website
		if (strlen($data['website']) > 0)
		{
			$data['website'] = trim(htmlspecialchars_uni($data['website']));
			$sql_where .= " AND u.`website` LIKE '%".$this->db->escape_string_like($data['website'])."%'";
		}
		
		// Search for AIM
		if (strlen($data['aim']) > 0)
		{
			$sql_where .= " AND u.`aim` LIKE '%".$this->db->escape_string_like($data['aim'])."%'";
		}
		
		// Search for ICQ
		if (strlen($data['icq']) > 0)
		{
			$sql_where .= " AND u.`icq` LIKE '%".$this->db->escape_string_like($data['icq'])."%'";
		}
		
		// Search for MSN
		if (strlen($data['msn']) > 0)
		{
			$sql_where .= " AND u.`msn` LIKE '%".$this->db->escape_string_like($data['msn'])."%'";
		}
		
		// Search for Yahoo
		if (strlen($data['yahoo']) > 0)
		{
			$sql_where .= " AND u.`yahoo` LIKE '%".$this->db->escape_string_like($data['yahoo'])."%'";
		}
		
		// Build the LIMIT-part of the query here
		if ($data['perpage'] == 0)
		{
			$limit_string = '';
		}
		else
		{
			if ($data['page'] > 0)
			{
				$limit_string = 'LIMIT '.(($data['page'] - 1) * $data['perpage']).', '.$data['perpage'];
			}
			else
			{
				$limit_string = 'LIMIT '.$data['perpage'];
			}
		}
		
		$sql .= '
			SELECT u.*, f.*
			FROM '.TABLE_PREFIX.'users u
			LEFT JOIN '.TABLE_PREFIX.'userfields f ON f.`ufid` = u.`uid`
			'.$sql_where.'
			ORDER BY '.$data['orderby'].' '.$data['orderdir'].'
			'.$limit_string.'
		';
		
		$query = $this->db->query($sql);
		
		$arr = array();
		
		while ($member = $this->db->fetch_array($query))
		{
			$arr[] = $member;
		}
		
		return $arr;
	}
	
	/**
	 * Read some info about a poll
	 *
	 * @param integer $poll_id ID of Poll to fetch infos from
	 * @return array
	*/
	public function getPoll($poll_id)
	{
		if ($poll_id == 0)
		{
			$this->_errorAndDie('Specified poll ID cannot be 0!');
		}
		
		$query = $this->db->query('
			SELECT *
			FROM '.TABLE_PREFIX.'polls
			WHERE `pid` = '.(int) $poll_id.'
			LIMIT 1
		');
		
		$poll = $this->db->fetch_array($query);
		
		$separator = '||~|~||';
		
		$poll['optionsarray'] = explode($separator, $poll['options']);
		$poll['votesarray'] = explode($separator, $poll['votes']);
		
		/**
		 * At this point we are doing another query, so it is easier
		 * Little Todo: Include an INNER JOIN in the initial Poll-fetching query to save one query
		 * YOu have to make sure that columns of "thread" won't override columns of "poll"
		 * Therefore the solution right now at hand will be sufficient, until people start to moan :)
		*/
		$poll['thread'] = $this->getThread($poll['tid']);
		
		$poll['whovoted'] = $this->getWhoVoted($poll_id);
		
		return $poll;
	}
	
	/**
	 * Returns post data of specified post
	 * Refers to: inc/functions.php & inc/class_parser.php
	 *
	 * @param integer $post_id Post ID to fetch data from
	 * @param boolean $parsed Shall the Post message be parsed?
	 * @param array $parse_options Array of yes/no options - allow_html,filter_badwords,allow_mycode,allow_smilies,nl2br,me_username
	 * @param array $override_forum_parse_options Whether parse options should be defined by forum or by the script.
	 												If they are being overridden, the array will contain the options
	 * @return array|boolean: If unsuccessful, it returns false - Otherwise the Database row
	*/
	public function getPost($post_id, $parsed = false, $override_forum_parse_options = array())
	{
		if ($post_id == 0)
		{
			$this->_errorAndDie('Specified post ID cannot be 0!');
		}
		
		// Get the Post data
		$post = get_post($post_id);
		
		// Post not found? --> False
		if (empty($post))
		{
			return false;
		}
		
		// Do we have permission?
		$forumpermissions = forum_permissions($post['fid']);
		if ($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
		{
			// error_no_permission();
			return false;
		}
		
		// If the post shall not be parsed, we can already return it at this point
		if ($parsed == false || empty($post))
		{
			return $post;
		}
		
		// So we want to parse the message
		
		/**
		 * We don't want to override the parse options defined by the forum,
		 * so we have first to get these options defined for the forum
		*/
		if (count($override_forum_parse_options) == 0)
		{
			// Get the Forum data according to the forum id stored with the post
			$forum = $this->getForum($post['fid']);
			
			// Set up the parser options.
			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode'],
				"filter_badwords" => 1
			);
			
		}
		else
		{
			// Self-defined options given in the public function parameter
			$parser_options = array(
				'allow_html' => (isset($override_forum_parse_options['allow_html']) && $override_forum_parse_options['allow_html'] == 1) ? 1 : 0,
				'allow_mycode' => (isset($override_forum_parse_options['allow_mycode']) && $override_forum_parse_options['allow_mycode'] == 1) ? 1 : 0,
				'allow_smilies' => (isset($override_forum_parse_options['allow_smilies']) && $override_forum_parse_options['allow_smilies'] == 1) ? 1 : 0,
				'allow_imgcode' => (isset($override_forum_parse_options['allow_imgcode']) && $override_forum_parse_options['allow_imgcode'] == 1) ? 1 : 0,
				'filter_badwords' => (isset($override_forum_parse_options['filter_badwords']) && $override_forum_parse_options['filter_badwords'] == 1) ? 1 : 0,
			);
		}
		
		// Overwrite the message with the parsed message
		$post['message'] = $this->parser->parse_message($post['message'], $parser_options);
		
		return $post;
	}
	
	/**
	 * Get posts which match the given criteria
	 *
	 * @param array $params Parameters for the query
	 * @return array
	*/
	public function getPosts($params = array('fields' => '*', 'order_by' => 'dateline', 'order_dir' => 'DESC', 'limit_start' => 0, 'limit' => 0, 'where' => ''))
	{
		// We will store the posts in here
		$posts = array();
		
		// No matter what parameters will be given, the query starts with the following
		$sql = 'SELECT '.$params['fields'].'
			    FROM '.TABLE_PREFIX.'posts';
		
		// Get all posts or just (hopefully) posts which match certain criteria?
		$sql .= ($params['where'] != '') ? ' WHERE '.$params['where'] : '';
		
		// Are the posts going to be ordered by a field?
		if ($params['order_by'] != '')
		{
			$sql .= ' ORDER BY '.$params['order_by'];
			if ($params['order_dir'] != '')
			{
				$sql .= ' '.$params['order_dir'];
			}
			else
			{
				$sql .= ' ASC';
			}
		}
		
		// Get all posts or (hopefully) just a few?
		if ($params['limit'] != 0)
		{
			$sql .= ' LIMIT ';
			if (isset($params['limit_start']))
			{
				$sql .= $params['limit_start'].', '.$params['limit'];
			}
			else
			{
				$sql .= $params['limit'];
			}
		}
		
		// Run the query
		$query = $this->db->query($sql);
		
		// Store the returned data in the array we return
		while ($post = $this->db->fetch_array($query))
		{
			$posts[] = $post;
		}
		
		return $posts;
	}
	
	/**
	 * Get the Posts of a particular thread
	 *
	 * @param integer $thread_id
	 * @param string $fields If you want to fetch certain fields, define them as a string here (separated by comma)
	 * @param array $options Options for the query [ array('limit_start', 'limit', 'orderby', 'order_dir') ]
	 * @return array
	*/
	public function getPostsOfThread($thread_id, $fields = '*', $options = array())
	{
		// This is what we will be returning
		$arr = array();
		
		$thread = $this->db->fetch_field('SELECT `fid` FROM '.TABLE_PREFIX.'threads WHERE `tid` = '.intval($thread_id).' LIMIT 1', 0);
		
		// Do we have permission?
		$forumpermissions = forum_permissions($thread['fid']);
		if ($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
		{
			// error_no_permission();
			return false;
		}
		
		// Let's request the posts from the database
		$query = $this->db->simple_select('posts', $fields, '`tid` = '.intval($thread_id), $options);
		
		// All we need to do now is to assign them to our returning array
		while ($post = $this->db->fetch_array($query))
		{
			$arr[] = $post;
		}
		
		return $arr;
	}
	
	/**
	 * Read the messages from database of a user
	 *
	 * @param integer $user_id ID of user
	 * @param array $params Array with options for SQL Query (orderby, sort)
	 * @param boolean $translate_folders If the folders should be turned into readable format à la "inbox"
	 * @return array
	*/
	public function getPrivateMessagesOfUser($user_id, $params = array('orderby' => 'pm.dateline', 'sort' => 'DESC'), $translate_folders = true)
	{		
		/**
		 * This is what we will be returning
		 * Structure of the array to return:
		 * array(
		 *    'Inbox' => array( ... Messages ... )
		 * )
		 *
		 * 'Inbox' is the translated folder of folder #1
		*/
		$arr = array();
		
		// If we want to translate the folder names, we need to include the file which contains the translation function
		if ($translate_folders == true)
		{
			include_once MYBB_ROOT.'inc/functions_user.php';
		}
		
		// Run the Query for Private Messages
		$query = $this->db->query('
			SELECT pm.*, fu.username AS fromusername, tu.username as tousername
			FROM '.TABLE_PREFIX.'privatemessages pm
			LEFT JOIN '.TABLE_PREFIX.'users fu ON (fu.uid=pm.fromid)
			LEFT JOIN '.TABLE_PREFIX.'users tu ON (tu.uid=pm.toid)
			WHERE pm.uid = '.intval($user_id).'
			ORDER BY '.$params['orderby'].' '.$params['sort'].'
		');
		
		// Do we have messages?
		if ($this->db->num_rows($query) > 0)
		{
			// Uhh, let's iterate the messages!
			while ($message = $this->db->fetch_array($query))
			{
				// If we translate the folder names, our array index will be the translated folder name
				if ($translate_folders == true)
				{
					$arr[get_pm_folder_name($message['folder'])][] = $message;
				}
				// If we don't want translated folder names, our array index will be the folder number
				else
				{
					$arr[$message['folder']][] = $message;
				}
			}
		}
		
		return $arr;
	}
	
	/**
	 * Returns data of a specified thread
	 * Refers to: inc/functions.php
	 *
	 * @param integer $thread_id ID of the thread to fetch data from
	 * @return array|boolean If unsuccessful, it returns false - Otherwise the Database row
	*/
	public function getThread($thread_id)
	{
		$thread = get_thread($thread_id);
		
		// Do we have permission?
		$forumpermissions = forum_permissions($thread['fid']);
		if ($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
		{
			// error_no_permission();
			return false;
		}
		else
		{
			return $thread;
		}
	}
	
	/**
	 * Get Threads of one or more forums
	 *
	 * @param integer $forum_id IDs of Forums to fetch threads from
	 * @param string $fields If you want to fetch certain fields, define a string with them
	 * @param string $where Additional WHERE constellation if needed
	 * @pararm array $query_params Parameters for the Query to run in the database 
	 *							   (order_by, order_dir, limit_start, limit [limit will only be acknowledged if both limit vars are defined])
	 * @param boolean $excluse_invisible Shall we get invisible threads too?
	 * @param boolean $join_forums Do we also want to get the forum information of where the threads are located?
	 * @param boolean $join_first_post Shall we get the first post of the thread? (= initial post)
	 * @return array
	*/
	public function getThreads($forum_id, $fields = '*', $where = '', $query_params = array('order_by' => 't.`subject`', 'order_dir' => 'ASC'), $exclude_invisible = true, $join_forums = false, $join_first_post = false)
	{
		// Do we have permission?
		if (!is_array($forum_id))
		{
			$forumpermissions = forum_permissions($forum_id);
			if ($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
			{
				// error_no_permission();
				return false;
			}
		}
		else
		{
			// Check for every single forum
			foreach ($forum_id as $forum_id_single)
			{
				$forumpermissions = forum_permissions($forum_id_single);
				if ($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
				{
					// error_no_permission();
					return false;
				}
			}
		}
		
		// This is what we will be returning
		$threads = array();
		
		// Do we want to get invisible threads as well?
		$fetch_invisible_threads = ($exclude_invisible == true) ? '1' : '0';
		$condition = 't.`visible` = '.$fetch_invisible_threads;
		
		// Are we fetching threads from multiple forums?
		if (is_array($forum_id))
		{
			$condition .= ' AND t.`fid` IN ('.implode(', ', $forum_id).')';
		}
		// Or are we just fetching threads from one forum?
		else
		{
			$condition .= ($forum_id == 0) ? '' : ' AND t.`fid` = '.$forum_id;
		}
		
		// An additional WHERE clause has been added
		if ($where != '')
		{
			$condition .= ' AND '.$where;
		}
		
		// Do we want to get information of the forum where the thread is located in?
		$forum_join = ($join_forums == true) ? 'INNER JOIN '.TABLE_PREFIX.'forums f ON f.`fid` = t.`fid`' : '';
		
		// Do we want to get the first post from the thread?
		$first_post_join = ($join_first_post == true) ? 'INNER JOIN '.TABLE_PREFIX.'posts p ON p.`pid` = t.`firstpost`' : '';
		
		// Is a Limit defined?
		$limit = (isset($query_params['limit_start']) && isset($query_params['limit'])) ? 'LIMIT '.intval($query_params['limit_start']).', '.intval($query_params['limit']) : '';
		
		// Run the Query
		$query = $this->db->query('
			SELECT '.$fields.'
			FROM '.TABLE_PREFIX.'threads t
			'.$forum_join.'
			'.$first_post_join.'
			WHERE '.$condition.'
			ORDER BY '.$query_params['order_by'].' '.$query_params['order_dir'].'
			'.$limit.'
		');
		
		// Iterate through the results and assign it to our returning array
		while ($thread = $this->db->fetch_array($query))
		{
			$threads[] = $thread;
		}
		
		return $threads;
	}
	
	/**
	 * Return array with unread threads of a forum
	 *
	 * @param integer $forum_id
	 * @return array
	*/
	public function getUnreadThreadsOfForum($forum_id)
	{
		$threads = $this->getThreads($forum_id);
		$tids = array();
		// Thread array keys shall be thread IDs
		foreach ($threads as $key => $thread)
		{
			$tids[] = $thread['tid'];
			$threads[$thread['tid']] = $thread;
			unset($threads[$key]);
		}
		$tids = implode(',', $tids);
		$query = $this->db->simple_select("threadsread", "*", "uid='{$this->mybb->user['uid']}' AND tid IN ({$tids})");
		while ($readthread = $this->db->fetch_array($query))
		{
			// Dateline of checking forum is past of last activity - so we delete the entry in array
			if ($readthread['dateline'] > $threads[$readthread['tid']]['lastpost'])
			{
				unset($threads[$readthread['tid']]);
			}
		}
		return $threads;
	}
	
	/**
	 * Returns data of a user
	 * Refers to: inc/functions.php
	 *
	 * @param integer $user_id ID of User to fetch data from (0 = own user)
	 * @return array
	*/
	public function getUser($user_id = 0)
	{
		// If given user id is 0, we use the own User ID
		if ($user_id == 0)
		{
			return get_user($this->mybb->user['uid']);
		}
		// Otherwise we fetch info from given User ID
		else
		{
			return get_user($user_id);
		}
	}
	
	/**
	 * Fetch the users being online
	 * Refers to: index.php
	 *
	 * @param boolean $colored_usernames Define if we want to return formatted usernames (color)
	 * @return array
	*/
	public function getWhoIsOnline($colored_usernames = true)
	{
		// This is what we are going to return
		$arr = array(
			'bots' => array(),
			'count_anonymous' => 0,
			'count_bots' => 0,
			'count_guests' => 0,
			'count_members' => 0,
			'members' => array()
		);
		
		// We only fetch the Who's Online list if the setting tells us that we can
		if($this->mybb->settings['showwol'] != 0 && $this->mybb->usergroup['canviewonline'] != 0)
		{
			// Get the online users.
			$timesearch = TIME_NOW - $this->mybb->settings['wolcutoff'];
			$comma = '';
			$query = $this->db->query("
				SELECT s.sid, s.ip, s.uid, s.time, s.location, s.location1, u.username, u.invisible, u.usergroup, u.displaygroup
				FROM ".TABLE_PREFIX."sessions s
				LEFT JOIN ".TABLE_PREFIX."users u ON (s.uid=u.uid)
				WHERE s.time>'$timesearch'
				ORDER BY u.username ASC, s.time DESC
			");
			
			// Iterated users will be stored here to prevent double iterating one user
			$doneusers = array();
		
			// Fetch spiders
			$spiders = $this->cache->read("spiders");
		
			// Loop through all users.
			while($user = $this->db->fetch_array($query))
			{				
				// Create a key to test if this user is a search bot.
				$botkey = my_strtolower(str_replace("bot=", '', $user['sid']));
		
				// Decide what type of user we are dealing with.
				if($user['uid'] > 0)
				{
					// The user is registered.
					if($doneusers[$user['uid']] < $user['time'] || !$doneusers[$user['uid']])
					{
						// If the user is logged in anonymously, update the count for that.
						if($user['invisible'] == 1)
						{
							++$arr['count_anonymous'];
						}
						
						if($user['invisible'] != 1 || $this->mybb->usergroup['canviewwolinvis'] == 1 || $user['uid'] == $this->mybb->user['uid'])
						{		
							// Maybe we don't want colored usernames
							if ($colored_usernames == true)
							{
								// Properly format the username and assign the template.
								$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
							}

							$user['profilelink'] = build_profile_link($user['username'], $user['uid']);
						}
						
						// This user has been handled.
						$doneusers[$user['uid']] = $user['time'];
						
						// Add the user to the members, since he is registered and logged in
						$arr['members'][]= $user;
						
						// Increase member counter
						++$arr['count_members'];
					}
				}
				
				// The user is a search bot.
				elseif(my_strpos($user['sid'], "bot=") !== false && $spiders[$botkey])
				{
					++$arr['count_bots'];
					$arr['bots'][] = $spiders[$botkey];
				}
				
				// The user is a guest
				else
				{
					++$arr['count_guests'];
				}
			}
		}
		
		return $arr;
	}
	
	/**
	 * Return members which have posted in a thread
	 *
	 * @param integer $thread_id ID of Thread
	 * @return array|false
	*/
	public function getWhoPosted($thread_id, $sort_by_posts = true)
	{
		$thread = $this->getThread($thread_id); // No need to check for permissions, the public function already does it
		$forum = $this->getForum($thread['fid']); // No need to check for permissions, the public function already does it
		if (!$this->checkForumPassword($forum['fid']))
		{
			return false;
		}
		if($sort_by_posts === true)
		{
			$sort_sql = ' ORDER BY posts DESC';
		}
		else
		{
			$sort_sql = ' ORDER BY p.username ASC';
		}
		$query = $this->db->query('
			SELECT COUNT(p.pid) AS posts, p.username AS postusername, u.uid, u.username, u.usergroup, u.displaygroup
			FROM '.TABLE_PREFIX.'posts p
			LEFT JOIN '.TABLE_PREFIX.'users u ON (u.uid=p.uid)
			WHERE tid='.intval($thread_id).' AND p.visible = 1
			GROUP BY u.uid
			'.$sortsql.'
		');
		$i = 0;
		$arr = array();
		$arr['total_posts'] = 0;
		while ($poster = $this->db->fetch_array($query))
		{
			if($poster['username'] == '')
			{
				$poster['username'] = $poster['postusername'];
			}
			$poster_name = format_name($poster['username'], $poster['usergroup'], $poster['displaygroup']);
			$arr[$i]['username']= $poster['username'];
			$arr[$i]['profile_link'] = build_profile_link($poster_name, $poster['uid'], '_blank', $onclick);
			$arr[$i]['posts'] = $poster['posts'];
			++$i;
			$arr['total_posts'] += $poster['posts'];
		}
		return $arr;
	}
	
	/**
	 * Returns users which have voted in a poll
	 *
	 * @param integer $poll_id ID of Poll
	 * @return array
	*/
	public function getWhoVoted($poll_id)
	{
		if ($poll_id == 0)
		{
			$this->_errorAndDie('Specified post ID cannot be 0!');
		}
		
		$query = $this->db->query('
			SELECT pv.`vid`, pv.`uid`, pv.`voteoption`, pv.`dateline`,
				   u.`username`, u.`usergroup`, u.`displaygroup`
			FROM '.TABLE_PREFIX.'pollvotes pv
			LEFT JOIN '.TABLE_PREFIX.'users u ON u.`uid` = pv.`uid`
			WHERE pv.`pid` = '.(int) $poll_id.'
		');
		
		$arr = array();
		$i = 0;
		while ($voter = $this->db->fetch_array($query))
		{
			$voter_name = format_name($voter['username'], $voter['usergroup'], $voter['displaygroup']);
			$arr[$i] = array(
				'vid' => $voter['vid'],
				'username' => $voter['username'],
				'profile_link' => build_profile_link($voter_name, $voter['uid'], '_blank', $onclick),
				'voteoption' => $voter['voteoption'],
				'dateline' => $voter['dateline']
			);
			++$i;
		}
		
		return $arr;
	}
	
	/**
	 * Increases the Amount of Views of a thread
	 *
	 * @param integer $thread_id ID of Thread
	*/
	public function incViews($thread_id)
	{
		// All we do here is to run the increment query
		$this->db->query('
			UPDATE '.TABLE_PREFIX.'threads
			SET `views` = `views` + 1
			WHERE `tid` = '.intval($thread_id).'
		');
	}
	
	/**
	 * Is the user logged in?
	 *
	 * @return boolean
	*/
	public function isLoggedIn()
	{
		// If the user is logged in, he has an UID
		return ($this->mybb->user['uid'] != 0) ? true : false;
	}
	
	/**
	 * Is the user a moderator?
	 * This public function checks if the user has certain rights to perform an action in a forum
	 * Refers to: inc/functions.php
	 *
	 * @param integer $forum_id ID of the forum to check permissions for
	 * @param string $action Action which shall be checked for
	 * @param integer $user_id ID of User
	*/
	public function isModerator($forum_id = 0, $action = '', $user_id = 0)
	{
		// If we aren't logged in, we cannot possibly a moderator
		if ($this->isLoggedIn())
		{
			return false;
		}
		
		// If given user_id is 0 we tak the user_id of the current user --> Check if own user is mod
		return is_moderator($forum_id, $action, ($user_id == 0) ? $this->mybb->user['uid'] : $user_id);
	}
	
	/**
	 * Returns if a user has super admin permission
	 * Refers to: inc/functions.php
	 *
	 * @param integer $user_id User-ID
	 * @return boolean
	*/
	public function isSuperAdmin($user_id = 0)
	{
		// If specified user_id is 0, we want to know if current user is Super Admin
		return is_super_admin(($user_id == 0) ? $this->mybb->user['uid'] : $user_id);
	}
	
	/**
	 * Login procedure for a user + password
	 * Possible ToDo: Return error messages / array / whatever
	 *
	 * @param string $username Username
	 * @param string $password Password of User
	 * @return boolean
	*/
	
	public function login($username, $password) 
	{
		$this->plugins->run_hooks("member_do_login_start");

		/**
		 * If we are already logged in, we do not have to perform the login procedure
		*/
		if ($this->isLoggedIn())
		{
			return true;
		}

		// Is a fatal call if user has had too many tries
		$errors = array();
		$logins = login_attempt_check();

		require_once MYBB_ROOT."inc/datahandlers/login.php";
		$loginhandler = new LoginDataHandler("get");

		$user = array(
			'username' => $username,
			'password' => $password,
			'remember' => "yes", // For some reason, MyBB is refering to this by string and not a boolean...
			'imagestring' => $captcha_string
		);

		$options = array(
			'fields' => 'loginattempts',
			'username_method' => (int)$this->mybb->settings['username_method'],
		);

		$user_loginattempts = get_user_by_username($user['username'], $options);
		$user['loginattempts'] = (int)$user_loginattempts['loginattempts'];

		$loginhandler->set_data($user);
		$validated = $loginhandler->validate_login();

		if(!$validated)
		{
			$this->mybb->input['action'] = "login";
			$this->mybb->request_method = "get";

			my_setcookie('loginattempts', $logins + 1);
			$db->update_query("users", array('loginattempts' => 'loginattempts+1'), "uid='".(int)$loginhandler->login_data['uid']."'", 1, true);

			$errors = $loginhandler->get_friendly_errors();

			$user['loginattempts'] = (int)$loginhandler->login_data['loginattempts'];

			// If we need a captcha set it here
			if($this->mybb->settings['failedcaptchalogincount'] > 0 && ($user['loginattempts'] > $this->mybb->settings['failedcaptchalogincount'] || (int)$this->mybb->cookies['loginattempts'] > $this->mybb->settings['failedcaptchalogincount']))
			{
				$do_captcha = true;
				$correct = $loginhandler->captcha_verified;
			}
		}
		else if($validated && $loginhandler->captcha_verified == true)
		{
			// Successful login
			if($loginhandler->login_data['coppauser'])
			{
				//error($this->lang->error_awaitingcoppa);
				return false;
			}
			
			$loginhandler->complete_login();

			$this->plugins->run_hooks("member_do_login_end");

			// Saving login data in user, so isLoggedIn works without having to reload the page
			$this->mybb->user = $loginhandler->login_data;
		}

		$this->plugins->run_hooks("member_do_login_end");
		return true;
	}
	
	/**
	 * Logs an administrator action taking any arguments as log data.
	 * Taken from admin/inc/functions.php
	 *
	 * NEEDS MODULE AND ACTION AS PARAMS! DONT FORGET TO PUT THIS INTO DOCUMENTATION
	 */
	public function logAdminAction()
	{
		$data = func_get_args();

		if(count($data) == 1 && is_array($data[0]))
		{
			$data = $data[0];
		}
	
		if(!is_array($data))
		{
			$data = array($data);
		}
	
		$log_entry = array(
			"uid" => $this->mybb->user['uid'],
			"ipaddress" => $this->db->escape_string(get_ip()),
			"dateline" => TIME_NOW,
			"module" => $this->db->escape_string($this->mybb->input['module']),
			"action" => $this->db->escape_string($this->mybb->input['action']),
			"data" => $this->db->escape_string(@serialize($data))
		);
	
		$this->db->insert_query("adminlog", $log_entry);
	}
	
	/**
	 * Log an action taken by a user with moderator rights
	 *
	 * @param array $data Data array with information necessary to pass onto the log
	 * @param string $action Name of the action
	*/
	public function logModeratorAction($data, $action = '')
	{
		// If the fid or tid is not set, set it at 0 so MySQL doesn't choke on it.
		if($data['fid'] == '')
		{
			$fid = 0;
		}
		else
		{
			$fid = $data['fid'];
			unset($data['fid']);
		}
	
		if($data['tid'] == '')
		{
			$tid = 0;
		}
		else
		{
			$tid = $data['tid'];
			unset($data['tid']);
		}
	
		// Any remaining extra data - we serialize and insert in to its own column
		if(is_array($data))
		{
			$data = serialize($data);
		}
	
		$sql_array = array(
			"uid" => $this->mybb->user['uid'],
			"dateline" => TIME_NOW,
			"fid" => $fid,
			"tid" => $tid,
			"action" => $this->db->escape_string($action),
			"data" => $this->db->escape_string($data),
			"ipaddress" => $this->db->escape_string($this->session->ipaddress)
		);
		$this->db->insert_query("moderatorlog", $sql_array);
	}
	
	/**
	 * Logout procedure
	 *
	 * @return boolean
	*/
	public function logout()
	{
		// If the user is not logged in at all, we make him believe that the logout procedure workedjust fine
		if (!$this->isLoggedIn())
		{
			return true;
		}

		// Check session ID if we have one
		if($this->mybb->input['sid'] && $this->mybb->input['sid'] != $this->mybb->session->sid)
		{
			return false;
		}
		// Otherwise, check logoutkey
		else if (!$this->mybb->input['sid'] && $this->mybb->input['logoutkey'] != $this->mybb->user['logoutkey'])
		{
			return false;
		}
		
		// Clear essential login cookies
		my_unsetcookie("mybbuser");
		my_unsetcookie("sid");
		
		// The logged in user data will be updated
		if($this->mybb->user['uid'])
		{
			$time = TIME_NOW;
			$lastvisit = array(
				"lastactive" => $time-900,
				"lastvisit" => $time,
			);
			$this->db->update_query("users", $lastvisit, "uid='".$this->mybb->user['uid']."'");
			$this->db->delete_query("sessions", "sid='".$this->mybb->session->sid."'");
		}
		
		// If there are any hooks to run, we call them here
		$this->plugins->run_hooks("member_logout_end");
		
		return true;
	}
	
	/**
	 * Marks one or more forums read
	 *
	 * @param integer $forum_id If Forum ID is set to 0 all forums will be marked as read
	 * @param string $redirect_url If there should be a redirection afterwards, define the URL here
	 * @return boolean Only returns false if it fails, otherwise, if it does not redirect, it returns true
	*/
	public function markRead($id = 0, $redirect_url = '')
	{
		// "Mark-Read" functions are located in inc/functions_indicators.php
		require_once MYBB_ROOT."/inc/functions_indicators.php";
		
		// Make sure the ID is a number
		$id = intval($id);
		
		// If the given Forum ID is 0, it tells us that we shall mark all forums as read
		if ($id == 0)
		{
			mark_all_forums_read();
			
			// If we want to redirect to an url, we do so
			if ($redirect_url != '')
			{
				redirect($redirect_url, $this->lang->redirect_markforumsread);
			}
		}
		
		// If a specific ID has been defined, we certainly want to mark ONE forum as read
		else
		{
			// Does the Forum exist?
			$validforum = $this->getForum($id);
			
			// If the forum is invalid, marking as read failed
			if (!$validforum)
			{
				return false;
			}
			
			// If we want to redirect to an url, we do so
			if ($redirect_url != '')
			{
				redirect($redirect_url, $this->lang->redirect_markforumsread);
			}
		}
		
		return true;
	}
	
	/**
	 * This public function creates a class object if it is necessary
	 * This is needed, if we want to use classes, which are not included in the init routine of mybb (example: Moderation)
	 *
	 * @param string $object Name of the class object
	 * @param string $class_name Name of the Class
	 * @param string $include_path The Path where we can find the class
	*/
	public function MyBBIntegratorClassObject($object, $class_name, $include_path)
	{
		if (isset($this->{$object}))
		{
			return;
		}
		else
		{
			require_once $include_path;
			$this->{$object} = new $class_name;
		}
	}
	
	/**
	 * Enables you to close one or more threads
	 * One thread: $thread_id is int
	 * More threads: $thread_id is array with ints
	 *
	 * @param integer|array $thread_id See above
	 * @param integer $forum_id ID of forum where the thread is located
	 * @return boolean
	*/
	public function openThread($thread_id, $forum_id)
	{
		if (!is_moderator($forum_id, "canopenclosethreads"))
		{
			return false;
		}
		
		$this->lang->load('moderation');
		
		$this->MyBBIntegratorClassObject('moderation', 'Moderation', MYBB_ROOT.'/inc/class_moderation.php');
		
		$this->moderation->open_threads($thread_id);
		
		$modlogdata['fid'] = $forum_id;
		
		$this->logModeratorAction($modlogdata, $this->lang->mod_process);
		
		return true;
	}
	
	/**
	 * Parses a string/message with the MyBB Parser Class
	 * Refers to: /inc/class_parser.php
	 *
	 * @param string $message The String which shall be parsed
	 * @param array $options Options the parser can accept: filter_badwords, allow_html, allow_mycode, me_username, allow_smilies, nl2br, 
	 * @return string Parsed string
	*/
	public function parseString($message, $options = array())
	{
		// Set base URL for parsing smilies
		$base_url= $this->mybb->settings['bburl'];

		if($base_url!= "")
		{
			if(my_substr($base_url, my_strlen($base_url) -1) != "/")
			{
				$base_url= $base_url."/";
			}
		}

		$message = $this->plugins->run_hooks("parse_message_start", $message);

		// Get rid of cartridge returns for they are the workings of the devil
		$message = str_replace("\r", "", $message);

		// Filter bad words if requested.
		if($options['filter_badwords'])
		{
			$message = $this->parser->parse_badwords($message);
		}

		if($options['allow_html'] != 1)
		{
			$message = $this->parser->parse_html($message);
		}
		else
		{		
			while(preg_match("#<script(.*)>(.*)</script(.*)>#is", $message))
			{
				$message = preg_replace("#<script(.*)>(.*)</script(.*)>#is", "&lt;script$1&gt;$2&lt;/script$3&gt;", $message);
			}
			// Remove these completely
			$message = preg_replace("#\s*<base[^>]*>\s*#is", "", $message);
			$message = preg_replace("#\s*<meta[^>]*>\s*#is", "", $message);
			$message = str_replace(array('<?php', '<!--', '-->', '?>', "<br />\n", "<br>\n"), array('&lt;?php', '&lt;!--', '--&gt;', '?&gt;', "\n", "\n"), $message);
		}
		
		// If MyCode needs to be replaced, first filter out [code] and [php] tags.
		if($options['allow_mycode'])
		{
			// First we split up the contents of code and php tags to ensure they're not parsed.
			preg_match_all("#\[(code|php)\](.*?)\[/\\1\](\r\n?|\n?)#si", $message, $code_matches, PREG_SET_ORDER);
			$message = preg_replace("#\[(code|php)\](.*?)\[/\\1\](\r\n?|\n?)#si", "<mybb-code>\n", $message);
		}

		// Always fix bad Javascript in the message.
		$message = $this->parser->fix_javascript($message);
		
		// Replace "me" code and slaps if we have a username
		if($options['me_username'])
		{			
			$message = preg_replace('#(>|^|\r|\n)/me ([^\r\n<]*)#i', "\\1<span style=\"color: red;\">* {$options['me_username']} \\2</span>", $message);
			$message = preg_replace('#(>|^|\r|\n)/slap ([^\r\n<]*)#i', "\\1<span style=\"color: red;\">* {$options['me_username']} {$this->lang->slaps} \\2 {$this->lang->with_trout}</span>", $message);
		}
		
		// If we can, parse smilies
		if($options['allow_smilies'])
		{
			$message = $this->parser->parse_smilies($message, $options['allow_html']);
		}

		// Replace MyCode if requested.
		if($options['allow_mycode'])
		{
			$message = $this->parser->parse_mycode($message, $options);
		}

		// Run plugin hooks
		$message = $this->plugins->run_hooks("parse_message", $message);
		
		if($options['allow_mycode'])
		{
			// Now that we're done, if we split up any code tags, parse them and glue it all back together
			if(count($code_matches) > 0)
			{
				foreach($code_matches as $text)
				{
					// Fix up HTML inside the code tags so it is clean
					if($options['allow_html'] != 0)
					{
						$text[2] = $this->parser->parse_html($text[2]);
					}
					
					if(my_strtolower($text[1]) == "code")
					{
						$code = $this->parser->mycode_parse_code($text[2]);
					}
					elseif(my_strtolower($text[1]) == "php")
					{
						$code = $this->parser->mycode_parse_php($text[2]);
					}
					$message = preg_replace("#\<mybb-code>\n?#", $code, $message, 1);
				}
			}
		}

		if($options['nl2br'] !== 0)
		{
			$message = nl2br($message);
			// Fix up new lines and block level elements
			$message = preg_replace("#(</?(?:html|head|body|div|p|form|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|div|p|blockquote|cite|hr)[^>]*>)\s*<br />#i", "$1", $message);
			$message = preg_replace("#(&nbsp;)+(</?(?:html|head|body|div|p|form|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|div|p|blockquote|cite|hr)[^>]*>)#i", "$2", $message);
		}

		$message = my_wordwrap($message);
	
		$message = $this->plugins->run_hooks("parse_message_end", $message);
				
		return $message;
	}
	
	/**
	 * Register procedure
	 * Refers to: /member.php
	 *
	 * @param array $info Contains user information of the User to be registered
	 * @return array|string If registration fails, we return an array containing the error message, 
	 * 						If registration is successful, we return the string, which notifies the user of what will be the next action
	*/
	public function register($info = array())
	{
		// Load the language phrases we need for the registration
		$this->lang->load('member');
		
		/**
		 * $info contains the given user information for the registration
		 * We need to make sure that every possible key is given, so we do not generate ugly E_NOIICE errors
		*/
		$possible_info_keys	= array(
			'username', 'password', 'password2', 'email', 'email2', 'referrer', 'timezone', 'language',
			'profile_fields', 'allownotices', 'hideemail', 'subscriptionmethod', 
			'receivepms', 'pmnotice', 'emailpmnotify', 'invisible', 'dstcorrection'
		);
		
		// Iterate the possible info keys to create the array entry in $info if it does not exist
		foreach ($possible_info_keys as $possible_info_key)
		{
			if (!isset($info[$possible_info_key]))
			{
				$info[$possible_info_key] = '';
			}
		}
		
		echo '<pre>'; print_r($info); echo '</pre>';
		
		// Run whatever hook specified at the beginning of the registration		
		$this->plugins->run_hooks('member_do_register_start');
		
		// If register type is random password, we generate one
		if($this->mybb->settings['regtype'] == "randompass")
		{
			$info['password'] = random_str();
			$info['password2'] = $info['password'];
		}
		
		if($this->mybb->settings['regtype'] == "verify" || $this->mybb->settings['regtype'] == "admin" || $info['coppa'] == 1)
		{
			$usergroup = 5;
		}
		else
		{
			$usergroup = 2;
		}
		
		// Set up user handler.
		require_once MYBB_ROOT."inc/datahandlers/user.php";
		$userhandler = new UserDataHandler("insert");
		
		// Set the data for the new user.
		$user = array(
			"username" => $info['username'],
			"password" => $info['password'],
			"password2" => $info['password2'],
			"email" => $info['email'],
			"email2" => $info['email2'],
			"usergroup" => $usergroup,
			"referrer" => $info['referrername'],
			"timezone" => $info['timezone'],
			"language" => $info['language'],
			"profile_fields" => $info['profile_fields'],
			"regip" => $this->mybb->session->ipaddress,
			"longregip" => ip2long($this->mybb->session->ipaddress),
			"coppa_user" => intval($this->mybb->cookies['coppauser']),
		);
		
		if(isset($info['regcheck1']) && isset($info['regcheck2']))
		{
			$user['regcheck1'] = $info['regcheck1'];
			$user['regcheck2'] = $info['regcheck2'];
		}
		
		// Do we have a saved COPPA DOB?
		if($this->mybb->cookies['coppadob'])
		{
			list($dob_day, $dob_month, $dob_year) = explode("-", $this->mybb->cookies['coppadob']);
			$user['birthday'] = array(
				"day" => $dob_day,
				"month" => $dob_month,
				"year" => $dob_year
			);
		}
		
		// Generate the options array of the user
		$user['options'] = array(
			"allownotices" => $info['allownotices'],
			"hideemail" => $info['hideemail'],
			"subscriptionmethod" => $info['subscriptionmethod'],
			"receivepms" => $info['receivepms'],
			"pmnotice" => $info['pmnotice'],
			"emailpmnotify" => $info['emailpmnotify'],
			"invisible" => $info['invisible'],
			"dstcorrection" => $info['dstcorrection']
		);
		
		// Assign data to the data handler
		$userhandler->set_data($user);
		
		// If the validation of the user failed, we return nice (friendly) errors
		if(!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
			return $errors;
		}
		
		// Create the User in the database
		$user_info = $userhandler->insert_user();
		
		// We need to set a cookie, if we don't want a random password (and it is no COPPA user), so he is instantly logged in
		if($this->mybb->settings['regtype'] != "randompass" && !$this->mybb->cookies['coppauser'])
		{
			// Log them in
			my_setcookie("mybbuser", $user_info['uid']."_".$user_info['loginkey'], null, true);
		}
		
		/**
		 * Coppa User
		 * Nothing special, just return that the coppa user will be redirected
		*/
		if($this->mybb->cookies['coppauser'])
		{
			$this->lang->redirect_registered_coppa_activate = $this->lang->sprintf($this->lang->redirect_registered_coppa_activate, $this->mybb->settings['bbname'], $user_info['username']);
			my_unsetcookie("coppauser");
			my_unsetcookie("coppadob");
			
			// Run whatever hook is defined at the end of a registration
			$this->plugins->run_hooks("member_do_register_end");
			
			return $this->lang->redirect_registered_coppa_activate;
		}
		
		/**
		 * Register Mode: Email Verification
		 * A mail is dispatched containing an activation link.
		 * The activation link is a reference to the newly created database entry
		*/
		else if($this->mybb->settings['regtype'] == "verify")
		{
			// Generate and save the activation code in the database
			$activationcode = random_str();
			$now = TIME_NOW;
			$activationarray = array(
				"uid" => $user_info['uid'],
				"dateline" => TIME_NOW,
				"code" => $activationcode,
				"type" => "r"
			);
			$this->db->insert_query("awaitingactivation", $activationarray);
			
			// Generate and send the email
			$emailsubject = $this->lang->sprintf($this->lang->emailsubject_activateaccount, $this->mybb->settings['bbname']);
			$emailmessage = $this->lang->sprintf($this->lang->email_activateaccount, $user_info['username'], $this->mybb->settings['bbname'], $this->mybb->settings['bburl'], $user_info['uid'], $activationcode);
			my_mail($user_info['email'], $emailsubject, $emailmessage);
			
			// Build the message to return
			$this->lang->redirect_registered_activation = $this->lang->sprintf($this->lang->redirect_registered_activation, $this->mybb->settings['bbname'], $user_info['username']);
			
			// Run whatever hook is defined at the end of a registration
			$this->plugins->run_hooks("member_do_register_end");
			
			return $this->lang->redirect_registered_activation;
		}
		
		/**
		 * Register Mode: Send Random Password
		 * A mail is dispatched, containing the random password for the user
		*/
		else if($this->mybb->settings['regtype'] == "randompass")
		{
			// Generate and send the email
			$emailsubject = $this->lang->sprintf($this->lang->emailsubject_randompassword, $this->mybb->settings['bbname']);
			$emailmessage = $this->lang->sprintf($this->lang->email_randompassword, $user['username'], $this->mybb->settings['bbname'], $user_info['username'], $user_info['password']);
			my_mail($user_info['email'], $emailsubject, $emailmessage);
			
			// Run whatever hook is defined at the end of a registration
			$this->plugins->run_hooks("member_do_register_end");
			
			return $this->lang->redirect_registered_passwordsent;
		}
		
		/**
		 * Register Mode: Admin Activation
		 * Return the message that the user will need to be authorized by an admin
		*/
		else if($this->mybb->settings['regtype'] == "admin")
		{
			// Build the message to return
			$this->lang->redirect_registered_admin_activate = $this->lang->sprintf($this->lang->redirect_registered_admin_activate, $this->mybb->settings['bbname'], $user_info['username']);
			
			// Run whatever hook is defined at the end of a registration
			$this->plugins->run_hooks("member_do_register_end");
			
			return $this->lang->redirect_registered_admin_activate;
		}
		
		/**
		 * No activation required whatsoever,
		 * directly registered
		*/
		else
		{
			// Build the message to return
			$this->lang->redirect_registered = $this->lang->sprintf($this->lang->redirect_registered, $this->mybb->settings['bbname'], $user_info['username']);
			
			// Run whatever hook is defined at the end of a registration
			$this->plugins->run_hooks('member_do_register_end');
			
			return $this->lang->redirect_registered;
		}
	}
	
	/**
	 * Will remove a Forum/Category and everything related to it
	 * Taken from admin/modules/forum/management.php
	 *
	 * @param integer $forum_id ID of Forum/Category
	 * @return boolean
	*/
	public function removeForumOrCategory($forum_id)
	{
		$this->plugins->run_hooks("admin_forum_management_delete");
		
		$query = $this->db->simple_select("forums", "*", "fid='{$forum_id}'");
		$forum = $this->db->fetch_array($query);
		
		// Does the forum not exist?
		if (!$forum['fid'])
		{
			return false;
		}
		
		$fid = intval($forum_id);
		$forum_info = $this->getForum($fid);
		
		// Delete the forum
		$this->db->delete_query("forums", "fid='$fid'");
		switch ($this->db->type)
		{
			case "pgsql":
			case "sqlite3":
			case "sqlite2":
				$query = $this->db->simple_select("forums", "*", "','|| parentlist|| ',' LIKE '%,$fid,%'");
				break;
			default:
				$query = $this->db->simple_select("forums", "*", "CONCAT(',', parentlist, ',') LIKE '%,$fid,%'");
		}		
		while ($forum = $this->db->fetch_array($query))
		{
			$fids[$forum['fid']] = $fid;
			$delquery .= " OR fid='{$forum['fid']}'";
		}

		/**
		 * This slab of code pulls out the moderators for this forum,
		 * checks if they moderate any other forums, and if they don't
		 * it moves them back to the registered usergroup
		 */

		$query = $this->db->simple_select("moderators", "*", "fid='$fid'");
		while ($mod = $this->db->fetch_array($query))
		{
			$moderators[$mod['uid']] = $mod['uid'];
		}
		
		if (is_array($moderators))
		{
			$mod_list = implode(",", $moderators);
			$query = $this->db->simple_select("moderators", "*", "fid != '$fid' AND uid IN ($mod_list)");
			while ($mod = $this->db->fetch_array($query))
			{
				unset($moderators[$mod['uid']]);
			}
		}
		
		if (is_array($moderators))
		{
			$mod_list = implode(",", $moderators);
			if($mod_list)
			{
				$updatequery = array(
					"usergroup" => "2"
				);
				$this->db->update_query("users", $updatequery, "uid IN ($mod_list) AND usergroup='6'");
			}
		}
		
		switch($this->db->type)
		{
			case "pgsql":
			case "sqlite3":
			case "sqlite2":
				$this->db->delete_query("forums", "','||parentlist||',' LIKE '%,$fid,%'");
				break;
			default:
				$this->db->delete_query("forums", "CONCAT(',',parentlist,',') LIKE '%,$fid,%'");
		}
		
		$this->db->delete_query("threads", "fid='{$fid}' {$delquery}");
		$this->db->delete_query("posts", "fid='{$fid}' {$delquery}");
		$this->db->delete_query("moderators", "fid='{$fid}' {$delquery}");
		$this->db->delete_query("forumsubscriptions", "fid='{$fid}' {$delquery}");

		$this->cache->update_forums();
		$this->cache->update_moderators();
		$this->cache->update_forumpermissions();
		
		// Log admin action - Need to add 2 params in input array so logging contains correct info
		$this->mybb->input['module'] = 'forum/management';
		$this->mybb->input['action'] = 'delete';
		$this->logAdminAction($forum_info['fid'], $forum_info['name']);
		
		$this->plugins->run_hooks("admin_forum_management_delete_commit");
		
		return true;
	}
	
	/**
	 * Delete a post
	 *
	 * @param integer $post_id ID of Post
	 * @return ?
	*/
	public function removePost($post_id)
	{
		require_once MYBB_ROOT."inc/functions_post.php";
		require_once MYBB_ROOT."inc/functions_upload.php";
		
		$this->lang->load('editpost');

		$post = $this->getPost($post_id);
		
		$tid = $post['tid'];
		$fid = $post['fid'];
		$pid = $post['pid'];
		
		$forumpermissions = forum_permissions($fid);
		
		$this->plugins->run_hooks("editpost_deletepost");
		
		$query = $this->db->simple_select("posts", "pid", "tid='{$tid}'", array("limit" => 1, "order_by" => "dateline", "order_dir" => "asc"));
		$firstcheck = $this->db->fetch_array($query);
		if ($firstcheck['pid'] == $pid)
		{
			$firstpost = 1;
		}
		else
		{
			$firstpost = 0;
		}
		
		$modlogdata['fid'] = $fid;
		$modlogdata['tid'] = $tid;
		
		if ($firstpost)
		{
			if ($forumpermissions['candeletethreads'] == 1 || is_moderator($fid, "candeleteposts"))
			{
				delete_thread($tid);
				mark_reports($tid, "thread");
				$this->logModeratorAction($modlogdata, $this->lang->thread_deleted);
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			if ($forumpermissions['candeleteposts'] == 1 || is_moderator($fid, "candeleteposts"))
			{
				// Select the first post before this
				delete_post($pid, $tid);
				mark_reports($pid, "post");
				$this->logModeratorAction($modlogdata, $this->lang->post_deleted);
				$query = $this->db->simple_select("posts", "pid", "tid='{$tid}' AND dateline <= '{$post['dateline']}'", array("limit" => 1, "order_by" => "dateline", "order_dir" => "desc"));
				$next_post = $this->db->fetch_array($query);
				return true;
			}
			else
			{
				return false;
			}
		}
	}

	/**
	 * Delete a user in the database
	 *
	 * @param integer $thread Thread ID
	 * @return boolean
	*/
	public function removeThread($thread)
	{
		$tid = intval($thread);

		$this->lang->load('editpost');

		$deleted = delete_thread($tid);
		mark_reports($tid, "thread");

		$modlogdata['tid'] = $tid;
		$this->logModeratorAction($modlogdata, $this->lang->thread_deleted);

		return $deleted;
	}

	/**
	 * Delete a user in the database
	 *
	 * @param integer|string $user User ID or username
	 * @return boolean
	*/
	public function removeUser($user)
	{
		// If no ID is given, we check if there is a user with the specified username
		if (!is_numeric($user))
		{
			$query = $this->db->simple_select('users', 'uid', 'username=\''.$this->dbEscape($user).'\'');
			$user_id = $this->db->fetch_field($query, 'uid', 0);
			
			// User does not exist? --> False
			if (empty($user_id))
			{
				return false;
			}
			
			$user_id = intval($user_id);
		}
		else
		{		
			$user_id = intval($user);
		}
		
		$this->plugins->run_hooks('admin_user_users_delete');
		
		// Delete the user
		$this->db->update_query("posts", array('uid' => 0), "uid='{$user_id}'");
		$this->db->delete_query("userfields", "ufid='{$user_id}'");
		$this->db->delete_query("privatemessages", "uid='{$user_id}'");
		$this->db->delete_query("events", "uid='{$user_id}'");
		$this->db->delete_query("moderators", "id='{$user_id}'");
		$this->db->delete_query("forumsubscriptions", "uid='{$user_id}'");
		$this->db->delete_query("threadsubscriptions", "uid='{$user_id}'");
		$this->db->delete_query("sessions", "uid='{$user_id}'");
		$this->db->delete_query("banned", "uid='{$user_id}'");
		$this->db->delete_query("threadratings", "uid='{$user_id}'");
		$this->db->delete_query("users", "uid='{$user_id}'");
		$this->db->delete_query("joinrequests", "uid='{$user_id}'");
		$this->db->delete_query("warnings", "uid='{$user_id}'");

		// Update forum stats
		update_stats(array('numusers' => '-1'));
		
		$this->plugins->run_hooks('admin_user_users_delete_commit');
		
		return true;
	}
	
	/**
	 * Send a private message from someone to someone
	*/
	public function sendPrivateMessage($data = array())
	{
		// Let's do default values and check if all required data keys are passed
		$default_data = array(
			'fromid' => 0,
			'subject' => '',
			'message' => '',
			'icon' => 0,
			'to_username' => ''
		);
		
		// Set default values if they are missing!
		foreach ($default_data as $default_data_key => $default_data_val)
		{
			if (!isset($data[$default_data_key]))
			{
				$data[$default_data_key] = $default_data_val;
			}
		}
		
		$this->lang->load('private');
		
		$this->plugins->run_hooks('private_send_do_send');
		
		// Attempt to see if this PM is a duplicate or not
		$time_cutoff = TIME_NOW - (5 * 60 * 60);
		$query = $this->db->query("
			SELECT pm.pmid
			FROM ".TABLE_PREFIX."privatemessages pm
			LEFT JOIN ".TABLE_PREFIX."users u ON(u.uid=pm.toid)
			WHERE u.username='".$this->db->escape_string($data['to_uername'])."' AND pm.dateline > {$time_cutoff} AND pm.fromid='{".$data['fromid']."}' AND pm.subject='".$this->db->escape_string($data['subject'])."' AND pm.message='".$this->db->escape_string($data['message'])."' AND pm.folder!='3'
		");
		$duplicate_check = $this->db->fetch_field($query, "pmid");
		if ($duplicate_check)
		{
			return $this->lang->error_pm_already_submitted;
		}
		
		require_once MYBB_ROOT."inc/datahandlers/pm.php";
		$pmhandler = new PMDataHandler();
		
		// Split up any recipients we have
		$data['to'] = explode(",", $data['to_username']);
		$data['to'] = array_map("trim", $data['to']);
		if (!empty($data['bcc']))
		{
			$data['bcc'] = explode(",", $data['bcc']);
			$data['bcc'] = array_map("trim", $data['bcc']);
		}
		
		$data['options'] = array(
			"signature" => (isset($data['options']['signature'])) ? $data['options']['signature'] : NULL,
			"disablesmilies" => (isset($data['options']['disablesmilies'])) ? $data['options']['disablesmilies'] : NULL,
			"savecopy" => (isset($data['options']['savecopy'])) ? $data['options']['savecopy'] : NULL,
			"readreceipt" => (isset($data['options']['readreceipt'])) ? $data['options']['readreceipt'] : NULL
		);
		
		/* Unnecessary
		if($data['saveasdraft'])
		{
			$data['saveasdraft'] = 1;
		} */
		
		$pmhandler->set_data($data);
		
		// Now let the pm handler do all the hard work.
		if(!$pmhandler->validate_pm())
		{
			$pm_errors = $pmhandler->get_friendly_errors();
			return inline_error($pm_errors);
			
		}
		else
		{
			$pminfo = $pmhandler->insert_pm();
			$this->plugins->run_hooks("private_do_send_end");
	
			if (isset($pminfo['draftsaved']))
			{
				return $this->lang->redirect_pmsaved;
			}
			else
			{
				return $this->lang->redirect_pmsent;
			}
		}
	}
	
	/**
	 * Use built-in set-cookie public function of MyBB
	 *
	 * @param string $name Cookie Name
	 * @param mixed $value Cookie Value
	 * @param integer $expires Timestamp of Expiry
	 * @param boolean Use cookie for HTTP only?
	*/
	public function setCookie($name, $value = '', $expires = NULL, $httponly = false)
	{
		my_setcookie($name, $value, $expires, $httponly);
	}
	
	/**
	 * Set a new password for a user
	 *
	 * @param integer User-ID
	 * @param string New Password
	 * @param boolean Return errors as MyBB array or nicely formated?
	 * @return boolean|array
	*/
	public function updatePasswordOfUser($user_id, $password, $inline_error = true)
	{
		include_once MYBB_ROOT.'inc/functions_user.php';
		require_once MYBB_ROOT.'inc/datahandlers/user.php';
		$userhandler = new UserDataHandler('update');
		
		$data = array(
			'uid' => intval($user_id),
			'password' => $password
		);
		
		$userhandler->set_data($data);
		
		if (!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
			return ($inline_error === true) ? inline_error($errors) : $errors;
		}
		
		$userhandler->update_user();
		
		return true;
	}
	
	/**
	 * Update content and information of a single post
	 *
	 * @param array $data Post-Data
	 * @param boolean|array|string $inline_errors Return arrays as array or string or return bool true when all good
	*/
	public function updatePost($data, $inline_errors)
	{
		require_once MYBB_ROOT.'inc/functions_post.php';
		require_once MYBB_ROOT.'/inc/datahandlers/post.php';
		$posthandler = new PostDataHandler('update');
		$posthandler->action = 'post';
		
		$this->plugins->run_hooks('editpost_do_editpost_start');
		
		$posthandler->set_data($data);
		
		if (!$posthandler->validate_post())
		{
			$errors = $posthandler->get_friendly_errors();
			return ($inline_errors === true) ? inline_error($errors) : $errors;
		}
		
		$this->plugins->run_hooks('editpost_do_editpost_end');
		
		return $posthandler->update_post();
	}
	
	/**
	 * Updates a thread in the database
	 *
	 * @param array $data Thread data
	 * @param boolean $inline_errors Defines if we want a formatted error string or an array
	 * @return array|string 
	 * @return array|string When true it will return an array with threadID, postID and status of being visible - false = error array or inline string 
	*/
	public function updateThread($data, $inline_errors = true)
	{
		if (!isset($data['tid']))
		{
			$this->_errorAndDie('public function <i>updateThread</i>: Must pass thread id in array parameter - Required array key is <i>tid</i>');
		}
		
		// Posthandler is used for a post, so let's fetch the thread-post
		$thread = $this->getThread($data['tid']);
		$data['pid'] = $thread['firstpost'];
		
		require_once MYBB_ROOT.'inc/functions_post.php';
		require_once MYBB_ROOT.'/inc/datahandlers/post.php';
		$posthandler = new PostDataHandler('update');
		$posthandler->action = 'post';
		$posthandler->set_data($data);
		if (!$posthandler->validate_post())
		{
			$errors = $posthandler->get_friendly_errors();
			return ($inline_errors === true) ? inline_error($errors) : $errors;
		}
		return $posthandler->update_post();
	}
	
	/**
	 * Updates userdata
	 *
	 * @param array $userdata Data of the User (uid is required as index)
	 * @param boolean Return errors as MyBB array or nicely formated?
	 * @return boolean|array
	*/
	public function updateUser($userdata = array(), $inline_error = true)
	{
		// Userdata Array needs to contain the UserID
		if (!isset($userdata['uid']))
		{
			$this->_errorAndDie('A UserID (Array-Key: <i>uid</i>) is required to update a user');
		}
		
		require_once MYBB_ROOT.'inc/functions_user.php';
		require_once MYBB_ROOT.'inc/datahandlers/user.php';
		$userhandler = new UserDataHandler('update');
		$userhandler->set_data($userdata);
		
		if (!$userhandler->validate_user())
		{
			$errors = $userhandler->get_friendly_errors();
			return ($inline_error === true) ? inline_error($errors) : $errors;
		}
		
		$userhandler->update_user();
		
		return true;
	}
	
	/**
	 * Checks if given captcha is correct
	 *
	 * @param string $hash Captcha-Hash
	 * @param string $string the Letters of the captcha
	 * @return boolean
	*/
	public function validateCaptcha($hash, $string)
	{
		$imagehash = $this->dbEscape($hash);
		$imagestring = $this->dbEscape($string);
		$query = $this->db->simple_select("captcha", "*", "imagehash='{$imagehash}' AND imagestring='{$imagestring}'");
		$imgcheck = $this->db->fetch_array($query);
		if($imgcheck['dateline'] > 0)
		{		
			return true;
		}
		else
		{
			$this->db->delete_query("captcha", "imagehash='{$imagehash}'");
			return false;
		}
	}
	
	/**
	 * Perform a vote in a poll
	 *
	 * @param integer $poll_id ID of Poll
	 * @param integer $user_id ID of User
	 * @param integer|array Vote option (basically what you vote!) - if multiple, you can define more options in an array
	*/
	public function vote($poll_id, $user_id = 0, $option = NULL)
	{
		// Load the Language Phrases
		$this->lang->load('polls');
		
		// A bit sanitizing...
		$poll_id = (int) $poll_id;
		$user_id = (int) $user_id;
		
		// Let's fetch infos of the poll
		$query = $this->db->simple_select("polls", "*", "pid='".intval($poll_id)."'");
		$poll = $this->db->fetch_array($query);
		$poll['timeout'] = $poll['timeout']*60*60*24;
		
		$this->plugins->run_hooks("polls_vote_start");
		
		// Does the poll exist?
		if (!$poll['pid'])
		{
			return $this->lang->error_invalidpoll;
		}
		
		// Does the poll exist in a valid thread?
		$query = $this->db->simple_select("threads", "*", "poll='".$poll['pid']."'");
		$thread = $this->db->fetch_array($query);
		if (!$thread['tid'])
		{
			return $this->lang->error_invalidthread;
		}
		
		// Do we have the permissino to vote?
		$fid = $thread['fid'];
		$forumpermissions = forum_permissions($fid);
		if ($forumpermissions['canvotepolls'] == 0)
		{
			return false;
		}
		
		// Has the poll expired?
		$expiretime = $poll['dateline'] + $poll['timeout'];
		if ($poll['closed'] == 1 || $thread['closed'] == 1 || ($expiretime < TIME_NOW && $poll['timeout']))
		{
			return $this->lang->error_pollclosed;
		}
		
		// Did we pass an option to vote for?
		if (empty($option))
		{
			return $this->lang->error_nopolloptions;
		}
		
		// Check if the user has voted before...
		if ($user_id > 0)
		{
			$query = $this->db->simple_select("pollvotes", "*", "uid='".$user_id."' AND pid='".$poll['pid']."'");
			$votecheck = $this->db->fetch_array($query);
		}
		
		if ($votecheck['vid'] || $this->mybb->cookies['pollvotes'][$poll['pid']])
		{
			return $this->lang->error_alreadyvoted;
		}
		elseif ($user_id == 0)
		{
			// Give a cookie to guests to inhibit revotes
			my_setcookie("pollvotes[{$poll['pid']}]", '1');
		}
		
		$votesql = '';
		$votesarray = explode("||~|~||", $poll['votes']);
		$numvotes = $poll['numvotes'];
		if ($poll['multiple'] == 1)
		{
			foreach ($option as $voteoption => $vote)
			{
				if ($vote == 1 && isset($votesarray[$voteoption-1]))
				{
					if ($votesql)
					{
						$votesql .= ",";
					}
					$votesql .= "('".$poll['pid']."','".$user_id."','".$this->db->escape_string($voteoption)."', ".TIME_NOW.")";
					$votesarray[$voteoption-1]++;
					$numvotes = $numvotes+1;
				}
			}
		}
		else
		{
			if (!isset($votesarray[$option-1]))
			{
				return $this->lang->error_nopolloptions;
			}
			$votesql = "('".$poll['pid']."','".$user_id."','".$this->db->escape_string($option)."', ".TIME_NOW.")";
			$votesarray[$option-1]++;
			$numvotes = $numvotes+1;
		}
		
		// Save the fact that we voted
		$this->db->write_query("
			INSERT INTO 
			".TABLE_PREFIX."pollvotes (pid,uid,voteoption,dateline) 
			VALUES $votesql
		");
		$voteslist = '';
		for ($i = 1; $i <= $poll['numoptions']; ++$i)
		{
			if ($i > 1)
			{
				$voteslist .= "||~|~||";
			}
			$voteslist .= $votesarray[$i-1];
		}
		$updatedpoll = array(
			"votes" => $this->db->escape_string($voteslist),
			"numvotes" => intval($numvotes),
		);
	
		$this->plugins->run_hooks("polls_vote_process");
	
		$this->db->update_query("polls", $updatedpoll, "pid='".$poll['pid']."'");
	
		$this->plugins->run_hooks("polls_vote_end");
	
		return true;
	}
}

?>