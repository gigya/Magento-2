<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\user;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaJsonObject;

class GigyaProfile extends GigyaJsonObject
{

    /**
     * @var int
     */
    private ?int $birthDay=null;

    /**
     * @var int
     */
    private ?int $birthMonth=null;

    /**
     * @var int
     */
    private ?int $birthYear = null;

    /**
     * @var string
     */
    private ?string $city = null;

    /**
     * @var string
     */
    private ?string $country = null;

    /**
     * @var string
     */
    private ?string $email = null;

    /**
     * @var string
     */
    private ?string $firstName = null;

    /**
     * @var string
     */
    private ?string $gender=null;

    /**
     * @var string
     */
    private ?string $lastName = null;

    /**
     * @var string
     */
    private ?string $nickname = null;

    /**
     * @var string
     */
    private ?string $photoURL = null;

    /**
     * @var string
     */
    private ?string $profileURL = null;

    /**
     * @var string
     */
    private ?string $state = null;

    /**
     * @var string
     */
    private ?string $thumbnailURL = null;

    /**
     * @var string
     */
    private ?string $zip = null;

    /**
     * @var string
     */
    private ?string $bio = null;

    /**
     * @var string
     */
    private ?string $address = null;

    /**
     * @var string
     */
    private ?string $educationLevel = null;

    /**
     * @var int
     */
    private ?int $followersCount = null;

    /**
     * @var int
     */
    private ?int $followingCount = null;

    /**
     * @var string
     */
    private ?string $hometown = null;

    /**
     * @var string
     */
    private ?string $honors = null;

    /**
     * @var string
     */
    private ?string $industry = null;

    /**
     * @var string
     */
    private ?string $interestedIn = null;

    /**
     * @var string
     */
    private ?string $languages = null;

    /**
     * @var string
     */
    private ?string $locale = null;

    /**
     * @var string
     */
    private ?string $name = null;

    /**
     * @var string
     */
    private ?string $religion = null;

    /**
     * @var string
     */
    private ?string $timezone = null;

    /**
     * @var string
     */
    private ?string $username = null;

    /**
     * @var boolean
     */
    private ?bool $verified = null;

    /**
     * @var string
     */
    private ?string $relationshipStatus = null;

    /**
     * @var array
     */
    private ?array $likes = null;

    /**
     * @var array
     */
    private ?array $favorites = null;

    /**
     * @var array
     */
    private ?array $skills = null;

    /**
     * @var array
     */
    private ?array $education = null;

    /**
     * @var array
     */
    private ?array $phones = null;

    /**
     * @var array
     */
    private ?array $works = null;

    /**
     * @var array
     */
    private ?array $publications = null;

    /**
     * @return int
     */
    public function getBirthDay(): int | null
    {
        return $this->birthDay;
    }

    /**
     * @param int $birthDay
     */
    public function setBirthDay($birthDay): void
    {
        $this->birthDay = $birthDay;
    }

    /**
     * @return int
     */
    public function getBirthMonth(): int | null
    {
        return $this->birthMonth;
    }

    /**
     * @param int $birthMonth
     */
    public function setBirthMonth($birthMonth): void
    {
        $this->birthMonth = $birthMonth;
    }

    /**
     * @return int
     */
    public function getBirthYear(): int | null
    {
        return $this->birthYear;
    }

    /**
     * @param int $birthYear
     */
    public function setBirthYear($birthYear): void
    {
        $this->birthYear = $birthYear;
    }

    /**
     * @return string
     */
    public function getCity(): string | null
    {
        return $this->city;
    }

    /**
     * @param string $city
     */
    public function setCity($city): void
    {
        $this->city = $city;
    }

    /**
     * @return string
     */
    public function getCountry(): string | null
    {
        return $this->country;
    }

    /**
     * @param string $country
     */
    public function setCountry($country): void
    {
        $this->country = $country;
    }

    /**
     * @return string
     */
    public function getEmail(): string | null
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail($email): void
    {
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getFirstName(): string | null
    {
        return $this->firstName;
    }

    /**
     * @param string $firstName
     */
    public function setFirstName($firstName): void
    {
        $this->firstName = $firstName;
    }

    /**
     * @return string
     */
    public function getGender(): string | null
    {
        return $this->gender;
    }

    /**
     * @param string $gender
     */
    public function setGender($gender): void
    {
        $this->gender = $gender;
    }

    /**
     * @return string
     */
    public function getLastName(): string | null
    {
        return $this->lastName;
    }

    /**
     * @param string $lastName
     */
    public function setLastName($lastName): void
    {
        $this->lastName = $lastName;
    }

    /**
     * @return string
     */
    public function getNickname(): string | null
    {
        return $this->nickname;
    }

    /**
     * @param string $nickname
     */
    public function setNickname($nickname): void
    {
        $this->nickname = $nickname;
    }

    /**
     * @return string
     */
    public function getPhotoURL(): string | null
    {
        return $this->photoURL;
    }

    /**
     * @param string $photoURL
     */
    public function setPhotoURL($photoURL): void
    {
        $this->photoURL = $photoURL;
    }

    /**
     * @return string
     */
    public function getProfileURL(): string | null
    {
        return $this->profileURL;
    }

    /**
     * @param string $profileURL
     */
    public function setProfileURL($profileURL): void
    {
        $this->profileURL = $profileURL;
    }

    /**
     * @return string
     */
    public function getState(): string | null
    {
        return $this->state;
    }

    /**
     * @param string $state
     */
    public function setState($state): void
    {
        $this->state = $state;
    }

    /**
     * @return string
     */
    public function getThumbnailURL(): string | null
    {
        return $this->thumbnailURL;
    }

    /**
     * @param string $thumbnailURL
     */
    public function setThumbnailURL($thumbnailURL): void
    {
        $this->thumbnailURL = $thumbnailURL;
    }

    /**
     * @return string
     */
    public function getZip(): string | null
    {
        return $this->zip;
    }

    /**
     * @param string $zip
     */
    public function setZip($zip): void
    {
        $this->zip = $zip;
    }

    /**
     * @return string
     */
    public function getBio(): string | null
    {
        return $this->bio;
    }

    /**
     * @param string $bio
     */
    public function setBio($bio): void
    {
        $this->bio = $bio;
    }

    /**
     * @return string
     */
    public function getAddress(): string | null
    {
        return $this->address;
    }

    /**
     * @param string $address
     */
    public function setAddress($address): void
    {
        $this->address = $address;
    }

    /**
     * @return string
     */
    public function getEducationLevel(): string | null
    {
        return $this->educationLevel;
    }

    /**
     * @param string $educationLevel
     */
    public function setEducationLevel($educationLevel): void
    {
        $this->educationLevel = $educationLevel;
    }

    /**
     * @return int
     */
    public function getFollowersCount(): int | null
    {
        return $this->followersCount;
    }

    /**
     * @param int $followersCount
     */
    public function setFollowersCount($followersCount): void
    {
        $this->followersCount = $followersCount;
    }

    /**
     * @return int
     */
    public function getFollowingCount(): int | null
    {
        return $this->followingCount;
    }

    /**
     * @param int $followingCount
     */
    public function setFollowingCount($followingCount): void
    {
        $this->followingCount = $followingCount;
    }

    /**
     * @return string
     */
    public function getHometown(): string | null
    {
        return $this->hometown;
    }

    /**
     * @param string $hometown
     */
    public function setHometown($hometown): void
    {
        $this->hometown = $hometown;
    }

    /**
     * @return string
     */
    public function getHonors(): string | null
    {
        return $this->honors;
    }

    /**
     * @param string $honors
     */
    public function setHonors($honors): void
    {
        $this->honors = $honors;
    }

    /**
     * @return string
     */
    public function getIndustry(): string | null
    {
        return $this->industry;
    }

    /**
     * @param string $industry
     */
    public function setIndustry($industry): void
    {
        $this->industry = $industry;
    }

    /**
     * @return string
     */
    public function getInterestedIn(): string | null
    {
        return $this->interestedIn;
    }

    /**
     * @param string $interestedIn
     */
    public function setInterestedIn($interestedIn): void
    {
        $this->interestedIn = $interestedIn;
    }

    /**
     * @return string
     */
    public function getLanguages(): string | null
    {
        return $this->languages;
    }

    /**
     * @param string $languages
     */
    public function setLanguages($languages): void
    {
        $this->languages = $languages;
    }

    /**
     * @return string
     */
    public function getLocale(): string | null
    {
        return $this->locale;
    }

    /**
     * @param string $locale
     */
    public function setLocale($locale): void
    {
        $this->locale = $locale;
    }

    /**
     * @return string
     */
    public function getName(): string | null
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getReligion(): string | null
    {
        return $this->religion;
    }

    /**
     * @param string $religion
     */
    public function setReligion($religion): void
    {
        $this->religion = $religion;
    }

    /**
     * @return string
     */
    public function getTimezone(): string | null
    {
        return $this->timezone;
    }

    /**
     * @param string $timezone
     */
    public function setTimezone($timezone): void
    {
        $this->timezone = $timezone;
    }

    /**
     * @return string
     */
    public function getUsername(): string | null
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername($username): void
    {
        $this->username = $username;
    }

    /**
     * @return boolean
     */
    public function isVerified(): bool | null
    {
        return $this->verified;
    }

    /**
     * @param boolean $verified
     */
    public function setVerified($verified): void
    {
        $this->verified = $verified;
    }

    /**
     * @return string
     */
    public function getRelationshipStatus(): string | null
    {
        return $this->relationshipStatus;
    }

    /**
     * @param string $relationshipStatus
     */
    public function setRelationshipStatus($relationshipStatus): void
    {
        $this->relationshipStatus = $relationshipStatus;
    }

    /**
     * @return array
     */
    public function getLikes(): array | null
    {
        return $this->likes;
    }

    /**
     * @param array $likes
     */
    public function setLikes($likes): void
    {
        $this->likes = $likes;
    }

    /**
     * @return array
     */
    public function getFavorites(): array | null
    {
        return $this->favorites;
    }

    /**
     * @param array $favorites
     */
    public function setFavorites($favorites): void
    {
        $this->favorites = $favorites;
    }

    /**
     * @return array
     */
    public function getSkills(): array | null
    {
        return $this->skills;
    }

    /**
     * @param array $skills
     */
    public function setSkills($skills): void
    {
        $this->skills = $skills;
    }

    /**
     * @return array
     */
    public function getEducation(): array | null
    {
        return $this->education;
    }

    /**
     * @param array $education
     */
    public function setEducation($education): void
    {
        $this->education = $education;
    }

    /**
     * @return array
     */
    public function getPhones(): array | null
    {
        return $this->phones;
    }

    /**
     * @param array $phones
     */
    public function setPhones($phones): void
    {
        $this->phones = $phones;
    }

    /**
     * @return array
     */
    public function getWorks(): array | null
    {
        return $this->works;
    }

    /**
     * @param array $works
     */
    public function setWorks($works): void
    {
        $this->works = $works;
    }

    /**
     * @return array
     */
    public function getPublications(): array | null
    {
        return $this->publications;
    }

    /**
     * @param array $publications
     */
    public function setPublications($publications): void
    {
        $this->publications = $publications;
    }

    public function __toString()
    {
        return json_encode(get_object_vars($this));
    }
}
