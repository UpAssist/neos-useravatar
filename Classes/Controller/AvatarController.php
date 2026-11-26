<?php

declare(strict_types=1);

namespace UpAssist\Neos\UserAvatar\Controller;

use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Locale;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\ResourceManagement\PersistentResource;
use Neos\Neos\Domain\Model\User;
use UpAssist\Neos\UserAvatar\Service\UserAvatarService;

/**
 * Everything the logged-in backend user can do with their own photo:
 *
 * - `GET /neos/user-avatar`: redirect to the uploaded photo or to Gravatar, or 404 when there is
 *   none (a 404 makes the browser keep the initials or icon)
 * - `GET /neos/user-avatar/settings`: JSON for the photo block on the User Settings page
 * - `POST /neos/user-avatar/upload` and `/remove`: change the photo, then back to User Settings
 */
class AvatarController extends ActionController
{
    private const LABELS = [
        'heading', 'none', 'upload', 'uploadHelp', 'save', 'remove',
        'message.nofile', 'message.saved', 'message.invalid', 'message.removed',
    ];

    /**
     * @var array<string>
     */
    protected $supportedMediaTypes = ['text/html', 'application/json'];

    #[Flow\Inject]
    protected UserAvatarService $userAvatarService;

    #[Flow\Inject]
    protected Translator $translator;

    #[Flow\InjectConfiguration(path: 'userInterface.defaultLanguage', package: 'Neos.Neos')]
    protected string $defaultLanguage;

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

    public function settingsAction(): string
    {
        $this->response->setHttpHeader('Cache-Control', 'private, no-cache');
        $this->response->setContentType('application/json');
        $user = $this->userAvatarService->getCurrentUser();
        if ($user === null) {
            $this->response->setStatusCode(404);
            return '{}';
        }

        $locale = $this->localeOf($user);
        $gravatarNote = null;
        if ($this->userAvatarService->isGravatarEnabled()) {
            $email = $this->userAvatarService->getEmailAddress($user);
            $gravatarNote = $email === null
                ? $this->translate('gravatarNoEmail', [], $locale)
                : $this->translate('gravatarHelp', [$email], $locale);
        }

        return json_encode([
            'photoUri' => $this->userAvatarService->getAvatarUri($user),
            'hasUpload' => $this->userAvatarService->getUploadedAvatar($user) !== null,
            'gravatarNote' => $gravatarNote,
            'accept' => implode(',', UserAvatarService::ALLOWED_MEDIA_TYPES),
            'labels' => array_combine(self::LABELS, array_map(fn (string $id) => $this->translate($id, [], $locale), self::LABELS)),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function uploadAction(?PersistentResource $avatar = null): void
    {
        $this->assertPost();
        $user = $this->userAvatarService->getCurrentUser();
        if ($user === null || $avatar === null) {
            $this->backToUserSettings('nofile');
        }
        try {
            $this->userAvatarService->storeAvatar($user, $avatar);
        } catch (\InvalidArgumentException) {
            $this->backToUserSettings('invalid');
        }
        $this->backToUserSettings('saved');
    }

    public function removeAction(): void
    {
        $this->assertPost();
        $user = $this->userAvatarService->getCurrentUser();
        if ($user !== null) {
            $this->userAvatarService->removeAvatar($user);
        }
        $this->backToUserSettings('removed');
    }

    /**
     * Flow checks the CSRF token only on POST, so actions that change anything accept nothing else.
     */
    private function assertPost(): void
    {
        if ($this->request->getHttpRequest()->getMethod() !== 'POST') {
            $this->throwStatus(405);
        }
    }

    private function backToUserSettings(string $status): never
    {
        $uri = $this->uriBuilder
            ->reset()
            ->setArguments(['upassist-useravatar' => $status])
            ->uriFor('index', ['module' => 'user/usersettings'], 'Backend\Module', 'Neos.Neos');
        $this->redirectToUri($uri, 0, 303);
    }

    private function localeOf(User $user): Locale
    {
        $language = $user->getPreferences()?->getInterfaceLanguage() ?: $this->defaultLanguage;
        try {
            return new Locale($language);
        } catch (\Throwable) {
            return new Locale('en');
        }
    }

    private function translate(string $id, array $arguments, Locale $locale): string
    {
        return (string)($this->translator->translateById($id, $arguments, null, $locale, 'Main', 'UpAssist.Neos.UserAvatar') ?? $id);
    }
}
