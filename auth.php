<?php
/**
 * DokuWiki Plugin authjoomlasso (Auth Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <dokuwiki@cosmocode.de>
 * @author  Stephan Thamm <stephan@innovailable.eu>
 */

/**
 * Class auth_plugin_authjoomla
 *
 */
class auth_plugin_authjoomlasso extends auth_plugin_authpdo
{

    /** @inheritdoc */
    public function __construct()
    {
        $this->initializeConfiguration();
        parent::__construct(); // PDO setup

        $this->cando['external'] = true;
		$this->cando['logout'] = false;
    }

    public function trustExternal($user, $pass, $sticky = true) {
        if ($user) {
            msg("This auth plugin does not support manual login");
        }

        return $this->loginWithCookie($this->getConf('backendcookie')) || $this->loginWithCookie($this->getConf('frontendcookie'));
    }

    protected function timedOut($now, $start) {
        global $conf;

        $timeout = $conf['auth_security_timeout'];

        if (empty($timeout)) return false;
        if ($timeout <= 0) return true;

        return $now > $start + $timeout;
    }

    protected function loginWithCookie($cookie_name) {
        global $USERINFO;

        if (empty($cookie_name)) return false;

        $session = $_COOKIE[$cookie_name];
        if (empty($session)) return false;

        $sql = $this->getConf('select-session');
        $result = $this->_query($sql, ['session' => $session]);
        if ($result === false) return false;

        $user = $result[0]['user'];

        if (empty($user)) return false;

        $now = time();

        if ($_SESSION[DOKU_COOKIE]['auth']['user'] == $user && !$this->timedOut($now, $_SESSION[DOKU_COOKIE]['auth']['time'])) {
			$USERINFO = $_SESSION[DOKU_COOKIE]['auth']['info'];
			$_SERVER['REMOTE_USER'] = $user;
            return true;
        } else {
            $data = $this->getUserData($user, true);

            if ($data == false) return false;

			$USERINFO['name'] = $data['name'];
			$USERINFO['mail'] = $data['mail'];
			$USERINFO['grps'] = $data['grps'];

			$_SERVER['REMOTE_USER'] = $user;
			$_SESSION[DOKU_COOKIE]['auth']['user'] = $user;
			$_SESSION[DOKU_COOKIE]['auth']['info'] = $USERINFO;
			$_SESSION[DOKU_COOKIE]['auth']['time'] = $now;

            return true;
        }
    }

    /**
     * Initialize database configuration
     */
    protected function initializeConfiguration()
    {
        $prefix = $this->getConf('tableprefix');

        /** @noinspection SqlNoDataSourceInspection */
        /** @noinspection SqlResolve */
        $this->conf['select-user'] = '
            SELECT `id` AS `uid`,
                   `username` AS `user`,
                   `name` AS `name`,
                   `password` AS `hash`,
                   `email` AS `mail`
              FROM `' . $prefix . 'users`
             WHERE `username` = :user
               AND `block` = 0
               AND `activation` = 0        
        ';

        /** @noinspection SqlNoDataSourceInspection */
        /** @noinspection SqlResolve */
        $this->conf['select-user-groups'] = '
            SELECT
              p.id AS `gid`,
              (
                SELECT GROUP_CONCAT(xp.`title` ORDER BY xp.`lft` SEPARATOR \'/\')
                  FROM `' . $prefix . 'usergroups` AS xp
                WHERE p.`lft` BETWEEN xp.`lft` AND xp.`rgt`
              ) AS `group`
              FROM `' . $prefix . 'user_usergroup_map` AS m,
                   `' . $prefix . 'usergroups` AS g,
                   `' . $prefix . 'usergroups` AS p
             WHERE m.`user_id`  = :uid
               AND g.`id` = m.`group_id`
               AND p.`lft` <= g.`lft`
               AND p.`rgt` >= g.`rgt`
        ';

        /** @noinspection SqlNoDataSourceInspection */
        /** @noinspection SqlResolve */
        $this->conf['select-groups'] = '
            SELECT n.id AS `gid`, GROUP_CONCAT(p.`title` ORDER BY p.lft SEPARATOR \'/\') as `group`
              FROM `' . $prefix . 'usergroups` AS n, `' . $prefix . 'usergroups` AS p
             WHERE n.lft BETWEEN p.lft AND p.rgt
          GROUP BY n.id
          ORDER BY n.id
        ';

        /** @noinspection SqlNoDataSourceInspection */
        /** @noinspection SqlResolve */
        $this->conf['select-session'] = '
            SELECT s.`username` as `user`
              FROM `' . $prefix . 'session` AS s,
                   `' . $prefix . 'users` AS u
             WHERE s.`session_id` = :session
               AND s.`userid` = u.`id`
               AND `block` = 0
               AND `activation` = 0
        ';
    }

    /**
     * Sets up the language strings
     *
     * Needed to inherit from the parent class. It's abit ugly but currently no better way exists.
     */
    public function setupLocale()
    {
        if ($this->localised) return;
        global $conf;

        // load authpdo language files
        /** @var array $lang is loaded by include */
        $path = DOKU_PLUGIN . 'authpdo/lang/';
        @include($path . 'en/lang.php');
        if ($conf['lang'] != 'en') @include($path . $conf['lang'] . '/lang.php');
        $pdolang = $lang;

        // load our authloomla language files and config overrides
        parent::setupLocale();

        // merge them both
        $this->lang = array_merge($this->lang, $pdolang);
    }

    /** @inheritdoc */
    public function isCaseSensitive()
    {
        return false;
    }


}

// vim:ts=4:sw=4:et:
