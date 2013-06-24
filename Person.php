<?php
/**
 * Person
 * Represents a person that can be involved in a movie (with different roles)
 *
 * This class is automatically mapped to a MongoDB collection by
 * Doctrine framework
 *
 */

namespace Domain\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Domain\Document\Extensions\SearchExtension;
use WebsiteBundle\Common\Search\SearchableFactory;
use Domain\Document\Interfaces\TransferObjectInterface;

/**
 * Person
 *
 * @MongoDB\Document(collection="people", repositoryClass="Domain\Repository\Repository")
 *
 */
class Person extends Document implements Sluggable, TransferObjectInterface {

    private static $SLUG_BUILDER_FIELDS = array('firstname', 'lastname');
    private static $SLUG_FIELD = 'slug';

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
    protected $slug;

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
     */
    protected $website;

    /**
     * @var string
     * @MongoDB\String
     */
    protected $gender;

    /**
     * @var string
     * @MongoDB\String
     */
    protected $bio;

    /**
     * @var String
     * @MongoDB\Collection
     */
    protected $roles = array();

    /**
     * @var string
     */
    const GENDER_MALE = 'm';

    /**
     * @var string
     */
    const GENDER_FEMALE = 'f';

    /**
     * @var string
     */
    const ROLE_DIRECTOR = 'director';

    /**
     * @var string
     */
    const ROLE_WRITER = 'writer';

    /**
     * @var string
     */
    const ROLE_ACTOR = 'actor';

    /**
     * @var string
     */
    const ROLE_PRODUCER = 'producer';

    /**
     * @var string
     */
    const ROLE_EDITOR = 'editor';

    /**
     * @var string
     */
    const ROLE_SINGER = 'singer';


    /**
     * Allows to extend the document functionality for search
     *
     */
    public function addSearchExtension() {
        $extension = new SearchExtension($this);
        $extension->setSearchType(SearchableFactory::TYPE_PERSON);
        $this->addExtension($extension);
    }


    /**
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @param $id string
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getSlug() {
        return $this->slug;
    }

    /**
     * @todo #beta implement a way to generate slugs
     * @param string $slug
     * @return void
     */
    public function setSlug($slug) {
        $this->slug = $slug;
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
     * @return string
     */
    public function getFirstname() {
        return $this->firstname;
    }

    /**
     * @param string $contentId
     * @return void
     */
    public function setFirstname($firstname) {
        $this->firstname = $firstname;
    }

    /**
     * @return string
     */
    public function getLastname() {
        return $this->lastname;
    }

    public function getFullName() {
        $name = $this->firstname;
        if (isset($this->lastname) && $this->lastname != ''){
            $name = $name.' '.$this->lastname;
        }
        return $name;
    }

    /**
     * Sets the fullname
     *
     * @todo #beta this is a hack. The fullname is set to the firstname
     *             We need to get rid of the firstname and secondname and
     *             just use fullname.
     * @param string $fullname
     */
    public function setFullName($fullname) {
        $this->firstname = $fullname;
        $this->lastname = '';
    }

    /**
     * @param string $contentId
     * @return void
     */
    public function setLastname($lastname) {
        $this->lastname = $lastname;
    }

    /**
     * @return string
     */
    public function getWebsite() {
        return $this->website;
    }

    /**
     * @param string $website
     * @return void
     */
    public function setWebsite($website) {
        $this->website = $website;
    }

    /**
     * @return string
     */
    public function getGender() {
        return $this->website;
    }

    /**
     * @param string $gender
     * @return void
     */
    public function setGender($gender) {
        if (\in_array($gender, array(self::GENDER_FEMALE, self::GENDER_MALE))) {
            $this->gender = $gender;
        }
    }

    /**
     * @return string
     */
    public function getRoles() {
        return $this->roles;
    }

    /**
     * @param array $roles
     * @return void
     * @throws InvalidArgumentException
     */
    public function setRoles($roles) {

        foreach ($roles as $role) {
            if (!$this->isAllowedRole($role)) {
                throw new \Domain\Exception\InvalidArgumentException('Role not supported');
            }
        }

        $this->roles = $roles;
        // @todo $roles is now an array - it should be fixed in Search
//        if (isset(self::$roleSearchTypeMatching[$roles])) {
//            $this->setSearchType(self::$roleSearchTypeMatching[$roles]);
//        }
    }

    /**
     * Adds a role to the current person
     * @param string $role Allowed role type
     * @return void
     * @throw InvalidArgumentException
     */
    public function addRole($role) {
        if ($this->isAllowedRole($role)) {
            $this->roles[] = $role;
        } else {
            throw new \Domain\Exception\InvalidArgumentException('Role not supported');
        }
    }

    /**
     * Returns true if the person has $role
     *
     * @param string $role
     * @return boolean
     */
    public function hasRole($role) {
        return \in_array($role, $this->roles);
    }

    /**
     * @return string
     */
    public function getBio() {
        return $this->bio;
    }

    /**
     * @param string $bio
     * @return void
     */
    public function setBio($bio) {
        $this->bio = $bio;
    }

    /**
     * Checks whether the given $role is allowed
     * @param string $role
     * @return boolean
     */
    private function isAllowedRole($role) {
        return \in_array($role, array(
            self::ROLE_ACTOR,
            self::ROLE_DIRECTOR,
            self::ROLE_PRODUCER,
            self::ROLE_WRITER,
            self::ROLE_SINGER,
            self::ROLE_EDITOR,
        ));
    }


    /**
     * @return void
     */
    public function generateSlug() {
        \WebsiteBundle\Common\Util\SlugGenerator::generate($this);
    }

    /**
     * @MongoDB\PrePersist
     * @return void
     */
    public function ensureSlug() {
        if ($this->slug === null) {
            $this->generateSlug();
        }
    }


    /**
     * @see Document
     */
    public function asArray() {
        // Data from the document
        $data = array();
        $data['id'] = $this->getId();
        $data['slug'] = $this->getSlug();
        $data['firstname'] = $this->firstname;
        $data['lastname'] = $this->lastname;
        $data['roles'] = $this->roles;

        return $data;
    }


    /**
     * @see Document
     */
    public function fromArray($data) {
        $this->setId($data['id']);
        $this->slug = isset($data['slug']) ?  $data['slug'] : NULL;

        $this->firstname = isset($data['firstname']) ?  $data['firstname'] : NULL;
        $this->lastname = isset($data['lastname']) ?  $data['lastname'] : NULL;
        $this->roles = isset($data['roles']) ?  $data['roles'] : array();
    }

    /**
     * @todo added for #beta in order to place content in
     *       the index fields
     */
    public function __toString() {
        return $this->firstname . " " . $this->lastname;
    }

    /**
     * @return string
     */
    public function getTransferObjectName() {
        return 'PersonDTO';
    }

}
