<?php
/**
 *
 * QUAX displayName Extension. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2018, Andreas Kotes, https://github.com/berlincount/
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace QUAX\displayName\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\VarDumper\VarDumper;

/**
 * QUAX displayName Extension Event listener.
 */
class listener implements EventSubscriberInterface
{

        /** @var \phpbb\user */
        protected $user;

        public function __construct(
                \phpbb\user $user)
        {
                $this->user = $user;
        }

        // subscribe to all events where we need to rewrite data
        static public function getSubscribedEvents()
        {
          return array(
            'core.modify_username_string' => 'modify_username_string',
            'core.modify_text_for_display_before' => 'modify_text_for_display_before',
            'mobiquo.createMessage' => 'mobiquo_createMessage',
            'mobiquo.getDisplayName' => 'mobiquo_getDisplayName',
            'mobiquo.getMessage' => 'mobiquo_getMessage',
            'mobiquo.getBox' => 'mobiquo_getBox',
            'mobiquo.searchUser' => 'mobiquo_searchUser',
            'mobiquo.searchUser.post' => 'mobiquo_searchUser_post',
          );
        }

        // get displayname from profile fields
        static public function getDisplayName($user_id) {
            global $phpbb_container;

            /* @var $cp \phpbb\profilefields\manager */
            $cp = $phpbb_container->get('profilefields.manager');
            $profile_fields = $cp->grab_profile_fields_data($user_id);

            if (array_key_exists($user_id, $profile_fields) &&
                array_key_exists('displayname', $profile_fields[$user_id]) &&
                !empty($profile_fields[$user_id]['displayname']['value'])
            ) {
              return $profile_fields[$user_id]['displayname']['value'];
            }

            return NULL;
        }

        // get user id from username
        static public function getUserId($username) {
            global $phpbb_root_path, $phpEx;

            if (!function_exists('user_get_id_name')) {
              include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
            }

            $ids = array();
            $names = array($username);
            if (user_get_id_name($ids, $names) === false) {
                return($ids[0]);
            } else {
                return NULL;
            }
        }

        // override username display rendering (web display)
        public function modify_username_string($event) {
            $displayname = $this->getDisplayName($event['user_id']);

            if (!empty($displayname)) {
              $namelen = strlen($event['username']);
              if ($event['username_string'] == $event['username']) {
                $event['username_string'] = $displayname.' ('.$event['username_string'].')';
              } else if (substr($event['username_string'], -$namelen-4) === $event['username'].'</a>') {
                // strlen('</a>') == 4
                $event['username_string'] = substr($event['username_string'], 0, strlen($event['username_string'])-$namelen-4).$displayname.' ('.$event['username'].')</a>';
              } else if (substr($event['username_string'], -$namelen-7) === $event['username'].'</span>') {
                // strlen('</span>') == 7
                $event['username_string'] = substr($event['username_string'], 0, strlen($event['username_string'])-$namelen-7).$displayname.' ('.$event['username'].')</span>';
              }
            }
        }

        // override usernames in quote headers inside posts (web display)
        public function modify_text_for_display_before($event) {
            $text = $event['text'];

            // replace all quotes
            $count = preg_match_all('%\<QUOTE author="([^"]+)">%', $text, $matches, PREG_SET_ORDER);
            foreach ($matches as $key => $match) {
              $userId = $this->getUserId($match[1]);
              if (!is_null($userId)) {
                $displayname = $this->getDisplayName($userId);
                if (!empty($displayname)) {
                  $text = str_replace($match[0], '<QUOTE author="'.$displayname.' ('.$match[1].')">', $text);
                }
              }
            }

            // storage updated message
            $event['text'] = $text;
        }

        // remove displayname from username field before posting message
        public function mobiquo_createMessage($event) {
            $message = $event['message'];

            foreach ($message->usernames as $key => $value) {
                if (preg_match('%\(([^\)]+)\)$%', $value, $matches)) {
                   $username = $matches[1];
                   $userId = $this->getUserId($username);
                   if (!is_null($userId)) {
                     $displayname = $this->getDisplayName($userId);
                     if (!empty($displayname) && ($value == $displayname.' ('.$username.')')) {
                       $message->usernames[$key] = $username;
                     }
                   }
                }
            }

            // storage updated message
            $event['message'] = $message;
        }

        // override username display rendering (tapatalk)
        public function mobiquo_getDisplayName($event) {
            $displayname = $this->getDisplayName($event['userId']);

            if (!empty($displayname)) {
              $event['displayName'] = $displayname.' ('.$event['displayName'].')';
            }
        }

        // override usernames in PM headers and quote blocks (tapatalk)
        public function mobiquo_getMessage($event) {
            $message = $event['message'];

            // replace the 'msg_from' id
            $displayname = $this->getDisplayName($message['msg_from_id']);

            if (!empty($displayname)) {
              $message['msg_from'] = $displayname.' ('.$message['msg_from'].')';
            }

            // replace all quotes
            $count = preg_match_all('%\[quote uid=([0-9]+) name="([^"]+)"([^\]]*)\]%', $message['text_body'], $matches, PREG_SET_ORDER);
            foreach ($matches as $key => $match) {
              $displayname = $this->getDisplayName($match[1]);
              if (!empty($displayname)) {
                $message['text_body'] = str_replace($match[0], '[quote uid='.$match[1].' name="'.$displayname.' ('.$match[2].')"'.$match[3].']', $message['text_body']);
              }
            }

            // storage updated message
            $event['message'] = $message;
        }

        // override usernames in message boxes (tapatalk)
        public function mobiquo_getBox($event) {
            $box = $event['box'];
            $list = $box['list'];
            foreach ($list as $key => $message) {
                $displayname = $this->getDisplayName($message['msg_from_id']);

              if (!empty($displayname)) {
                  $message['msg_from'] = $displayname.' ('.$message['msg_from'].')';
                  $list[$key] = $message;
              }
            }
            $box['list'] = $list;
            $event['box'] = $box;
        }

        // add displayname based results to user search results (tapatalk)
        public function mobiquo_searchUser($event) {
            global $db;

            // collect userIds already found
            $userIds = array();
            foreach ($event['datas'] as $key => $value) {
              $userIds[] = $value->userId->oriValue;
            }

            // search for the user by realname
            $sql = "SELECT user_id FROM ".PROFILE_FIELDS_DATA_TABLE." WHERE LOWER(pf_displayname) LIKE LOWER('%".$db->sql_escape($event['keywords'])."%')";
            $sqlresult = $db->sql_query($sql);

            // collect all new userids
            while($row = $db->sql_fetchrow($sqlresult))
            {
                $userIds[] = $row['user_id'];
            }
            $db->sql_freeresult($sqlresult);

            // unify the array
            $userIds = array_unique($userIds);

            // convert to user objects
            $oMbqRdEtUser = \MbqMain::$oClk->newObj('MbqRdEtUser');
            $userObjects = $oMbqRdEtUser->getObjsMbqEtUser($userIds, array('case' => 'byUserIds'));

            // replace previous array completely
            $event['datas'] = $userObjects;

            $event['totalNum'] = sizeof($event['datas']);
        }

        // reformat usernames in list for proper search result rendering
        public function mobiquo_searchUser_post($event) {
            $list = $event['list'];
            foreach ($list as $key => $value) {
              if (preg_match('%^(.*) \(([^\)]+)\)$%', $value['user_name'], $matches)) {
                $value['user_name'] = $matches[2].' - '.$matches[1];
                $list[$key] = $value;
              }
            }

            // store updated list
            $event['list'] = $list;
        }
}
