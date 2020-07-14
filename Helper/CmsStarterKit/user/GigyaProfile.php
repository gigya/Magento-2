<?php

namespace Gigya\GigyaIM\Helper\CmsStarterKit\user;

use Gigya\GigyaIM\Helper\CmsStarterKit\GigyaJsonObject;

class GigyaProfile extends GigyaJsonObject
{

	/**
	 * @var int
	 */
	private $birthDay;

	/**
	 * @var int
	 */
	private $birthMonth;

	/**
	 * @var int
	 */
	private $birthYear;

	/**
	 * @var string
	 */
	private $city;

	/**
	 * @var string
	 */
	private $country;

	/**
	 * @var string
	 */
	private $email;

	/**
	 * @var string
	 */
	private $firstName;

	/**
	 * @var string
	 */
	private $gender;

	/**
	 * @var string
	 */
	private $lastName;

	/**
	 * @var string
	 */
	private $nickname;

	/**
	 * @var string
	 */
	private $photoURL;

	/**
	 * @var string
	 */
	private $profileURL;

	/**
	 * @var string
	 */
	private $state;

	/**
	 * @var string
	 */
	private $thumbnailURL;

	/**
	 * @var string
	 */
	private $zip;

	/**
	 * @var string
	 */
	private $bio;

	/**
	 * @var string
	 */
	private $address;

	/**
	 * @var string
	 */
	private $educationLevel;

	/**
	 * @var int
	 */
	private $followersCount;

	/**
	 * @var int
	 */
	private $followingCount;

	/**
	 * @var string
	 */
	private $hometown;

	/**
	 * @var string
	 */
	private $honors;

	/**
	 * @var string
	 */
	private $industry;

	/**
	 * @var string
	 */
	private $interestedIn;

	/**
	 * @var string
	 */
	private $languages;

	/**
	 * @var string
	 */
	private $locale;

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $religion;

	/**
	 * @var string
	 */
	private $timezone;

	/**
	 * @var string
	 */
	private $username;

	/**
	 * @var boolean
	 */
	private $verified;

	/**
	 * @var string
	 */
	private $relationshipStatus;

	/**
	 * @var array
	 */
	private $likes;

	/**
	 * @var array
	 */
	private $favorites;

	/**
	 * @var array
	 */
	private $skills;

	/**
	 * @var array
	 */
	private $education;

	/**
	 * @var array
	 */
	private $phones;

	/**
	 * @var array
	 */
	private $works;

	/**
	 * @var array
	 */
	private $publications;

	/**
	 * @return int
	 */
	public function getBirthDay() {
		return $this->birthDay;
	}

	/**
	 * @param int $birthDay
	 */
	public function setBirthDay($birthDay) {
		$this->birthDay = $birthDay;
	}

	/**
	 * @return int
	 */
	public function getBirthMonth() {
		return $this->birthMonth;
	}

	/**
	 * @param int $birthMonth
	 */
	public function setBirthMonth($birthMonth) {
		$this->birthMonth = $birthMonth;
	}

	/**
	 * @return int
	 */
	public function getBirthYear() {
		return $this->birthYear;
	}

	/**
	 * @param int $birthYear
	 */
	public function setBirthYear($birthYear) {
		$this->birthYear = $birthYear;
	}

	/**
	 * @return string
	 */
	public function getCity() {
		return $this->city;
	}

	/**
	 * @param string $city
	 */
	public function setCity($city) {
		$this->city = $city;
	}

	/**
	 * @return string
	 */
	public function getCountry() {
		return $this->country;
	}

	/**
	 * @param string $country
	 */
	public function setCountry($country) {
		$this->country = $country;
	}

	/**
	 * @return string
	 */
	public function getEmail() {
		return $this->email;
	}

	/**
	 * @param string $email
	 */
	public function setEmail($email) {
		$this->email = $email;
	}

	/**
	 * @return string
	 */
	public function getFirstName() {
		return $this->firstName;
	}

	/**
	 * @param string $firstName
	 */
	public function setFirstName($firstName) {
		$this->firstName = $firstName;
	}

	/**
	 * @return string
	 */
	public function getGender() {
		return $this->gender;
	}

	/**
	 * @param string $gender
	 */
	public function setGender($gender) {
		$this->gender = $gender;
	}

	/**
	 * @return string
	 */
	public function getLastName() {
		return $this->lastName;
	}

	/**
	 * @param string $lastName
	 */
	public function setLastName($lastName) {
		$this->lastName = $lastName;
	}

	/**
	 * @return string
	 */
	public function getNickname() {
		return $this->nickname;
	}

	/**
	 * @param string $nickname
	 */
	public function setNickname($nickname) {
		$this->nickname = $nickname;
	}

	/**
	 * @return string
	 */
	public function getPhotoURL() {
		return $this->photoURL;
	}

	/**
	 * @param string $photoURL
	 */
	public function setPhotoURL($photoURL) {
		$this->photoURL = $photoURL;
	}

	/**
	 * @return string
	 */
	public function getProfileURL() {
		return $this->profileURL;
	}

	/**
	 * @param string $profileURL
	 */
	public function setProfileURL($profileURL) {
		$this->profileURL = $profileURL;
	}

	/**
	 * @return string
	 */
	public function getState() {
		return $this->state;
	}

	/**
	 * @param string $state
	 */
	public function setState($state) {
		$this->state = $state;
	}

	/**
	 * @return string
	 */
	public function getThumbnailURL() {
		return $this->thumbnailURL;
	}

	/**
	 * @param string $thumbnailURL
	 */
	public function setThumbnailURL($thumbnailURL) {
		$this->thumbnailURL = $thumbnailURL;
	}

	/**
	 * @return string
	 */
	public function getZip() {
		return $this->zip;
	}

	/**
	 * @param string $zip
	 */
	public function setZip($zip) {
		$this->zip = $zip;
	}

	/**
	 * @return string
	 */
	public function getBio() {
		return $this->bio;
	}

	/**
	 * @param string $bio
	 */
	public function setBio($bio) {
		$this->bio = $bio;
	}

	/**
	 * @return string
	 */
	public function getAddress() {
		return $this->address;
	}

	/**
	 * @param string $address
	 */
	public function setAddress($address) {
		$this->address = $address;
	}

	/**
	 * @return string
	 */
	public function getEducationLevel() {
		return $this->educationLevel;
	}

	/**
	 * @param string $educationLevel
	 */
	public function setEducationLevel($educationLevel) {
		$this->educationLevel = $educationLevel;
	}

	/**
	 * @return int
	 */
	public function getFollowersCount() {
		return $this->followersCount;
	}

	/**
	 * @param int $followersCount
	 */
	public function setFollowersCount($followersCount) {
		$this->followersCount = $followersCount;
	}

	/**
	 * @return int
	 */
	public function getFollowingCount() {
		return $this->followingCount;
	}

	/**
	 * @param int $followingCount
	 */
	public function setFollowingCount($followingCount) {
		$this->followingCount = $followingCount;
	}

	/**
	 * @return string
	 */
	public function getHometown() {
		return $this->hometown;
	}

	/**
	 * @param string $hometown
	 */
	public function setHometown($hometown) {
		$this->hometown = $hometown;
	}

	/**
	 * @return string
	 */
	public function getHonors() {
		return $this->honors;
	}

	/**
	 * @param string $honors
	 */
	public function setHonors($honors) {
		$this->honors = $honors;
	}

	/**
	 * @return string
	 */
	public function getIndustry() {
		return $this->industry;
	}

	/**
	 * @param string $industry
	 */
	public function setIndustry($industry) {
		$this->industry = $industry;
	}

	/**
	 * @return string
	 */
	public function getInterestedIn() {
		return $this->interestedIn;
	}

	/**
	 * @param string $interestedIn
	 */
	public function setInterestedIn($interestedIn) {
		$this->interestedIn = $interestedIn;
	}

	/**
	 * @return string
	 */
	public function getLanguages() {
		return $this->languages;
	}

	/**
	 * @param string $languages
	 */
	public function setLanguages($languages) {
		$this->languages = $languages;
	}

	/**
	 * @return string
	 */
	public function getLocale() {
		return $this->locale;
	}

	/**
	 * @param string $locale
	 */
	public function setLocale($locale) {
		$this->locale = $locale;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getReligion() {
		return $this->religion;
	}

	/**
	 * @param string $religion
	 */
	public function setReligion($religion) {
		$this->religion = $religion;
	}

	/**
	 * @return string
	 */
	public function getTimezone() {
		return $this->timezone;
	}

	/**
	 * @param string $timezone
	 */
	public function setTimezone($timezone) {
		$this->timezone = $timezone;
	}

	/**
	 * @return string
	 */
	public function getUsername() {
		return $this->username;
	}

	/**
	 * @param string $username
	 */
	public function setUsername($username) {
		$this->username = $username;
	}

	/**
	 * @return boolean
	 */
	public function isVerified() {
		return $this->verified;
	}

	/**
	 * @param boolean $verified
	 */
	public function setVerified($verified) {
		$this->verified = $verified;
	}

	/**
	 * @return string
	 */
	public function getRelationshipStatus() {
		return $this->relationshipStatus;
	}

	/**
	 * @param string $relationshipStatus
	 */
	public function setRelationshipStatus($relationshipStatus) {
		$this->relationshipStatus = $relationshipStatus;
	}

	/**
	 * @return array
	 */
	public function getLikes() {
		return $this->likes;
	}

	/**
	 * @param array $likes
	 */
	public function setLikes($likes) {
		$this->likes = $likes;
	}

	/**
	 * @return array
	 */
	public function getFavorites() {
		return $this->favorites;
	}

	/**
	 * @param array $favorites
	 */
	public function setFavorites($favorites) {
		$this->favorites = $favorites;
	}

	/**
	 * @return array
	 */
	public function getSkills() {
		return $this->skills;
	}

	/**
	 * @param array $skills
	 */
	public function setSkills($skills) {
		$this->skills = $skills;
	}

	/**
	 * @return array
	 */
	public function getEducation() {
		return $this->education;
	}

	/**
	 * @param array $education
	 */
	public function setEducation($education) {
		$this->education = $education;
	}

	/**
	 * @return array
	 */
	public function getPhones() {
		return $this->phones;
	}

	/**
	 * @param array $phones
	 */
	public function setPhones($phones) {
		$this->phones = $phones;
	}

	/**
	 * @return array
	 */
	public function getWorks() {
		return $this->works;
	}

	/**
	 * @param array $works
	 */
	public function setWorks($works) {
		$this->works = $works;
	}

	/**
	 * @return array
	 */
	public function getPublications() {
		return $this->publications;
	}

	/**
	 * @param array $publications
	 */
	public function setPublications($publications) {
		$this->publications = $publications;
	}

	public function __toString() {
		return json_encode(get_object_vars($this));
	}
}