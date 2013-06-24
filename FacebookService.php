<?php

namespace WebsiteBundle\Common\AppService;

use Domain\Document\ContentNode;
use Domain\Document\Person;
use WebsiteBundle\Common\Search\DocumentSearch;
use WebsiteBundle\Common\Search\CatalogSearch;
use WebsiteBundle\Common\Search\Searchable;

/**
 * Service that permits Facebook Social integration
 *
 */
class FacebookService extends AppService {

    const TOKEN = 'access_token';
    const PROFILE = '/%s';
    const FRIENDS = '/%s/friends';
    const FEED = '/%s/feed';

     /**
     * @var string
     */
    const FILTER_ALL = 'all';

    /* @var $facebook \WebsiteBundle\Common\SocialIntegration\Facebook */
    private $facebook;
    private $user;

    /**
     * @var string
     * @see parameters.ini
     */
    private $appId;

    /**
     * @var string
     * @see parameters.ini
     */
    private $appSecret;

    /**
     * @var CatalogSearch
     */
    private $catalogSearch;

    private static $AVAILABLE_FILTERS = array(
         'title',
    );

    private static $preferencesFieldsMapping = array(
        'tv' => ContentNode::TYPE_TVSHOW,
        'tv-show' => ContentNode::TYPE_TVSHOW,
        'movie' => ContentNode::TYPE_MOVIE,
        'movie-general' => ContentNode::TYPE_MOVIE,
        'actor-director' => 'actor-director',
        'movie-genre' => Searchable::TYPE_DOC_GENRE
    );

    /**
     * @param string $appId
     * @param string $appSecret
     */
    public function __construct($appId, $appSecret, $catalogSearch) {
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->catalogSearch = $catalogSearch;
    }

    /**
     * @param \Symfony\Component\DependencyInjection\Container $container Optional
     * @return FacebookService
     */
    public static function getInstance($container = null) {
        return $container->get('facebook');
    }

    /**
     * @return string
     */
    public function getApplicationID() {
        return $this->appId;
    }

    /**
     * @return string
     */
    public function getApplicationSecret() {
        return $this->appSecret;
    }

    private function getFacebook(){
        if($this->facebook == null){
            $config = array (
                'appId' => $this->appId,
                'secret' => $this->appSecret,
                'cookie' => true,
            );
            $this->facebook = new \WebsiteBundle\Common\SocialIntegration\Facebook($config);
            if ($this->user) {
                $this->facebook->setAccessToken($this->user->getFacebookToken());
            }
        }
        return $this->facebook;
    }

    /**
     *
     * @return array
     */
    public function getFriends(){
        if($this->user == null){
            throw new \Domain\Exception\InvalidArgumentException("You have to set an user before calling this method");
        }

        $fbFriends = array();

        if (!$this->isFacebookLinked()) {
            return $fbFriends;
        }

        try{
            /* @var $facebook Facebook */
            $facebook = $this->getFacebook();

            $fbToken =  $this->user->getFacebookToken();

            $params = array(self::TOKEN => $fbToken,);
            $facebookFriends = $facebook->api(sprintf(self::FRIENDS, $this->user->getFacebookId()), $params);
            $facebookFriends = $facebookFriends['data'];

            $dm = $this->getDm();

            $ids = array();

            if ($facebookFriends) {
                foreach($facebookFriends as $facebookFriend){
                    if(!$this->user->getDocument()->isFacebookFriendIgnored($facebookFriend['id'])){
                        $ids[] = $facebookFriend['id'];
                    }
                }
            }

            $qb = $dm->createQueryBuilder('Domain\Document\User')
                         ->field('facebookId')
                         ->in($ids);

            $query = $qb->getQuery();

            $friends = $query->execute();

            $fbFriends = $friends->toArray();

            /* @var $friend \Domain\Document\Interfaces\UzUserInterface */
            foreach($fbFriends as $key => $friend){
                $fbFriends[$key] = $friend->getUserLite();
            }
        } catch (\WebsiteBundle\Common\Exception\MissingDocumentException $e) {
            $this->container->get('logger')->err("Document not found: " . $e->getMessage() . ": " . $e->getTraceAsString());
            $fbFriends = array();
        } catch (\WebsiteBundle\Common\SocialIntegration\FacebookApiException $e) {
            $this->container->get('logger')->err("Unable to retrieve friends from Facebook: " . $e->getMessage() . ": " . $e->getTraceAsString());
            $fbFriends = array();
        }
        return $fbFriends;
    }

    public function getToken(){
        $ses = $this->getFacebook()->getAccessToken();
        return $ses;
    }

    public function setToken($token){
        $this->getFacebook()->setAccessToken($token);
    }

    /**
     * Sets owner of the facebook account
     *
     * @param \Domain\Document\Interfaces\UzUserInterface $user
     * @return FacebookService
     */
    public function setUser(\Domain\Document\Interfaces\UzUserInterface $user){
        $this->user = $user;
        return $this;
    }

    /**
     * Gets the user profile information
     *
     * @param type $params
     * @return type
     */
    public function getProfile($params = array()){
        if($this->user && $this->user->getFacebookToken()){
            $mergedParams = array_merge(array(self::TOKEN => $this->user->getFacebookToken()), $params);
        } else {
            $mergedParams = $params;
        }

        try{
            $fbMe = '/me';
            if ($this->user && $this->user->getFacebookId()) {
                $fbMe = sprintf(self::PROFILE, $this->user->getFacebookId());
            }
            return $this->getFacebook()->api($fbMe, $mergedParams);
        } catch (\WebsiteBundle\Common\SocialIntegration\FacebookApiException $e){
            $this->container->get('logger')->err($e->getMessage() . ": " . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Gets the user interests (movies and/or TV shows)
     *
     * @param type $type
     * @param type $params
     * @return type
     */
    public function getInterests($type, $params = array()){
        if($this->user && $this->user->getFacebookToken()){
            $mergedParams = array_merge(array(self::TOKEN => $this->user->getFacebookToken()), $params);
        } else {
            $mergedParams = $params;
        }

        $request = sprintf(self::PROFILE, $this->user->getFacebookId()) . "/" . $type;

        try{
            return $this->getFacebook()->api($request, $mergedParams);
        } catch (\WebsiteBundle\Common\SocialIntegration\FacebookApiException $e){
            $this->container->get('logger')->err($e->getMessage() . ": " . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Posts a new message to a friend wall
     *
     * @param string $title
     * @param string $subtitle
     * @param string $content
     * @param string $imageUrl
     * @param string $caption
     * @param string $link
     * @param string $action
     * @param string $actionLink
     * @return int post_id or -1 if fail
     */
    public function postToWall(
            $title,
            $subtitle,
            $content,
            $imageUrl,
            $caption = 'Size.com',
            $link ='site.com',
            $action = null,
            $actionLink = null
    ){
        if ($this->user == null){
            throw new \Domain\Exception\InvalidArgumentException("You have to set an user before calling this method");
        }

        if (!$this->isFacebookLinked()) {
            return -1;
        }

        $args = array(
            'message' => $title,
            'link' => $link,
            'picture' => $imageUrl,
            'name' => $subtitle,
            'caption' => $caption,
            'description' => $content,
        );

        if($action && $actionLink){
            $args['actions'] = array("name" => $action, "link" => $actionLink);
        }
        /* @var $this->facebook WebsiteBundle\Common\SocialIntegration\Facebook */
        try{
            $response = $this->getFacebook()->api(sprintf(self::FEED, $this->user->getFacebookId()), 'post', $args);
            return $response['id'];
        } catch (\WebsiteBundle\Common\SocialIntegration\FacebookApiException $e){
            $this->container->get('logger')->err("Unable to post to facebook: " . $e->getMessage() . ": " . $e->getTraceAsString());
            return -1;
        }
    }

    /**
     * Checks if the facebook link is active
     *
     * @return boolean
     */
    public function isFacebookLinked() {
        if (is_null($this->user)) {
            throw new \Domain\Exception\InvalidArgumentException("You have to set an user before calling this method");
        }

        return ($this->user->getFacebookId() && $this->user->getFacebookToken());
    }

    /**
     * Gets/set the interests from FB
     * @return void
     */
    public function setFBInterests () {
        $movies_interests = $this->getInterests('movies', array(
            'fields' => 'name, category',
        ));

        $tvshow_interests = $this->getInterests('television', array(
            'fields' => 'name, category',
        ));

        $interests = array_merge($movies_interests['data'], $tvshow_interests['data']);

        $this->restoreFBMovieTVShowInterests($interests);
        $this->restoreFBPeopleInterests($interests);
        $this->restoreFBGenreInterests($interests);
    }

    /**
     * Returns matched interests
     *
     * @param array $params
     */
    private function getMovieTVShowInterest($params) {
        // Raw filter  calculation

        $filter = array();
        foreach (self::$AVAILABLE_FILTERS as $key) {
            if (isset($params[$key]) && $params[$key] !== self::FILTER_ALL) {
                $filterKey = \Domain\Search\ContentNodeSearch::$indexFieldsMapping[$key];
                $filter[] = array($filterKey, DocumentSearch::escapeSearchValue($params[$key]), $filterKey);
            }
            // Calculated parameters
            /* @var $catalogSearch CatalogSearch */
            if (isset(self::$preferencesFieldsMapping[$params['type']])) {
                $type = self::$preferencesFieldsMapping[$params['type']];
            } else {
                $type = 'all';
            }

            $results = $this->catalogSearch->getContent($type, $filter);

            return $results;
        }
    }

    /**
     * Restore Movies/TVShows interests from FB
     *
     * @param user $user
     */
    private function restoreFBMovieTVShowInterests($interests) {

        foreach ($interests as $interest) {

            $categorySlug = \WebsiteBundle\Common\Util\Url\Urlizer::urlize($interest['category']);
            if (isset(self::$preferencesFieldsMapping[$categorySlug]) &&
                    (self::$preferencesFieldsMapping[$categorySlug] === 'movie' ||
                    self::$preferencesFieldsMapping[$categorySlug] === 'tvshow')){
                $params = array('title' => $interest['name'],
                                'type' => self::$preferencesFieldsMapping[$categorySlug]);

                $result = $this->getMovieTVShowInterest($params);

                if (!empty($result)) {
                    $service = \WebsiteBundle\Common\AppService\InterestsService::getInstance($this->container);
                    $service->setUser($this->user);

                    if (count($result) === 1) {
                        if ($result[0]->getType() === ContentNode::TYPE_TVSHOW) {
                            $service->addTvShowBySlug($result[0]->getSlug());
                        }

                        if ($result[0]->getType() === ContentNode::TYPE_MOVIE) {
                            $service->addMovieBySlug($result[0]->getSlug());
                        }
                    }

                    if (count($result) > 1) {
                        foreach ($result as $object) {
                            if ($object->getTitle() === $params['title']) {
                                if ($object->getType() === ContentNode::TYPE_TVSHOW) {
                                    $service->addTvShowBySlug($object->getSlug());
                                }
                                if ($object->getType() === ContentNode::TYPE_MOVIE) {
                                    $service->addMovieBySlug($object->getSlug());
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Restores interests in People (directors/actors)
     *
     * @param array $interests
     */
    private function restoreFBPeopleInterests($interests) {

        $people = $this->getDm()->getRepository('\Domain\\Document\\Person');

        foreach ($interests as $interest) {
            $categorySlug = \WebsiteBundle\Common\Util\Url\Urlizer::urlize($interest['category']);

            if (isset(self::$preferencesFieldsMapping[$categorySlug]) && $categorySlug === 'actor-director') {
                $nameSlug = \WebsiteBundle\Common\Util\Url\Urlizer::urlize($interest['name']);
                $person = $people->findOneBySlug($nameSlug);
                if (isset($person)) {
                    $service = \WebsiteBundle\Common\AppService\InterestsService::getInstance($this->container);
                    $service->setUser($this->user);

                    $roles = $person->getRoles();
                    if (in_array(Person::ROLE_ACTOR, $roles)) {
                        $service->addActorBySlug($person->getSlug());
                    }
                    if (in_array(Person::ROLE_DIRECTOR, $roles)) {
                        $service->addDirectorBySlug($person->getSlug());
                    }
                }
            }
        }
    }

    /**
     * Restores user interests in Genres (drama/comedy...)
     *
     * @param array $interests
     */
    private function restoreFBGenreInterests($interests) {

        foreach ($interests as $interest) {
            $categorySlug = \WebsiteBundle\Common\Util\Url\Urlizer::urlize($interest['category']);
            $nameSlug = \WebsiteBundle\Common\Util\Url\Urlizer::urlize($interest['name']);

            if (isset(self::$preferencesFieldsMapping[$categorySlug]) && $categorySlug === 'movie-genre') {
                $genres = $this->getDm()->getRepository('\Domain\\Document\\Genre');
                $genre = $genres->findOneBy(array('slug' => $nameSlug));
                if (isset($genre)) {
                    $service = \WebsiteBundle\Common\AppService\InterestsService::getInstance($this->container);
                    $service->setUser($this->user);
                    $service->addGenreBySlug($nameSlug);
                }
            }
        }
    }
}

