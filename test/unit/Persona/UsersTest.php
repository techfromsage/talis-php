<?php

use Talis\Persona\Client\Users;

$appRoot = dirname(dirname(dirname(__DIR__)));
if (!defined('APPROOT')) {
    define('APPROOT', $appRoot);
}

require_once $appRoot . '/test/unit/TestBase.php';

class UsersTest extends TestBase
{
    public function testGetUserByGupidEmptyGupidThrowsException()
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid gupid');
        $personaClient = new Users(
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
        $this->setExpectedException('InvalidArgumentException', 'Invalid token');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Did not retrieve successful response code');
        $mockClient = $this->getMock('Talis\Persona\Client\Users', ['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->throwException(new Exception('Did not retrieve successful response code')));

        $mockClient->getUserByGupid('123', '456');
    }

    public function testGetUserByGupidReturnsUserWhenGupidFound()
    {
        $mockClient = $this->getMock('Talis\Persona\Client\Users', ['performRequest'], [
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
        $this->assertInternalType('array', $user['gupids']);
        $this->assertCount(1, $user['gupids']);
        $this->assertEquals('google:789', $user['gupids'][0]);
        $this->assertInternalType('array', $user['profile']);
        $this->assertCount(2, $user['profile']);
        $this->assertEquals('max@payne.com', $user['profile']['email']);
        $this->assertEquals('Max Payne', $user['profile']['name']);
    }

    public function testGetUserByGuidsEmptyTokenThrowsException()
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid token');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Error finding user profiles: Could not retrieve OAuth response code');
        $mockClient = $this->getMock('Talis\Persona\Client\Users', ['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->throwException(new Exception('Could not retrieve OAuth response code')));

        $mockClient->getUserByGuids(['HK-47'], '456');
    }

    public function testGetUserByGuidsReturnsUserWhenGuidsFound()
    {
        $mockClient = $this->getMock('Talis\Persona\Client\Users', ['performRequest'], [
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
        $this->assertInternalType('array', $users[0]['gupids']);
        $this->assertCount(1, $users[0]['gupids']);
        $this->assertEquals('google:789', $users[0]['gupids'][0]);
        $this->assertInternalType('array', $users[0]['profile']);
        $this->assertCount(2, $users[0]['profile']);
        $this->assertEquals('max@payne.com', $users[0]['profile']['email']);
        $this->assertEquals('Max Payne', $users[0]['profile']['name']);
    }

    public function testCreateUserEmptyGupid()
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid gupid');
        $personaClient = new Users(
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
        $this->setExpectedException('InvalidArgumentException', 'Invalid gupid');
        $personaClient = new Users(
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
        $mockClient = $this->getMock('Talis\Persona\Client\Users', ['performRequest'], [
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
        $this->setExpectedException('InvalidArgumentException', 'Invalid token');
        $personaClient = new Users(
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
        $this->setExpectedException('InvalidArgumentException', 'Invalid token');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Error creating user: Could not retrieve OAuth response code');
        $mockClient = $this->getMock('Talis\Persona\Client\Users', ['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->throwException(new Exception('Could not retrieve OAuth response code')));
        $mockClient->createUser('gupid', ['email' => ''], '123');
    }

    public function testCreateUserPostSucceeds()
    {
        $mockClient = $this->getMock('Talis\Persona\Client\Users', ['performRequest'], [
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
        $this->setExpectedException('Exception', 'Invalid guid');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Invalid guid');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Invalid profile');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Invalid profile');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Invalid token');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Invalid token');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Error updating user: Could not retrieve OAuth response code');
        $mockClient = $this->getMock('Talis\Persona\Client\Users', ['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->throwException(new Exception('Could not retrieve OAuth response code')));
        $mockClient->updateUser('guid', ['email' => ''], '123');
    }

    public function testUpdateUserPutSucceeds()
    {
        $mockClient = $this->getMock('Talis\Persona\Client\Users', ['performRequest'], [
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
        $this->setExpectedException('Exception', 'Invalid guid');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Invalid gupid');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Invalid token');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Invalid token');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Error adding gupid to user: Could not retrieve OAuth response code');
        $mockClient = $this->getMock('Talis\Persona\Client\Users', ['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->throwException(new Exception('Could not retrieve OAuth response code')));
        $mockClient->addGupidToUser('123', '456', '987');
    }

    public function testAddGupidToUserPutSucceeds()
    {
        $mockClient = $this->getMock('Talis\Persona\Client\Users', ['performRequest'], [
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
        $this->setExpectedException('Exception', 'Invalid oldGuid');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Invalid newGuid');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Invalid token');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Invalid token');
        $personaClient = new Users(
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
        $this->setExpectedException('Exception', 'Error merging users: Could not retrieve OAuth response code');
        $mockClient = $this->getMock('Talis\Persona\Client\Users', ['performRequest'], [
            [
                'userAgent' => 'unittest',
                'persona_host' => 'localhost',
                'cacheBackend' => $this->cacheBackend,
            ]
        ]);
        $mockClient->expects($this->once())
            ->method('performRequest')
            ->will($this->throwException(new Exception('Could not retrieve OAuth response code')));
        $mockClient->mergeUsers('123', '456', '987');
    }

    public function testMergeUsersPostSucceeds()
    {
        $mockClient = $this->getMock('Talis\Persona\Client\Users', ['performRequest'], [
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
}
