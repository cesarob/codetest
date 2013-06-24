<?php

/**
 * Main User object
 *
 * This class is automatically mapped to a MongoDB document by
 * Doctrine framework
 *
 * @since 1.0
 */

namespace Domain\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Domain\Document\Interfaces;
use Domain\Document\Avatar;
use Domain\Document\UserProfile;
use Domain\Document\State;
use Domain\Document\City;
use Symfony\Component\Security\Core\User\AdvancedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use WebsiteBundle\Common\Authorization\RoleHelper;
use WebsiteBundle\Common\Authorization\Role;
use WebsiteBundle\Common\Util\DateTimeHelper;
use Domain\Document\Interests;
use Domain\Document\Interfaces\UserInterface;
use Domain\Document\Interfaces\TransferObjectInterface;

/**
 *
 * @MongoDB\Document(collection="users", repositoryClass="Domain\Repository\UserRepository")
 *
 */
class User implements UserInterface, Sluggable, TransferObjectInterface {

    private static $SLUG_BUILDER_FIELDS = array('username');
    private static $SLUG_FIELD = 'username';
    private static $CREDIT_CARD_ANONYMIZED_SIZE = 16;
    /**
     * @var string
     * @MongoDB\Id
     */
    protected $id;

    /**
     * @var string
     * @MongoDB\String
     * @MongoDB\Index(unique=true, safe="true")
     */
    protected $username;
    /**
     * @var string
     * @MongoDB\String
     */
    protected $firstname;
    /**
     * @var string
     * @MongoDB\String
     */
    protected $lastname;
    /**
     * @var string
     * @MongoDB\String
     * @MongoDB\Index(unique=true, order="asc", safe="true")
     * @assert:Email()
     * @assert:NotBlank()
     */
    protected $email;
    /**
     * This index cannot be UNIQUE since non-facebook users will have a NULL here
     * @see http://www.mongodb.org/display/DOCS/Indexes
     *
     * @var string
     * @MongoDB\String
     * @MongoDB\Index(order="asc", safe="true")
     */
    protected $facebookId;
    /**
     * Old facebook ID. Set to facebookId when disconnected from Facebook.
     * @var string
     * @MongoDB\String
     */
    protected $oldFacebookId;
    /**
     * Twitter Id
     *
     * @var string
     * @MongoDB\String
     */
    protected $twitterId;
    /**
     * Old Twitter Id
     *
     * @var string
     * @MongoDB\String
     */
    protected $oldTwitterId;
    /**
     * Twitter OAuth Token
     *
     * @var string
     * @MongoDB\String
     */
    protected $twitterToken;
    /**
     * Twitter OAuth Secret
     *
     * @var string
     * @MongoDB\String
     */
    protected $twitterSecret;
    /**
     * @var string
     * @MongoDB\String
     */
    protected $facebookToken;
    /**
     * @var string
     * @MongoDB\String
     */
    protected $gender;
    /**
     * @var Doctrine\ODM\MongoDB\Mapping\Types\DateType
     * @MongoDB\Date
     */
    protected $dateOfBirth;
    /**
     * @var string
     * @MongoDB\String
     * @assert:NotBlank()
     */
    protected $password;
    /**
     * @var string
     * @MongoDB\String
     */
    protected $passwordResetHash;
    /**
     * @var string
     * @MongoDB\String
     */
    protected $country;
    /**
     * @var Domain\Document\State
     * @MongoDB\ReferenceOne(targetDocument="Domain\Document\State", cascade="all")
     */
    protected $state;
    /**
     * @var string
     * @MongoDB\String
     */
    protected $city;
    /**
     * @var string
     * @MongoDB\String
     */
    protected $zip;
    /**
     * @var string
     * @MongoDB\String
     */
    protected $locale = 'es';
    /**
     * Specifies whether the user account is confirmed
     * @var string
     * @MongoDB\Boolean
     */
    protected $confirmed = false;
    /**
     * User status (changes the behavior of the application)
     * @var integer
     * @MongoDB\Int
     */
    protected $status = self::STATUS_ACTIVE;
    /**
     * Flag to indicate the date when the user was marked as 'marked_to_remove'
     *
     * @var \DateTime
     * @MongoDB\Date
     *
     */
    protected $removeRequestDate = null;
    /**
     * User profile
     * @var UserProfile
     * @MongoDB\ReferenceOne(targetDocument="Domain\Document\UserProfile", cascade="persist")
     */
    protected $userProfile;
    /**
     * User avatar
     * @var Avatar
     * @MongoDB\EmbedOne(targetDocument="Domain\Document\Avatar")
     */
    protected $avatar;
    /**
     * User avatar
     * @var Avatar
     * @MongoDB\EmbedOne(targetDocument="Domain\Document\Avatar")
     */
    protected $mediumAvatar;
    /**
     * User avatar
     * @var Avatar
     * @MongoDB\EmbedOne(targetDocument="Domain\Document\Avatar")
     */
    protected $smallAvatar;
    /**
     * User settings
     * @var Settings
     * @MongoDB\EmbedOne(targetDocument="Domain\Document\Settings")
     */
    protected $settings;
    /**
     * @var string
     * @MongoDB\Int
     */
    protected $confirmationCode;
    /**
     * @var integer
     * @MongoDB\Int
     */
    protected $tosVersion;
    /**
     * @MongoDB\ReferenceMany(targetDocument="Domain\Document\User", inversedBy="followers")
     */
    protected $following;
    /**
     * @MongoDB\ReferenceMany(targetDocument="Domain\Document\User", mappedBy="following")
     */
    protected $followers;
    /**
     * This is an integer representing user's Roles.
     * @MongoDB\Int
     */
    protected $roles = 0;
    /**
     * @MongoDB\EmbedOne(targetDocument="Domain\Document\Privacy")
     *
     * @var Privacy
     */
    protected $privacy;
    /**
     * @var Subscription
     * @MongoDB\EmbedOne(
     *      discriminatorField="type",
     *      discriminatorMap={
     *          "none"="NoneSubscription",
     *          "basic"="Subscription",
     *          "trial"="Subscription",
     *          "quicktrial"="QuickTrialSubscription",
     *          "promocode"="PromoCodeSubscription"
     *      }
     *  )
     */
    protected $subscription;
    /**
     * @var InboxMetaData
     */
    protected $inboxMetaData;
    /**
     * Facebook Id's that have been removed from friends.
     *
     * @MongoDB\Hash
     * @var array
     */
    protected $ignoreFacebookIds = array();

    /**
     * Flag to indicate the date the singup process took place.
     *
     * @var \DateTime
     * @MongoDB\Date
     *
     */
    protected $signUpDate = null;

    /**
     * @var DeletedUser
     * @MongoDB\ReferenceOne(targetDocument="Domain\Document\DeletedUser")
     */
    protected $deletedUser;

    /**
     * True when we need to generate the slug
     * @var boolean
     */
    private $updateSlugFlag = false;

    /**
     * Flag to indicate that the crm needs to be updated.
     *
     * @var \DateTime
     * @MongoDB\Date
     *
     */
    private $dirtyInCrm = null;

    /**
     * Allows to disable the update of the crm
     */
    private $updateCrm = true;

    /**
     * Lite version of the User
     * @var UserLite
     */
    private $userLite = null;


    /**
     * @var UserCampaign
     * @MongoDB\EmbedOne(targetDocument="Domain\Document\UserCampaign")
     */
    protected $campaign = null;


    /**
     * @var string
     */
    const GENDER_MALE = 'm';

    /**
     * @var string
     */
    const GENDER_FEMALE = 'f';

    // Statuses
    const STATUS_ACTIVE = 1;
    const STATUS_DISABLED = 2;
    const STATUS_DISABLED_BY_ADMIN = 3;
    const STATUS_MARKED_TO_REMOVE = 4;
    const STATUS_DELETED = 5;

    /**
     * Period in hours
     * @var string
     */
    const FACEBOOK_INCOMPLETE = '2';

    /**
     * Period in hours
     * @var string
     */
    const REGISTRATION_INCOMPLETE = '168'; // a week

    /**
     * Security salt for password storage/comprobation
     * @var string
     */
    const PASSWORD_SECURITY_SALT = 'sdkfj34788x.23489tgbhmfgrh';

    public function __construct() {
        $this->followers = new \Doctrine\Common\Collections\ArrayCollection();
        $this->following = new \Doctrine\Common\Collections\ArrayCollection();
        $this->userProfile = new UserProfile();
        $this->userProfile->setUser($this);
        $this->smallAvatar = new Avatar();
        $this->mediumAvatar = new Avatar();
        $this->avatar = new Avatar();
        $this->settings = new Settings();
        $this->privacy = new Privacy();
        $this->subscription = new NoneSubscription();
        $this->inboxMetaData = new InboxMetaData();
        $this->signUpDate = new \DateTime('now');
    }

    /**
     * This static method is intented to be used as a callback when calling usort
     * for sorting User arrays by the User's name
     * @param User $u1 first user
     * @param User $u2 second user
     * @return 0 if they have the same name, -1 if $u1 should be first abd 1 if not.
     */
    public static function compareByName($u1, $u2) {
        $result = 1;
        if ($u1->getFullName() === $u2->getFullName()) {
            $result = 0;
        } else if ($u1->getFullName() < $u2->getFullName()) {
            $result = -1;
        }

        return $result;
    }

    /**
     * This static method is intented to be used as a callback when calling usort
     * for sorting User arrays by the User's id
     * @param User $u1 first user
     * @param User $u2 second user
     * @return 0 if they have the same name, -1 if $u1 should be first abd 1 if not.
     */
    public static function compareById($u1, $u2) {
        $result = 1;
        if ($u1->getId() === $u2->getId()) {
            $result = 0;
        } else if ($u1->getId() < $u2->getId()) {
            $result = -1;
        }

        return $result;
    }

    /**
     *
     * @return Settings
     */
    public function getSettings() {
        return $this->settings;
    }

    /**
     *
     * @param Settings $settings
     */
    public function setSettings(Settings $settings) {
        $this->settings = $settings;
    }

    /**
     * @return User[] followed by this User
     */
    public function getFollowing() {
        if (!$this->following) {
            return array();
        }
        return $this->following->toArray();
    }

    public function setFollowing(\Doctrine\Common\Collections\ArrayCollection $following) {
        $this->following = $following;
    }

    /**
     * @return User[] of the users following this user
     */
    public function getFollowers() {
        return $this->followers->toArray();
    }

    public function setFollowers(\Doctrine\Common\Collections\ArrayCollection $followers) {
        $this->followers = $followers;
    }

    /**
     * This user starts to follow $following user.
     * @param User $following that this User is following
     * @return true if started following and false if was already following. This
     * Can be used later on for notifications
     */
    public function addFollowing(User $following) {
        $result = true;

        if($following->getId() === $this->getId()){
            $result = false;
        }

        if($result){
            foreach($this->following as $user){
                if($user->getId() === $following->getId()){
                    $result =  false;
                    break;
                }
            }
        }

        if($result){
            $this->following[] = $following;
            $following->followers[] = $this;
        }

        return $result;
    }

    public function removeFollowing(User $friend) {
        foreach ($this->following as $key => $user) {
            if ($user->getId() === $friend->getId()) {
                unset($this->following[$key]);
                // hack!!!
                // This breakage is done by mongo but it is not done inmediately so
                // we are breaking it explicitly here.
                $friend->removeFollower($this);
                return true;
            }

        }
        return false;
    }

    public function removeFollower(User $friend) {
        foreach ($this->followers as $key => $user) {
            if ($user->getId() === $friend->getId()) {
                unset($this->followers[$key]);
                return true;
            }

        }
        return false;
    }

    /**
     * @return the $avatar
     */
    public function getAvatar() {
        return $this->avatar;
    }

    /**
     * @param Avatar $avatar
     */
    public function setAvatar(Avatar $avatar) {
        $this->avatar = $avatar;
    }

    /**
     * @return the $avatar
     */
    public function getSmallAvatar() {
        return $this->smallAvatar;
    }

    /**
     * @param Avatar $avatar
     */
    public function setSmallAvatar(Avatar $avatar) {
        $this->smallAvatar = $avatar;
    }

    /**
     * @return the $avatar
     */
    public function getMediumAvatar() {
        return $this->mediumAvatar;
    }

    /**
     * @param Avatar $avatar
     */
    public function setMediumAvatar(Avatar $avatar) {
        $this->mediumAvatar = $avatar;
    }

    /**
     * @return UserProfile
     */
    public function getUserProfile() {
        return $this->userProfile;
    }

    /**
     * @param UserProfile $userProfile (important, it could be null)
     * @return void
     */
    public function setUserProfile($userProfile) {
        $this->userProfile = $userProfile;
    }

    /**
     * Get id
     * @return integer $id
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param int $id
     * @return void
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * Set first name
     *
     * @param string $firstname
     */
    public function setFirstName($firstname) {
        $this->firstname = $firstname;
    }

    /**
     * Get first name
     *
     * @return string $name
     */
    public function getFirstName() {
        return $this->firstname;
    }

    /**
     * Set last name
     *
     * @param string $lastname
     */
    public function setLastName($lastname) {
        $this->lastname = $lastname;
    }

    /**
     * Get first name
     *
     * @return string $name
     */
    public function getLastName() {
        return $this->lastname;
    }

    /**
     * Get full name
     *
     * @return string Returns the first name + last name
     */
    public function getFullName() {
        return $this->firstname . ' ' . $this->lastname;
    }

    /**
     * Set email
     *
     * @param string $email
     */
    public function setEmail($email) {
        $this->email = \mb_strtolower($email, 'UTF-8');
    }

    /**
     * Get email
     *
     * @return string $email
     */
    public function getEmail() {
        return \mb_strtolower($this->email, 'UTF-8');
    }

    /**
     * Set gender
     *
     * @param string $gender
     */
    public function setGender($gender) {
        if (in_array($gender, array(self::GENDER_MALE, self::GENDER_FEMALE))) {
            $this->gender = $gender;
        }
    }

    /**
     * Get gender
     *
     * @return string Returns the gender (User::GENDER_MALE or User::GENDER_FEMALE)
     */
    public function getGender() {
        return $this->gender;
    }

    /**
     * Set date of birth
     *
     * @param string $date
     */
    public function setDateOfBirth($date) {
        $this->dateOfBirth = $date;
    }

    /**
     * Get date of birth
     *
     * @return \DateTime Returns the date of birth
     */
    public function getDateOfBirth() {
        return $this->dateOfBirth;
    }

    /**
     * Returns current user's age
     *
     * @todo #beta Implement.
     * @return integer
     */
    public function getAge() {
        return 0;
    }

    /**
     * @param string $username
     * @return void
     */
    public function setUsername($username) {
        $this->username = $username;
        $this->updateSlugFlag = true;
    }

    /**
     * @return string
     */
    public function getUsername() {
        return $this->username;
    }

    /**
     * Sets a user password, the given parameter gets encrypted by using
     * a SHA1 algorithm
     *
     * @param string $password
     * @return void
     */
    public function setPassword($password) {
        // Simple check - it should contain something
        if (!empty($password)) {
            //Now the password encryption is made by AuthenticationService,
            //so here, it should be just set.
            $this->password = $password;
        }
    }

    /**
     * @return string
     */
    public function getPassword() {
        return $this->password;
    }

    /**
     * Returns true whether the password matches
     *
     * @param string $password
     * @return boolean
     */
    public function isCorrectPassword($password, $encoded = false) {
        throw new \Exception("This should be performed by AuthenticationService");
        return $encoded ? $this->getPassword() === $password : $this->getPassword() === $this->getEncodedPassword($password);
    }

    /**
     * Returns a string with a encoded version of the given password
     *
     * @param string $password
     * @return string
     */
    private function getEncodedPassword($password) {
        throw new \Exception("This should be performed by AuthenticationService");
        return sha1($password . self::PASSWORD_SECURITY_SALT);
    }

    /**
     * Sets the hash used to reset a password
     *
     * @return void
     */
    public function setPasswordResetHash() {
        $this->passwordResetHash = \sha1(\uniqid());
    }

    /**
     * Gets a password hash
     *
     * @return void
     */
    public function getPasswordResetHash() {
        return $this->passwordResetHash;
    }

    /**
     * @param string $country The country code
     * @return void
     */
    public function setCountry($country) {
        $this->country = $country;
    }

    /**
     * @return Domain\Document\Country
     */
    public function getCountry() {
        return $this->country;
    }

    /**
     * @param Domain\Document\State $state
     * @return void
     */
    public function setState(\Domain\Document\State $state) {
        $this->state = $state;
    }

    /**
     * @return Domain\Document\State
     */
    public function getState() {
        return $this->state;
    }

    /**
     * @param string $city
     * @return void
     */
    public function setCity($city) {
        $this->city = $city;
    }

    /**
     * @return string
     */
    public function getCity() {
        return $this->city;
    }

    /**
     * @param string $zip
     * @return void
     */
    public function setZip($zip) {
        $this->zip = $zip;
    }

    /**
     * @return string
     */
    public function getZip() {
        return $this->zip;
    }

    /**
     * @param string $locale
     * @return void
     */
    public function setLocale($locale) {
        $this->locale = $locale;
    }

    /**
     * @return string
     */
    public function getLocale() {
        return $this->locale;
    }

    /**
     * @param boolean $confirmed
     * @return void
     */
    public function setConfirmed($confirmed) {
        $this->confirmed = $confirmed;
    }

    /**
     * @return boolean
     */
    public function getConfirmed() {
        return $this->confirmed;
    }

    /**
     * @param int $status
     * @return void
     */
    public function setStatus($status) {
        $this->status = $status;
    }

    /**
     * @return int
     */
    public function getStatus() {
        return $this->status;
    }

    /**
     * @param \DateTime $date
     * @return void
     */
    public function setRemoveRequestDate($date) {
        $this->removeRequestDate = $date;
    }

    /**
     * @return date
     */
    public function getRemoveRequestDate() {
        return $this->removeRequestDate;
    }


    /**
     * @param \DateTime $date
     * @return void
     */
    public function setSignUpDate($date) {
        $this->signUpDate = $date;
    }

    /**
     * @return \DateTime
     */
    public function getSignUpDate() {
        return $this->signUpDate;
    }

    /**
     * Returns true when the user account is confirmed by the user,
     * which means that the e-mail validation process was successful
     *
     * @return boolean
     */
    public function isAccountConfirmed() {
        return $this->confirmed === true;
    }

    /**
     * Returns true whether the user is disabled (normal or admin disabling)
     *
     * @return boolean
     */
    public function isDisabled() {
        return ($this->status === self::STATUS_DISABLED)
        || ($this->status === self::STATUS_DISABLED_BY_ADMIN);
    }

    /**
     * @return boolean True when the user was previously deleted
     */
    public function isDeleted() {
        return $this->status === self::STATUS_DELETED;
    }

    public function getDeletedUser() {
        return $this->deletedUser;
    }

    public function setDeletedUser(DeletedUser $deletedUser) {
        $this->deletedUser = $deletedUser;
    }

    /**
     * Notifies to the CRM about the new updated user
     *
     * @MongoDB\PostPersist
     * @MongoDB\PostUpdate
     * @return void
     */
    public function postUpdate() {
        $this->updateCrm();
    }

    /**
     * Returns a JSON string representation of the object
     * It does not include the password value
     *
     * @return string
     */
    public function asJson() {
        $data = $this->asArray();
        return json_encode($data);
    }

    /**
     * Returns a JSON string representation of the whole object
     * It does not include the password value
     *
     * @return string
     */
    public function asCompleteJson() {
        $data = $this->asCompleteArray();
        return json_encode($data);
    }

    /**
     * Populates data from Json
     *
     * @param string|array $json
     * @return void
     */
    public function fromJson($json) {
        $data = \json_decode($json, TRUE);
        $this->fromArray($data);
    }


    /**
     * Returns the user data as an associative array
     * It does not cinlcude the password value
     *
     * @return array
     */
    public function asCompleteArray() {
        $reflection = new \ReflectionClass($this);
        $data = array();

        foreach ($reflection->getProperties() as $prop) {
            $prop->setAccessible(true);
            // @todo : this is a nasty hack to avoid the crash when we try to retrieve an user
            // with a lot of followers/following (pr user, for example).
            // This need to be refactored, we need to implement the medium and large versions of userDTO
            if($prop->getName() != "followers" && $prop->getName() != "following") {
                $data[$prop->getName()] = $prop->getValue($this);
            }
        }

      // Password is never returned in this format
        unset($data['password']);
        return $data;
    }

    public function asArray() {
        $data = array();

        $data['id'] = $this->id;
        $data['email'] = $this->email;
        $data['username'] = $this->username;
        $data['firstname'] = $this->firstname;
        $data['lastname'] = $this->lastname;
        $data['gender'] = $this->gender;
        // @todo define the format for the interchange
        // $data['dateOfBirth'] = $this->dateOfBirth;
        // @todo has been removed?
        //$data['address'] = $this->address;
        $data['country'] = $this->country;
        $data['state'] = $this->state;
        $data['city'] = $this->city;
        $data['zip'] = $this->zip;

        return $data;
    }



    public function fromArray($data) {
        $this->setArrayField('id', $data);
        $this->setArrayField('email', $data);
        $this->setArrayField('username', $data);
        $this->setArrayField('firstname', $data);
        $this->setArrayField('lastname', $data);
        $this->setArrayField('gender', $data);
        $this->setArrayField('country', $data);
        $this->setArrayField('state', $data);
        $this->setArrayField('city', $data);
        $this->setArrayField('zip', $data);

        // @todo define the format for the interchange
        //$this->dateOfBirth = $data['dateOfBirth'];
        // @todo has been removed?
        //$this->address = $data['address'];
    }


    /**
     * @param integer
     * @return void
     */
    public function setFacebookId($fbId) {
        $this->facebookId = $fbId;
    }

    /**
     * @return integer
     */
    public function getFacebookId() {
        return $this->facebookId;
    }

    /**
     * @param integer $version
     * @return void
     */
    public function setTosVersion($version) {
        $this->tosVersion = $version;
    }

    /**
     * @return integer
     */
    public function getTosVersion() {
        return $this->tosVersion;
    }

    /**
     * @param Subscription $subscription
     * @return void
     */
    public function setSubscription(Subscription $subscription) {
        $this->subscription = $subscription;
    }

    /**
     * @return Subscription
     */
    public function getSubscription() {
        return $this->subscription;
    }

    /**
     * @param integer $code
     * @return void
     */
    public function setConfirmationCode($code = null) {
        if ($code === null) {
            $code = $this->generateConfirmationCode();
        } else if ($code !== null && !is_int($code)) {
            $code = intval($code);
        }

        $this->confirmationCode = $code;
    }

    /**
     * @return integer
     */
    public function getConfirmationCode() {
        return $this->confirmationCode;
    }

    /**
     * @return integer
     */
    private function generateConfirmationCode() {
        return rand(10000, 99999);
    }

    /**
     *
     * @param UserInterface $user
     * @return Boolean
     */
    public function equals(UserInterface $user) {
        return ($user->getUsername() === $this->getUsername() &&
        $user->getPassword() === $this->getPassword() &&
        $user->getSalt() === $this->getSalt());
    }

    /**
     * @todo #beta implement
     */
    public function eraseCredentials() {

    }

    public function getRoles() {
        return RoleHelper::getInstance()->getRolesForMask($this->roles);
    }

    public function getSalt() {
        return $this->getId() . self::PASSWORD_SECURITY_SALT;
    }

    public function isAccountNonExpired() {
        return ($this->status === self::STATUS_ACTIVE && $this->confirmed);
    }

    public function isAccountNonLocked() {
        return $this->isAccountNonExpired();
    }

    public function isCredentialsNonExpired() {
        return $this->isAccountNonExpired();
    }

    public function isEnabled() {
        return ($this->status === self::STATUS_ACTIVE);
    }

    public function importRoles($roles) {
        $this->roles = $roles;
    }

    public function addRole(Role $role) {
        $this->roles = $this->roles | $role->getValue();
    }

    public function removeRole(Role $role) {
        $this->roles = $this->roles & (~$role->getValue());
    }

    /**
     * Returns true whether the user has the given role
     *
     * @param int $role RoleHelper::SUBSCRIBED_ROLE | RoleHelper::CONTENT_ADMIN_ROLE | ...
     * @return boolean
     */
    public function hasRole($role) {
        return RoleHelper::getInstance()->hasRoleInMask($this->roles, $role);
    }

    /**
     * @todo #beta verify and fix
     * @return int
     */
    public function getRolesMask() {
        return $this->roles;
    }

    //This is just for being able to connect this Document with the twig forms:
    public function getLocation() {
        if ($this->city == null) {
            return "";
        }
        return $this->city;
    }

    /**
     * @todo Move this to Interests object
     * @return array
     */
    public function getInterestsAsStrings() {
        $returnList = array();
        /* @var $interests Interests */
        $interests = $this->getUserProfile()->getInterests();

        if (!$interests) {
            return array();
        }

        /* @var $movie Movie */
        $movies = $interests->getMovies();
        foreach ($movies as $movie) {

            $returnList[$movie->getName()] = $movie->getId();
        }

        /* @var $tvShow \Domain\Document\TvShow */
        $tvShows = $interests->getTvShows();
        foreach ($tvShows as $tvShow) {
            $returnList[$tvShow->getName()] = $tvShow->getId();
        }

        /* @var $director Person */
        $directors = $interests->getDirectors();
        foreach ($directors as $director) {
            $returnList[$director->getName()] = $director->getId();
        }

        /* @var $actor Person */
        $actors = $interests->getActors();
        foreach ($actors as $actor) {
            $returnList[$actor->getName()] = $actor->getId();
        }

        /* @var $genre Genre */
        $genres = $interests->getGenres();
        foreach ($genres as $genre) {
            $returnList[$genre->getLocalized()->getName()] = $genre->getId();
        }

        return $returnList;
    }

    public function getPrivacy() {
        return $this->privacy;
    }

    public function setPrivacy($privacy) {
        $this->privacy = $privacy;
    }

    /**
     * @return InboxMetaData
     */
    public function getInboxMetaData() {
        return $this->getUserLite()->getInboxMetadata();
    }

    /**
     * @param int $facebookId
     * @return void
     */
    public function ignoreFacebookFriend($facebookId) {
        $this->ignoreFacebookIds[$facebookId] = true;
    }

    /**
     * @param int $facebookId
     * @return void
     */
    public function unignoreFacebookFriend($facebookId) {
        unset($this->ignoreFacebookIds[$facebookId]);
    }

    /**
     * @param int $facebookId
     * @return boolean
     */
    public function isFacebookFriendIgnored($facebookId) {
        if (!isset($this->ignoreFacebookIds[$facebookId])) {
            return false;
        }
        return $this->ignoreFacebookIds[$facebookId];
    }

    /**
     * @param string $facebookToken
     * @return void
     */
    public function setFacebookToken($facebookToken) {
        $this->facebookToken = $facebookToken;

        if ($facebookToken) {
            $this->settings->enableFacebookPublishing();
            $this->settings->setDefaultFacebookAction(true);
        } else {
            $this->settings->disableFacebookPublishing();
        }
    }

    /**
     * @return string
     */
    public function getFacebookToken() {
        return $this->facebookToken;
    }

    /**
     * @return User
     */
    public function getDocument() {
        return $this;
    }

    /**
     * @return Lite\UserLite
     */
    public function getUserLite($light = true) {
        if($this->userLite == null) {
            $this->userLite = new Lite\UserLite($this, $light);
        }
        return $this->userLite;
    }

    public function getTwitterId() {
        return $this->twitterId;
    }

    public function setTwitterId($twitterId) {
        $this->twitterId = $twitterId;
    }

    public function getOldFacebookId() {
        return $this->oldFacebookId;
    }

    public function setOldFacebookId($facebookId) {
        $this->oldFacebookId = $facebookId;
        $this->settings->setDefaultFacebookAction(false);
    }

    public function getTwitterToken() {
        return $this->twitterToken;
    }

    public function setTwitterToken($token) {
        $this->twitterToken = $token;

        if ($token) {
            $this->settings->enableTwitterPublishing();
            $this->settings->setDefaultTwitterAction(true);
        } else {
            $this->settings->disableTwitterPublishing();
        }
    }

    public function getTwitterSecret() {
        return $this->twitterSecret;
    }

    public function setTwitterSecret($secret) {
        $this->twitterSecret = $secret;
    }

    public function isTwitterLinked() {
        return $this->twitterSecret && $this->twitterToken && $this->twitterId;
    }

    public function setOldTwitterId($twitterId) {
        $this->oldTwitterId = $twitterId;
    }

    public function getOldTwitterId() {
        return $this->oldTwitterId;
    }

    /**
     * @see Sluggable
     */
    public function getSlug() {
        $field = self::$SLUG_FIELD;
        return $this->$field;
    }

    /**
     * @see Sluggable
     */
    public function setSlug($slug) {
        $field = self::$SLUG_FIELD;
        $this->$field = $slug;
    }

    /**
     * @see Sluggable
     */
    public static function getSlugBuilderFields() {
        return self::$SLUG_BUILDER_FIELDS;
    }

    /**
     * @see Sluggable
     */
    public static function getSlugField() {
        return self::$SLUG_FIELD;
    }

    /**
     * @MongoDB\PrePersist
     * @return void
     */
    public function ensureSlug() {
        if ($this->username === null) {
            $this->updateSlugFlag = true;
            //Hack to generate slug from full name
            $this->username = $this->getFullName();
        }
        if ($this->updateSlugFlag) {
            $this->generateSlug();
        }
    }

    /**
     * @return void
     */
    public function generateSlug() {
        \WebsiteBundle\Common\Util\SlugGenerator::generate($this);
    }

    public function getDirtyInCrm() {
        return $this->dirtyInCrm;
    }

    public function setDirtyInCrm($dirtyInCrm) {
        $this->dirtyInCrm = $dirtyInCrm;
    }

    public function setUpdateCrm($value) {
        $this->updateCrm = $value;
    }

    public function getUpdateCrm() {
        return $this->updateCrm;
    }

    public function updateCrm() {
        // We could also use getNowTimestamp()
        if ($this->updateCrm) {
            $this->dirtyInCrm = \WebsiteBundle\Common\Util\DateTimeHelper::getNow();
        }
    }

    /**
     * Indicates if it is posisble to delete the user
     * @return boolean
     */
    public function deletable() {
        if ($this->getStatus() === self::STATUS_MARKED_TO_REMOVE) {
            return true;
        }

        $nowDate = new \DateTime('now');
        $userRegistartionDate = $this->getSignUpDate();
        $signUpInterval = $nowDate->diff($userRegistartionDate);
        $hoursSinceSignUp = $signUpInterval->format('%a') * 24 + $signUpInterval->format('%h');

        if ($this->getFacebookId() !== null && empty($this->password)
                && $hoursSinceSignUp > self::FACEBOOK_INCOMPLETE) {
            return true;
        } else if (!$this->isAccountConfirmed()
                && $hoursSinceSignUp > self::REGISTRATION_INCOMPLETE) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getTransferObjectName() {
        return 'UserDTO';
    }

    /**
     *
     */

    public function markToBeRemoved() {
        $this->setStatus(self::STATUS_MARKED_TO_REMOVE);
        $this->setRemoveRequestDate(DateTimeHelper::getNow());
    }

    /**
     * @return bool
     */
    public function hasDefaultPaymentMethod() {
        $paymentMethod = $this->settings->getPaymentMethod();

        return $paymentMethod !== null;
    }

    /**
     * @return string
     */
    public function getDefaultPaymentMethod() {
        return $this->settings->getPaymentMethod();
    }

    /**
     * @return array
     */
    public function getCreditCardDataAsArray() {
        $creditCardPreview = $this->settings->getCreditCardPreview();
        if (empty($creditCardPreview)) {
            $creditCardPreview = '';
            $creditCardCcv = '';
            $fullFormValidation = 'true';
        } else {
            $creditCardPreview = \str_pad($creditCardPreview, self::$CREDIT_CARD_ANONYMIZED_SIZE, '****', STR_PAD_LEFT);
            $creditCardCcv = '***';
            $fullFormValidation = 'false';
        }

        return array(
            'creditCardExpiryMonth' => $this->settings->getCreditCardExpiryMonth(),
            'creditCardExpiryYear' => $this->settings->getCreditCardExpiryYear(),
            'creditCardPreview' => $creditCardPreview,
            'creditCardCcv' => $creditCardCcv,
            'creditCardOwner' => $this->settings->getCreditCardHolderName(),
            'creditCardAddress' => $this->settings->getCreditCardAddress(),
            'creditCardCity' => $this->settings->getCreditCardCity(),
            'creditCardZip' => $this->settings->getCreditCardZip(),
            'fullFormValidation' => $fullFormValidation,
        );
    }

    public function getCampaign() {
        return $this->campaign;
    }

    /**
     *
     */
    public function setCampaign(UserCampaign $campaign = null) {
        $this->campaign = $campaign;
    }


    protected function setArrayField($field, $data) {
        if (isset($data[$field])) {
            // caveat, if null is coming in the array, it won't override previous
            // set values
            $method = "set" . $field;
            $this->$method($data[$field]);
        }
    }

}
