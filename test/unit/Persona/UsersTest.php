<?php

namespace test\unit\Persona;

use Doctrine\Common\Cache\ArrayCache;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use Talis\Persona\Client\Users;
use test\CompatAssert;
use test\TestBase;

class UsersTest extends TestBase
{
    private $cacheBackend;

    /**
     * @before
     */
    public function initializeCache()
    {
        $this->cacheBackend = new ArrayCache();
    }

    public function testGetUserByGupidEmptyGupidThrowsException()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Invalid gupid');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->getUserByGupid('', '');
    }

    public function testGetUserByGupidEmptyTokenThrowsException()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Invalid token');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->getUserByGupid('123', '');
    }

    public function testGetUserByGupidThrowsExceptionWhenGupidNotFound()
    {
        $this->setExpectedException(Exception::class, 'Did not retrieve successful response code');
        $mockClient = $this->getMockUsersClient(['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->throwException(new \Exception('Did not retrieve successful response code')));

        $mockClient->getUserByGupid('123', '456');
    }

    public function testGetUserByGupidReturnsUserWhenGupidFound()
    {
        $mockClient = $this->getMockUsersClient(['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $expectedResponse = [
            'guid' => '456',
            'gupids' => ['google:789'],
            'profile' => [
                'email' => 'max@payne.com',
                'name' => 'Max Payne'
            ]
        ];
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->returnValue($expectedResponse));

        $user = $mockClient->getUserByGupid('123', '456');
        $this->assertEquals('456', $user['guid']);
        CompatAssert::assertIsArray($user['gupids']);
        $this->assertCount(1, $user['gupids']);
        $this->assertEquals('google:789', $user['gupids'][0]);
        CompatAssert::assertIsArray($user['profile']);
        $this->assertCount(2, $user['profile']);
        $this->assertEquals('max@payne.com', $user['profile']['email']);
        $this->assertEquals('Max Payne', $user['profile']['name']);
    }

    public function testGetUserByGuidsEmptyTokenThrowsException()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Invalid token');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->getUserByGuids(['123'], '');
    }

    public function testGetUserByGuidsThrowsExceptionWhenGuidsNotFound()
    {
        $this->setExpectedException(Exception::class, 'Error finding user profiles: Could not retrieve OAuth response code');
        $mockClient = $this->getMockUsersClient(['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->throwException(new \Exception('Could not retrieve OAuth response code')));

        $mockClient->getUserByGuids(['HK-47'], '456');
    }

    public function testGetUserByGuidsReturnsUserWhenGuidsFound()
    {
        $mockClient = $this->getMockUsersClient(['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $expectedResponse = [
            [
                'guid' => '456',
                'gupids' => ['google:789'],
                'profile' => [
                    'email' => 'max@payne.com',
                    'name' => 'Max Payne'
                ]
            ]
        ];
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->returnValue($expectedResponse));

        $users = $mockClient->getUserByGuids(['123'], '456');
        $this->assertCount(1, $users);
        $this->assertEquals('456', $users[0]['guid']);
        CompatAssert::assertIsArray($users[0]['gupids']);
        $this->assertCount(1, $users[0]['gupids']);
        $this->assertEquals('google:789', $users[0]['gupids'][0]);
        CompatAssert::assertIsArray($users[0]['profile']);
        $this->assertCount(2, $users[0]['profile']);
        $this->assertEquals('max@payne.com', $users[0]['profile']['email']);
        $this->assertEquals('Max Payne', $users[0]['profile']['name']);
    }

    public function testCreateUserEmptyGupid()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Invalid gupid');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->createUser('', [], 'token');
    }

    public function testCreateUserInvalidGupid()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Invalid gupid');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->createUser(['gupid'], [], 'token');
    }

    public function testCreateUserEmptyProfile()
    {
        $mockClient = $this->getMockUsersClient(['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $expectedResponse = ['gupid' => '123'];
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->returnValue($expectedResponse));
        $mockClient->createUser('gupid', [], 'token');
    }

    public function testCreateUserEmptyToken()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Invalid token');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->createUser('gupid', ['email' => ''], '');
    }

    public function testCreateUserInvalidToken()
    {
        $this->setExpectedException(InvalidArgumentException::class, 'Invalid token');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->createUser('gupid', ['email' => ''], ['']);
    }

    public function testCreateUserPostFails()
    {
        $this->setExpectedException(Exception::class, 'Error creating user: Could not retrieve OAuth response code');
        $mockClient = $this->getMockUsersClient(['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->throwException(new \Exception('Could not retrieve OAuth response code')));
        $mockClient->createUser('gupid', ['email' => ''], '123');
    }

    public function testCreateUserPostSucceeds()
    {
        $mockClient = $this->getMockUsersClient(['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $expectedResponse = ['gupid' => '123', 'profile' => []];
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->returnValue($expectedResponse));
        $this->assertEquals($expectedResponse, $mockClient->createUser('123', ['email' => ''], '123'));
    }

    public function testUpdateUserEmptyGuid()
    {
        $this->setExpectedException(Exception::class, 'Invalid guid');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateUser('', [], '987');
    }

    public function testUpdateUserInvalidGuid()
    {
        $this->setExpectedException(Exception::class, 'Invalid guid');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateUser([], [], '987');
    }

    public function testUpdateUserEmptyProfile()
    {
        $this->setExpectedException(Exception::class, 'Invalid profile');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateUser('123', [], '987');
    }

    public function testUpdateUserInvalidProfile()
    {
        $this->setExpectedException(Exception::class, 'Invalid profile');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateUser('123', [], '987');
    }

    public function testUpdateUserEmptyToken()
    {
        $this->setExpectedException(Exception::class, 'Invalid token');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateUser('123', ['email' => 'PROFILE'], '');
    }

    public function testUpdateUserInvalidToken()
    {
        $this->setExpectedException(Exception::class, 'Invalid token');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->updateUser('123', ['email' => 'PROFILE'], ['']);
    }

    public function testUpdateUserPutFails()
    {
        $this->setExpectedException(Exception::class, 'Error updating user: Could not retrieve OAuth response code');
        $mockClient = $this->getMockUsersClient(['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->throwException(new \Exception('Could not retrieve OAuth response code')));
        $mockClient->updateUser('guid', ['email' => ''], '123');
    }

    public function testUpdateUserPutSucceeds()
    {
        $mockClient = $this->getMockUsersClient(['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $expectedResponse = ['gupid' => '123', 'profile' => []];
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->returnValue($expectedResponse));
        $this->assertEquals($expectedResponse, $mockClient->updateUser('123', ['email' => ''], '123'));
    }

    public function testAddGupidToUserInvalidGuid()
    {
        $this->setExpectedException(Exception::class, 'Invalid guid');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->addGupidToUser([], '456', '987');
    }

    public function testAddGupidToUserInvalidGupid()
    {
        $this->setExpectedException(Exception::class, 'Invalid gupid');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->addGupidToUser('123', [], '987');
    }

    public function testAddGupidToUserEmptyToken()
    {
        $this->setExpectedException(Exception::class, 'Invalid token');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->addGupidToUser('123', '456', '');
    }

    public function testAddGupidToUserInvalidToken()
    {
        $this->setExpectedException(Exception::class, 'Invalid token');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->addGupidToUser('123', '456', []);
    }

    public function testAddGupidToUserPatchFails()
    {
        $this->setExpectedException(Exception::class, 'Error adding gupid to user: Could not retrieve OAuth response code');
        $mockClient = $this->getMockUsersClient(['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->throwException(new \Exception('Could not retrieve OAuth response code')));
        $mockClient->addGupidToUser('123', '456', '987');
    }

    public function testAddGupidToUserPutSucceeds()
    {
        $mockClient = $this->getMockUsersClient(['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $expectedResponse = ['gupid' => '123', 'profile' => []];
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->returnValue($expectedResponse));
        $this->assertEquals($expectedResponse, $mockClient->addGupidToUser('123', '456', '987'));
    }

    public function testMergeUsersInvalidOldGuid()
    {
        $this->setExpectedException(Exception::class, 'Invalid oldGuid');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->mergeUsers([], '456', '987');
    }

    public function testMergeUsersInvalidNewGuid()
    {
        $this->setExpectedException(Exception::class, 'Invalid newGuid');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->mergeUsers('123', [], '987');
    }

    public function testMergeUsersEmptyToken()
    {
        $this->setExpectedException(Exception::class, 'Invalid token');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->mergeUsers('123', '456', '');
    }

    public function testMergeUsersInvalidToken()
    {
        $this->setExpectedException(Exception::class, 'Invalid token');
        $personaClient = $this->newUsers(
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        );
        $personaClient->mergeUsers('123', '456', []);
    }

    public function testMergeUsersPostFails()
    {
        $this->setExpectedException(Exception::class, 'Error merging users: Could not retrieve OAuth response code');
        $mockClient = $this->getMockUsersClient(['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->throwException(new \Exception('Could not retrieve OAuth response code')));
        $mockClient->mergeUsers('123', '456', '987');
    }

    public function testMergeUsersPostSucceeds()
    {
        $mockClient = $this->getMockUsersClient(['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $expectedResponse = ['gupid' => '456', 'profile' => []];
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->returnValue($expectedResponse));
        $this->assertEquals($expectedResponse, $mockClient->mergeUsers('123', '456', '987'));
    }

    private function newUsers(array $config)
    {
        $users = new Users($config);
        $users->setLogger(new \Psr\Log\NullLogger());
        return $users;
    }

    /**
     * @param string[] $methods
     * @param array $arguments
     * @return \Talis\Persona\Client\Users|MockObject
     */
    private function getMockUsersClient(array $methods, array $arguments)
    {
        /** @var MockObject&\Talis\Persona\Client\Users */
        $mockClient = $this->getMockBuilder(\Talis\Persona\Client\Users::class)
            ->setMethods($methods)
            ->setConstructorArgs($arguments)
            ->getMock();

        $mockClient->setLogger(new \Psr\Log\NullLogger());

        return $mockClient;
    }
}
