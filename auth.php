<?php
/**
 * DokuWiki Plugin authjoomla (Auth Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <dokuwiki@cosmocode.de>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

class auth_plugin_authjoomla extends auth_plugin_authpdo
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->initializeConfiguration();
        parent::__construct(); // PDO setup
        $this->ssoByCookie();
    }

    public function checkPass($user, $pass)
    {
        // username already set by SSO
        if ($_SERVER['REMOTE_USER'] &&
            $_SERVER['REMOTE_USER'] == $user &&
            !empty($this->getConf('cookiename'))
        ) return true;

        return parent::checkPass($user, $pass); // TODO: Change the autogenerated stub
    }


    protected function ssoByCookie()
    {
        global $INPUT;

        if (!empty($_COOKIE[DOKU_COOKIE])) return; // DokuWiki auth cookie found
        if (empty($_COOKIE['joomla_user_state'])) return;
        if ($_COOKIE['joomla_user_state'] !== 'logged_in') return;
        if (empty($this->getConf('cookiename'))) return;
        if (empty($_COOKIE[$this->getConf('cookiename')])) return;

        // check session in Joomla DB
        $session = $_COOKIE[$this->getConf('cookiename')];
        $sql = $this->getConf('select-session');
        $result = $this->_query($sql, ['session' => $session]);
        if ($result === false) return;

        // force login
        $_SERVER['REMOTE_USER'] = $result[0]['user'];
        $INPUT->set('u', $_SERVER['REMOTE_USER']);
        $INPUT->set('p', 'sso_only');
    }

    public function logOff()
    {
        parent::logOff();
        setcookie('joomla_user_state', '', time() - 3600, '/');
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
            SELECT g.`title` AS `group`
              FROM `' . $prefix . 'user_usergroup_map` AS m,
                   `' . $prefix . 'usergroups` AS g
             WHERE m.`group_id` = g.`id`
               AND m.`user_id` = :uid
        ';

        #FIXME we probably want to limit the time here
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
}

// vim:ts=4:sw=4:et:
