<?php

if (!defined('FORUM')) {
	die();
}


class Subforums
{
	function __construct()
	{
		global $forum_id;
		global $ext_info, $forum_user;
		
		// Load extension language file
		if (file_exists($ext_info['path'] . '/lang/' . $forum_user['language'] . '/' . $ext_info['id'] . '.php')) {
			require $ext_info['path'] . '/lang/' . $forum_user['language'] . '/' . $ext_info['id'] . '.php';
		}
		else {
			require $ext_info['path'] . '/lang/English/' . $ext_info['id'] . '.php';
		}

		$this->lang = $lang_xn_subforums;
		$this->fid = $forum_id;

		$this->tree = false;
		$this->cat = false;

		$this->list = $this->get_list();
	}


	private function padleft($n, $char)
	{
		$s = '';
		for ($i = 0; $i < $n; $i++) {
			$s .= $char;
		}
		return $s;
	}


	private function get_list()
	{
		global $forum_db, $forum_user;

		// Select all subforums
		$sub_query = array(
			'SELECT'   => 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster, f.parent_id',
			'FROM'     => 'categories AS c',
			'JOINS'    => array(
				array(
					'INNER JOIN' => 'forums AS f',
					'ON'         => 'c.id = f.cat_id'
				),
				array(
					'LEFT JOIN'  => 'forum_perms AS fp',
					'ON'         => '(fp.forum_id = f.id AND fp.group_id = ' . $forum_user['g_id'] . ')'
				)
			),
			'WHERE'    => '(fp.read_forum IS NULL OR fp.read_forum = 1) AND f.parent_id > 0',
			'ORDER BY' => 'c.disp_position, c.id, f.disp_position'
		);

		$sub_result = $forum_db->query_build($sub_query) or error(__FILE__, __LINE__);

		// Generate array of subforums
		$list = array();
		while ($sub_forum = $forum_db->fetch_assoc($sub_result)) {
			$list[$sub_forum['parent_id']][] = $sub_forum;
		}
		return $list;
	}


	public function get_index_desc($fid, $level)
	{
		global $forum_url, $forum_config;

		$s = '';
		if (!empty($this->list[$fid])) {
			$title = count($this->list[$fid]) == 1 ? $this->lang['Subforum'] : $this->lang['Subforums'];
			$s = '<dl class="index-subforums index-subforums-level' . $level . '"><dt>' . $title . ':</dt>';
			foreach ($this->list[$fid] as $cur_subforum) {
				$link = forum_link($forum_url['forum'], array($cur_subforum['fid'], sef_friendly($cur_subforum['forum_name'])));
				$desc = $this->get_index_desc($cur_subforum['fid'], $level + 1);
				$desc_text = '';
				if ($forum_config['o_subforums_show_list'] == 2 && $cur_subforum['forum_desc'] != '') {
					$desc_text =  '&nbsp;&mdash; ' . $cur_subforum['forum_desc'];
				}
				$s .= '<dd><a href="' . $link . '">' . $cur_subforum['forum_name'] .
					'</a>' . $desc . $desc_text . '</dd>';
			}
			$s .= '</dl>';
		}
		return $s;
	}


	public function get_option($fid, $level, $selected_id = 0, $self_id = 0)
	{
		global $forum_url, $forum_count, $sef_friendly_names, $subforums_tab;

		$s = '';
		if (!empty($this->list[$fid])) {
			foreach ($this->list[$fid] as $cur_subforum) {
				$sef_friendly_names[$cur_subforum['fid']] = sef_friendly($cur_subforum['forum_name']);
				$redirect_tag = ($cur_subforum['redirect_url'] != '') ? ' &gt;&gt;&gt;' : '';
				$s .= $this->padleft($subforums_tab, "\t") .
					'<option value="' . $cur_subforum['fid'] . '"' .
					($selected_id == $cur_subforum['fid'] ? ' selected="selected"' : '') .
					($cur_subforum['fid'] == $self_id ? ' class="option-subforums-self"' : '') .
					'>' . $this->padleft($level, '&nbsp;&nbsp;') . '↳ ' .
					forum_htmlencode($cur_subforum['forum_name']) . $redirect_tag .
					'</option>';
				$forum_count++;
				$s .= $this->get_option($cur_subforum['fid'], $level + 1, $selected_id, $self_id);
			}
		}
		return $s;
	}


	public function add_crumbs($fid)
	{
		global $forum_db, $forum_page, $forum_url, $sef_friendly_names;
	
		$subforums_first_crumb = array_shift($forum_page['crumbs']);
		$subforums_query = array(
			'SELECT' => 'f.id AS fid, f.forum_name, f.parent_id',
			'FROM'   => 'forums AS f'
		);

		$subforums_result = $forum_db->query_build($subforums_query) or error(__FILE__, __LINE__);
		while ($cur_parent_forum = $forum_db->fetch_assoc($subforums_result)) {
			$parent_forums[$cur_parent_forum['fid']] = $cur_parent_forum;
		}

		$subforums_marked = array();
		$subforums_marked[$fid] = $fid;
		$cur_fid = $parent_forums[$fid]['parent_id'];
		while ($cur_fid && !isset($subforums_marked[$cur_fid])) {
			array_unshift($forum_page['crumbs'], array($parent_forums[$cur_fid]['forum_name'], forum_link($forum_url['forum'], array($cur_fid, sef_friendly($parent_forums[$cur_fid]['forum_name'])))));
		
			// Mark current forum as already used (protect cycles)
			$subforums_marked[$cur_fid] = $cur_fid;
			// Get next parent level
			$cur_fid = $parent_forums[$cur_fid]['parent_id'];
		}
		if (isset($subforums_marked[$cur_fid])) {
			array_unshift($forum_page['crumbs'], '…');
		}
		array_unshift($forum_page['crumbs'], $subforums_first_crumb);
	}


	private function get_subcat($fid)
	{
		global $subforums_marked;

		if (!empty($this->list[$fid])) {
			foreach ($this->list[$fid] as $cur_subforum) {
				if (!isset($subforums_marked[$cur_subforum['fid']])) {
					$subforums_marked[$cur_subforum['fid']] = $cur_subforum['fid'];
					$this->get_cat($cur_subforum['fid']);
				}
			}
		}
	}


	public function get_cat()
	{
		if (!$this->cat) {
			$this->cat = $this->get_subcat($this->fid);
		}
		return $this->cat;
	}


	private function get_subtree($fid)
	{
		$tree = array();
		$tree[] = $fid;
		if (!empty($this->list[$fid])) {
			foreach ($this->list[$fid] as $cur_subforum) {
				$tree += $this->get_tree($cur_subforum['fid']);
			}
		}
		return $tree;
	}


	public function get_tree()
	{
		if (!$this->tree) {
			$this->tree = $this->get_subtree($this->fid);
		}
		return $this->tree;
	}
}


$subforums = new Subforums();

?>
