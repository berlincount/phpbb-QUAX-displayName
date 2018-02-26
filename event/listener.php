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
                    'core.viewtopic_cache_user_data'	=> 'viewtopic_cache_user_data',
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

	/**
	 *
	 * @param \phpbb\event\data	$event	Event object
         */
	public function viewtopic_cache_user_data($event)
        {
            $displayname = $this->getDisplayName($event['poster_id']);

            if (!is_null($displayname)) {
              // get user_cache_data
              $user_cache_data = $event['user_cache_data'];
              $namelen = strlen($user_cache_data['username']);
              // strlen('</a>') == 4
              if (substr($user_cache_data['author_full'], -$namelen-4) === $user_cache_data['author_username'].'</a>') {
                $user_cache_data['author_full'] = $displayname.' ('.$user_cache_data['author_full'].')';
                $event['user_cache_data'] = $user_cache_data;
              }
            }
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
