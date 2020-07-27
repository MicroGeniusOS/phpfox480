<?php
/**
 * [PHPFOX_HEADER]
 */

defined('PHPFOX') or exit('NO DICE!');

use MediaEmbed\MediaEmbed;

/**
 *
 *
 * @copyright       [PHPFOX_COPYRIGHT]
 * @author          phpFox LLC
 * @package         Phpfox_Service
 * @version         $Id: link.class.php 7240 2014-03-31 15:22:15Z Fern $
 */
class Link_Service_Link extends Phpfox_Service
{
    /**
     * Class constructor
     */

    /**
     * @var string
     */
    private $_sYouTubeApiKey = 'AIzaSyA-pIQldPRcIDyKk_xe5Fl9YIkGhF-B7os';
    private $_sTwitterTokenUrl = 'https://api.twitter.com/oauth2/token';
    private $_sTwitterTweetsUrl = 'https://api.twitter.com/labs/2/tweets';
    private $_sTwitterUsersUrl = 'https://api.twitter.com/labs/2/users';
    private $_sTwitterApiKey = 'kHpFx7kigbz8htvQ3cPc9PXqC';
    private $_sTwitterSecretKey = '21W7tzWW3AfkVnQjXmlvJhDxaTaxVKAR0BHHqJMzbGKCQB8GS4';

    public $_sTable;

    public function __construct()
    {
        $this->_sTable = Phpfox::getT('link');
        $sYTApi = Phpfox::getParam('link.youtube_data_api_key');
        $sTTApi = Phpfox::getParam('link.twitter_data_api_key');
        $sTTSecret = Phpfox::getParam('link.twitter_data_secret_key');
        if (!empty($sYTApi) && mb_strlen($sYTApi) > 12) {
            $this->_sYouTubeApiKey = $sYTApi;
        }
        if (!empty($sTTApi)) {
            $this->_sTwitterApiKey = $sTTApi;
        }
        if (!empty($sTTSecret)) {
            $this->_sTwitterSecretKey = $sTTSecret;
        }
    }

    private function parseTwitterUrl($url)
    {
        $aReturn = false;
        $aReturnDefault = [
            'title'       => 'Login on Twitter',
            'description' => 'Welcome back to Twitter. Sign in now to check your notifications, join the conversation and catch up on Tweets from the people you follow.',
            'image'       => 'https://abs.twimg.com/responsive-web/web/icon-ios.8ea219d4.png',
        ];
        $pattern = '|https?://(www\.)?twitter\.com|';
        if (preg_match($pattern, $url, $matches)) {
            $patternStatus = '|https?://(www\.)?twitter\.com/(?:\#!/)?(\w+)/status(es)?/(\d+)|';
            $patternUser = '|https?://(www\.)?twitter\.com/(#!/)?@?([^/]*)|';
            if (extension_loaded('curl') && function_exists('curl_init')) {
                $arr = [
                    'grant_type' => 'client_credentials'
                ];
                $data_string = http_build_query($arr);

                $hCurl = curl_init();
                curl_setopt($hCurl, CURLOPT_URL, $this->_sTwitterTokenUrl);
                curl_setopt($hCurl, CURLOPT_HEADER, false);
                curl_setopt($hCurl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded;charset=UTF-8']);
                curl_setopt($hCurl, CURLOPT_USERPWD, $this->_sTwitterApiKey . ":" . $this->_sTwitterSecretKey);
                curl_setopt($hCurl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($hCurl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($hCurl, CURLOPT_POST, true);
                curl_setopt($hCurl, CURLOPT_POSTFIELDS, $data_string);

                // Run the exec
                $sData = curl_exec($hCurl);

                // Close the curl connection
                curl_close($hCurl);

                $oData = json_decode($sData);
                if (!empty($oData->access_token)) {
                    $token = $oData->access_token;
                    $reqUrl = $id = $userName = '';
                    if (preg_match($patternStatus, $url, $matches)) {
                        if (!empty($matches[4])) {
                            $id = $matches[4];
                            $reqUrl = $this->_sTwitterTweetsUrl . '/' . $id . '?expansions=attachments.media_keys,author_id&media.fields=url';
                        }
                    } else if (preg_match($patternUser, $url, $matches)) {
                        if (!empty($matches[3])) {
                            $userName = $matches[3];
                            $reqUrl = $this->_sTwitterUsersUrl . '/by?usernames=' . $userName . '&user.fields=profile_image_url,description';
                        }
                    }

                    if (!empty($reqUrl)) {
                        $hCurl = curl_init();
                        curl_setopt($hCurl, CURLOPT_URL, $reqUrl);
                        curl_setopt($hCurl, CURLOPT_HEADER, false);
                        curl_setopt($hCurl, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
                        curl_setopt($hCurl, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($hCurl, CURLOPT_SSL_VERIFYPEER, false);

                        // Run the exec
                        $sData = curl_exec($hCurl);

                        // Close the curl connection
                        curl_close($hCurl);

                        $oData = json_decode($sData);
                        if (!isset($oData->data)) {
                            return $aReturnDefault;
                        }

                        if (!empty($id)) {
                            $image = isset($oData->includes->media[0]) ? $oData->includes->media[0]->url : '';
                            $title = isset($oData->includes->users[0]) ? $oData->includes->users[0]->name : '';
                            if($title) {
                                $title .= ' on Twitter';
                            }
                            $aReturn = [
                                'title'       => $title,
                                'description' => $oData->data->text,
                                'image'       => $image,
                            ];
                        } else if (!empty($userName)) {
                            if (!isset($oData->data[0])) {
                                return $aReturnDefault;
                            }
                            $image = str_replace('_normal', '', $oData->data[0]->profile_image_url);
                            $aReturn = [
                                'title'       => $oData->data[0]->name,
                                'description' => $oData->data[0]->description,
                                'image'       => $image,
                            ];
                        }
                    }
                }
            } else {
                return $aReturnDefault;
            }
        }
        return $aReturn;
    }

    private function parseInstagramUrl($url)
    {
        $aReturn = false;
        $pattern = '/(https?:\/\/www\.)?instagram\.com(\/p\/\w+\/?)/';
        if (preg_match($pattern, $url, $match)) {
            $json = fox_get_contents('https://api.instagram.com/oembed/?url=' . $url);
            $oInstagramData = json_decode($json);
            $aReturn = [
                'title' => $oInstagramData->title,
                'image' => $oInstagramData->thumbnail_url,
            ];

        }
        return $aReturn;
    }

    private function parseYouTubeUrl($url)
    {
        $aReturn = false;
        $pattern = '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i';
        if (preg_match($pattern, $url, $match)) {
            $json = fox_get_contents('https://www.googleapis.com/youtube/v3/videos?id=' . $match[1] . '&key=' . $this->_sYouTubeApiKey . '&part=snippet,contentDetails');
            $oYTData = json_decode($json);
            $start = new DateTime('@0'); // Unix epoch
            //Not a valid youtube url
            if (!isset($oYTData->items[0])) {
                return false;
            }
            $start->add(new DateInterval($oYTData->items[0]->contentDetails->duration));
            $duration = $start->format('H') * 60 * 60 + $start->format('i') * 60 + $start->format('s');
            $aReturn = [
                'title'       => $oYTData->items[0]->snippet->title,
                'image'       => $oYTData->items[0]->snippet->thumbnails->maxres->url,
                'description' => $oYTData->items[0]->snippet->description,
                'duration'    => sprintf("%s", $duration),
                'embed'       => '<iframe src="//www.youtube.com/embed/' . $oYTData->items[0]->id . '?wmode=transparent" type="text/html" width="480" height="295" frameborder="0" allowfullscreen></iframe>'
            ];

        }
        return $aReturn;
    }

    public function getLink($sUrl)
    {
        if (substr($sUrl, 0, 7) != 'http://' && substr($sUrl, 0, 8) != 'https://') {
            $sUrl = 'http://' . $sUrl;
        }

        $aParts = parse_url($sUrl);
        if (!isset($aParts['host'])) {
            return Phpfox_Error::set(_p('not_a_valid_link'), true);
        }

        $host = $aParts['host'];
        $image = $embed = $title = $description = $duration = '';
        if ($aTwitter = $this->parseTwitterUrl($sUrl)) {
            $title = $aTwitter['title'];
            $description = $aTwitter['description'];
            $image = $aTwitter['image'];
        } else if ($aInstagram = $this->parseInstagramUrl($sUrl)) {
            $title = $aInstagram['title'];
            $image = $aInstagram['image'];
        } else if ($aYT = $this->parseYouTubeUrl($sUrl)) {
            $title = $aYT['title'];
            $description = $aYT['description'];
            $image = $aYT['image'];
            $duration = $aYT['duration'];
            $embed = $aYT['embed'];
        } else {
            $encoding = 'utf-8';
            $sContent = Phpfox_Request::instance()->send($sUrl, [], 'GET', $_SERVER['HTTP_USER_AGENT'], null, true);

            // special case check encoding from html
            preg_match('/<html(.*?)>/i', $sContent, $aRegMatches);
            preg_match('/<meta[^<].*charset=["]?([\w-]*)["]?/i', $sContent, $aCharsetMatches);
            if (isset($aCharsetMatches[1])) {
                $encoding = $aCharsetMatches[1];
            } else if (isset($aRegMatches[1])) {
                preg_match('/lang=["|\'](.*?)["|\']/is', $aRegMatches[1], $aLanguages);
                if (isset($aLanguages[1]) && in_array($aLanguages[1], ['uk'])) {
                    $encoding = 'Windows-1251';
                }
            }

            // get title
            if (preg_match('/<title>(.*?)<\/title>/is', $sContent, $aMatches)) {
                $title = $aMatches[1];
            } else if (preg_match('/<title (.*?)>(.*?)<\/title>/is', $sContent, $aMatches) && isset($aMatches[2])) {
                $title = $aMatches[2];
            }

            // get all meta tags
            $aParseBuild = [];
            preg_match_all('/<(meta|link)(.*?)>/i', $sContent, $aRegMatches);
            if (!empty($aRegMatches[2])) {
                foreach ($aRegMatches[2] as $sLine) {
                    $sLine = rtrim($sLine, '/');
                    $sLine = trim($sLine);
                    preg_match('/(property|name|rel|image_src)=("|\')(.*?)("|\')/is', $sLine, $aType);
                    if (count($aType) && isset($aType[3])) {
                        $sType = $aType[3];
                        preg_match('/(content|type)=("|\')(.*?)("|\')/i', $sLine, $aValue);
                        if (count($aValue) && isset($aValue[3])) {
                            if ($sType == 'alternate') {
                                $sType = $aValue[3];
                                preg_match('/href=("|\')(.*?)("|\')/i', $sLine, $aHref);
                                if (isset($aHref[2])) {
                                    $aValue[3] = $aHref[2];
                                }
                            }
                            $aParseBuild[$sType] = $aValue[3];
                        }
                    }
                }
            }

            // get meta with DOMDocument when $aParseBuild empty
            if (empty($aParseBuild) && class_exists('DOMDocument')) {
                $doc = new DOMDocument("1.0", $encoding);
                // now we inject another meta tag
                $contentType = '<meta http-equiv="Content-Type" content="text/html; charset=' . $encoding . '">';
                $sContent = str_replace('<head>', '<head>' . $contentType, $sContent);

                if (function_exists('mb_convert_encoding')) {
                    @$doc->loadHTML(mb_convert_encoding($sContent, 'HTML-ENTITIES', $encoding));
                } else {
                    @$doc->loadHTML($sContent);
                }
                $metaList = $doc->getElementsByTagName("meta");
                foreach ($metaList as $iKey => $meta) {
                    $type = $meta->getAttribute('property');
                    if (empty($type)) {
                        $type = $meta->getAttribute('name');
                    }
                    $aParseBuild[$type] = $meta->getAttribute('content');
                }
            }

            // check and get thumbnail
            if (!empty($aParseBuild['og:image']) || !empty($aParseBuild['twitter:image'])) {
                $image = !empty($aParseBuild['og:image']) ? $aParseBuild['og:image'] : $aParseBuild['twitter:image'];
            } else {
                preg_match('/http(?:s?):\/\/(?:www\.|web\.|m\.)?facebook\.com\/([A-z0-9\.]+)\/videos(?:\/[0-9A-z].+)?\/(\d+)(?:.+)?$/', $sUrl, $aFbVideo);
                if (!empty($aFbVideo[2])) {
                    $image = 'https://graph.facebook.com/' . $aFbVideo[2] . '/picture';
                }
            }

            // check and get title
            if (!empty($aParseBuild['og:title']) || !empty($aParseBuild['twitter:title'])) {
                $title = !empty($aParseBuild['og:title']) ? $aParseBuild['og:title'] : $aParseBuild['twitter:title'];
            }

            // check and get description
            if (!empty($aParseBuild['description']) || !empty($aParseBuild['og:description']) || !empty($aParseBuild['twitter:description'])) {
                $description = !empty($aParseBuild['description']) ? $aParseBuild['description'] : ($aParseBuild['og:description'] ? $aParseBuild['og:description'] : $aParseBuild['twitter:description']);
            }

            // check and get embed media
            $sEmbedUrl = $sUrl;
            if (preg_match('/dailymotion/', $sEmbedUrl) && substr($sEmbedUrl, 0, 8) == 'https://') {
                $sEmbedUrl = str_replace('https', 'http', $sEmbedUrl);
            }
            $MediaEmbed = new MediaEmbed();
            $MediaObject = $MediaEmbed->parseUrl($sEmbedUrl);
            if ($MediaObject instanceof \MediaEmbed\Object\MediaObject) {
                if ($embedImage = $MediaObject->image()) {
                    $image = $embedImage;
                }
                $embed = $MediaObject->getEmbedCode();
            }
            if (empty($embed)) {
                if (isset($aParseBuild['application/json+oembed'])) {
                    stream_context_create(
                        [
                            'http' => [
                                'header'     => 'Connection: close',
                                'user_agent' => $_SERVER['HTTP_USER_AGENT']
                            ]
                        ]);
                    $source = json_decode(preg_replace('/[^(\x20-\x7F)]*/', '', fox_get_contents($aParseBuild['application/json+oembed'])));
                    if (isset($source->html) && isset($aParseBuild['al:android:url'])) {
                        $id = str_replace('fb://photo/', '', $aParseBuild['al:android:url']);
                        $image = 'https://graph.facebook.com/' . $id . '/picture';
                        $embed = '<div class="fb_video_iframe"><iframe src="https://www.facebook.com/video/embed?video_id=' . $id . '"></iframe></div>';
                    }
                }
            }

            // check and get duration
            if (!empty($aParseBuild['duration']) || !empty($aParseBuild['video:duration'])) {
                $duration = !empty($aParseBuild['duration']) ? $aParseBuild['duration'] : $aParseBuild['video:duration'];
            } else if (preg_match('/(.*?)duration":{"raw":(.*?),/', $sContent, $aMatches)) {
                if (isset($aMatches[2])) {
                    $duration = $aMatches[2];
                }
            }

            // check encoding and convert string to requested character encoding
            if ($encoding != "utf-8") {
                $title = iconv($encoding, "utf-8", $title);
                $description = iconv($encoding, "utf-8", $description);
            }
        }

        if (strpos($image, 'http') === false && !preg_match('/^\/\//', $image)) {
            $image = $aParts['scheme'] . '://' . $aParts['host'] . $image;
        }

        return [
            'link'          => $sUrl,
            'title'         => $title,
            'description'   => $description,
            'duration'      => $duration,
            'default_image' => Phpfox::getLib('url')->secureUrl($image),
            'embed_code'    => $embed,
            'host'          => $host,
        ];
    }

    public function getEmbedCode($iId, $bIsPopUp = false)
    {
        $aLinkEmbed = $this->database()->select('embed_code')
            ->from(Phpfox::getT('link_embed'))
            ->where('link_id = ' . (int)$iId)
            ->execute('getSlaveRow');

        $iWidth = 640;
        $iHeight = 390;
        if (!$bIsPopUp) {
            $iWidth = 480;
            $iHeight = 295;
        }

        $aLinkEmbed['embed_code'] = preg_replace('/width=\"(.*?)\"/i', 'width="' . $iWidth . '"',
            $aLinkEmbed['embed_code']);
        $aLinkEmbed['embed_code'] = preg_replace('/height=\"(.*?)\"/i', 'height="' . $iHeight . '"',
            $aLinkEmbed['embed_code']);
        $aLinkEmbed['embed_code'] = str_replace(['&lt;', '&gt;', '&quot;'], ['<', '>', '"'],
            $aLinkEmbed['embed_code']);

        if (Phpfox::getParam('core.force_https_secure_pages')) {
            $aLinkEmbed['embed_code'] = str_replace('http://', 'https://', $aLinkEmbed['embed_code']);
        }

        return $aLinkEmbed['embed_code'];
    }

    /**
     * @param $iId
     *
     * @return array|bool|int|string
     */
    public function getLinkById($iId)
    {
        $aLink = $this->database()->select('l.*, u.user_name')
            ->from(Phpfox::getT('link'), 'l')
            ->join(Phpfox::getT('user'), 'u', 'u.user_id = l.user_id')
            ->where('l.link_id = ' . (int)$iId)
            ->execute('getSlaveRow');

        if (!isset($aLink['link_id'])) {
            return false;
        }

        if (in_array($aLink['module_id'], ['pages', 'groups'])) {
            if (($aLink['module_id'] == 'pages' && Phpfox::isAppActive('Core_Pages')) || ($aLink['module_id'] == 'groups' && Phpfox::isAppActive('PHPfox_Groups'))) {
                $aPage = Phpfox::getService($aLink['module_id'])->getForView($aLink['parent_user_id']);
                if (empty($aPage)) {
                    return $aLink;
                }
                if ($aLink['module_id'] == 'pages') {
                    $aLink['redirect_link'] = Phpfox::getService($aLink['module_id'])->getUrl($aPage['page_id'], $aPage['title'], $aPage['vanity_url']) . 'wall/?link-id=' . $aLink['link_id'];
                } else {
                    $aLink['redirect_link'] = Phpfox::getService($aLink['module_id'])->getUrl($aPage['page_id'], $aPage['title'], $aPage['vanity_url']) . '?link-id=' . $aLink['link_id'];
                }
            } else {
                $aLink['redirect_link'] = '';
            }
        }
        return $aLink;
    }

    /**
     * Get feed/detail link for notification/mail
     *
     * @param $iItemId
     *
     * @return string
     */
    public function getFeedLink($iItemId)
    {
        $oLink = $this->getLinkById($iItemId);
        if (!$oLink) {
            return null;
        }
        if (!empty($oLink['redirect_link'])) {
            $link = $oLink['redirect_link'];
        } else {
            $feed = (Phpfox::isModule('feed') ? Phpfox::getService('feed')->getParentFeedItem('link', $oLink['link_id']) : null);
            if (!$feed || ($feed && empty($feed['user_name']))) {
                $link = Phpfox::permalink($oLink['module_id'], $oLink['parent_user_id']);
            } else {
                $link = Phpfox_Url::instance()->makeUrl($feed['user_name'], ['link-id' => $oLink['link_id']]);
            }
        }
        return $link;
    }

    /**
     * Get url to a specific link
     *
     * @param $iLinkId
     *
     * @return bool|string
     */
    public function getUrl($iLinkId)
    {
        $iUserId = db()->select('user_id')->from(':link')->where(['link_id' => $iLinkId])->executeField();

        if (!$iUserId) {
            return '';
        }

        return Phpfox::getService('user')->getLink($iUserId, null, ['link-id' => $iLinkId]);
    }

    /**
     * Check if link is allowed
     *
     * @param $sLink
     *
     * @return bool
     */
    public function isInternalLink($sLink)
    {
        $bIsInternalLink = false;
        $aSites = explode(',',
            trim(Phpfox::getParam('core.url_spam_white_list')) . ',' . Phpfox::getParam('core.host'));

        // process url in href attribute
        foreach ($aSites as $sSite) {
            $sSite = trim($sSite);
            $sSite = str_replace(['.', '*'], ['\.', '(.*?)'], $sSite);

            if (preg_match('/' . str_replace('/', '\/', $sSite) . '/is', $sLink)) {
                $bIsInternalLink = true;
            }
        }

        return $bIsInternalLink;
    }

    /**
     * If a call is made to an unknown method attempt to connect
     * it to a specific plug-in with the same name thus allowing
     * plug-in developers the ability to extend classes.
     *
     * @param string $sMethod    is the name of the method
     * @param array  $aArguments is the array of arguments of being passed
     *
     * @return null
     */
    public function __call($sMethod, $aArguments)
    {
        /**
         * Check if such a plug-in exists and if it does call it.
         */
        if ($sPlugin = Phpfox_Plugin::get('link.service_link__call')) {
            eval($sPlugin);
            return null;
        }

        /**
         * No method or plug-in found we must throw a error.
         */
        Phpfox_Error::trigger('Call to undefined method ' . __CLASS__ . '::' . $sMethod . '()', E_USER_ERROR);
    }
}