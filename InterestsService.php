<?php


namespace WebsiteBundle\Common\AppService;

use Domain\Document\Interests;
use Doctrine\ODM\MongoDB\MongoDBException;

use Domain\Document\Interfaces\UserInterface;


class InterestsService extends AppService{

    const PREVIEW_LENGTH = 100;
    /**
     *
     * @var UserInterface
     */
    private $user;
    /**
     * @param \Symfony\Component\DependencyInjection\Container $container Optional
     * @return InterestsService
     */
    public static function getInstance($container = null) {
        return parent::getInstance($container);
    }

    /**
     * @param UserInterface $user
     * @return InterestsService
     */
    public function setUser(UserInterface $user){
        $this->user = $user;
        return $this;
    }

    /**
     * Adds movie to intersts
     *
     * @param string $slug of the movie
     * @param boolean $autoflush defines if it has to be commited to the DB
     * @return boolean true if added.
     */
    public function addMovieBySlug($slug, $autoflush = true){
        if(!$this->user){
            throw new \InvalidArgumentException("You have to set an user first");
        }

        $interests = $this->getInterests();

        $movies = $interests->getMovies();

        foreach($movies as $movie){
            if($movie->getSlug() === $slug){
                return false;
            }
        }

        $movie = $this->getDm()->getRepository('\Domain\\Document\\Movie')->findOneBy(array('slug' => $slug));

        $dm = $this->getDm();

        $interests->addMovie($movie);

        $dm->persist($interests);

        if($autoflush){
            $this->getDm()->flush(array('safe' => true));
        }
        return true;
    }

    /**
     * Adds tvshow to intersts
     *
     * @param string $slug of the tvshow
     * @param boolean $autoflush defines if it has to be commited to the DB
     * @return boolean true if added.
     */
    public function addTvShowBySlug($slug, $autoflush = true){
        if(!$this->user){
            throw new \InvalidArgumentException("You have to set an user first");
        }

        $interests = $this->getInterests();

        $tvshows = $interests->getTvShows();

        foreach($tvshows as $tvshow){
            if($tvshow->getSlug() === $slug){
                return false;
            }
        }

        $tvshow = $this->getDm()->getRepository('\Domain\\Document\\TvShow')->findOneBy(array('slug' => $slug));

        $dm = $this->getDm();

        $interests->addTvShow($tvshow);

        $dm->persist($interests);

        if($autoflush){
            $this->getDm()->flush(array('safe' => true));
        }
        return true;
    }

    /**
     * Adds director to intersts
     *
     * @param string $slug of the director
     * @param boolean $autoflush defines if it has to be commited to the DB
     * @return boolean true if added.
     */
    public function addDirectorBySlug($slug, $autoflush = true){
        if(!$this->user){
            throw new \InvalidArgumentException("You have to set an user first");
        }

        $interests = $this->getInterests();

        $directors = $interests->getDirectors();

        foreach($directors as $director){
            if($director->getSlug() === $slug){
                return false;
            }
        }

        $director = $this->getDm()->getRepository('\Domain\\Document\\Person')->findOneBy(array('slug' => $slug));

        $dm = $this->getDm();

        $interests->addDirector($director);

        $dm->persist($interests);

        if($autoflush){
            $this->getDm()->flush(array('safe' => true));
        }
        return true;
    }

    /**
     * Adds actor to intersts
     *
     * @param string $slug of the actor
     * @param boolean $autoflush defines if it has to be commited to the DB
     * @return boolean true if added.
     */
    public function addActorBySlug($slug, $autoflush = true){
        if(!$this->user){
            throw new \InvalidArgumentException("You have to set an user first");
        }

        $interests = $this->getInterests();

        $actors = $interests->getActors();

        foreach($actors as $actor){
            if($actor->getSlug() === $slug){
                return false;
            }
        }

        $actor = $this->getDm()->getRepository('\Domain\\Document\\Person')->findOneBy(array('slug' => $slug));

        $dm = $this->getDm();

        $interests->addActor($actor);

        $dm->persist($interests);

        if($autoflush){
            $this->getDm()->flush(array('safe' => true));
        }
        return true;
    }

    /**
     * Adds genre to intersts
     *
     * @param string $slug of the tvshow
     * @param boolean $autoflush defines if it has to be commited to the DB
     * @return boolean true if added.
     */
    public function addGenreBySlug($slug, $autoflush = true){
        if(!$this->user){
            throw new \InvalidArgumentException("You have to set an user first");
        }

        $interests = $this->getInterests();

        $genres = $interests->getGenres();

        foreach($genres as $genre){
            if($genre->getSlug() === $slug){
                return false;
            }
        }

        $genre = $this->getDm()->getRepository('\Domain\\Document\\Genre')->findOneBy(array('slug' => $slug));

        $dm = $this->getDm();

        $interests->addGenre($genre);

        $dm->persist($interests);

        if($autoflush){
            $this->getDm()->flush(array('safe' => true));
        }
        return true;
    }

    //removes
    public function removeMovieBySlug($slug, $autoflush = true){
        if(!$this->user){
            throw new \InvalidArgumentException("You have to set an user first");
        }

        $movie = $this->getDm()->getRepository('\Domain\\Document\\Movie')->findOneBy(array('slug' => $slug));

        $dm = $this->getDm();

        $dbRef = $dm->createDBRef($movie);

        /* @var $qb \Doctrine\ORM\QueryBuilder */
        $q = $dm->createQueryBuilder('\Domain\\Document\\UserProfile')
        ->update()
        ->field('_id')->equals(new \MongoId($this->user->getDocument()->getUserProfile()->getId()))
        ->field('interests.movies')->pull($dbRef)->getQuery();

        $q->execute();
        if($autoflush){
            $this->getDm()->flush(array('safe' => true));
        }
    }

    public function removeTvShowBySlug($slug, $autoflush = true){
        if(!$this->user){
            throw new \InvalidArgumentException("You have to set an user first");
        }

        $tvShow = $this->getDm()->getRepository('\Domain\\Document\\TvShow')->findOneBy(array('slug' => $slug));

        $dm = $this->getDm();

        $dbRef = $dm->createDBRef($tvShow);

        /* @var $qb \Doctrine\ORM\QueryBuilder */
        $q = $dm->createQueryBuilder('\Domain\\Document\\UserProfile')
        ->update()
        ->field('_id')->equals(new \MongoId($this->user->getDocument()->getUserProfile()->getId()))
        ->field('interests.tvShows')->pull($dbRef)->getQuery();

        $q->execute();
        if($autoflush){
            $this->getDm()->flush(array('safe' => true));
        }
    }

    public function removeDirectorBySlug($slug, $autoflush = true){
        if(!$this->user){
            throw new \InvalidArgumentException("You have to set an user first");
        }

        $person = $this->getDm()->getRepository('\Domain\\Document\\Person')->findOneBy(array('slug' => $slug));

        $dm = $this->getDm();

        $dbRef = $dm->createDBRef($person);

        /* @var $qb \Doctrine\ORM\QueryBuilder */
        $q = $dm->createQueryBuilder('\Domain\\Document\\UserProfile')
        ->update()
        ->field('_id')->equals(new \MongoId($this->user->getDocument()->getUserProfile()->getId()))
        ->field('interests.directors')->pull($dbRef)->getQuery();

        $q->execute();
        if($autoflush){
            $this->getDm()->flush(array('safe' => true));
        }
    }

    public function removeActorBySlug($slug, $autoflush = true){
        if(!$this->user){
            throw new \InvalidArgumentException("You have to set an user first");
        }

        $person = $this->getDm()->getRepository('\Domain\\Document\\Person')->findOneBy(array('slug' => $slug));

        $dm = $this->getDm();

        $dbRef = $dm->createDBRef($person);

        /* @var $qb \Doctrine\ORM\QueryBuilder */
        $q = $dm->createQueryBuilder('\Domain\\Document\\UserProfile')
        ->update()
        ->field('_id')->equals(new \MongoId($this->user->getDocument()->getUserProfile()->getId()))
        ->field('interests.actors')->pull($dbRef)->getQuery();

        $q->execute();
        if($autoflush){
            $this->getDm()->flush(array('safe' => true));
        }
    }

    public function removeGenreBySlug($slug, $autoflush = true){
        if(!$this->user){
            throw new \InvalidArgumentException("You have to set an user first");
        }

        $genre = $this->getDm()->getRepository('\Domain\\Document\\Genre')->findOneBy(array('slug' => $slug));

        $dm = $this->getDm();

        $dbRef = $dm->createDBRef($genre);

        /* @var $qb \Doctrine\ORM\QueryBuilder */
        $q = $dm->createQueryBuilder('\Domain\\Document\\UserProfile')
        ->update()
        ->field('_id')->equals(new \MongoId($this->user->getDocument()->getUserProfile()->getId()))
        ->field('interests.genres')->pull($dbRef)->getQuery();

        $q->execute();
        if($autoflush){
            $this->getDm()->flush(array('safe' => true));
        }
    }



    private function getInterests(){
        return $this->user->getDocument()->getUserProfile()->getInterests();
    }

    /**
     * Check every field added in interests and clean if the reference is broken.
     *
     * @param Interests $interest
     */
    public function cleanInterest(Interests $interests) {
        if ($interests != null) {
            $this->cleanItems($interests->getGenres());
            $this->cleanItems($interests->getActors());
            $this->cleanItems($interests->getMovies());
            $this->cleanItems($interests->getDirectors());
            $this->cleanItems($interests->getTvShows());

            $dm = $this->getDm();
            $this->getDm()->persist($interests);
            $this->getDm()->flush(array('safe' => true));
        }
    }

    private function cleanItems($interestItems) {
        if ($interestItems != null) {
            foreach ($interestItems as $key => $item) {
                try {
                    $item->getId();
                } catch (MongoDBException $e) {
                    print "Deleting item...\n";
                    unset($interestItems[$key]);
                }
            }
        }
    }
}

