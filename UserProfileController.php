<?php

namespace WebsiteBundle\Controller;

/**
 * Main controller for user profiles
 */
use WebsiteBundle\Controller\Controller;
use Domain\Document\Privacy;
use Domain\Document\InboxEntry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * UserProfile Controller
 *
 */
class UserProfileController extends Controller {
    const BROWSE_OTHER = 0;
    const BROWSE_SELF = 1;

    const ACTION_FOLLOW = 'follow';
    const ACTION_UNFOLLOW = 'unfollow';

    const ADD_FRIEND_CLASS = 'add-friend';
    const IS_FRIEND_CLASS = 'is-friend';

    const INBOX_PAGE_SIZE = 10;

    const LAST_SEEN_PAGE_SIZE = 10;
    const LAST_REVIEWED_PAGE_SIZE = 3;
    const LAST_FAVORITES_PAGE_SIZE = 5;
    const MAIN_REVIEWS_SECTION_PAGE_SIZE = 10;
    const MAIN_FAVORITES_SECTION_PAGE_SIZE = 10;

    const TWITTER_BASE_URL = 'http://twitter.com/';

    /**
     * @var string
     */
    protected $defaultDocument = 'Domain\Document\User';
    private $params = array(
        'domBodyId' => 'user-profile',
        'domBodyClass' => 'index',
        'mainClass' => 'group',
        'updatePreferencesUrl' => '',
        'followButtonAction' => '',
        'requireNewUserExperience' => false,
    );

    /**
     * Entry point for showing user profile page.
     * @param string $username
     */
    public function indexAction($username) {
        if (!$this->isLoggedIn()) {
            return $this->redirectForLogin(\WebsiteBundle\Form\LoginForm::ACCESS_TYPE_USER_PROFILE);
        }

        $user = $this->getDefaultRepository()->findOneBy(array('username' => $username));

        if ($user === null) {
            throw new NotFoundHttpException();
        }

        $me = $this->getSessionUser();

        $this->params['user'] = $user;
        $privacy = $user->getPrivacy();

        if ($username === $me->getUsername()) {
            $this->params['browse_mode'] = self::BROWSE_SELF;
            $this->params['allowActivityDeletion'] = true;
            $this->params['updatePreferencesUrl'] = $this->generateUrl('user_profile_update_privacy', array('username' => $username));
            /* @var $privacy \Domain\Document\Privacy */

            $this->params['showLastSeen'] = $privacy->isProfileEnabled(Privacy::SHOW_LAST_SEEN);
            $this->params['showLastReviews'] = $privacy->isProfileEnabled(Privacy::SHOW_LAST_REVIEWS);
            $this->params['showLastFavorites'] = $privacy->isProfileEnabled(Privacy::SHOW_LAST_FAVORITES);

            $this->params['canSeeLastSeen'] = true;
            $this->params['canSeeLastReviews'] = true;
            $this->params['canSeeLastFavorites'] = true;


            // This is a check to identify whether we need to ask the user for more basic data
            // Encourage - new user experience module
            $this->params['requireNewUserExperience'] = $this->requireNewUserExperience($user);
        } else {
            $followerService = $this->get('user.friends');
            if ($followerService->isFollowing($me, $user)) {
                $action = self::ACTION_UNFOLLOW;
                $this->params['addAssFriendClass'] = self::IS_FRIEND_CLASS;
                $this->params['addAssFriendText'] = $this->getTranslator()->trans('Remove %name% from friends', array('%name%' => $user->getFirstName()), 'userprofile');
            } else {
                $action = self::ACTION_FOLLOW;
                $this->params['addAssFriendClass'] = self::ADD_FRIEND_CLASS;
                $this->params['addAssFriendText'] = $this->getTranslator()->trans('Add %name% as a friend', array('%name%' => $user->getFirstName()), 'userprofile');
            }

            if ($followerService->isFollowing($me, $user) && $followerService->isFollowing($user, $me)) {
                $this->params['shareBoxEnabled'] = true;
            } else {
                $this->params['shareBoxEnabled'] = false;
            }

            $this->params['followButtonAction'] = $this->generateUrl('user_profile_actions', array('username' => $username, 'action' => $action));
            $this->params['allowActivityDeletion'] = false;
            $this->params['canSeeLastSeen'] = $privacy->isProfileEnabled(Privacy::SHOW_LAST_SEEN);
            $this->params['canSeeLastReviews'] = $privacy->isProfileEnabled(Privacy::SHOW_LAST_REVIEWS);
            $this->params['canSeeLastFavorites'] = $privacy->isProfileEnabled(Privacy::SHOW_LAST_FAVORITES);
            $this->params['browse_mode'] = self::BROWSE_OTHER;

            /* @var $userProfile Domain\Document\UserProfile */
            $userProfile = $user->getUserProfile();

            $twitterAccount = $user->getTwitterId();
            if (null != $twitterAccount) {
                $this->params['twitterUrl'] = self::TWITTER_BASE_URL . $twitterAccount;
            }
        }

        if ($this->params['canSeeLastSeen']) {
            /* @todo #beta fix this and not fetch all results */
            $seenTotal = 1;
            \WebsiteBundle\Common\AppService\WatchedContentService::getInstance($this->container)->getLastActivities($user, 0, $seenTotal);
            $this->params['lastSeenTotal'] = $seenTotal;
            $url1 = $this->generateUrl('user_profile_last_seen', array('username' => $username, 'page' => 1));
            $this->params['getLastSeenUrl'] = substr($url1, 0, strrpos($url1, '/') + 1);
            $this->params['removeLastSeenUrl'] = $this->generateUrl('user_profile_remove_last_seen', array('username' => $username, 'contentId' => 'contentId'));
        }

        if ($this->params['canSeeLastReviews']) {
            $totRev = 1;
            $this->params['lastReviewed'] = \WebsiteBundle\Common\AppService\ReviewsService::getInstance($this->container)->getLastActivities($user, self::LAST_REVIEWED_PAGE_SIZE, $totRev);
            $this->params['lastReviewedTotal'] = $totRev;
            $this->params['hasMoreReviews'] = $this->params['lastReviewedTotal'] > self::LAST_REVIEWED_PAGE_SIZE;
            $this->params['getLastReviews'] = $this->generateUrl('user_profile_last_favorites', array('username' => $username));
            $this->params['maxLastReviews'] = self::LAST_REVIEWED_PAGE_SIZE;
            $this->params['emptyLastReviews'] = $this->generateUrl('user_profile_empty_favorites', array('username' => $username));
        }

        if ($this->params['canSeeLastFavorites']) {
            $totFav = 1;
            $this->params['lastFavorites'] = \WebsiteBundle\Common\AppService\FavoritesService::getInstance($this->container)->getLastActivities($user, self::LAST_FAVORITES_PAGE_SIZE, $totFav);
            $this->params['lastFavoritesTotal'] = $totFav;
            $this->params['hasMoreFavorites'] = $this->params['lastFavoritesTotal'] > self::LAST_FAVORITES_PAGE_SIZE;
        }

        $response = $this->render('WebsiteBundle:UserProfile:index.html.twig', $this->params);

        return $response;
    }

    public function lastSeenAction($username, $page) {
        if (!$this->isLoggedIn()) {
            return $this->redirectForLogin(\WebsiteBundle\Form\LoginForm::ACCESS_TYPE_USER_PROFILE);
        }

        $user = $this->getDefaultRepository()->findOneBy(array('username' => $username));

        if ($user === null) {
            throw new NotFoundHttpException();
        }

        $me = $this->getSessionUser();

        $this->params['user'] = $user;

        if ($username === $me->getUsername()) {
            $canSee = true;
            $this->params['allowActivityDeletion'] = true;
        } else {
            $this->params['allowActivityDeletion'] = false;
            $privacy = $user->getPrivacy();
            $canSee = $privacy->isProfileEnabled(Privacy::SHOW_LAST_SEEN);
        }

        if (!$canSee) {
            throw new \Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException();
        }

        $seenService = \WebsiteBundle\Common\AppService\WatchedContentService::getInstance($this->container);
        $seenService->setOwnerUser($user);
        $pager = $seenService->getPager(self::LAST_SEEN_PAGE_SIZE);
        $pager->setCurrentPage($page);

        $this->params['user'] = $user;
        $this->params['lastSeen'] = $pager->getItems();

        return $this->render('WebsiteBundle:UserProfile:last-seen-content.html.twig', $this->params);
    }

    /**
     * Removes users activity
     *
     * @param string $username
     * @param string $activityId
     * @return Response
     */
    public function removeActivityAction($username, $activityId) {
        $success = true;
        $data = array();
        $token = $this->get('request')->get('token');

        if ($this->isLoggedIn() && $this->get('request')->getMethod() === 'POST'
                && $this->getSessionUser()->getUsername() === $username
                && $this->isValidCsrfToken($token, $activityId)) {

            $reviewService = \WebsiteBundle\Common\AppService\ReviewsService::getInstance($this->container);
            $contentReview = $reviewService->getContentReviewById($activityId);

            $reviewService->deleteReview($contentReview, $this->getSessionUser());
            $data = array(
                'removedActivity' => $activityId,
            );

            $success = true;
        } else {
            $success = false;
            $data['reason'] = $this->getTranslator()->trans('Invalid request', array(), 'core');
        }

        $jsonResponse = new \WebsiteBundle\Common\ClientApp\JsonResponse($success, $data);
        return $this->jsonResponse($jsonResponse);
    }

    public function updatePrivacyAction($username) {
        $success = true;
        $data = array();
        if (!$this->isLoggedIn()) {
            $success = false;
            $data['reason'] = "You need to be logged in to do this!";
        }

        // @todo #beta verify privacy and that the user actually exists
        $me = $this->getSessionUser()->getDocument();

        if ($username != $me->getUsername()) {
            $success = false;
            $data['reason'] = "You are not allowed to do this";
        }

        if ($success) {
            /* @var $privacy Privacy */
            $privacy = $me->getPrivacy();

            $lastSeen = $this->get('request')->get('showLastSeen');
            if ('true' === $lastSeen) {
                $privacy->enableProfile(Privacy::SHOW_LAST_SEEN);
                $data['seen'] = true;
                $data['seenPrivacy'] = "";
            } elseif ('false' === $lastSeen) {
                $privacy->disableProfile(Privacy::SHOW_LAST_SEEN);
                $data['seen'] = false;
                $data['seenPrivacy'] = $this->renderView("WebsiteBundle:UserProfile:hidden-for-others.html.twig", array('section' => 'seen'));
            }

            $lastReviews = $this->get('request')->get('showLastReviews');
            if ('true' === $lastReviews) {
                $privacy->enableProfile(Privacy::SHOW_LAST_REVIEWS);
                $data['reviews'] = true;
                $data['reviewsPrivacy'] = "";
            } elseif ('false' === $lastReviews) {
                $privacy->disableProfile(Privacy::SHOW_LAST_REVIEWS);
                $data['reviews'] = false;
                $data['reviewsPrivacy'] = $this->renderView("WebsiteBundle:UserProfile:hidden-for-others.html.twig", array('section' => 'reviews'));
            }

            $lastFavorites = $this->get('request')->get('showLastFavorites');
            if ('true' === $lastFavorites) {
                $privacy->enableProfile(Privacy::SHOW_LAST_FAVORITES);
                $data['favorites'] = true;
                $data['favoritesPrivacy'] = "";
            } elseif ('false' === $lastFavorites) {
                $privacy->disableProfile(Privacy::SHOW_LAST_FAVORITES);
                $data['favorites'] = false;
                $data['favoritesPrivacy'] = $this->renderView("WebsiteBundle:UserProfile:hidden-for-others.html.twig", array('section' => 'favorites'));
            }

            $dm = $this->getDm();
            $me->setPrivacy($privacy);
            $dm->persist($me);
            $dm->flush(array('safe' => true));
        }

        $jsonResponse = new \WebsiteBundle\Common\ClientApp\JsonResponse($success, $data);
        return $this->jsonResponse($jsonResponse);
    }

    public function inboxAction($username, $_format) {
        if (!$this->isLoggedIn()) {
            return $this->redirectForLogin(\WebsiteBundle\Form\LoginForm::ACCESS_TYPE_PRIVATE_RESOURCE, null, null, $this->generateUrl('user_inbox', array('username' => $username)));
        }

        $me = $this->getSessionUser();

        if ($username != $me->getUsername()) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('You are not allowed to access someone else\'s inbox');
        }
        $this->params['domBodyClass'] = 'user-inbox';

        $inboxService = $this->container->get('inbox')->setUser($me);

        $featureService = $this->container->get('feature');
        $isPromocodeFeatureOn = $featureService->isFeatureOn('promocodes');
        $notInTypeFilter = array();
        if (!$isPromocodeFeatureOn) {
            $notInTypeFilter[] = InboxEntry::TYPE_NEW_PROMOCODE;
        }
        /* @var $pager \WebsiteBundle\Common\UI\UzPager */
        $pager = $inboxService->getInbox(0, self::INBOX_PAGE_SIZE, array(), $notInTypeFilter);

        $this->params['totalEntries'] = $pager->countItems();
        $this->params['entries'] = $pager->getItems();
        $this->params['hasMorePages'] = $pager->hasNext();
        $this->params['totalPages'] = $pager->count();

        $this->params['user'] = $me->getDocument();

        if ($_format == 'json') {
            $this->params['format'] = 'json';
            $response = $this->pagedInboxAction($username, 0, $this->params['format']);
        } else {
            $this->params['format'] = 'html';
            $this->params['locale'] = $me->getDocument()->getLocale();
            $response = $this->render('WebsiteBundle:UserProfile\Inbox:inbox.html.twig', $this->params);
            $inboxService->markEntriesAsRead($this->params['entries']);
            $inboxService->clear(false);
        }


        return $response;
    }

    public function pagedInboxAction($username, $page, $_format) {
        if (!$this->isLoggedIn()) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException("Login first!");
        }

        $me = $this->getSessionUser();

        if ($username != $me->getUsername()) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException('You are not allowed to access someone else\'s inbox');
        }
        $ibs = $this->container->get('inbox')->setUser($me);

        /* @var $pager \WebsiteBundle\Common\UI\UzPager */

        $featureService = $this->container->get('feature');
        $isPromocodeFeatureOn = $featureService->isFeatureOn('promocodes');
        $notInTypeFilter = array();
        if (!$isPromocodeFeatureOn) {
            $notInTypeFilter[] = InboxEntry::TYPE_NEW_PROMOCODE;
        }

        $pager = $ibs->getInbox($page, self::INBOX_PAGE_SIZE, array(), $notInTypeFilter);

        $this->params['entries'] = $pager->getItems();
        $this->params['locale'] = $me->getDocument()->getLocale();

        $response = $this->render('WebsiteBundle:UserProfile\Inbox:inbox-content.html.twig', $this->params);

        //The following actions are performed after the response is generated.
        $dm = $this->getDm();
        foreach ($this->params['entries'] as $entry) {
            /* @var $entry \Domain\Document\InboxEntry */
            $entry->setRead(true);
            $dm->persist($entry);
        }
        $ibs->clear(false);
        $dm->flush(array('safe' => true));

        return $response;
    }

    public function actionAction($username, $action) {
        $success = true;
        $responseData = array();

        if (!$this->isLoggedIn()) {
            $success = false;
            $responseData = array('message' => "You have to be logged in to do this",);
        } else {
            switch ($action) {
                case self::ACTION_FOLLOW:
                    $response = $this->follow($username);
                    break;
                case self::ACTION_UNFOLLOW:
                    $response = $this->unfollow($username);
                    break;
                default:
                    throw new \Symfony\Component\Routing\Matcher\Exception\NotFoundException($action . " is not a valid method");
                    break;
            }
        }

        return $response;
    }

    private function follow($username) {
        if (!$this->isValidCsrfToken($this->get('request')->get('token'))) {
            $response = new \WebsiteBundle\Common\ClientApp\JsonResponse(false, array('message' => 'Invalid CSRF Token'));
        } else {
            $follower = $this->getSessionUser();

            $followed = $this->getUzUserByUsername($username);

            /* @var $fw \WebsiteBundle\Common\AppService\FriendsService */
            $friendsService = $this->get('user.friends');
            $followerAdded = $friendsService->addFollower($followed, $follower);

            if ($followerAdded) {
                $isShareEnabled = $friendsService->isFollowing($followed, $follower);

                $responseData = array(
                    'buttonAction' => $this->generateUrl('user_profile_actions', array('username' => $username, 'action' => self::ACTION_UNFOLLOW)),
                    'buttonClass' => self::IS_FRIEND_CLASS,
                    'buttonTitle' => $this->getTranslator()->trans('Remove %name% from friends', array('%name%' => $followed->getFirstName()), 'userprofile'),
                    'buttonRemoveClass' => self::ADD_FRIEND_CLASS,
                    'share' => array(
                        'enabled' => $isShareEnabled,
                        'dataUrl' => $this->generateUrl('user_share_content', array('username' => $username)),
                        'dataShareContext' => 'user',
                        'title' => ($isShareEnabled) ? $this->getTranslator()->trans('Share something with %name%', array('%name%' => $followed->getFirstName()), 'userprofile') :
                                    $this->getTranslator()->trans('You both should be friends', array(), 'userprofile'),
                    ),
                );
            } else {
                $responseData = array(
                    'message' => "You were already following " . $followed->getFirstName(),
                );
            }
            $response = new \WebsiteBundle\Common\ClientApp\JsonResponse($followerAdded, $responseData);
        }

        return $this->jsonResponse($response);
    }

    private function unFollow($username) {
        if (!$this->isValidCsrfToken($this->get('request')->get('token'))) {
            $response = new \WebsiteBundle\Common\ClientApp\JsonResponse(false, array('message' => 'Invalid CSRF Token'));
        } else {
            $follower = $this->getSessionUser();

            $followed = $this->getUzUserByUsername($username);

            $friendsService = $this->get('user.friends');
            $followerRemoved = $friendsService->removeFollower($followed, $follower);

            if ($followerRemoved) {
                $responseData = array(
                    'buttonAction' => $this->generateUrl('user_profile_actions', array('username' => $username, 'action' => self::ACTION_FOLLOW)),
                    'buttonClass' => self::ADD_FRIEND_CLASS,
                    'buttonTitle' => $this->getTranslator()->trans('Add %name% as a friend', array('%name%' => $followed->getFirstName()), 'userprofile'),
                    'buttonRemoveClass' => self::IS_FRIEND_CLASS,
                    'share' => array(
                        'enabled' => false,
                        'dataUrl' => '',
                        'dataShareContext' => '',
                        'title' => $this->getTranslator()->trans('You both should be friends', array(), 'userprofile'),
                    ),
                );
            } else {
                $responseData = array(
                    'message' => "You weren't already following " . $followed->getFirstName(),
                );
            }

            $response = new \WebsiteBundle\Common\ClientApp\JsonResponse($followerRemoved, $responseData);
        }

        return $this->jsonResponse($response);
    }

    public function removeLastSeenAction($username, $contentId) {
        if (!$this->isLoggedIn()) {
            $responseData = array('message' => 'you have to log in');
            return $this->jsonResponse(new \WebsiteBundle\Common\ClientApp\JsonResponse(false, $responseData));
        }

        $user = $this->getSessionUser();
        if ($user->getUsername() != $username) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException();
        }

        $lastSeenService = \WebsiteBundle\Common\AppService\WatchedContentService::getInstance($this->container);
        $success = $lastSeenService->setOwnerUser($user)->removeLastWatched($contentId);

        $response = new \WebsiteBundle\Common\ClientApp\JsonResponse($success, array());
        return $this->jsonResponse($response);
    }

    public function reviewsAction($username) {
        if (!$this->isLoggedIn()) {
            return $this->redirectForLogin(null, null, null, $this->generateUrl('user_profile_reviews', array('username' => $username)));
        }

        $repo = $this->getDefaultRepository();
        $user = $repo->findOneBy(array('username' => $username));

        if ($this->getSessionUser()->getUsername() === $username) {
            $this->params['browse_mode'] = self::BROWSE_SELF;
        } else {
            $privacy = $user->getPrivacy();

            if (!$privacy->isProfileEnabled(Privacy::SHOW_LAST_REVIEWS)) {
                return $this->redirectTo($this->generateUrl('user_profile'), array('username' => $username));
            }

            $this->params['browse_mode'] = self::BROWSE_OTHER;
        }

        $this->params['user'] = $user;
        $this->params['domBodyClass'] = "user-reviews";
        return $this->render('WebsiteBundle:UserProfile:reviews.html.twig', $this->params);
    }

    public function ajaxReviewsAction($username, $page = 0) {
        $user = $this->getDefaultRepository()->findOneBy(array('username' => $username));

        if ($user === null) {
            throw new NotFoundHttpException();
        }

        $success = true;

        $revServ = \WebsiteBundle\Common\AppService\ReviewsService::getInstance($this->container)->setOwnerUser($user);

        if (!$this->isLoggedIn()) {
            $success = false;
        }

        if ($this->getSessionUser()->getUsername() != $username) {
            $privacy = $user->getPrivacy();

            if (!$privacy->isProfileEnabled(Privacy::SHOW_LAST_REVIEWS)) {
                $success = false;
            }
        }

        if ($success) {
            /* @var $pager \WebsiteBundle\Common\UI\UzPager */
            $pager = $revServ->getPager(self::MAIN_REVIEWS_SECTION_PAGE_SIZE);
            $pager->setCurrentPage($page);

            if ($page == 0) {
                $this->params['totalReviews'] = $pager->countItems();
            }

            $this->params['reviews'] = $pager->getItems();
            $this->params['totalPages'] = $pager->count();
            $this->params['hasMorePages'] = $this->params['totalPages'] > $page + 1;
            $this->params['nextPageUrl'] = $this->generateUrl('user_profile_reviews_ajax', array('page' => $page + 1, 'username' => $username));
            $this->params['user'] = $user;
        } else {
            $this->params['totalReviews'] = '0';
            $this->params['reviews'] = "";
            $this->params['totalPages'] = "";
            $this->params['hasMorePages'] = "";
            $this->params['nextPageUrl'] = "";
        }

        return $this->render('WebsiteBundle:UserProfile:reviews-content.html.twig', $this->params);
    }

    public function favoritesAction($username, $_format = 'html') {
        if (!$this->isLoggedIn()) {
            return $this->redirectForLogin(null, null, null, $this->generateUrl('user_profile_favorites', array('username' => $username)));
        }

        $repo = $this->getDefaultRepository();
        $user = $repo->findOneBy(array('username' => $username));

        if ($this->getSessionUser()->getUsername() === $username) {
            $this->params['browse_mode'] = self::BROWSE_SELF;
        } else {
            $privacy = $user->getPrivacy();

            if (!$privacy->isProfileEnabled(Privacy::SHOW_LAST_FAVORITES)) {
                return $this->redirectTo($this->generateUrl('user_profile'), array('username' => $username));
            }

            $this->params['browse_mode'] = self::BROWSE_OTHER;
        }

        $this->params['user'] = $user;
        $this->params['domBodyClass'] = "user-favorites";

        if ($_format == 'json') {
            $this->params['format'] = \WebsiteBundle\Common\ClientApp\JsonResponse::TYPE_JSON;
            $result = $this->pagedFavoritesAction($username, 0, $this->params['format']);
        } else {
            $this->params['format'] = 'html';
            $result = $this->render('WebsiteBundle:UserProfile:favorites.html.twig', $this->params);
        }

        return $result;
    }

    public function pagedFavoritesAction($username, $page=0, $_format='html') {
        $user = $this->getDefaultRepository()->findOneBy(array('username' => $username));

        if ($user === null) {
            throw new NotFoundHttpException();
        }

        $success = true;

        $revServ = \WebsiteBundle\Common\AppService\FavoritesService::getInstance($this->container)->setOwnerUser($user);

        if (!$this->isLoggedIn()) {
            $success = false;
        }

        if ($this->getSessionUser()->getUsername() != $username) {
            $privacy = $user->getPrivacy();

            if (!$privacy->isProfileEnabled(Privacy::SHOW_LAST_FAVORITES)) {
                $success = false;
            }
        }

        if ($success) {
            /* @var $pager \WebsiteBundle\Common\UI\UzPager */
            $pager = $revServ->getPager(self::MAIN_FAVORITES_SECTION_PAGE_SIZE);
            $pager->setCurrentPage($page);

            if ($page == 0) {
                $this->params['totalFavorites'] = $pager->countItems();
            }

            $this->params['favorites'] = $pager->getItems();
            $this->params['totalPages'] = $pager->count();
            $this->params['hasMorePages'] = $this->params['totalPages'] > $page + 1;
            $this->params['nextPageUrl'] = $this->generateUrl('user_profile_favorites_paged', array('page' => $page + 1, 'username' => $username));
            $this->params['user'] = $user;
        } else {
            $this->params['totalFavorites'] = '0';
            $this->params['favorites'] = "";
            $this->params['totalPages'] = "";
            $this->params['hasMorePages'] = "";
            $this->params['nextPageUrl'] = "";
        }

        return $this->render('WebsiteBundle:UserProfile:favorites-content.html.twig', $this->params);
    }

    public function lastReviewsAction($username) {
        /* @var $user \Domain\Document\User */
        $user = $this->getDefaultRepository()->findOneBy(array('username' => $username));

        if ($username === $this->getSessionUser()->getUsername()) {
            $browse_mode = self::BROWSE_SELF;
        } else {
            $browse_mode = self::BROWSE_OTHER;
        }

        $params = array(
            'user' => $user,
            'browse_mode' => $browse_mode,
        );

        $content = array();
        $totRev = 1;
        $lastReviews = \WebsiteBundle\Common\AppService\ReviewsService::getInstance($this->container)->getLastActivities($user, self::LAST_REVIEWED_PAGE_SIZE, $totRev);
        foreach ($lastReviews as $activity) {
            $params['activity'] = $activity;
            $content[] = $this->renderView('WebsiteBundle:UserProfile:last-reviews-element.html.twig', $params);
        }

        $response = new \WebsiteBundle\Common\ClientApp\JsonResponse(true, $content);
        return $this->jsonResponse($response);
    }

    public function emptyLastReviewsAction($username) {
        /* @var $user \Domain\Document\User */
        $user = $this->getDefaultRepository()->findOneBy(array('username' => $username));

        if ($username === $this->getSessionUser()->getUsername()) {
            $browse_mode = self::BROWSE_SELF;
        } else {
            $browse_mode = self::BROWSE_OTHER;
        }

        $params = array(
            'user' => $user,
            'browse_mode' => $browse_mode,
            'lastReviewedTotal' => 0,
            'showLastReviews' => $user->getPrivacy()->isProfileEnabled(Privacy::SHOW_LAST_REVIEWS)
        );

        $content = $this->renderView('WebsiteBundle:UserProfile:last-reviews-empty.html.twig', $params);
        $response = new \WebsiteBundle\Common\ClientApp\JsonResponse(true, $content);
        return $this->jsonResponse($response);
    }

    /**
     * @see API
     *
     * @param string $username
     * @param string $contentType
     * @param string $contentSlug
     * @return void
     */
    public function userFavoriteAction($username, $contentType, $contentSlug) {
    }


    public function interestsAction($username) {
        $success = true;
        $content = "";
        if (!$this->isLoggedIn()) {
            $success = false;
        }

        if ($success) {
            $user = $this->getSessionUser();
            if ($user->getUsername() === $username) {
                $this->params['browse_mode'] = self::BROWSE_SELF;
            } else {
                $this->params['browse_mode'] = self::BROWSE_OTHER;
            }
            $this->params['selfUser'] = $user->getUsername() === $username;
            $this->params['user'] = $this->getDefaultRepository()->findOneBy(array('username' => $username));
            $this->params['onOverlay'] = true;

            $content = $this->renderView('WebsiteBundle:Preferences:interests_content.html.twig', $this->params);
        }

        $response = new \WebsiteBundle\Common\ClientApp\JsonResponse($success, array('content' => $content));
        return $this->jsonResponse($response);
    }

    /**
     * Returns true whether the given $user requires a new user experience module, false otherwise
     *
     * @param UserInterface $user
     * @return boolean
     */
    private function requireNewUserExperience($user) {
        $requireNUE = false;
        if ((!$user->getFirstName() || !$user->getLastName()) && !$user->getCity()) {
            $requireNUE = true;
        }

        return $requireNUE;
    }

    private function getUserByUsername($username) {
        return $this->getDefaultRepository()->findOneBy(array('username' => $username));
    }

    // API V2 Method
    public function profileAction(Request $request){}



}
