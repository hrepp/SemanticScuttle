<?php
class SemanticScuttle_Service_Bookmark2Tag extends SemanticScuttle_DbService
{
    /**
     * Returns the single service instance
     *
     * @param DB $db Database object
     *
     * @return SemanticScuttle_Service
     */
    public static function getInstance($db)
    {
        static $instance;
        if (!isset($instance)) {
            $instance = new self($db);
        }
        return $instance;
    }

    public function __construct($db)
    {
        $this->db = $db;
        $this->tablename = $GLOBALS['tableprefix'] .'bookmarks2tags';
    }

    function isNotSystemTag($var) {
        if (utf8_substr($var, 0, 7) == 'system:')
        return false;
        else
        return true;
    }

    /**
     * Attach tags to a bookmark.
     *
     * Make sure that categories is an array of trimmed strings.
     * If the categories are coming in from an API call, be sure
     * that underscores are converted into strings.
     *
     * @param integer $bookmarkid ID of the bookmark
     * @param array   $tags       Array of tags (strings, trimmed)
     * @param boolean $fromApi    If this is from an API call
     * @param string  $extension  File extension (i.e. 'pdf')
     * @param boolean $replace    If existing tags for this bookmark
     *                            are to be replaced
     * @param boolean $fromImport If this is from a file import
     *
     * @return boolean True if all went well
     */
    public function attachTags(
        $bookmarkid, $tags, $fromApi = false,
        $extension = null, $replace = true, $fromImport = false
    ) {
        if (!is_array($tags)) {
            $tags = trim($tags);
            if ($tags != '') {
                if (substr($tags, -1) == ',') {
                    $tags = substr($tags, 0, -1);
                }
                if ($fromApi) {
                    $tags = explode(' ', $tags);
                } else {
                    $tags = explode(',', $tags);
                }
            } else {
                $tags = null;
            }
        }

        $tagservice =SemanticScuttle_Service_Factory::get('Tag');
        $tags = $tagservice->normalize($tags);


        $tags_count = is_array($tags)?count($tags):0;

        for ($i = 0; $i < $tags_count; $i++) {
            $tags[$i] = trim(strtolower($tags[$i]));
            if ($fromApi) {
                include_once 'SemanticScuttle/functions.php';
                $tags[$i] = convertTag($tags[$i], 'in');
            }
        }

        if ($tags_count > 0) {
            // Remove system tags
            $tags = array_filter($tags, array($this, "isNotSystemTag"));

            // Eliminate any duplicate categories
            $temp = array_unique($tags);
            $tags = array_values($temp);
        } else {
            // Unfiled
            $tags[] = 'system:unfiled';
        }

        // Media and file types
        if (!is_null($extension)) {
            include_once 'SemanticScuttle/functions.php';

            if ($keys = multi_array_search($extension, $GLOBALS['filetypes'])) {
                $tags[] = 'system:filetype:'. $extension;
                $tags[] = 'system:media:'. array_shift($keys);
            }
        }

        // Imported
        if ($fromImport) {
            $tags[] = 'system:imported';
        }

        $this->db->sql_transaction('begin');

        if ($replace) {
            if (!$this->deleteTagsForBookmark($bookmarkid)){
                $this->db->sql_transaction('rollback');
                message_die(GENERAL_ERROR, 'Could not attach tags (deleting old ones failed)', '', __LINE__, __FILE__, $sql, $this->db);
                return false;
            }
        }

        $bs  = SemanticScuttle_Service_Factory::get('Bookmark');
        $tts = SemanticScuttle_Service_Factory::get('Tag2Tag');

        // Create links between tags
        foreach ($tags as $key => $tag) {
            if (strpos($tag, '=')) {
                // case "="
                $pieces = explode('=', $tag);
                $nbPieces = count($pieces);
                if ($nbPieces <= 1) {
                    continue;
                }
                for ($i = 0; $i < $nbPieces-1; $i++) {
                    $bookmark = $bs->getBookmark($bookmarkid);
                    $uId = $bookmark['uId'];
                    $tts->addLinkedTags($pieces[$i], $pieces[$i+1], '=', $uId);
                }
                // Attach just the last tag to the bookmark
                $tags[$key] = $pieces[0];
            } else {
                // case ">"
                $pieces   = explode('>', $tag);
                $nbPieces = count($pieces);
                if ($nbPieces <= 1) {
                    continue;
                }
                for ($i = 0; $i < $nbPieces-1; $i++) {
                    $bookmark = $bs->getBookmark($bookmarkid);
                    $uId = $bookmark['uId'];
                    $tts->addLinkedTags($pieces[$i], $pieces[$i+1], '>', $uId);
                }
                // Attach just the last tag to the bookmark
                $tags[$key] = $pieces[$nbPieces-1];
            }
        }

        //after exploding, there may be duplicate keys
        //since we are in a transaction, hasTag() may
        // not return true for newly added duplicate tags
        $tags = array_unique($tags);

        // Add the tags to the DB.
        foreach ($tags as $tag) {
            if ($tag == '') {
                continue;
            }
            if ($this->hasTag($bookmarkid, $tag)) {
                continue;
            }

            $values = array(
                'bId' => intval($bookmarkid),
                'tag' => $tag
            );

            $sql = 'INSERT INTO '. $this->getTableName()
                . ' ' . $this->db->sql_build_array('INSERT', $values);
            if (!($dbresult =& $this->db->sql_query($sql))) {
                $this->db->sql_transaction('rollback');
                message_die(
                    GENERAL_ERROR, 'Could not attach tags',
                    '', __LINE__, __FILE__, $sql, $this->db
                );
                return false;
            }
        }
        $this->db->sql_transaction('commit');
        return true;
    }

    function deleteTag($uId, $tag) {
        $bs =SemanticScuttle_Service_Factory::get('Bookmark');

        $query = 'DELETE FROM '. $this->getTableName();
        $query.= ' USING '. $this->getTableName() .', '. $bs->getTableName();
        $query.= ' WHERE '. $this->getTableName() .'.bId = '. $bs->getTableName() .'.bId';
        $query.= ' AND '. $bs->getTableName() .'.uId = '. $uId;
        $query.= ' AND '. $this->getTableName() .'.tag = "'. $this->db->sql_escape($tag) .'"';

        if (!($dbresult =& $this->db->sql_query($query))) {
            message_die(GENERAL_ERROR, 'Could not delete tags', '', __LINE__, __FILE__, $query, $this->db);
            return false;
        }

        return true;
    }

    function deleteTagsForBookmark($bookmarkid) {
        if (!is_int($bookmarkid)) {
            message_die(GENERAL_ERROR, 'Could not delete tags (invalid bookmarkid)', '', __LINE__, __FILE__, $query);
            return false;
        }

        $query = 'DELETE FROM '. $this->getTableName() .' WHERE bId = '. intval($bookmarkid);

        if (!($dbresult =& $this->db->sql_query($query))) {
            message_die(GENERAL_ERROR, 'Could not delete tags', '', __LINE__, __FILE__, $query, $this->db);
            return false;
        }

        return true;
    }

    /* Allow deletion in admin page */
    function deleteTagsForUser($uId) {
        $qmask = 'DELETE FROM %s USING %s, %s WHERE %s.bId = %s.bId AND %s.uId = %d';
        $query = sprintf($qmask,
        $this->getTableName(),
        $this->getTableName(),
        $GLOBALS['tableprefix'].'bookmarks',
        $this->getTableName(),
        $GLOBALS['tableprefix'].'bookmarks',
        $GLOBALS['tableprefix'].'bookmarks',
        $uId);

        if (!($dbresult =& $this->db->sql_query($query))) {
            message_die(GENERAL_ERROR, 'Could not delete tags', '', __LINE__, __FILE__, $query, $this->db);
            return false;
        }

        return true;
    }

    function &getTagsForBookmark($bookmarkid) {
        if (!is_numeric($bookmarkid)) {
            message_die(GENERAL_ERROR, 'Could not get tags (invalid bookmarkid)', '', __LINE__, __FILE__, $query);
            return false;
        }

        $query = 'SELECT tag FROM '. $this->getTableName() .' WHERE bId = '. intval($bookmarkid) .' AND LEFT(tag, 7) <> "system:" ORDER BY id ASC';

        if (!($dbresult =& $this->db->sql_query($query))) {
            message_die(GENERAL_ERROR, 'Could not get tags', '', __LINE__, __FILE__, $query, $this->db);
            return false;
        }

        $tags = array();
        while ($row =& $this->db->sql_fetchrow($dbresult)) {
            $tags[] = $row['tag'];
        }
        $this->db->sql_freeresult($dbresult);
        return $tags;
    }

    function &getTags($userid = NULL) {
        $userservice =SemanticScuttle_Service_Factory::get('User');
        $logged_on_user = $userservice->getCurrentUserId();

        $query = 'SELECT T.tag, COUNT(B.bId) AS bCount FROM '. $GLOBALS['tableprefix'] .'bookmarks AS B INNER JOIN '. $userservice->getTableName() .' AS U ON B.uId = U.'. $userservice->getFieldName('primary') .' INNER JOIN '. $GLOBALS['tableprefix'] .'bookmarks2tags AS T ON B.bId = T.bId';

        $conditions = array();
        if (!is_null($userid)) {
            $conditions['U.'. $userservice->getFieldName('primary')] = intval($userid);
            if ($logged_on_user != $userid)
            $conditions['B.bStatus'] = 0;
        } else {
            $conditions['B.bStatus'] = 0;
        }

        $query .= ' WHERE '. $this->db->sql_build_array('SELECT', $conditions) .' AND LEFT(T.tag, 7) <> "system:" GROUP BY T.tag ORDER BY bCount DESC, tag';

        if (!($dbresult =& $this->db->sql_query($query))) {
            message_die(GENERAL_ERROR, 'Could not get tags', '', __LINE__, __FILE__, $query, $this->db);
            return false;
        }

        $output = $this->db->sql_fetchrowset($dbresult);
        $this->db->sql_freeresult($dbresult);
        return $output;
    }


    // Returns the tags related to the specified tags; i.e. attached to the same bookmarks
    function &getRelatedTags($tags, $for_user = NULL, $logged_on_user = NULL, $limit = 10) {
        $conditions = array();
        // Only count the tags that are visible to the current user.
        if ($for_user != $logged_on_user || is_null($for_user))
        $conditions['B.bStatus'] = 0;

        if (!is_null($for_user))
        $conditions['B.uId'] = $for_user;

        // Set up the tags, if need be.
        if (is_numeric($tags))
        $tags = NULL;
        if (!is_array($tags) and !is_null($tags))
        $tags = explode('+', trim($tags));

        $tagcount = count($tags);
        for ($i = 0; $i < $tagcount; $i++) {
            $tags[$i] = trim($tags[$i]);
        }

        // Set up the SQL query.
        $query_1 = 'SELECT DISTINCTROW T0.tag, COUNT(B.bId) AS bCount FROM '. $GLOBALS['tableprefix'] .'bookmarks AS B, '. $this->getTableName() .' AS T0';
        $query_2 = '';
        $query_3 = ' WHERE B.bId = T0.bId ';
        if (count($conditions) > 0)
        $query_4 = ' AND '. $this->db->sql_build_array('SELECT', $conditions);
        else
        $query_4 = '';
        // Handle the parts of the query that depend on any tags that are present.
        for ($i = 1; $i <= $tagcount; $i++) {
            $query_2 .= ', '. $this->getTableName() .' AS T'. $i;
            $query_4 .= ' AND T'. $i .'.bId = B.bId AND T'. $i .'.tag = "'. $this->db->sql_escape($tags[$i - 1]) .'" AND T0.tag <> "'. $this->db->sql_escape($tags[$i - 1]) .'"';
        }
        $query_5 = ' AND LEFT(T0.tag, 7) <> "system:" GROUP BY T0.tag ORDER BY bCount DESC, T0.tag';
        $query = $query_1 . $query_2 . $query_3 . $query_4 . $query_5;

        if (! ($dbresult =& $this->db->sql_query_limit($query, $limit)) ){
            message_die(GENERAL_ERROR, 'Could not get related tags', '', __LINE__, __FILE__, $query, $this->db);
            return false;
        }
        $output = $this->db->sql_fetchrowset($dbresult);
        $this->db->sql_freeresult($dbresult);
        return $output;
    }

    // Returns the most popular tags used for a particular bookmark hash
    function &getRelatedTagsByHash($hash, $limit = 20) {
        $userservice = SemanticScuttle_Service_Factory :: get('User');
        $sId = $userservice->getCurrentUserId();
        // Logged in
        if ($userservice->isLoggedOn()) {
            $arrWatch = $userservice->getWatchList($sId);
            // From public bookmarks or user's own
            $privacy = ' AND ((B.bStatus = 0) OR (B.uId = '. $sId .')';
            // From shared bookmarks in watchlist
            foreach ($arrWatch as $w) {
                $privacy .= ' OR (B.uId = '. $w .' AND B.bStatus = 1)';
            }
            $privacy .= ') ';
            // Not logged in
        } else {
            $privacy = ' AND B.bStatus = 0 ';
        }

        $query = 'SELECT T.tag, COUNT(T.tag) AS bCount FROM '.$GLOBALS['tableprefix'].'bookmarks AS B LEFT JOIN '.$GLOBALS['tableprefix'].'bookmarks2tags AS T ON B.bId = T.bId WHERE B.bHash = "'. $hash .'" '. $privacy .'AND LEFT(T.tag, 7) <> "system:" GROUP BY T.tag ORDER BY bCount DESC';

        if (!($dbresult =& $this->db->sql_query_limit($query, $limit))) {
            message_die(GENERAL_ERROR, 'Could not get related tags for this hash', '', __LINE__, __FILE__, $query, $this->db);
            return false;
        }
        $output = $this->db->sql_fetchrowset($dbresult);
        $this->db->sql_freeresult($dbresult);
        return $output;
    }

    function &getAdminTags($limit = 30, $logged_on_user = NULL, $days = NULL) {
        // look for admin ids
        $userservice = SemanticScuttle_Service_Factory :: get('User');
        $adminIds = $userservice->getAdminIds();

        // ask for their tags
        return $this->getPopularTags($adminIds, $limit, $logged_on_user, $days);
    }

    function &getContactTags($user, $limit = 30, $logged_on_user = NULL, $days = NULL) {
        // look for contact ids
        $userservice = SemanticScuttle_Service_Factory :: get('User');
        $contacts = $userservice->getWatchlist($user);

        // add the user (to show him/her also his/her tags)
        if(!is_null($logged_on_user)) {
            $contacts[] = $logged_on_user;
        }

        // ask for their tags
        return $this->getPopularTags($contacts, $limit, $logged_on_user, $days);
    }

    // $users can be {NULL, an id, an array of id}
    function &getPopularTags($user = NULL, $limit = 30, $logged_on_user = NULL, $days = NULL) {
        // Only count the tags that are visible to the current user.
        if (($user != $logged_on_user) || is_null($user) || ($user === false))
        $privacy = ' AND B.bStatus = 0';
        else
        $privacy = '';

        if (is_null($days) || !is_int($days))
        $span = '';
        else
        $span = ' AND B.bDatetime > "'. date('Y-m-d H:i:s', time() - (86400 * $days)) .'"';

        $query = 'SELECT T.tag, COUNT(T.bId) AS bCount FROM '. $this->getTableName() .' AS T, '. $GLOBALS['tableprefix'] .'bookmarks AS B WHERE ';
        if (is_null($user) || ($user === false)) {
            $query .= 'B.bId = T.bId AND B.bStatus = 0';
        } elseif(is_array($user)) {
            $query .= ' (1 = 0';  //tricks
            foreach($user as $u) {
                $query .= ' OR B.uId = '. $this->db->sql_escape($u) .' AND B.bId = T.bId';
            }
            $query .= ' )';
        } else {
            $query .= 'B.uId = '. $this->db->sql_escape($user) .' AND B.bId = T.bId'. $privacy;
        }
        $query .= $span .' AND LEFT(T.tag, 7) <> "system:" GROUP BY T.tag ORDER BY bCount DESC, tag';

        if (!($dbresult =& $this->db->sql_query_limit($query, $limit))) {
            message_die(GENERAL_ERROR, 'Could not get popular tags', '', __LINE__, __FILE__, $query, $this->db);
            return false;
        }

        $output = $this->db->sql_fetchrowset($dbresult);
        $this->db->sql_freeresult($dbresult);
        return $output;
    }

    function hasTag($bookmarkid, $tag) {
        $query = 'SELECT COUNT(*) AS tCount FROM '. $this->getTableName() .' WHERE bId = '. intval($bookmarkid) .' AND tag ="'. $this->db->sql_escape($tag) .'"';

        if (! ($dbresult =& $this->db->sql_query($query)) ) {
            message_die(GENERAL_ERROR, 'Could not find tag', '', __LINE__, __FILE__, $query, $this->db);
            return false;
        }

        if ($row =& $this->db->sql_fetchrow($dbresult)) {
            if ($row['tCount'] > 0) {
                $output = true;
            }
        }
        $output = false;
        $this->db->sql_freeresult($dbresult);
        return $output;
    }

    function renameTag($userid, $old, $new, $fromApi = false) {
        $bookmarkservice =SemanticScuttle_Service_Factory::get('Bookmark');
        $tagservice =SemanticScuttle_Service_Factory::get('Tag');

        if (is_null($userid) || is_null($old) || is_null($new))
        return false;

        // Find bookmarks with old tag
        $bookmarksInfo =& $bookmarkservice->getBookmarks(0, NULL, $userid, $old);
        $bookmarks =& $bookmarksInfo['bookmarks'];

        // Delete old tag
        $this->deleteTag($userid, $old);

        // Attach new tags
        $new = $tagservice->normalize($new);

        foreach(array_keys($bookmarks) as $key) {
            $row =& $bookmarks[$key];
            $this->attachTags($row['bId'], $new, $fromApi, NULL, false);
        }

        return true;
    }

    function &tagCloud($tags = NULL, $steps = 5, $sizemin = 90, $sizemax = 225, $sortOrder = NULL) {

        if (is_null($tags) || count($tags) < 1) {
            $output = false;
            return $output;
        }

        $min = $tags[count($tags) - 1]['bCount'];
        $max = $tags[0]['bCount'];

        for ($i = 1; $i <= $steps; $i++) {
            $delta = ($max - $min) / (2 * $steps - $i);
            $limit[$i] = $i * $delta + $min;
        }
        $sizestep = ($sizemax - $sizemin) / $steps;
        foreach ($tags as $row) {
            $next = false;
            for ($i = 1; $i <= $steps; $i++) {
                if (!$next && $row['bCount'] <= $limit[$i]) {
                    $size = $sizestep * ($i - 1) + $sizemin;
                    $next = true;
                }
            }
            $tempArray = array('size' => $size .'%');
            $row = array_merge($row, $tempArray);
            $output[] = $row;
        }

        if ($sortOrder == 'alphabet_asc') {
            usort($output, create_function('$a,$b','return strcasecmp(utf8_deaccent($a["tag"]), utf8_deaccent($b["tag"]));'));
        }

        return $output;
    }

    function deleteAll() {
        $query = 'TRUNCATE TABLE `'. $this->getTableName() .'`';
        $this->db->sql_query($query);
    }

}
?>