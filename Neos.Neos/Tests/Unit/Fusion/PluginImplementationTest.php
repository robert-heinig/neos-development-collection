<?php
namespace Neos\Neos\Tests\Unit\Fusion;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\Dispatcher;
use Neos\Flow\Tests\UnitTestCase;
use Neos\Fusion\Core\Runtime;
use Neos\Neos\Fusion\PluginImplementation;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface as HttpRequestInterface;

/**
 * Testcase for the ConvertNodeUris Fusion implementation
 */
class PluginImplementationTest extends UnitTestCase
{
    /**
     * @var PluginImplementation
     */
    protected $pluginImplementation;

    /**
     * @var Runtime
     */
    protected $mockRuntime;

    /**
     * @var ControllerContext
     */
    protected $mockControllerContext;

    /**
     * @var MockObject|Uri
     */
    protected $mockHttpUri;

    /**
     * @var MockObject|HttpRequestInterface
     */
    protected $mockHttpRequest;

    /**
     * @var MockObject|ActionRequest
     */
    protected $mockActionRequest;

    /**
     * @var MockObject|Dispatcher
     */
    protected $mockDispatcher;

    public function setUp(): void
    {
        $this->markTestSkipped('TODO Doesnt test any thing really, has to be rewritten as behat test.');

        $this->pluginImplementation = $this->getAccessibleMock(PluginImplementation::class, ['buildPluginRequest'], [], '', false);

        $this->mockHttpUri = $this->getMockBuilder(Uri::class)->disableOriginalConstructor()->getMock();
        $this->mockHttpUri->method('getHost')->willReturn('localhost');

        $this->mockHttpRequest = $this->getMockBuilder(HttpRequestInterface::class)->disableOriginalConstructor()->getMock();
        $this->mockHttpRequest->method('getUri')->willReturn($this->mockHttpUri);

        $this->mockActionRequest = $this->getMockBuilder(ActionRequest::class)->disableOriginalConstructor()->getMock();
        $this->mockActionRequest->method('getHttpRequest')->willReturn($this->mockHttpRequest);

        $this->mockControllerContext = $this->getMockBuilder(ControllerContext::class)->disableOriginalConstructor()->getMock();
        $this->mockControllerContext->method('getRequest')->willReturn($this->mockActionRequest);

        $this->mockRuntime = $this->getMockBuilder(Runtime::class)->disableOriginalConstructor()->getMock();
        $this->mockRuntime->method('getControllerContext')->willReturn($this->mockControllerContext);
        $this->pluginImplementation->_set('runtime', $this->mockRuntime);

        $this->mockDispatcher = $this->getMockBuilder(Dispatcher::class)->disableOriginalConstructor()->getMock();
        $this->pluginImplementation->_set('dispatcher', $this->mockDispatcher);
    }

    /**
     * @return array
     */
    public function responseHeadersDataProvider(): array
    {
        /*
         * Fyi (from christian) Multiple competing headers like that are a broken use case anyways.
         * Headers by definition can appear multiple times, we can't really know if we should remove the first one and when not.
         * IMHO the test is misleading the result might as well (correctly) be key => [value, value]
         */
        return [
            [
                'Plugin response key does already exist in parent with same value',
                ['parent' => ['key' => 'value'], 'plugin' => ['key' => 'value']],
                ['key' => 'value']
            ],
            [
                'Plugin response key does not exist in parent with different value',
                ['parent' => ['key' => 'value'], 'plugin' => ['key' => 'otherValue']],
                ['key' => 'otherValue']
            ],
            [
                'Plugin response key does not exist in parent',
                ['parent' => ['key' => 'value'], 'plugin' => ['otherkey' => 'value']],
                ['key' => 'value', 'otherkey' => 'value']
            ]
        ];
    }

    /**
     * Test if the response headers of the plugin - set within the plugin action / dispatch - were set into the parent response.
     *
     * @dataProvider responseHeadersDataProvider
     * @test
     */
    public function evaluateSetHeaderIntoParent(string $message, array $input, array $expected): void
    {
        $this->pluginImplementation->method('buildPluginRequest')->willReturn($this->mockActionRequest);

        $parentResponse = new ActionResponse();
        $this->_setHeadersIntoResponse($parentResponse, $input['parent']);
        $this->mockControllerContext->method('getResponse')->willReturn($parentResponse);

        $this->mockDispatcher->method('dispatch')->willReturnCallback(function (ActionRequest $request) use ($input) {
            return new Response(headers: $input['plugin']);
        });

        $this->mockRuntime->expects($this->any())->method('getCurrentContext')->willReturn(['node' => null, 'documentNode' => null]);

        $this->pluginImplementation->evaluate();

        foreach ($expected as $expectedKey => $expectedValue) {
            self::assertEquals($expectedValue, (string)$parentResponse->getHttpHeader($expectedKey), $message);
        }
    }

    /**
     *  Sets the array based headers into the Response
     *
     * @param ActionResponse $response
     * @param array $headers
     * @return ActionResponse
     */
    private function _setHeadersIntoResponse(ActionResponse $response, array $headers): ActionResponse
    {
        foreach ($headers as $key => $value) {
            $response->setHttpHeader($key, $value);
        }

        return $response;
    }
}
