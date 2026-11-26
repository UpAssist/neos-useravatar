<?php

declare(strict_types=1);

namespace UpAssist\Neos\UserAvatar\Controller;

use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use UpAssist\Neos\UserAvatar\Service\UserAvatarService;

/**
 * Answers `/neos/user-avatar` for the logged-in backend user: a redirect to the uploaded photo
 * or to Gravatar, or 404 when there is none. A 404 makes the browser keep the initials or icon.
 */
class AvatarController extends ActionController
{
    #[Flow\Inject]
    protected UserAvatarService $userAvatarService;

    public function showAction(): string
    {
        $this->response->setHttpHeader('Cache-Control', 'private, no-cache');
        $user = $this->userAvatarService->getCurrentUser();
        $uri = $user === null ? null : $this->userAvatarService->getAvatarUri($user);
        if ($uri === null) {
            $this->response->setStatusCode(404);
            return '';
        }
        $this->response->setRedirectUri(new Uri($uri), 302);

        return '';
    }
}
