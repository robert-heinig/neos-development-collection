<?php

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\Neos\Controller\Backend;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\Dispatcher;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Context;
use Neos\Neos\Service\BackendRedirectionService;
use Neos\Utility\Arrays;
use Neos\Utility\MediaTypes;
use Neos\Neos\Controller\BackendUserTranslationTrait;
use Neos\Neos\Controller\Exception\DisabledModuleException;
use Neos\Party\Domain\Service\PartyService;

#[Flow\Scope('singleton')]
class ModuleController extends ActionController
{
    use BackendUserTranslationTrait;

    /**
     * @Flow\Inject
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @Flow\Inject
     * @var Context
     */
    protected $securityContext;

    /**
     * @Flow\Inject
     * @var MenuHelper
     */
    protected $menuHelper;

    /**
     * @Flow\Inject
     * @var PartyService
     */
    protected $partyService;

    /**
     * @Flow\Inject
     * @var BackendRedirectionService
     */
    protected $backendRedirectionService;

    /**
     * @param array $module
     * @phpstan-param array<string,mixed> $module
     * @return mixed
     * @throws DisabledModuleException
     */
    public function indexAction(array $module)
    {
        $moduleRequest = $this->request->createSubRequest();
        $moduleRequest->setArgumentNamespace('moduleArguments');
        $moduleRequest->setControllerObjectName($module['controller']);
        $moduleRequest->setControllerActionName($module['action']);
        if (isset($module['format'])) {
            $moduleRequest->setFormat($module['format']);
        }
        if (
            $this->request->hasArgument($moduleRequest->getArgumentNamespace()) === true
            && is_array($this->request->getArgument($moduleRequest->getArgumentNamespace()))
        ) {
            $moduleRequest->setArguments($this->request->getArgument($moduleRequest->getArgumentNamespace()));
        }
        foreach ($this->request->getPluginArguments() as $argumentNamespace => $argument) {
            $moduleRequest->setArgument('--' . $argumentNamespace, $argument);
        }

        $modules = explode('/', $module['module']);

        $moduleConfiguration = Arrays::getValueByPath($this->settings['modules'], implode('.submodules.', $modules));
        $moduleConfiguration['path'] = $module['module'];

        if (!$this->menuHelper->isModuleEnabled($moduleConfiguration['path'])) {
            throw new DisabledModuleException(sprintf(
                'The module "%s" is disabled. You can enable it with the "enabled" flag in Settings.yaml.',
                $module['module']
            ), 1437148922);
        }

        $moduleBreadcrumb = [];
        $path = [];
        foreach ($modules as $moduleIdentifier) {
            $path[] = $moduleIdentifier;
            $config = Arrays::getValueByPath($this->settings['modules'], implode('.submodules.', $path));
            $moduleBreadcrumb[implode('/', $path)] = $config;
        }

        $moduleRequest->setArgument('__moduleConfiguration', $moduleConfiguration);

        $moduleResponse = $this->dispatcher->dispatch($moduleRequest);

        if ($moduleResponse->hasHeader('Location')) {
            // Preserve redirects see b57d72aeeaa2e6da4d9c0a80363025fefd63d813
            return $moduleResponse;
        } elseif ($moduleRequest->getFormat() !== 'html') {
            // Allow ajax request with json or similar dd7e5c99924bf1b8618775bec08cc4f2cb1a6d2a
            // todo just return $moduleResponse and trust its content-type instead of inferring the requested content-type
            $mediaType = MediaTypes::getMediaTypeFromFilename('file.' . $moduleRequest->getFormat());
            if ($mediaType !== 'application/octet-stream') {
                $moduleResponse = $moduleResponse->withHeader('Content-Type', $mediaType);
            }
            return $moduleResponse;
        } else {
            /** @var ?Account $authenticatedAccount */
            $authenticatedAccount = $this->securityContext->getAccount();
            $user = $authenticatedAccount === null ? null : $this->partyService->getAssignedPartyOfAccount($authenticatedAccount);

            $sites = $this->menuHelper->buildSiteList($this->controllerContext);

            $this->view->assignMultiple([
                'moduleClass' => implode('-', $modules),
                'moduleContents' => $moduleResponse->getBody()->getContents(),
                'title' => $moduleRequest->hasArgument('title')
                    ? $moduleRequest->getArgument('title')
                    : $moduleConfiguration['label'],
                'rootModule' => array_shift($modules),
                'submodule' => array_shift($modules),
                'moduleConfiguration' => $moduleConfiguration,
                'moduleBreadcrumb' => $moduleBreadcrumb,
                'user' => $user,
                'modules' => $this->menuHelper->buildModuleList($this->controllerContext),
                'sites' => $sites,
                'primaryModuleUri' => $this->backendRedirectionService->getAfterLoginRedirectionUri($this->controllerContext),
            ]);
        }
    }
}
