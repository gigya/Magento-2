<?php

namespace Gigya\GigyaIM\Test\Unit\Helper\CmsStarterKit\user;

use Gigya\GigyaIM\Helper\CmsStarterKit\fieldMapping\ConfItem;
use Gigya\GigyaIM\Model\GigyaAccountService;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaProfile;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaSubscriptionContainer;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaSubscription;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaSubscriptionDoubleOptIn;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUser;
use Gigya\GigyaIM\Helper\CmsStarterKit\user\GigyaUserFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class GigyaUserFactoryTest extends TestCase
{
    public function testAccountWithMissingAndNullFieldsCanBeReadBack(): void
    {
        $gigyaUser = GigyaUserFactory::createGigyaUserFromArray([
            'UID' => 'abc123',
            'loginIDs' => ['emails' => ['jane@example.com']],
            'profile' => [
                'email' => 'jane@example.com',
                'firstName' => 'Jane',
                'photoURL' => null,
            ],
            'subscriptions' => [
                'newsletter' => ['email' => ['isSubscribed' => true]],
            ],
        ]);

        $profile = $gigyaUser->getProfile();
        $this->assertSame('jane@example.com', $profile->getEmail());
        $this->assertSame('Jane', $profile->getFirstName());
        $this->assertNull($profile->getPhotoURL());
        $this->assertNull($profile->getLastName());
        $this->assertNull($profile->getBirthYear());
        $this->assertNull($profile->isVerified());
        $this->assertNull($profile->getLikes());
        $this->assertSame('abc123', $gigyaUser->getUID());
        $this->assertNull($gigyaUser->getLoginProvider());
        $this->assertNull($gigyaUser->getSignatureTimestamp());
        $this->assertSame('jane@example.com', $gigyaUser->getGigyaLoginId());

        $subscription = $gigyaUser->getSubscriptionById('newsletter');
        $this->assertTrue($subscription->getIsSubscribed());
        $this->assertNull($subscription->getTags());
        $this->assertNull($subscription->getDoubleOptInAsArray());
    }

    public function testGettersOfEmptyObjectsReturnNull(): void
    {
        $this->assertGettersReturnNull(new GigyaProfile(null));
        $this->assertGettersReturnNull(new GigyaSubscription(null));
        $this->assertGettersReturnNull(new GigyaSubscriptionDoubleOptIn(null));
        $this->assertGettersReturnNull(new GigyaSubscriptionContainer(null));
        $this->assertGettersReturnNull(new ConfItem([]), ['getDirection']);
    }

    private function assertGettersReturnNull(object $object, array $withDefault = []): void
    {
        foreach (get_class_methods($object) as $method) {
            $reflection = new ReflectionMethod($object, $method);
            if (!preg_match('/^(get|is)[A-Z]/', $method)
                || in_array($method, $withDefault, true)
                || $reflection->getNumberOfParameters() > 0
                || $reflection->getDeclaringClass()->getName() !== get_class($object)
            ) {
                continue;
            }
            $this->assertNull($object->$method(), $method);
        }
    }

    public function testAccountWithNullTopLevelFieldsBuildsAndProducesUpdatePayload(): void
    {
        $gigyaUser = GigyaUserFactory::createGigyaUserFromArray([
            'UID' => 'abc123',
            'isActive' => null,
            'isVerified' => null,
            'data' => null,
            'profile' => ['email' => 'jane@example.com'],
            'subscriptions' => [
                'newsletter' => ['email' => ['isSubscribed' => true, 'doubleOptIn' => ['status' => 'NotConfirmed']]],
            ],
        ]);

        $this->assertNull($gigyaUser->getIsActive());
        $this->assertNull($gigyaUser->getIsVerified());
        $this->assertNull($gigyaUser->getData());

        $payload = GigyaAccountService::getGigyaApiAccountData($gigyaUser);
        $this->assertSame('abc123', $payload['UID']);
        $this->assertArrayNotHasKey('data', $payload);
        $this->assertArrayNotHasKey('isActive', $payload);
        $this->assertArrayNotHasKey('isVerified', $payload);
        $this->assertSame(
            [
                'isSubscribed' => true,
                'doubleOptIn' => ['emailSentTime' => null, 'confirmTime' => null, 'status' => 'NotConfirmed'],
            ],
            $payload['subscriptions']['newsletter']['email']
        );
    }

    public function testAccountWithoutSubscriptionsHasNoSubscriptionById(): void
    {
        $gigyaUser = new GigyaUser(null);
        $gigyaUser->setSubscriptions(null);
        $gigyaUser->setData(null);

        $this->assertNull($gigyaUser->getSubscriptions());
        $this->assertNull($gigyaUser->getSubscriptionById('newsletter'));
        $this->assertNull($gigyaUser->getData());
    }

    public function testGigyaLoginIdIsNullWhenAccountHasNoEmail(): void
    {
        $gigyaUser = GigyaUserFactory::createGigyaUserFromArray(['UID' => 'abc123', 'profile' => []]);

        $this->assertNull($gigyaUser->getGigyaLoginId());
    }

    public function testSettersAcceptNull(): void
    {
        $profile = new GigyaProfile(null);
        $profile->setPhotoURL('https://example.com/a.png');
        $profile->setPhotoURL(null);
        $gigyaUser = new GigyaUser(null);
        $gigyaUser->setLoginProvider(null);

        $this->assertNull($profile->getPhotoURL());
        $this->assertNull($gigyaUser->getLoginProvider());
    }
}
