<?php

/*
 * This file is part of the HWIOAuthBundle package.
 *
 * (c) Hardware.Info <opensource@hardware.info>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace HWI\Bundle\OAuthBundle\Tests\OAuth\ResourceOwner;

use HWI\Bundle\OAuthBundle\OAuth\ResourceOwner\GenericOAuth1ResourceOwner;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpFoundation\Request;

class GenericOAuth1ResourceOwnerTest extends \PHPUnit_Framework_Testcase
{
    /**
     * @var GenericOAuth1ResourceOwner
     */
    protected $resourceOwner;
    protected $buzzClient;
    protected $buzzResponse;
    protected $buzzResponseContentType;
    protected $storage;

    protected $userResponse = '{"foo": "bar"}';

    public function setUp()
    {
        $this->resourceOwner = $this->createResourceOwner($this->getDefaultOptions(), 'oauth1');
    }

    protected function getDefaultOptions()
    {
        return array(
            'infos_url' => 'http://user.info/',
            'client_id' => 'clientid',
            'scope' => '',
            'request_token_url' => 'http://user.request/',
            'authorization_url' => 'http://user.auth/',
            'access_token_url' => 'http://user.access/',
            'client_secret' => 'clientsecret',
        );
    }

    protected function getDefaultPaths()
    {
        return array(
            'identifier' => 'id',
            'nickname'   => 'foo',
            'realname'   => 'foo_disp',
        );
    }

    protected function createResourceOwner(array $options, $name, $paths = null)
    {
        $this->buzzClient = $this->getMockBuilder('\Buzz\Client\ClientInterface')
            ->disableOriginalConstructor()->getMock();
        $httpUtils = $this->getMockBuilder('\Symfony\Component\Security\Http\HttpUtils')
            ->disableOriginalConstructor()->getMock();

        $this->storage = $this->getMock('\HWI\Bundle\OAuthBundle\OAuth\OAuth1RequestTokenStorageInterface');

        $resourceOwner = new GenericOAuth1ResourceOwner($this->buzzClient, $httpUtils, $options, $name, $this->storage);
        $resourceOwner->addPaths($paths ?: $this->getDefaultPaths());

        return $resourceOwner;
    }

    public function testGetOption()
    {
        $this->assertEquals('http://user.info/', $this->resourceOwner->getOption('infos_url'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetInvalidOptionThrowsException()
    {
        $this->resourceOwner->getOption('non_existing');
    }

    public function testGetUserInformation()
    {
        $this->markTestSkipped('Test will work from PHPUnit 3.7 onwards. See: https://github.com/sebastianbergmann/phpunit-mock-objects/issues/47.');
        $this->mockBuzz($this->userResponse);
        $userResponse = $this->resourceOwner->getUserInformation('access_token');

        $this->assertEquals('bar', $userResponse->getUsername());
        $this->assertEquals('access_token', $userResponse->getAccessToken());
    }

    public function testGetAuthorizationUrl()
    {
        $this->markTestSkipped('Test will work from PHPUnit 3.7 onwards. See: https://github.com/sebastianbergmann/phpunit-mock-objects/issues/47.');
        $this->mockBuzz('{"oauth_token": "token", "oauth_token_secret": "secret"}', 'application/json; charset=utf-8');
        $this->assertEquals(
            'http://user.auth/?oauth_token=token',
            $this->resourceOwner->getAuthorizationUrl('http://redirect.to/')
        );
    }

    public function testGetAccessToken()
    {
        $this->markTestSkipped('Test will work from PHPUnit 3.7 onwards. See: https://github.com/sebastianbergmann/phpunit-mock-objects/issues/47.');
        $this->mockBuzz('access_token=code');
        $request = new Request(array('oauth_verifier' => 'code'));
        $accessToken = $this->resourceOwner->getAccessToken($request, 'http://redirect.to/');
    }

    public function testGetAccessTokenJsonResponse()
    {
        $this->markTestSkipped('Test will work from PHPUnit 3.7 onwards. See: https://github.com/sebastianbergmann/phpunit-mock-objects/issues/47.');
        $this->mockBuzz('{"access_token": "code"}', 'application/json');
        $request = new Request(array('oauth_verifier' => 'code'));
        $accessToken = $this->resourceOwner->getAccessToken($request, 'http://redirect.to/');
    }

    public function testGetAccessTokenJsonCharsetResponse()
    {
        $this->markTestSkipped('Test will work from PHPUnit 3.7 onwards. See: https://github.com/sebastianbergmann/phpunit-mock-objects/issues/47.');
        $this->mockBuzz('{"access_token": "code"}', 'application/json; charset=utf-8');
        $request = new Request(array('oauth_verifier' => 'code'));
        $accessToken = $this->resourceOwner->getAccessToken($request, 'http://redirect.to/');
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationException
     */
    public function testGetAccessTokenFailedResponse()
    {
        $this->mockBuzz('invalid');

        $this->storage->expects($this->once())
            ->method('fetch')
            ->will($this->returnValue(array('oauth_token' => 'token', 'oauth_token_secret' => 'secret')));

        $request = new Request(array('oauth_token' => 'token', 'oauth_verifier' => 'code'));

        $this->resourceOwner->getAccessToken($request, 'http://redirect.to/');
    }

    /**
     * @expectedException \Symfony\Component\Security\Core\Exception\AuthenticationException
     */
    public function testGetAccessTokenErrorResponse()
    {
        $this->mockBuzz('error=foo');

        $this->storage->expects($this->once())
            ->method('fetch')
            ->will($this->returnValue(array('oauth_token' => 'token', 'oauth_token_secret' => 'secret')));

        $request = new Request(array('oauth_token' => 'token', 'oauth_verifier' => 'code'));

        $this->resourceOwner->getAccessToken($request, 'http://redirect.to/');
    }

    public function testGetSetName()
    {
        $this->assertEquals('oauth1', $this->resourceOwner->getName());
        $this->resourceOwner->setName('foo');
        $this->assertEquals('foo', $this->resourceOwner->getName());
    }

    public function testCustomResponseClass()
    {
        $options = $this->getDefaultOptions();
        $options['user_response_class'] = '\HWI\Bundle\OAuthBundle\Tests\Fixtures\CustomUserResponse';
        $resourceOwner = $this->createResourceOwner($options, 'oauth1');

        $this->mockBuzz();
        /**
         * @var $userResponse \HWI\Bundle\OAuthBundle\Tests\Fixtures\CustomUserResponse
         */
        $userResponse = $resourceOwner->getUserInformation(array('oauth_token' => 'token', 'oauth_token_secret' => 'secret'));

        $this->assertInstanceOf($options['user_response_class'], $userResponse);
        $this->assertEquals('foo666', $userResponse->getUsername());
        $this->assertEquals('foo', $userResponse->getNickname());
    }

    protected function mockBuzz($response = '', $contentType = 'text/plain')
    {
        $this->buzzClient->expects($this->once())
            ->method('send')
            ->will($this->returnCallback(array($this, 'buzzSendMock')));
        $this->buzzResponse = $response;
        $this->buzzResponseContentType = $contentType;
    }

    public function buzzSendMock($request, $response)
    {
        $response->setContent($this->buzzResponse);
        $response->addHeader('Content-Type: ' . $this->buzzResponseContentType);
    }
}
