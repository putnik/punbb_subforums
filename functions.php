<?php

/*
 * Subforums functions
 */

if (!defined('FORUM')) {
	die();
}

function subforums_padleft($n, $char)
{
	$s = '';
	for ($i = 0; $i < $n; $i++) {
		$s .= $char;
	}
	return $s;
}

function subforums_index_desc($fid, $level)
{
	global $subforums, $lang_xn_subforums, $forum_url, $forum_config;

	$s = '';
	if (!empty($subforums[$fid])) {
		$title = count($subforums[$fid]) == 1 ? $lang_xn_subforums['Subforum'] : $lang_xn_subforums['Subforums'];
		$s = '<dl class="index-subforums index-subforums-level' . $level . '"><dt>' . $title . ':</dt>';
		foreach ($subforums[$fid] as $cur_subforum) {
			$link = forum_link($forum_url['forum'], array($cur_subforum['fid'], sef_friendly($cur_subforum['forum_name'])));
			$desc = subforums_index_desc($cur_subforum['fid'], $level + 1);
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

function subforums_option($fid, $level, $selected_id = 0, $self_id = 0)
{
	global $subforums, $forum_url, $forum_count, $sef_friendly_names, $subforums_tab;

	$s = '';
	if (!empty($subforums[$fid])) {
		foreach ($subforums[$fid] as $cur_subforum) {
			$sef_friendly_names[$cur_subforum['fid']] = sef_friendly($cur_subforum['forum_name']);
			$redirect_tag = ($cur_subforum['redirect_url'] != '') ? ' &gt;&gt;&gt;' : '';
			$s .= subforums_padleft($subforums_tab, "\t") .
				'<option value="' . $cur_subforum['fid'] . '"' .
				($selected_id == $cur_subforum['fid'] ? ' selected="selected"' : '') .
				($cur_subforum['fid'] == $self_id ? ' class="option-subforums-self"' : '') .
				'>' . subforums_padleft($level, '&nbsp;&nbsp;') . '↳ ' .
				forum_htmlencode($cur_subforum['forum_name']) . $redirect_tag .
				'</option>';
			$forum_count++;
			$s .= subforums_option($cur_subforum['fid'], $level + 1, $selected_id, $self_id);
		}
	}
	return $s;
}

function subforums_get()
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
	$subforums = array();
	while ($sub_forum = $forum_db->fetch_assoc($sub_result)) {
		$subforums[$sub_forum['parent_id']][] = $sub_forum;
	}
	return $subforums;
}

function subforums_add_crumbs($fid)
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

function subforums_get_cat($fid)
{
	global $subforums_marked, $subforums;

	if (!empty($subforums[$fid])) {
		foreach ($subforums[$fid] as $cur_subforum) {
			if (!isset($subforums_marked[$cur_subforum['fid']])) {
				$subforums_marked[$cur_subforum['fid']] = $cur_subforum['fid'];
				subforums_get_cat($cur_subforum['fid']);
			}
		}
	}
}

function subforums_tree($fid)
{
	global $subforums;

	$tree = array();
	$tree[] = $fid;
	if (!empty($subforums[$fid])) {
		foreach ($subforums[$fid] as $cur_subforum) {
			$tree += subforums_tree($cur_subforum['fid']);
		}
	}
	return $tree;
}

?>
