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

	static public function getSubscribedEvents()
	{
		return array(
                    'core.modify_username_string' => 'modify_username_string',
                    'core.modify_text_for_display_before' => 'modify_text_for_display_before',
                    'mobiquo.getDisplayName' => 'mobiquo_getDisplayName',
                    'mobiquo.getMessage' => 'mobiquo_getMessage',
                    'mobiquo.getBox' => 'mobiquo_getBox',
		);
	}

        static public function getDisplayName($user_id) {
            global $phpbb_container;

	    /* @var $cp \phpbb\profilefields\manager */
	    $cp = $phpbb_container->get('profilefields.manager');
            $profile_fields = $cp->grab_profile_fields_data($user_id);

            if (array_key_exists($user_id, $profile_fields) &&
                array_key_exists('displayname', $profile_fields[$user_id]) &&
                !is_null($profile_fields[$user_id]['displayname']['value'])
            ) {
              return $profile_fields[$user_id]['displayname']['value'];
            }

            return NULL;
        }

        public function modify_username_string($event) {

            $displayname = $this->getDisplayName($event['user_id']);
            if (!empty($displayname)) {
              $namelen = strlen($event['username']);
              if ($event['username_string'] == $event['username']) {
                $event['username_string'] = $displayname.' ('.$event['username_string'].')';
              } else if (substr($event['username_string'], -$namelen-4) === $event['username'].'</a>') {
                // strlen('</a>') == 4
                $event['username_string'] = $displayname.' ('.$event['username_string'].')';
              } else if (substr($event['username_string'], -$namelen-7) === $event['username'].'</span>') {
                // strlen('</span>') == 7
                $event['username_string'] = substr($event['username_string'], 0, strlen($event['username_string'])-$namelen-7).$displayname.' ('.$event['username'].')</span>';
              }
            }
        }

        public function modify_text_for_display_before($event) {
            $text = $event['text'];

            // replace all quotes
            $count = preg_match_all('%\<QUOTE author="([^"]+)">%', $text, $matches, PREG_SET_ORDER);
            foreach ($matches as $key => $match) {
              $ids = array();
              $names = array($match[1]);
              if (user_get_id_name($ids, $names) === false) {
                $displayname = $this->getDisplayName($ids[0]);
                if (!is_null($displayname)) {
                  $text = str_replace($match[0], '<QUOTE author="'.$displayname.' ('.$match[1].')">', $text);
                }
              }
            }

            // storage updated message
            $event['text'] = $text;
        }

        public function mobiquo_getDisplayName($event) {
            $displayname = $this->getDisplayName($event['userId']);

            if (!is_null($displayname)) {
              $event['displayName'] = $displayname.' ('.$event['displayName'].')';
            }
        }

        public function mobiquo_getMessage($event) {
            $message = $event['message'];

            // replace the 'msg_from' id
            $displayname = $this->getDisplayName($message['msg_from_id']);

            if (!is_null($displayname)) {
              $message['msg_from'] = $displayname.' ('.$message['msg_from'].')';
            }

            // replace all quotes
            $count = preg_match_all('%\[quote uid=([0-9]+) name="([^"]+)"([^\]]*)\]%', $message['text_body'], $matches, PREG_SET_ORDER);
            foreach ($matches as $key => $match) {
              $displayname = $this->getDisplayName($match[1]);
              if (!is_null($displayname)) {
                $message['text_body'] = str_replace($match[0], '[quote uid='.$match[1].' name="'.$displayname.' ('.$match[2].')"'.$match[3].']', $message['text_body']);
              }
            }

            // storage updated message
            $event['message'] = $message;
        }

        public function mobiquo_getBox($event) {
            $box = $event['box'];
            $list = $box['list'];
            foreach ($list as $key => $message) {
                $displayname = $this->getDisplayName($message['msg_from_id']);

              if (!is_null($displayname)) {
                  $message['msg_from'] = $displayname.' ('.$message['msg_from'].')';
                  $list[$key] = $message;
              }
            }
            $box['list'] = $list;
            $event['box'] = $box;
        }
}
