<?php

if (!defined('FORUM')) {
    die();
}


class Subforums
{
    public $lang;
    private $fid;
    private $tree = [];
    private $cat = false;
    private $option_count = 0;
    private $list;

    function __construct()
    {
        global $forum_id, $ext_info, $forum_user;

        // Load extension language file
        $langDir = $ext_info['path'] . '/lang/';
        $langFile = $langDir . $forum_user['language'] . '/' . $ext_info['id'] . '.php';
        if (file_exists($langFile)) {
            require $langFile;
        } else {
            require $langDir . '/English/' . $ext_info['id'] . '.php';
        }

        /** @var string[] $lang_xn_subforums */
        $this->lang = $lang_xn_subforums;
        $this->fid = $forum_id;

        $this->list = $this->get_list();
    }

    /**
     * @param int $n
     * @param string $char
     * @return string
     */
    private function padleft($n, $char)
    {
        $s = '';
        for ($i = 0; $i < $n; $i++) {
            $s .= $char;
        }

        return $s;
    }

    /**
     * @return array
     */
    private function get_list()
    {
        global $forum_db, $forum_user;

        // Select all subforums
        $subQuery = [
            'SELECT' => 'c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc,'
                . ' f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post,'
                . ' f.last_post_id, f.last_poster, f.parent_id',
            'FROM' => 'categories AS c',
            'JOINS' => [
                [
                    'INNER JOIN' => 'forums AS f',
                    'ON' => 'c.id = f.cat_id',
                ],
                [
                    'LEFT JOIN' => 'forum_perms AS fp',
                    'ON' => '(fp.forum_id = f.id AND fp.group_id = ' . $forum_user['g_id'] . ')',
                ],
            ],
            'WHERE' => '(fp.read_forum IS NULL OR fp.read_forum = 1) AND f.parent_id > 0',
            'ORDER BY' => 'c.disp_position, c.id, f.disp_position',
        ];

        $subResult = $forum_db->query_build($subQuery) or error(__FILE__, __LINE__);

        // Generate array of subforums
        $list = [];
        while ($subForum = $forum_db->fetch_assoc($subResult)) {
            $list[$subForum['parent_id']][] = $subForum;
        }

        return $list;
    }

    /**
     * @param $fid
     * @param $level
     * @return string
     */
    public function get_index_desc($fid, $level)
    {
        global $forum_url, $forum_config;

        $s = '';
        if (!empty($this->list[$fid])) {
            $title = count($this->list[$fid]) == 1
                ? $this->lang['Subforum']
                : $this->lang['Subforums'];
            $s = '<dl class="index-subforums index-subforums-level' . $level . '">' .
                '<dt>' . $title . ':</dt>';
            foreach ($this->list[$fid] as $curSubforum) {
                $link = forum_link($forum_url['forum'], [
                    $curSubforum['fid'],
                    sef_friendly($curSubforum['forum_name']),
                ]);
                $desc = $this->get_index_desc($curSubforum['fid'], $level + 1);
                $descText = '';
                if ($forum_config['o_subforums_show_list'] == 2 &&
                    $curSubforum['forum_desc'] != ''
                ) {
                    $descText = '&nbsp;&mdash; ' . $curSubforum['forum_desc'];
                }
                $s .= '<dd><a href="' . $link . '">' . $curSubforum['forum_name'] .
                    '</a>' . $desc . $descText . '</dd>';
            }
            $s .= '</dl>';
        }

        return $s;
    }

    /**
     * @return void
     */
    public function clear_option_count()
    {
        $this->option_count = 0;
    }

    /**
     * @param $fid
     * @param $level
     * @param int $selected_id
     * @param int $self_id
     * @return string
     */
    public function get_option($fid, $level, $selected_id = 0, $self_id = 0)
    {
        global $sef_friendly_names;

        $s = '';
        if (!empty($this->list[$fid])) {
            foreach ($this->list[$fid] as $curSubforum) {
                $curFid = $curSubforum['fid'];
                $sef_friendly_names[$curFid] = sef_friendly($curSubforum['forum_name']);
                $redirectTag = ($curSubforum['redirect_url'] != '') ? ' &gt;&gt;&gt;' : '';
                $s .= '<option value="' . $curSubforum['fid'] . '"' .
                    ($curFid == $selected_id ? ' selected="selected"' : '') .
                    ($curFid == $self_id ? ' class="option-subforums-self"' : '') .
                    '>' . $this->padleft($level, '&nbsp;&nbsp;') . '↳ ' .
                    forum_htmlencode($curSubforum['forum_name']) . $redirectTag .
                    '</option>';
                $this->option_count++;
                $s .= $this->get_option($curSubforum['fid'], $level + 1, $selected_id, $self_id);
            }
        }

        return $s;
    }

    /**
     * @param int $fid
     */
    public function add_crumbs($fid)
    {
        global $forum_db, $forum_page, $forum_url;

        $subforumsFirstCrumb = array_shift($forum_page['crumbs']);
        $subforumsQuery = [
            'SELECT' => 'f.id AS fid, f.forum_name, f.parent_id',
            'FROM' => 'forums AS f',
        ];

        $subforumsResult = $forum_db->query_build($subforumsQuery) or error(__FILE__, __LINE__);
        while ($curParentForum = $forum_db->fetch_assoc($subforumsResult)) {
            $parent_forums[$curParentForum['fid']] = $curParentForum;
        }

        $subforumsMarked = [];
        $subforumsMarked[$fid] = $fid;
        $curFid = $parent_forums[$fid]['parent_id'];
        while ($curFid && !isset($subforumsMarked[$curFid])) {
            array_unshift($forum_page['crumbs'], [
                $parent_forums[$curFid]['forum_name'],
                forum_link($forum_url['forum'], [
                    $curFid,
                    sef_friendly($parent_forums[$curFid]['forum_name']),
                ]),
            ]);

            // Mark current forum as already used (protect cycles)
            $subforumsMarked[$curFid] = $curFid;
            // Get next parent level
            $curFid = $parent_forums[$curFid]['parent_id'];
        }
        if (isset($subforumsMarked[$curFid])) {
            array_unshift($forum_page['crumbs'], '…');
        }
        array_unshift($forum_page['crumbs'], $subforumsFirstCrumb);
    }

    /**
     * @param int $fid
     * @return void
     */
    private function get_subcat($fid)
    {
        global $subforums_marked;

        if (!empty($this->list[$fid])) {
            foreach ($this->list[$fid] as $curSubforum) {
                if (!isset($subforums_marked[$curSubforum['fid']])) {
                    $subforums_marked[$curSubforum['fid']] = $curSubforum['fid'];
                    $this->get_cat($curSubforum['fid']);
                }
            }
        }
    }

    /**
     * @return mixed
     */
    public function get_cat()
    {
        if (!$this->cat) {
            $this->cat = $this->get_subcat($this->fid);
        }

        return $this->cat;
    }

    /**
     * @param int $fid
     * @return array
     */
    public function get_tree($fid)
    {
        if (!isset($this->tree[$fid])) {
            $this->tree[$fid] = [];
            $this->tree[$fid][] = $fid;
            if (!empty($this->list[$fid])) {
                foreach ($this->list[$fid] as $cur_subforum) {
                    $this->tree[$fid] = array_merge(
                        $this->tree[$fid],
                        $this->get_tree($cur_subforum['fid'])
                    );
                }
            }
        }

        return $this->tree[$fid];
    }

    /**
     * @param array $query
     */
    public function multilevel_query(&$query)
    {
        $query['JOINS'][] = [
            'LEFT JOIN' => 'forums AS f2',
            'ON' => 'f.parent_id = f2.id',
        ];
        $query['JOINS'][] = [
            'LEFT JOIN' => 'forums AS f3',
            'ON' => 'f2.parent_id = f3.id',
        ];
        $query['JOINS'][] = [
            'LEFT JOIN' => 'forums AS f4',
            'ON' => 'f3.parent_id = f4.id',
        ];

        $subLevel = "IF(f2.id IS NULL, 0, 2) + IF(f3.id IS NULL, 0, 2) + IF(f4.id IS NULL, 0, 2)";
        $forumName = "CONCAT(LPAD('', $subLevel, ' '), '↳ ', f.forum_name))";
        $query['SELECT'] = str_replace(
            'f.forum_name',
            "IF(f2.id IS NULL, f.forum_name, $forumName AS forum_name",
            $query['SELECT']
        );
        $query['SELECT'] .= ',
            IFNULL(f4.disp_position, IFNULL(f3.disp_position, IFNULL(f2.disp_position, f.disp_position))) * 1000000000 +
            IF(f4.disp_position IS NOT NULL, f3.disp_position,
                IF(f3.disp_position IS NOT NULL, f2.disp_position,
                    IF(f2.disp_position IS NOT NULL, f.disp_position, 0))) * 1000000 +
            IF(f4.disp_position IS NOT NULL, f2.disp_position,
                IF(f3.disp_position IS NOT NULL, f.disp_position, 0)) * 1000 +
            IF(f4.disp_position IS NOT NULL, f.disp_position, 0)
            AS f_disp_position';

        $query['ORDER BY'] = str_replace('f.disp_position', 'f_disp_position', $query['ORDER BY']);
    }
}

$subforums = new Subforums();
